# Reverb Broadcasting Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the abandoned `laravel-echo-server` experiment with Laravel Reverb, enable dual-guard (Passport + session) broadcast authorization, and live-append new messages in `Thread.vue`.

**Architecture:** Reverb is the broadcast driver (native WebSocket server, no separate Node process). `Broadcast::routes()` authorizes both the React Native app's Passport tokens and the web session on one route. The frontend uses `@laravel/echo-vue`'s composables (`configureEcho`, `useEchoModel`) rather than a hand-rolled `window.Echo` global.

**Tech Stack:** Laravel 13, `laravel/reverb`, `@laravel/echo-vue`, Vue 3, PHP 8.3.

**Design doc:** `docs/plans/2026-07-09-reverb-broadcasting-design.md`

---

## Environment notes

- **PHP 8.3 required** — shell `php`/`composer` resolve to PHP 8.5. Use `/usr/local/opt/php@8.3/bin/php` explicitly for every `artisan`/`composer` invocation, e.g. `/usr/local/opt/php@8.3/bin/php /usr/local/bin/composer.phar require laravel/reverb` and `/usr/local/opt/php@8.3/bin/php artisan <command>`.
- Branch `feat/reverb-broadcasting` off `master` (already created).
- Run `pint --test`, `phpstan analyse`, and the full PHPUnit suite before every commit that touches PHP.

---

### Task 1: Install Reverb and review what it changes

**Files:**
- Modify: `composer.json`, `composer.lock`
- Modify or create: `config/broadcasting.php`, `config/reverb.php`, `.env`, `routes/channels.php` (review only — do not let it overwrite existing content)

**Step 1: Run the install command**

```bash
/usr/local/opt/php@8.3/bin/php /usr/local/bin/composer.phar require laravel/reverb
/usr/local/opt/php@8.3/bin/php artisan install:broadcasting --reverb
```

The command may prompt about overwriting existing files (`config/broadcasting.php`, `routes/channels.php` both already exist and are customized). **Answer "No" to any prompt that would overwrite `routes/channels.php`** — it has the `App.Models.User.{id}` and `online` channel definitions from this app, not stock content. If prompted for `config/broadcasting.php`, allow it to add the `reverb` connection but verify afterward (Step 2) that the `redis`/`log`/`null` connections are still present.

**Step 2: Review the diff carefully**

```bash
git diff --stat
git diff config/broadcasting.php routes/channels.php
```

Expected:
- `config/broadcasting.php` gains a `reverb` connection entry. The `pusher`, `redis`, `log`, `null` connections from before must still be present — if the command replaced the whole file and dropped them, manually re-add the `redis`/`log`/`null` blocks (see the version in git history before this task, `git show HEAD:config/broadcasting.php`).
- `routes/channels.php` is **unchanged** (still has `App.Models.User.{id}` and `online`). If the command touched it, `git checkout routes/channels.php` to restore it.
- New file `config/reverb.php` exists.
- `.env` gained `REVERB_*` vars and `BROADCAST_DRIVER=reverb` was set (or `BROADCAST_CONNECTION` — check which name `config/broadcasting.php` actually reads with `grep "env('BROADCAST" config/broadcasting.php` and make sure `.env`'s var name matches; this app's config reads `BROADCAST_DRIVER`, not the newer `BROADCAST_CONNECTION` name some Laravel skeletons default to).

**Step 3: Confirm the app still boots**

```bash
/usr/local/opt/php@8.3/bin/php artisan config:clear
/usr/local/opt/php@8.3/bin/php artisan about
```

Expected: no errors, `Broadcasting` section shows `reverb` as the driver.

**Step 4: Commit**

```bash
git add composer.json composer.lock config/broadcasting.php config/reverb.php
git commit -m "chore(reverb): install laravel/reverb and scaffold config"
```

---

### Task 2: Wire Redis-backed Reverb scaling (togglable, off by default)

**Files:**
- Modify: `config/reverb.php`

**Step 1: Read the scaling section**

```bash
grep -n -A15 "'scaling'" config/reverb.php
```

Confirm it already reads `REVERB_SCALING_ENABLED` and a redis connection name from env (this is Reverb's stock published config — it should already be wired to env vars out of the box). If the redis connection name it references doesn't match this app's existing `REDIS_CLIENT=predis`/default connection in `config/database.php`, adjust the `channel`/`connection` key to reference the existing default redis connection rather than introducing a second one.

**Step 2: Commit if any change was needed**

```bash
git add config/reverb.php
git commit -m "chore(reverb): point scaling config at the existing redis connection"
```

If no change was needed (stock config already correct), skip the commit — nothing to record.

---

### Task 3: Update `.env.example`

**Files:**
- Modify: `.env.example`

**Step 1: Edit the file**

Replace:
```
BROADCAST_DRIVER=redis
```
with:
```
BROADCAST_DRIVER=reverb
```

Add after the `REDIS_*` block:
```
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SCALING_ENABLED=false

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Delete the entire dead block:
```
LARAVEL_ECHO_SERVER_AUTH_HOST=localhost
LARAVEL_ECHO_SERVER_HOST=http://localhost
LARAVEL_ECHO_SERVER_PORT=6001
LARAVEL_ECHO_SERVER_PROTO=
LARAVEL_ECHO_SERVER_SSL_KEY=
LARAVEL_ECHO_SERVER_SSL_CERT=
LARAVEL_ECHO_SERVER_SSL_PASS=
LARAVEL_ECHO_SERVER_SSL_CHAIN=
```

**Step 2: Also update the real `.env`** (created in Task 1, not committed) so local dev actually has working values — copy the same additions, and generate real `REVERB_APP_ID`/`KEY`/`SECRET` values if `install:broadcasting --reverb` didn't already populate them (`artisan reverb:install` normally does this automatically — check `.env` before generating anything manually).

**Step 3: Commit**

```bash
git add .env.example
git commit -m "chore(reverb): update env template, drop dead laravel-echo-server vars"
```

---

### Task 4: Dual-guard broadcast authorization (TDD)

**Files:**
- Create: `tests/Feature/BroadcastAuthTest.php`
- Modify: `app/Providers/BroadcastServiceProvider.php`

**Step 1: Read the current provider**

```bash
cat app/Providers/BroadcastServiceProvider.php
```

Confirm it currently has `Broadcast::routes(['middleware' => ['auth:api']]);`.

**Step 2: Write the failing test**

Create `tests/Feature/BroadcastAuthTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class BroadcastAuthTest extends TestCase
{
    public function test_api_guard_authorizes_own_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$user->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk();
    }

    public function test_api_guard_rejects_other_users_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$other->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertForbidden();
    }

    public function test_web_guard_authorizes_own_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$user->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk();
    }

    public function test_web_guard_rejects_other_users_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$other->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertForbidden();
    }
}
```

**Step 3: Run it, confirm the web-guard tests fail**

```bash
/usr/local/opt/php@8.3/bin/php ./vendor/bin/phpunit tests/Feature/BroadcastAuthTest.php
```

Expected: `test_api_guard_authorizes_own_channel` and `test_api_guard_rejects_other_users_channel` PASS (route is already `auth:api`-only). `test_web_guard_authorizes_own_channel` FAILS (likely a redirect or 401/419, not 200 — the session-only request isn't matched by `auth:api`). `test_web_guard_rejects_other_users_channel` may pass or fail depending on how the unauthenticated-under-api-guard request is handled; don't worry about its exact current status, only that at least one web-guard test is red before the fix.

**Step 4: Apply the fix**

In `app/Providers/BroadcastServiceProvider.php`, change:

```php
Broadcast::routes(['middleware' => ['auth:api']]);
```

to:

```php
Broadcast::routes(['middleware' => ['web', 'auth:api,web']]);
```

Note: `['auth:api,web']` alone *replaces* Laravel's default `['web']` middleware rather than adding to it, so `StartSession`/`EncryptCookies`/`AuthenticateSession` (which live only in the `web` group in `app/Http/Kernel.php`) never run for `/broadcasting/auth` — session-cookie auth silently never works in production. `web` must be listed explicitly alongside the dual-guard `auth:api,web` check. This does not reintroduce a CSRF requirement for the mobile app's bearer-token calls: `BroadcastManager::routes()` unconditionally calls `->withoutMiddleware(PreventRequestForgery::class)` on this route, and Laravel's middleware-exclusion resolution also excludes subclasses (`app/Http/Middleware/VerifyCsrfToken` extends `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` extends `PreventRequestForgery`), so CSRF stays excluded either way.

**Step 5: Run the test again, confirm all four pass**

```bash
/usr/local/opt/php@8.3/bin/php ./vendor/bin/phpunit tests/Feature/BroadcastAuthTest.php
```

Expected: 4 tests, all green.

**Step 6: Run the full suite to confirm no regressions**

```bash
/usr/local/opt/php@8.3/bin/php ./vendor/bin/phpunit
/usr/local/opt/php@8.3/bin/php ./vendor/bin/pint --test
/usr/local/opt/php@8.3/bin/php -d memory_limit=512M ./vendor/bin/phpstan analyse
```

**Step 7: Commit**

```bash
git add app/Providers/BroadcastServiceProvider.php tests/Feature/BroadcastAuthTest.php
git commit -m "feat(reverb): dual-guard broadcast auth (auth:api,web)"
```

---

### Task 5: Frontend Echo bootstrap

**Files:**
- Modify: `package.json`, `package-lock.json` (or equivalent)
- Modify: `resources/js/app.js`

**Step 1: Install the package**

```bash
npm install @laravel/echo-vue
```

Confirm `laravel-echo` and `pusher-js` also landed as dependencies (either direct or transitive via `@laravel/echo-vue`) — check `package.json` and `node_modules/laravel-echo` exists:

```bash
cat package.json | grep -A5 '"dependencies"'
ls node_modules/laravel-echo 2>/dev/null && echo "present" || echo "MISSING - install explicitly: npm install laravel-echo pusher-js"
```

**Step 2: Configure Echo in `resources/js/app.js`**

Add near the top of the file (after the `createApp` import):

```js
import { configureEcho } from '@laravel/echo-vue'

configureEcho({ broadcaster: 'reverb' })
```

This reads `VITE_REVERB_*` from the environment automatically — no manual host/port wiring needed.

**Step 3: Verify the build doesn't break**

```bash
npm run build
```

Expected: build succeeds with no errors.

**Step 4: Commit**

```bash
git add package.json package-lock.json resources/js/app.js
git commit -m "feat(reverb): configure Echo for the reverb broadcaster"
```

---

### Task 6: Live-append new messages in `Thread.vue`

**Files:**
- Modify: `resources/js/pages/Thread.vue`

**Step 1: Read the current component**

```bash
cat resources/js/pages/Thread.vue
```

**Step 2: Convert the static `messages` prop usage into a local reactive array and wire the subscription**

Replace the `<script setup>` block with:

```js
<script setup>
import { ref, onMounted } from 'vue'
import { useEchoModel } from '@laravel/echo-vue'
import AppHeader from '../components/organisms/AppHeader.vue'
import MessageFeed from '../components/organisms/MessageFeed.vue'

const props = defineProps({
    auth: { type: Object, default: null },
    thread: { type: Object, required: true },
})

const messages = ref(props.thread.messages)

function transform(payload) {
    return {
        id: payload.id,
        body: payload.attributes.body,
        created_at: 'just now',
        user: { name: payload.attributes.user?.attributes?.name },
    }
}

onMounted(() => {
    if (!props.auth) {
        return
    }

    const { channel } = useEchoModel('App.Models.User', props.auth.id)

    channel().notification((notification) => {
        const payload = notification.payload

        if (payload?.attributes?.thread_id === props.thread.id) {
            messages.value.push(transform(payload))
        }
    })
})
</script>
```

Update the template's `<MessageFeed :messages="thread.messages" />` to `<MessageFeed :messages="messages" />`.

**Step 3: Verify the payload shape assumption**

Before trusting the `transform()` mapping above, confirm the actual JSON shape a broadcast notification arrives in. `MessageResource` (`app/Http/Resources/MessageResource.php`) nests `user` as `new UserResource($this->whenLoaded('user'))`, and `UserResource` wraps its fields under `attributes` too (`{type, id, attributes: {name, ...}}`) — hence `payload.attributes.user?.attributes?.name` above, not `payload.attributes.user?.name`. Re-check both resource files before implementing in case this has changed:

```bash
cat app/Http/Resources/MessageResource.php app/Http/Resources/UserResource.php
```

**Step 4: Build check**

```bash
npm run build
```

**Step 5: Commit**

```bash
git add resources/js/pages/Thread.vue
git commit -m "feat(reverb): live-append new messages in Thread.vue"
```

---

### Task 7: README note

**Files:**
- Modify: `README.md`

**Step 1: Add a section after "Seed database"**

```markdown
## Running the app (with real-time messaging)

Reverb requires a persistent process, and message broadcasts are queued — both must be running alongside the usual dev server:

```
php artisan reverb:start
php artisan queue:work
npm run dev
```
```

**Step 2: Commit**

```bash
git add README.md
git commit -m "docs: document reverb:start and queue:work for local dev"
```

---

### Task 8: Manual end-to-end verification

**No files changed — this is a verification-only task, do not skip it.**

**Step 1: Start all three processes** (separate terminals)

```bash
/usr/local/opt/php@8.3/bin/php artisan reverb:start
/usr/local/opt/php@8.3/bin/php artisan queue:work
npm run dev
```

**Step 2: Log in via the web UI**, navigate to a thread with at least one existing message.

**Step 3: Trigger a new message from another session**

```bash
/usr/local/opt/php@8.3/bin/php artisan tinker
```
```php
$service = app(\App\Interfaces\MessageServiceInterface::class);
$thread = \App\Models\Thread::first();
$sender = \App\Models\User::where('id', '!=', $thread->creator()->id)->first();
$service->newMessage($thread, $sender, ['type' => 'mood', 'version' => '1.0', 'payload' => ['mood' => 'happy']]);
```

**Step 4: Confirm** the message appears in the open browser tab without a page refresh, within a couple seconds (queue processing + broadcast delivery).

**Step 5: Check `reverb:start`'s terminal output** for connection/subscription log lines confirming the browser actually subscribed to the private channel — if nothing shows there, the auth or `configureEcho()` wiring is broken even if the message doesn't visibly fail.

**If this doesn't work:** do not proceed to Task 9. Use the `superpowers:systematic-debugging` skill — check queue worker logs, Reverb server logs, and the browser console/network tab for the `/broadcasting/auth` response, in that order.

---

### Task 9: Final full verification and open the PR

**Step 1: Full check**

```bash
/usr/local/opt/php@8.3/bin/php ./vendor/bin/phpunit
/usr/local/opt/php@8.3/bin/php ./vendor/bin/pint --test
/usr/local/opt/php@8.3/bin/php -d memory_limit=512M ./vendor/bin/phpstan analyse
npm run build
```

All must pass clean.

**Step 2: Push and open the PR**

```bash
git push -u origin feat/reverb-broadcasting
gh pr create --base chore/php-linting-formatting --title "feat: replace abandoned echo-server with Laravel Reverb" --body "$(cat <<'EOF'
## Summary
- Replaces the abandoned 2020 laravel-echo-server attempt with Laravel Reverb, the framework's native WebSocket server.
- Broadcast authorization now supports both the React Native app's Passport tokens and the web session (`auth:api,web`) on a single route/callback set — no duplicated authorization logic.
- `Thread.vue` live-appends new messages via the existing private `App.Models.User.{id}` channel, using `@laravel/echo-vue`'s composable API rather than a hand-rolled `window.Echo` global.
- Reverb's Redis-backed horizontal scaling is wired but off by default (`REVERB_SCALING_ENABLED=false`), ready for multi-instance production deployment later.
- Removed the dead `LARAVEL_ECHO_SERVER_*` env vars left over from the abandoned attempt.

Based on `chore/php-linting-formatting` (#78), same as #79 — branches off that stack rather than master since it needs the Pint/Larastan tooling already merged there.

Design doc: `docs/plans/2026-07-09-reverb-broadcasting-design.md`

## Test plan
- [x] `tests/Feature/BroadcastAuthTest.php` — dual-guard authorization, both guards, both authorized and rejected cases
- [x] Full PHPUnit suite, pint, phpstan all pass
- [x] Manual verification: reverb:start + queue:work + npm run dev, triggered a message via tinker, confirmed live append in the browser without refresh
- [ ] React Native app's delivery path — not verifiable from this repo, needs confirmation from whoever owns that client
EOF
)"
```

# Reverb Hardening & Usability Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Turn the merged Reverb broadcasting feature (PR #80) into a usable chat UI: real online presence, a Dashboard that live-updates, and the timestamps/counts/avatars a real inbox needs — plus close a pre-existing gap where unread counts have been hardcoded to zero since the Dashboard was first built.

**Architecture:** Backend exposes presence data, real unread/message counts, and marks threads read on view. Frontend gets one small shared composable for online presence (the one genuinely duplicated piece of logic) and inline per-page notification handling (Dashboard and Thread react to the same events differently, so a shared wrapper isn't worth it).

**Tech Stack:** Laravel 13, `laravel/reverb`, `@laravel/echo-vue` (`useEchoPresence`, `useEchoNotification`, `useConnectionStatus`, `echoIsConfigured`), Vue 3, PHP 8.3.

**Design doc:** `docs/plans/2026-07-12-reverb-hardening-design.md`

---

## Environment notes

- **PHP 8.3 required** — shell `php`/`composer` resolve to a newer version. Use `/usr/local/opt/php@8.3/bin/php` explicitly for every `artisan`/`composer`/`phpunit`/`pint`/`phpstan` invocation.
- Branch `feat/reverb-hardening` off `master` (already created — `master` now has PRs #78, #79, #80, #81 merged, so Pint/Larastan/Reverb/Boost are all present).
- Run `pint --test`, `phpstan analyse`, and the full PHPUnit suite before every commit that touches PHP.
- No JS test infrastructure exists in this repo — frontend tasks are verified via `npm run build` succeeding and manual browser verification (Task 12), not automated tests.

---

### Task 1: Fix the presence channel (TDD)

**Files:**
- Modify: `routes/channels.php`
- Modify: `tests/Feature/BroadcastAuthTest.php`

**Step 1: Read the current channel definition**

```bash
cat routes/channels.php
```

Confirm the `'online'` channel currently returns `$user->toArray()` behind a redundant `auth()->check()` check — this is not valid presence-channel data (a presence channel needs to return an array shaped like `['id' => ..., ...other info]`, not a full model array).

**Step 2: Write the failing test**

Add to `tests/Feature/BroadcastAuthTest.php` (inside the existing `BroadcastAuthTest` class):

```php
    public function test_presence_channel_authorizes_with_id_and_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-online',
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk();
        $response->assertJson([
            'channel_data' => [
                'user_id' => $user->id,
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
            ],
        ]);
    }
```

**Step 3: Run it, confirm it fails**

```bash
/usr/local/opt/php@8.3/bin/php ./vendor/bin/phpunit --filter test_presence_channel_authorizes_with_id_and_name tests/Feature/BroadcastAuthTest.php
```

Expected: FAIL. The current channel callback returns `$user->toArray()` (a huge model dump keyed by column name, not `{id, name}`), so the JSON shape assertion won't match — or the response may not even be `200` if `auth()->check()` behaves unexpectedly under a presence-channel auth request (verify what actually happens; don't assume, report what you see).

**Step 4: Fix the channel**

In `routes/channels.php`, replace:

```php
Broadcast::channel('online', function (User $user) {
    if (auth()->check()) {
        return $user->toArray();
    }
});
```

with:

```php
Broadcast::channel('online', function (User $user) {
    return ['id' => $user->id, 'name' => $user->name];
});
```

The `auth()->check()` guard is redundant — this callback only ever runs for an already-authenticated user (that's what `Broadcast::routes()`'s `auth:api,web` guard already established before this callback is reached). Returning an array unconditionally is what makes this a valid presence channel; returning `null`/`false` from a presence channel callback is how you'd reject a subscription, which isn't a case that applies here since only authenticated users reach this callback at all.

**Step 5: Run it, confirm it passes**

```bash
/usr/local/opt/php@8.3/bin/php ./vendor/bin/phpunit --filter test_presence_channel_authorizes_with_id_and_name tests/Feature/BroadcastAuthTest.php
```

Expected: PASS.

**Step 6: Run the full suite**

```bash
/usr/local/opt/php@8.3/bin/php ./vendor/bin/phpunit
/usr/local/opt/php@8.3/bin/php ./vendor/bin/pint --test
/usr/local/opt/php@8.3/bin/php -d memory_limit=1G ./vendor/bin/phpstan analyse
```

**Step 7: Commit**

```bash
git add routes/channels.php tests/Feature/BroadcastAuthTest.php
git commit -m "fix: rebuild the online channel as a real presence channel"
```

---

### Task 2: Message counts on the threads query

**Files:**
- Modify: `app/Services/MessageService.php`
- Test: `tests/Feature/MessageTest.php` (extend, or `tests/Unit/MessageTest.php` — check which already covers `threads()` and extend that one, don't create a third file for one assertion)

**Step 1: Read the current method**

```bash
grep -n -A5 "public function threads" app/Services/MessageService.php
```

Current body:

```php
    public function threads(User $user): LengthAwarePaginator
    {
        return Thread::forUser($user->id)->with('participants.user')->latest('updated_at')->paginate();
    }
```

**Step 2: Write the failing test**

Find the existing test that covers `MessageService::threads()` (search `tests/Unit/MessageTest.php` and `tests/Feature/MessageTest.php` for `->threads(`). Add an assertion to that existing test (don't write a whole new test method if one already exercises this path and just needs one more assertion) checking that a returned thread has a `messages_count` attribute matching the actual number of messages on that thread. If no existing test conveniently covers this, add a focused new test:

```php
public function test_threads_query_includes_message_count(): void
{
    $user = User::factory()->create();
    $thread = Thread::factory()->create();
    $thread->addParticipant([$user->id]);
    Message::factory()->count(3)->create(['thread_id' => $thread->id]);

    /** @var MessageServiceInterface $service */
    $service = app(MessageServiceInterface::class);
    $result = $service->threads($user)->firstWhere('id', $thread->id);

    $this->assertNotNull($result);
    $this->assertSame(3, $result->messages_count);
}
```

Adjust namespace/imports/factory calls to match whichever test file you're extending (check the top of the file for existing `use` statements and factory patterns — this codebase uses `Thread::factory()`, `Message::factory()`, and `addParticipant()` elsewhere, e.g. in `tests/Unit/MessageTest.php`).

**Step 3: Run it, confirm it fails**

Run the specific test file with `--filter`. Expected: FAIL — `messages_count` doesn't exist on the model yet (accessing it either returns `null` or throws, Eloquent doesn't have `withCount` applied).

**Step 4: Add the count**

In `app/Services/MessageService.php`:

```php
    public function threads(User $user): LengthAwarePaginator
    {
        return Thread::forUser($user->id)->with('participants.user')->withCount('messages')->latest('updated_at')->paginate();
    }
```

**Step 5: Run it, confirm it passes**

**Step 6: Run the full suite, pint, phpstan**

**Step 7: Commit**

```bash
git add app/Services/MessageService.php tests/...
git commit -m "feat: include message counts in the threads query"
```

---

### Task 3: Real unread counts + participant IDs on the Dashboard (TDD)

**Files:**
- Modify: `app/Http/Controllers/FrontEnd/DashboardController.php`
- Test: `tests/Feature/FrontEndTest.php` (check if it already covers the dashboard route; extend or add a new test method there)

**Step 1: Read the current controller**

```bash
cat app/Http/Controllers/FrontEnd/DashboardController.php
```

Current `index()`:

```php
    public function index(MessageServiceInterface $messages)
    {
        /** @var User $user */
        $user = Auth::user();
        $threads = $messages->threads($user);

        return view('dashboard', [
            'threads' => $threads->map(fn ($thread) => [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'unread_count' => 0,
                'participants' => $thread->participants
                    ->map(fn ($participant) => ['user' => ['name' => $participant->user?->name]])
                    ->values()
                    ->all(),
            ])->values()->all(),
        ]);
    }
```

**Step 2: Write the failing test**

Check `tests/Feature/FrontEndTest.php` for existing dashboard coverage (`grep -n "dashboard" tests/Feature/FrontEndTest.php`). Add (or extend) a test asserting the rendered page's `threads` prop carries a correct, non-zero `unread_count` for a thread with unread messages, and that each participant entry includes a `user.id`:

```php
public function test_dashboard_shows_real_unread_counts_and_participant_ids(): void
{
    $user = User::factory()->create();
    $other = User::factory()->create();
    $thread = Thread::factory()->create();
    $thread->addParticipant([$user->id, $other->id]);
    Message::factory()->create(['thread_id' => $thread->id, 'user_id' => $other->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewHas('threads', function ($threads) use ($thread, $other) {
        $found = collect($threads)->firstWhere('id', $thread->id);

        return $found !== null
            && $found['unread_count'] === 1
            && collect($found['participants'])->contains(fn ($p) => $p['user']['id'] === $other->id);
    });
}
```

Check the actual Blade/Inertia-style rendering mechanism this app uses (this is a plain Blade view passing `window.__PROPS__`, not Inertia — confirm how `assertViewHas` interacts with it, or adjust to whatever assertion style the existing `tests/Feature/FrontEndTest.php` already uses for this kind of check; don't guess blindly, read the existing test file's patterns first).

**Step 3: Run it, confirm it fails**

**Step 4: Implement**

```php
    public function index(MessageServiceInterface $messages)
    {
        /** @var User $user */
        $user = Auth::user();
        $threads = $messages->threads($user);

        return view('dashboard', [
            'threads' => $threads->map(fn ($thread) => [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'unread_count' => $thread->userUnreadMessagesCount($user->id),
                'messages_count' => $thread->messages_count,
                'participants' => $thread->participants
                    ->map(fn ($participant) => ['user' => ['id' => $participant->user?->id, 'name' => $participant->user?->name]])
                    ->values()
                    ->all(),
            ])->values()->all(),
        ]);
    }
```

`userUnreadMessagesCount()` is a method on the vendor `cmgmyr/messenger` `Thread` base class (already inherited by `App\Models\Thread`) — no import needed, it's already available since `$thread` is an `App\Models\Thread` instance. `messages_count` comes from Task 2's `withCount('messages')`.

**Step 5: Run it, confirm it passes**

**Step 6: Full suite, pint, phpstan**

**Step 7: Commit**

```bash
git add app/Http/Controllers/FrontEnd/DashboardController.php tests/Feature/FrontEndTest.php
git commit -m "feat: real unread counts, message counts, and participant IDs on the dashboard"
```

---

### Task 4: Mark thread read on view + participants/message count on Thread page (TDD)

**Files:**
- Modify: `app/Http/Controllers/FrontEnd/ThreadController.php`
- Test: `tests/Feature/FrontEndTest.php` (check for existing thread-show coverage first)

**Step 1: Read the current controller**

```bash
cat app/Http/Controllers/FrontEnd/ThreadController.php
```

Current `show()`:

```php
    public function show(string $thread)
    {
        $thread = $this->service->thread($thread);

        return view('thread', [
            'thread' => [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'messages' => $thread->messages->map(fn (Message $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->diffForHumans(),
                    'user' => ['name' => $message->user?->name],
                ])->values()->all(),
            ],
        ]);
    }
```

**Step 2: Write the failing test**

```php
public function test_viewing_a_thread_marks_it_read_and_exposes_participants_and_count(): void
{
    $user = User::factory()->create();
    $other = User::factory()->create();
    $thread = Thread::factory()->create();
    $thread->addParticipant([$user->id, $other->id]);
    Message::factory()->count(2)->create(['thread_id' => $thread->id, 'user_id' => $other->id]);

    $response = $this->actingAs($user)->get(route('thread.show', ['thread' => $thread->id]));

    $response->assertOk();
    $response->assertViewHas('thread', function ($data) use ($other) {
        return $data['messages_count'] === 2
            && collect($data['participants'])->contains(fn ($p) => $p['user']['id'] === $other->id);
    });

    $participant = $thread->participants()->where('user_id', $user->id)->firstOrFail();
    $this->assertNotNull($participant->fresh()->last_read);
}
```

Check `route('thread.show', ...)` matches the actual route name in `routes/web.php` (`grep -n "thread" routes/web.php`) before trusting this literally.

**Step 3: Run it, confirm it fails**

**Step 4: Implement**

```php
use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ThreadController extends Controller
{
    public MessageServiceInterface $service;

    public function __construct(MessageServiceInterface $service)
    {
        $this->service = $service;
    }

    // ...

    public function show(string $thread)
    {
        $thread = $this->service->thread($thread);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->service->markAsRead($thread, $user);

        return view('thread', [
            'thread' => [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'messages_count' => $thread->messages->count(),
                'participants' => $thread->participants
                    ->map(fn ($participant) => ['user' => ['id' => $participant->user?->id, 'name' => $participant->user?->name]])
                    ->values()
                    ->all(),
                'messages' => $thread->messages->map(fn (Message $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->diffForHumans(),
                    'user' => ['id' => $message->user?->id, 'name' => $message->user?->name],
                ])->values()->all(),
            ],
        ]);
    }

    // ...
}
```

Add the `use Illuminate\Support\Facades\Auth;` import if not already present (check the file's current imports first — it may only import `Request`/`View`/`Message`/`MessageServiceInterface`).

`messages_count` uses `$thread->messages->count()` on the already-eager-loaded collection (`MessageService::thread()` already does `Thread::with(['messages', 'participants.user'])`) — no extra query, unlike Task 2's `withCount()` which was needed because that path doesn't eager-load the full messages collection.

**Step 5: Run it, confirm it passes**

**Step 6: Full suite, pint, phpstan**

**Step 7: Commit**

```bash
git add app/Http/Controllers/FrontEnd/ThreadController.php tests/Feature/FrontEndTest.php
git commit -m "feat: mark thread read on view, expose participants and message count"
```

---

### Task 5: AppAvatar online dot

**Files:**
- Modify: `resources/js/components/atoms/AppAvatar.vue`

**Step 1: Read the current component**

```bash
cat resources/js/components/atoms/AppAvatar.vue
```

**Step 2: Add the prop and dot**

Add an `online` prop and a small absolutely-positioned dot. The avatar's root `<span>` needs `relative` positioning for the dot to anchor correctly:

```vue
<script setup>
import { computed } from 'vue'

const props = defineProps({
    name: { type: String, required: true },
    size: {
        type: String,
        default: 'md',
        validator: v => ['sm', 'md', 'lg'].includes(v),
    },
    online: { type: Boolean, default: false },
})

const initials = computed(() =>
    props.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(part => part[0].toUpperCase())
        .join('')
)
</script>

<template>
    <span class="relative inline-flex shrink-0">
        <span
            class="inline-flex items-center justify-center rounded-full bg-brand font-medium text-white"
            :class="{
                'h-8 w-8 text-xs': size === 'sm',
                'h-10 w-10 text-sm': size === 'md',
                'h-12 w-12 text-base': size === 'lg',
            }"
        >
            {{ initials }}
        </span>
        <span
            v-if="online"
            class="absolute bottom-0 right-0 rounded-full bg-green-500 ring-2 ring-white"
            :class="{
                'h-2 w-2': size === 'sm',
                'h-2.5 w-2.5': size === 'md',
                'h-3 w-3': size === 'lg',
            }"
        />
    </span>
</template>
```

**Step 3: Build check**

```bash
npm run build
```

Expected: succeeds, no errors.

**Step 4: Commit**

```bash
git add resources/js/components/atoms/AppAvatar.vue
git commit -m "feat: add online indicator dot to AppAvatar"
```

---

### Task 6: `useOnlinePresence` composable

**Files:**
- Create: `resources/js/composables/useOnlinePresence.js`

**Step 1: Verify the exact `useEchoPresence` return shape**

```bash
grep -n -A10 "useEchoPresence" node_modules/@laravel/echo-vue/dist/index.d.ts
```

Confirm it returns `{ channel: () => PresenceChannel, ... }`, and that the presence channel object has `here(cb)`, `joining(cb)`, `leaving(cb)` methods (standard Laravel Echo presence API — verify against `node_modules/laravel-echo/dist/echo.d.ts` if you want to double check the exact callback argument shape each of `here`/`joining`/`leaving` receives; `here` gets an array of member info objects, `joining`/`leaving` each get a single member info object).

**Step 2: Write the composable**

```js
import { ref } from 'vue'
import { useEchoPresence } from '@laravel/echo-vue'

export function useOnlinePresence() {
    const onlineUserIds = ref(new Set())

    const { channel } = useEchoPresence('online')

    channel()
        .here((users) => {
            onlineUserIds.value = new Set(users.map((u) => u.id))
        })
        .joining((user) => {
            onlineUserIds.value = new Set(onlineUserIds.value).add(user.id)
        })
        .leaving((user) => {
            const next = new Set(onlineUserIds.value)
            next.delete(user.id)
            onlineUserIds.value = next
        })

    return { onlineUserIds }
}
```

Note: `onlineUserIds.value` is reassigned (not mutated in place) on each update so Vue's reactivity picks up the change — a `Set` mutated via `.add()`/`.delete()` in place won't trigger reactivity on its own.

**Step 3: Build check**

```bash
npm run build
```

This won't catch runtime behavior (no component uses it yet), just confirms the file has no syntax errors and imports resolve. Real verification happens in Task 12.

**Step 4: Commit**

```bash
git add resources/js/composables/useOnlinePresence.js
git commit -m "feat: add useOnlinePresence composable"
```

---

### Task 7: Connection hardening in app.js

**Files:**
- Modify: `resources/js/app.js`

**Step 1: Verify the exact `ConnectionStatus` values**

```bash
grep -n "ConnectionStatus" node_modules/@laravel/echo-vue/dist/index.d.ts
cat node_modules/@laravel/echo-vue/dist/index.d.ts | grep -B2 -A10 "enum ConnectionStatus\|ConnectionStatus ="
```

If it's a TypeScript string-literal union or enum, confirm the exact set of values (likely something like `'connecting' | 'connected' | 'unavailable' | 'failed' | 'disconnected'`, matching Pusher/pusher-js's standard connection states — verify rather than assume, since this determines what the watcher checks against).

**Step 2: Read the current file**

```bash
cat resources/js/app.js
```

**Step 3: Add the hardening**

```js
import { createApp } from 'vue'
import { configureEcho, echoIsConfigured, useConnectionStatus } from '@laravel/echo-vue'
import { watch } from 'vue'

configureEcho({ broadcaster: 'reverb' })

if (import.meta.env.DEV && !echoIsConfigured()) {
    console.error(
        '[reverb] Echo is not configured — VITE_REVERB_* env vars are likely missing. ' +
        'Live updates (messages, presence) will not work. Check .env and run `php artisan reverb:install` if needed.'
    )
}

const connectionStatus = useConnectionStatus()

watch(connectionStatus, (status) => {
    if (status !== 'connected' && import.meta.env.DEV) {
        console.warn(`[reverb] connection status: ${status}`)
    }
}, { immediate: true })

const pages = {
    Welcome:   () => import('./pages/Welcome.vue'),
    Login:     () => import('./pages/Login.vue'),
    Dashboard: () => import('./pages/Dashboard.vue'),
    Thread:    () => import('./pages/Thread.vue'),
}

const name  = window.__PAGE__
const props = window.__PROPS__ ?? {}
const auth  = window.__AUTH__ ?? null

if (name && pages[name]) {
    pages[name]().then(({ default: Page }) => {
        createApp(Page, { ...props, auth }).mount('#app')
    })
}
```

Adjust the `status !== 'connected'` comparison to match whatever the actual `ConnectionStatus` values turned out to be in Step 1 — don't ship this without having verified it against the real type/enum.

**Step 4: Build check**

```bash
npm run build
```

**Step 5: Commit**

```bash
git add resources/js/app.js
git commit -m "feat: log Echo configuration/connection issues in dev mode"
```

---

### Task 8: MessageBubble online dot

**Files:**
- Modify: `resources/js/components/molecules/MessageBubble.vue`
- Modify: `resources/js/pages/Thread.vue` (the `transform()` function needs to carry `user.id`, not just `name`, for this to have anything to check against)

**Step 1: Read both files**

```bash
cat resources/js/components/molecules/MessageBubble.vue
cat resources/js/pages/Thread.vue
```

**Step 2: Update `Thread.vue`'s `transform()` to carry the sender's ID**

The current `transform()`:

```js
function transform(payload) {
    return {
        id: payload.id,
        body: payload.attributes.body,
        created_at: 'just now', // freshly-arrived messages are definitionally "just now"; no relative-time lib needed
        user: { name: payload.attributes.user?.attributes?.name },
    }
}
```

Add `id`:

```js
function transform(payload) {
    return {
        id: payload.id,
        body: payload.attributes.body,
        created_at: 'just now', // freshly-arrived messages are definitionally "just now"; no relative-time lib needed
        user: {
            id: payload.attributes.user?.attributes?.id,
            name: payload.attributes.user?.attributes?.name,
        },
    }
}
```

Check `UserResource`'s `toArray()` (`app/Http/Resources/UserResource.php`) to confirm it actually exposes `id` under `attributes` (not just at the top level) before trusting `payload.attributes.user?.attributes?.id` — read the file, don't assume the shape matches `name`'s nesting.

**Step 3: Update `MessageBubble.vue` to accept and use online status**

```vue
<script setup>
import { computed } from 'vue'
import AppAvatar from '../atoms/AppAvatar.vue'

const props = defineProps({
    message: { type: Object, required: true },
    onlineUserIds: { type: Set, default: () => new Set() },
})

const isStructured = computed(
    () => props.message.body !== null && typeof props.message.body === 'object'
)

const text = computed(() =>
    isStructured.value
        ? JSON.stringify(props.message.body, null, 2)
        : props.message.body
)
</script>

<template>
    <div class="flex gap-3">
        <AppAvatar
            :name="message.user?.name ?? '?'"
            size="sm"
            :online="onlineUserIds.has(message.user?.id)"
        />
        <div class="min-w-0 flex-1">
            <div class="flex items-baseline gap-2">
                <span class="font-medium text-gray-900">{{ message.user?.name }}</span>
                <span class="text-xs text-gray-400">{{ message.created_at }}</span>
            </div>
            <pre
                v-if="isStructured"
                class="mt-1 overflow-x-auto rounded-md bg-gray-50 p-3 text-sm text-gray-700"
            >{{ text }}</pre>
            <p v-else class="mt-1 whitespace-pre-wrap break-words text-gray-700">{{ text }}</p>
        </div>
    </div>
</template>
```

**Step 4: Thread the prop through `MessageFeed.vue`**

```bash
cat resources/js/components/organisms/MessageFeed.vue
```

It currently only takes `messages`. Add `onlineUserIds` and pass it through to each `MessageBubble`:

```vue
<script setup>
import MessageBubble from '../molecules/MessageBubble.vue'

defineProps({
    messages: { type: Array, default: () => [] },
    onlineUserIds: { type: Set, default: () => new Set() },
})
</script>

<template>
    <div v-if="messages.length" class="space-y-6">
        <MessageBubble v-for="message in messages" :key="message.id" :message="message" :online-user-ids="onlineUserIds" />
    </div>
    <p v-else class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-500">
        No messages yet.
    </p>
</template>
```

**Step 5: Build check**

```bash
npm run build
```

**Step 6: Commit**

```bash
git add resources/js/components/molecules/MessageBubble.vue resources/js/components/organisms/MessageFeed.vue resources/js/pages/Thread.vue app/Http/Resources/UserResource.php
git commit -m "feat: show online status on message sender avatars"
```

(Note: only include `app/Http/Resources/UserResource.php` in the `git add` if Step 2's check found it needed a change — if `id` was already exposed under `attributes`, there's nothing to change there and it shouldn't appear in `git status`.)

---

### Task 9: ThreadCard — timestamp, message count, last-message preview, avatars

**Files:**
- Modify: `resources/js/components/molecules/ThreadCard.vue`
- Modify: `resources/js/components/organisms/ThreadList.vue` (needs to pass `onlineUserIds` through)
- Modify: `app/Http/Controllers/FrontEnd/DashboardController.php` (needs to expose a last-message preview and `updated_at` — check what's missing before assuming Task 3 already covered it)

**Step 1: Check what the Dashboard payload is currently missing**

```bash
cat app/Http/Controllers/FrontEnd/DashboardController.php
```

After Task 3, the payload has `id`, `subject`, `unread_count`, `messages_count`, `participants` (with `user.id`/`user.name`). It does NOT have `updated_at` or a last-message preview. Add both:

```php
            'threads' => $threads->map(fn ($thread) => [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'updated_at' => $thread->updated_at?->diffForHumans(),
                'unread_count' => $thread->userUnreadMessagesCount($user->id),
                'messages_count' => $thread->messages_count,
                'last_message' => optional($thread->messages->sortByDesc('created_at')->first())->body,
                'participants' => $thread->participants
                    ->map(fn ($participant) => ['user' => ['id' => $participant->user?->id, 'name' => $participant->user?->name]])
                    ->values()
                    ->all(),
            ])->values()->all(),
```

`$thread->messages` isn't currently eager-loaded on this query (`MessageService::threads()` only does `with('participants.user')`) — check whether accessing `$thread->messages` here triggers a lazy-load per thread (an N+1). If so, add `->with('messages')` to `MessageService::threads()`'s query (same file/method touched in Task 2) rather than leaving an N+1 in place. Verify with `DB::enableQueryLog()`/`getQueryLog()` in a quick tinker check, or reason about it from the query builder chain — don't just assume either way.

**Step 2: Read the current ThreadCard**

```bash
cat resources/js/components/molecules/ThreadCard.vue
```

**Step 3: Add timestamp, count, preview, avatars**

```vue
<script setup>
import { computed } from 'vue'
import AppAvatar from '../atoms/AppAvatar.vue'
import AppBadge from '../atoms/AppBadge.vue'

const props = defineProps({
    thread: { type: Object, required: true },
    onlineUserIds: { type: Set, default: () => new Set() },
})

const names = computed(() =>
    (props.thread.participants ?? [])
        .map(p => p.user?.name)
        .filter(Boolean)
        .join(', ')
)
</script>

<template>
    <a
        :href="`/thread/${thread.id}`"
        class="flex items-center gap-4 rounded-lg border border-gray-200 p-4 transition-colors hover:bg-gray-50"
    >
        <div class="flex -space-x-2">
            <AppAvatar
                v-for="p in (thread.participants ?? [])"
                :key="p.user?.id"
                :name="p.user?.name || '?'"
                :online="onlineUserIds.has(p.user?.id)"
            />
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-baseline justify-between gap-2">
                <p class="truncate font-medium text-gray-900">{{ thread.subject }}</p>
                <span class="shrink-0 text-xs text-gray-400">{{ thread.updated_at }}</span>
            </div>
            <p class="truncate text-sm text-gray-500">{{ names }}</p>
            <p v-if="thread.last_message" class="truncate text-sm text-gray-400">
                {{ typeof thread.last_message === 'object' ? JSON.stringify(thread.last_message) : thread.last_message }}
            </p>
            <p class="text-xs text-gray-400">{{ thread.messages_count ?? 0 }} messages</p>
        </div>
        <AppBadge :count="thread.unread_count ?? 0" />
    </a>
</template>
```

If `thread.participants` has more than ~4-5 entries, the overlapping-avatar row could get visually wide — that's an acceptable, minor cosmetic gap for now (not worth building a "+N more" truncation for what's currently a low-participant-count app; revisit if it becomes a real problem).

**Step 4: Thread `onlineUserIds` through `ThreadList.vue`**

```bash
cat resources/js/components/organisms/ThreadList.vue
```

```vue
<script setup>
import ThreadCard from '../molecules/ThreadCard.vue'

defineProps({
    threads: { type: Array, default: () => [] },
    onlineUserIds: { type: Set, default: () => new Set() },
})
</script>

<template>
    <div v-if="threads.length" class="space-y-3">
        <ThreadCard v-for="thread in threads" :key="thread.id" :thread="thread" :online-user-ids="onlineUserIds" />
    </div>
    <p v-else class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-500">
        No conversations yet.
    </p>
</template>
```

**Step 5: Build check**

```bash
npm run build
```

**Step 6: Full backend suite if `MessageService.php` was touched in Step 1**

```bash
/usr/local/opt/php@8.3/bin/php ./vendor/bin/phpunit
/usr/local/opt/php@8.3/bin/php ./vendor/bin/pint --test
/usr/local/opt/php@8.3/bin/php -d memory_limit=1G ./vendor/bin/phpstan analyse
```

**Step 7: Commit**

```bash
git add app/Http/Controllers/FrontEnd/DashboardController.php app/Services/MessageService.php resources/js/components/molecules/ThreadCard.vue resources/js/components/organisms/ThreadList.vue
git commit -m "feat: ThreadCard timestamp, message count, last-message preview, participant avatars"
```

(Only include `app/Services/MessageService.php` if Step 1 required adding `->with('messages')`.)

---

### Task 10: Thread.vue header — participant avatars, message count, switch to `useEchoNotification`

**Files:**
- Modify: `resources/js/pages/Thread.vue`

**Step 1: Read the current file** (already read in Task 8, re-check current state after that task's edits)

**Step 2: Verify `useEchoNotification`'s exact signature**

```bash
grep -n -A10 "useEchoNotification" node_modules/@laravel/echo-vue/dist/index.d.ts
```

Confirm parameter order: `(channelName, callback?, event?, dependencies?)`.

**Step 3: Rewrite the script block**

```vue
<script setup>
import { ref, onMounted, computed } from 'vue'
import { useEchoNotification } from '@laravel/echo-vue'
import AppHeader from '../components/organisms/AppHeader.vue'
import AppAvatar from '../components/atoms/AppAvatar.vue'
import MessageFeed from '../components/organisms/MessageFeed.vue'
import { useOnlinePresence } from '../composables/useOnlinePresence'

const props = defineProps({
    auth: { type: Object, default: null },
    thread: { type: Object, required: true },
})

const messages = ref(props.thread.messages)
const { onlineUserIds } = useOnlinePresence()

const otherParticipants = computed(() =>
    (props.thread.participants ?? []).filter((p) => p.user?.id !== props.auth?.id)
)

function transform(payload) {
    return {
        id: payload.id,
        body: payload.attributes.body,
        created_at: 'just now', // freshly-arrived messages are definitionally "just now"; no relative-time lib needed
        user: {
            id: payload.attributes.user?.attributes?.id,
            name: payload.attributes.user?.attributes?.name,
        },
    }
}

onMounted(() => {
    if (!props.auth) {
        return
    }

    useEchoNotification(`App.Models.User.${props.auth.id}`, (notification) => {
        const payload = notification.payload

        if (payload?.attributes?.thread_id === props.thread.id) {
            if (!messages.value.some((m) => m.id === payload.id)) {
                messages.value.push(transform(payload))
            }
        }
    })
})
</script>

<template>
    <div class="min-h-screen">
        <AppHeader :auth="auth" />

        <main class="mx-auto max-w-3xl px-4 py-10">
            <a href="/dashboard" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            <h1 class="mb-2 mt-2 text-2xl font-bold text-gray-900">{{ thread.subject }}</h1>
            <div class="mb-6 flex items-center gap-3">
                <div class="flex -space-x-2">
                    <AppAvatar
                        v-for="p in otherParticipants"
                        :key="p.user?.id"
                        :name="p.user?.name || '?'"
                        size="sm"
                        :online="onlineUserIds.has(p.user?.id)"
                    />
                </div>
                <span class="text-sm text-gray-500">{{ thread.messages_count ?? messages.length }} messages</span>
            </div>
            <MessageFeed :messages="messages" :online-user-ids="onlineUserIds" />
        </main>
    </div>
</template>
</script>
```

(Remove the stray trailing `</script>` above — that's a typo in this plan text, not something to actually type; the file should end after the closing `</template>`.)

Note this task changed `useEchoNotification`'s call signature slightly from what Task 6/the design doc sketch showed — `useEchoNotification(channelName, callback, event?, dependencies?)`, not `useEchoModel(...).channel().notification(...)`. Double-check against Step 2's actual verified signature before finalizing; adjust if what you find differs from what's shown here.

**Step 4: Build check**

```bash
npm run build
```

**Step 5: Commit**

```bash
git add resources/js/pages/Thread.vue
git commit -m "feat: Thread.vue header shows participant avatars and message count, switch to useEchoNotification"
```

---

### Task 11: Dashboard.vue live wiring

**Files:**
- Modify: `resources/js/pages/Dashboard.vue`

**Step 1: Read the current file**

```bash
cat resources/js/pages/Dashboard.vue
```

**Step 2: Rewrite**

```vue
<script setup>
import { ref, onMounted } from 'vue'
import { useEchoNotification } from '@laravel/echo-vue'
import AppHeader from '../components/organisms/AppHeader.vue'
import ThreadList from '../components/organisms/ThreadList.vue'
import { useOnlinePresence } from '../composables/useOnlinePresence'

const props = defineProps({
    auth: { type: Object, default: null },
    threads: { type: Array, default: () => [] },
})

const threads = ref(props.threads)
const { onlineUserIds } = useOnlinePresence()

function transformThread(payload) {
    return {
        id: payload.id,
        subject: payload.attributes.subject,
        updated_at: 'just now',
        unread_count: 0,
        messages_count: payload.attributes.messages?.length ?? 0,
        last_message: payload.attributes.messages?.[0]?.attributes?.body ?? null,
        participants: (payload.attributes.participants ?? []).map((p) => ({
            user: { id: p.attributes?.user?.attributes?.id, name: p.attributes?.user?.attributes?.name },
        })),
    }
}

onMounted(() => {
    if (!props.auth) {
        return
    }

    useEchoNotification(`App.Models.User.${props.auth.id}`, (notification) => {
        const payload = notification.payload
        const type = notification.type

        if (type === 'App\\Notifications\\ThreadCreated') {
            if (!threads.value.some((t) => t.id === payload.id)) {
                threads.value.unshift(transformThread(payload))
            }
            return
        }

        if (type === 'App\\Notifications\\MessageCreated') {
            const threadId = payload.attributes?.thread_id
            const index = threads.value.findIndex((t) => t.id === threadId)

            if (index === -1) {
                return
            }

            const updated = {
                ...threads.value[index],
                updated_at: 'just now',
                unread_count: (threads.value[index].unread_count ?? 0) + 1,
                messages_count: (threads.value[index].messages_count ?? 0) + 1,
                last_message: payload.attributes?.body ?? threads.value[index].last_message,
            }

            const next = threads.value.filter((_, i) => i !== index)
            next.unshift(updated)
            threads.value = next
        }
    })
})
</script>

<template>
    <div class="min-h-screen">
        <AppHeader :auth="auth" />

        <main class="mx-auto max-w-3xl px-4 py-10">
            <h1 class="mb-6 text-2xl font-bold text-gray-900">Conversations</h1>
            <ThreadList :threads="threads" :online-user-ids="onlineUserIds" />
        </main>
    </div>
</template>
```

Before trusting `notification.type === 'App\\Notifications\\ThreadCreated'`, verify this is actually how `useEchoNotification`'s callback distinguishes notification types — check the `BroadcastNotification<TPayload>` type in `node_modules/@laravel/echo-vue/dist/index.d.ts` (`grep -n "BroadcastNotification" node_modules/@laravel/echo-vue/dist/*.d.ts`) and cross-reference against a real payload if possible (Task 12's manual verification will confirm this empirically — if this task's assumption about the `type` field is wrong, fix it here before moving on, don't wait until Task 12 to discover it).

Also verify `ThreadResource`'s actual JSON shape (`app/Http/Resources/ThreadResource.php`) matches what `transformThread()` assumes — `payload.attributes.subject`, `payload.attributes.messages` (an array of `MessageResource`-shaped objects), `payload.attributes.participants` (an array of `ParticipantResource`-shaped objects, each nesting `user` under `attributes.user.attributes`). Read the actual resource files rather than trusting this plan's assumption blindly.

**Step 3: Build check**

```bash
npm run build
```

**Step 4: Commit**

```bash
git add resources/js/pages/Dashboard.vue
git commit -m "feat: live-wire the Dashboard thread list (new threads, message previews, unread bumps)"
```

---

### Task 12: Manual end-to-end verification

**No files changed — this is a verification-only task, do not skip it.**

Follow the same pattern used for the original Reverb PR's manual verification (real browser, real Reverb server, real triggered actions via `tinker`) — see `docs/plans/2026-07-09-reverb-broadcasting.md` Task 8 for the exact mechanics (starting `php-fpm`/`reverb:start`/`queue:work`/`npm run dev`, generating a session cookie for a headless browser via a temporary test-support login route, using Playwright).

Verify, in order:

1. **Presence**: open the Dashboard/Thread page as one user in one browser context, open the same or a different page as a second user in a second browser context. Confirm the second user's avatar shows the online dot in the first user's view, and that it disappears when the second context closes (or navigates away, per whatever `leaving()` actually fires on).
2. **Dashboard live thread creation**: with the Dashboard open, trigger `newThread()` via `tinker` adding the viewing user as a recipient. Confirm the new thread appears at the top of the list without a refresh.
3. **Dashboard live message preview**: with the Dashboard open and an existing thread visible, trigger `newMessage()` via `tinker` into that thread. Confirm the card's preview/timestamp update and it moves to the top.
4. **Unread counts**: confirm a thread with unread messages shows the correct count on the Dashboard, and that opening it (Thread.vue) clears it — reload the Dashboard afterward and confirm it now shows zero.
5. **Message counts**: confirm `messages_count` shown on both Dashboard cards and the Thread header match the actual number of messages.
6. **Connection hardening**: temporarily stop the Reverb server process, reload a page, and confirm the browser console shows a clear dev-mode warning/error rather than a silent failure or a broken page — the page must still render and be usable for reading.

**If any of these don't work:** do not proceed to Task 13. Use the `superpowers:systematic-debugging` skill — check the browser console, Reverb server logs, and `queue.log`, in that order, same troubleshooting sequence as the original PR.

---

### Task 13: Final full verification

**Step 1: Full check**

```bash
/usr/local/opt/php@8.3/bin/php ./vendor/bin/phpunit
/usr/local/opt/php@8.3/bin/php ./vendor/bin/pint --test
/usr/local/opt/php@8.3/bin/php -d memory_limit=1G ./vendor/bin/phpstan analyse
npm run build
```

All must pass clean.

**Step 2: Push and open the PR**

```bash
git push -u origin feat/reverb-hardening
gh pr create --base master --title "feat: online presence, live dashboard, and thread usability polish" --body "$(cat <<'EOF'
## Summary
- Rebuilds the broken `'online'` channel as a real Laravel presence channel (was returning `$user->toArray()` behind a redundant auth check — not valid presence data).
- Fixes a pre-existing gap: `unread_count` has been hardcoded to `0` since the Dashboard was first built, and viewing a thread never actually marked it read. Both now wired to real logic using the vendor `cmgmyr/messenger` package's existing `userUnreadMessagesCount()` — no custom unread-tracking built from scratch.
- Dashboard now live-updates: new threads appear at the top without a refresh, existing threads' previews update and reorder on new messages, unread counts bump live.
- Thread and Dashboard cards gain: relative timestamps, message counts, last-message previews (ThreadCard had none of these before), and participant avatars with online-status dots.
- One small shared composable (`useOnlinePresence`) for the one piece of logic genuinely duplicated between Dashboard and Thread pages — notification handling stays inline per page since the two react to events differently.
- Switched from `useEchoModel(...).channel().notification(...)` to the more direct `useEchoNotification` hook (available in the installed package version, wasn't when the original PR was built).
- Added dev-mode connection-failure visibility in `app.js` — if Reverb is unreachable or misconfigured, the console says so clearly instead of silently doing nothing; the app stays fully usable either way (broadcasting is enhancement, not a dependency).

Design doc: `docs/plans/2026-07-12-reverb-hardening-design.md`

## Test plan
- [x] `tests/Feature/BroadcastAuthTest.php` — presence channel authorization shape
- [x] Backend feature tests for real unread/message counts and mark-as-read-on-view
- [x] Full PHPUnit suite, pint, phpstan all pass
- [x] Manual verification: presence dots, live thread creation/updates, unread count accuracy, connection-failure visibility — all confirmed against a real Reverb server and real browser sessions
EOF
)"
```

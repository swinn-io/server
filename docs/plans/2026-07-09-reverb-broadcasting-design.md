# Reverb Broadcasting — Design

**Status:** Approved 2026-07-09
**Source:** replace the abandoned `laravel-echo-server` experiment (added 2020, reverted with "echo server never works on localhost") with Laravel's native Reverb WebSocket server, and actually finish delivering real-time message updates.
**Scope this run:** Backend Reverb setup (dual-guard broadcast auth for both the React Native app's Passport tokens and the web session), frontend wiring for `Thread.vue` only. Dashboard live updates, presence, and the React Native client itself are explicitly out of scope.
**Delivery:** single feature branch off `master`, one PR.

---

## 1. Goal

Nothing currently consumes the broadcasting infrastructure that already half-exists in this app (`config/broadcasting.php` redis driver, `routes/channels.php` private `App.Models.User.{id}` channel, `MessageCreated`/`ThreadCreated`/`ParticipantCreated` notifications broadcasting via `via() => 'broadcast'`). A prior attempt to bridge this to the browser via `laravel-echo-server` was abandoned in 2020 because it never worked locally. This PR replaces that dead end with Laravel Reverb — the framework's own WebSocket server, no separate Node process to babysit — and wires `Thread.vue` to prove the whole path works end-to-end.

The React Native app is the actual primary real-time client (it's why Passport/`auth:api` exists on the broadcast auth route in the first place); the web UI is a secondary, intentionally simple surface. This PR must not break the RN app's auth path while adding web support.

## 2. Constraints & Decisions

- **Dual-guard broadcast auth, single route.** `Broadcast::routes(['middleware' => ['auth:api,web']])` — Laravel tries `api` (Passport) then falls back to `web` (session) on the same route, same `routes/channels.php` callbacks. Rejected: minting a Passport token for the browser session and using `auth:api` only — that hands a bearer credential to client-side JS (readable by any XSS on the page, unlike an `HttpOnly` session cookie), and Passport personal access tokens don't expire by default without extra scoping/TTL work this app doesn't have infrastructure for. Dual-guard is less custom logic and doesn't create a new credential-exposure surface.
- **No new channels.** Reuse the existing private `App.Models.User.{id}` channel that Notifications already broadcast on. `Thread.vue` filters incoming payloads client-side by `attributes.thread_id === thread.id` rather than the backend growing a per-thread channel.
- **Keep existing broadcasting connections.** `config/broadcasting.php`'s `redis`/`log`/`null` connections stay defined alongside the new `reverb` one — no reason to delete them, and Reverb's own horizontal-scaling feature (`REVERB_SCALING_ENABLED`) legitimately uses Redis as its pub/sub backbone between multiple Reverb server instances in production, reusing the existing `REDIS_*` env vars. This is a different mechanism from the old `BROADCAST_DRIVER=redis` (which had no consumer) and is worth wiring as a togglable option now.
- **Delete the dead `LARAVEL_ECHO_SERVER_*` env vars** from `.env.example` — leftover from the abandoned 2020 attempt, actively misleading now that this PR replaces that approach entirely.
- **Modern frontend integration: `@laravel/echo-vue`**, not a hand-rolled `window.Echo` global. `configureEcho({ broadcaster: 'reverb' })` once in `app.js`; `useEchoModel('App.Models.User', auth.id)` + `channel().notification()` in `Thread.vue` (there is no dedicated notification hook — this is the documented pattern for listening to Notification broadcasts specifically, as opposed to custom `ShouldBroadcast` events).
- **Queue worker is a hard prerequisite.** `MessageCreated`/`ThreadCreated`/`ParticipantCreated` all implement `ShouldQueue`, so broadcasts never fire without `artisan queue:work` running alongside `artisan reverb:start`. No process manager exists in this repo (no Procfile/supervisor config) — this stays undocumented-as-code, same as the existing `queue:work` situation, just called out explicitly in the README and PR description.
- **Out of scope, explicitly:** Kernel/`bootstrap/app.php` modernization (parked as its own separately-planned initiative — see the companion plan), Dashboard.vue live updates, the `online` presence channel, and any changes to the React Native app (different repo).

## 3. Components

| Component | Path | Change |
|-----------|------|--------|
| Reverb package + config | `composer.json`, `config/reverb.php` (new) | `php artisan install:broadcasting --reverb` |
| Broadcasting config | `config/broadcasting.php` | Add `reverb` connection; keep existing ones |
| Env | `.env.example` | Add `REVERB_*`/`VITE_REVERB_*`, `BROADCAST_DRIVER=reverb`, `REVERB_SCALING_ENABLED=false`; delete `LARAVEL_ECHO_SERVER_*` |
| Broadcast auth | `app/Providers/BroadcastServiceProvider.php` | `auth:api` → `auth:api,web` |
| JS deps | `package.json` | `@laravel/echo-vue` (+ whatever `install:broadcasting --reverb` adds) |
| Echo bootstrap | `resources/js/app.js` | `configureEcho({ broadcaster: 'reverb' })` |
| Live messages | `resources/js/pages/Thread.vue` | `useEchoModel` + `channel().notification()`, local reactive `messages` array, `transform()` payload mapper |
| Broadcast auth test | `tests/Feature/BroadcastAuthTest.php` (new) | Assert `/broadcasting/auth` authorizes correctly under both guards |

## 4. Data Flow

1. `MessageService::newMessage()` creates a `Message`, dispatches `MessageCreated` (queued) to the thread's recipients.
2. `MessageCreated::via()` returns the recipient's `notify_via`; when it includes `'broadcast'`, Laravel's `BroadcastChannel` pushes the notification onto the recipient's private `App.Models.User.{id}` channel via Reverb.
3. `Thread.vue`, subscribed via `useEchoModel('App.Models.User', auth.id)`, receives the notification, checks `notification.payload.attributes.thread_id === thread.id`, and if it matches, maps the payload (`MessageResource` JSON shape: `attributes.thread_id/body/user/...`) into the `{id, body, created_at, user: {name}}` shape `MessageBubble.vue` already expects from the server-rendered initial load, and pushes it into a local reactive array. `created_at` for freshly-pushed messages is rendered as `"just now"` rather than pulling in a relative-time library.
4. Auth for the channel subscription itself goes through `/broadcasting/auth`, guarded by `auth:api,web` — the RN app's Passport bearer token or the web session cookie, whichever is present.

## 5. Testing

- **Automated:** `tests/Feature/BroadcastAuthTest.php` — hits `/broadcasting/auth` for the private `App.Models.User.{id}` channel once authenticated via `actingAs($user, 'api')` (Passport) and once via plain session auth, asserting both authorize the channel's owner and reject a mismatched user.
- **Manual (run before merging):** `artisan reverb:start` + `artisan queue:work` + `npm run dev`, log in via the web UI, open a thread, trigger a new message via `tinker` calling `MessageServiceInterface::newMessage()`, confirm live append without a page refresh.
- **Explicit gap:** the React Native app's delivery path isn't verifiable from this repo. The dual-guard `auth:api` path is covered by the automated test; end-to-end confirmation against the actual RN client is the RN repo's responsibility.

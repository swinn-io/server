# Reverb Hardening & Usability — Design

**Status:** Approved 2026-07-12
**Source:** follow-up to the merged Reverb broadcasting PR (#80) — the backend/wiring exists but the UI around it is bare-bones (no online status, dashboard doesn't live-update, threads carry no timestamps/counts/avatars, unread counts are hardcoded to zero).
**Scope this run:** online presence, Dashboard live wiring (new threads + message preview updates + real unread counts), ThreadCard timestamps/message counts/last-message preview, Thread.vue header (participant avatars + message count), a small shared Echo composable for presence, and basic connection-failure visibility. Everything is scoped to the existing web UI — no changes to the React Native app, no new deployment infra.
**Delivery:** single feature branch off `master`, one PR.

---

## 1. Goal

The Reverb PR shipped working infrastructure but a minimal UI: `Thread.vue` live-appends messages, and that's it. This round makes the feature actually usable — online status, a dashboard that updates itself instead of requiring a refresh, and the count/timestamp/avatar details a real chat UI needs. It also closes a real gap in the current code: `unread_count` has been hardcoded to `0` since the Dashboard was first built, and `ThreadController::show()` never marks a thread read when you open it — both silently broken since day one, not something this PR is introducing.

## 2. Constraints & Decisions

- **Presence, not a heartbeat.** `routes/channels.php`'s `'online'` channel already exists but is broken — it returns `$user->toArray()` behind a redundant `auth()->check()` (the callback only runs for authenticated users in the first place), which isn't valid presence-channel data. Fixed to return `['id' => $user->id, 'name' => $user->name]`, which is all a presence channel needs — Echo/Reverb handle the live roster (`here`/`joining`/`leaving`) automatically. Rejected a last-active-timestamp heartbeat: it requires periodic client pings and DB writes, and isn't actually live — presence is what Reverb is for and costs nothing extra to wire correctly.
- **Reuse the vendor package's existing unread-count method.** `cmgmyr/messenger`'s `Thread` model already has `userUnreadMessagesCount($userId)` — no need to build unread-counting from scratch. Known, accepted tradeoff: this loads each thread's messages into memory to filter/count, one query per thread on the page (~15/page). Not worth a custom aggregate query at this app's current scale; revisit if the dashboard's thread count grows meaningfully.
- **One shared composable, not a general Echo abstraction layer.** `useOnlinePresence()` (`resources/js/composables/useOnlinePresence.js`) is the only genuinely duplicated, stable piece of logic — both `Dashboard.vue` and `Thread.vue` need the same reactive online-user-ID set. Notification listening (`ThreadCreated`/`MessageCreated`) stays inline per page because the two pages react to the same events differently (splice-and-reorder a list vs. append to a message feed) — wrapping that in a shared abstraction would be indirection without payoff. This is the YAGNI/KISS line: abstract the one thing that's truly the same twice, not everything that touches Echo.
- **Switch to `useEchoNotification`.** The installed `@laravel/echo-vue` version (newer than what was available when the original PR was built) has a direct `useEchoNotification` hook. Replaces `useEchoModel(...).channel().notification(...)` in `Thread.vue` with the more direct API; same behavior, one less layer.
- **Hardening means visible-but-non-fatal, not silent-and-broken.** After `configureEcho()` in `app.js`, check `echoIsConfigured()` and watch `useConnectionStatus()`. On a bad/failed connection state, log clearly to the console — louder in `import.meta.env.DEV` since that's when a developer needs to see why live updates aren't arriving. The app must remain fully usable for reading/sending messages if Reverb is unreachable; broadcasting is enhancement, not a dependency of core functionality.
- **Dashboard's unread bump on `MessageCreated` doesn't account for cross-tab state** (e.g., the thread open in another tab). Accepted as a known, minor edge case — building cross-tab sync would be over-engineering for what this app needs today.
- **Out of scope, explicitly:** anything backend-infra (queue driver, Reverb horizontal scaling — both already addressed or deliberately deferred in the original PR), the React Native app, and the Kernel/`bootstrap.php` modernization (its own separate, already-parked initiative).

## 3. Components

| Component | Path | Change |
|-----------|------|--------|
| Presence channel | `routes/channels.php` | `'online'` returns `['id', 'name']` array instead of `$user->toArray()` |
| Unread/message counts | `app/Http/Controllers/FrontEnd/DashboardController.php` | Real `unread_count` via `$thread->userUnreadMessagesCount($user->id)`; `messages_count` via `withCount('messages')` in the underlying query; participant mapping gains `id` |
| Mark-as-read on view | `app/Http/Controllers/FrontEnd/ThreadController.php` | `show()` now calls `MessageServiceInterface::markAsRead()` |
| Thread payload | `app/Http/Controllers/FrontEnd/ThreadController.php` | View data gains `participants` (currently omitted) and `messages_count`; message mapping gains sender `user.id` |
| Query support | `app/Services/MessageService.php` | `threads()` query gains `->withCount('messages')` |
| Presence composable | `resources/js/composables/useOnlinePresence.js` (new) | `useEchoPresence('online')` wrapper exposing a reactive online-user-ID set |
| Connection hardening | `resources/js/app.js` | `echoIsConfigured()` check + `useConnectionStatus()` watcher, dev-visible logging |
| Avatar online dot | `resources/js/components/atoms/AppAvatar.vue` | New `online` boolean prop, small dot overlay |
| Sender online dot | `resources/js/components/molecules/MessageBubble.vue` | Passes `online` to its `AppAvatar`; needs `message.user.id` (currently name-only in both the initial payload and the live `transform()`) |
| Thread card | `resources/js/components/molecules/ThreadCard.vue` | Relative timestamp, message count, last-message preview (new — none of these exist today), participant avatars gain online dots |
| Thread list | `resources/js/components/organisms/ThreadList.vue` | No structural change — still just renders `ThreadCard`s from a reactive array |
| Thread page | `resources/js/pages/Thread.vue` | Header gains a participant-avatar row (excluding self) + message count; switch to `useEchoNotification` |
| Dashboard page | `resources/js/pages/Dashboard.vue` | `threads` prop becomes a local reactive `ref`; subscribes to `ThreadCreated`/`MessageCreated` via `useEchoNotification`, updates/reorders/unshifts accordingly |

## 4. Data Flow

**Presence:** any page mounts `useOnlinePresence()` once → `Echo.join('online')` → `here()` populates the initial online-ID set, `joining()`/`leaving()` keep it live. Every `AppAvatar` usage site checks `onlineUserIds.has(user.id)` to decide whether to render the dot.

**Dashboard live updates:** `useEchoNotification('App.Models.User.{id}', callback)` on the recipient's own private channel (same channel `Thread.vue` already uses, just also listened to here).
- `ThreadCreated` → transform payload → `threads.value.unshift(...)`.
- `MessageCreated` → find thread by `thread_id` in `threads.value` → update preview/`updated_at`/bump `unread_count` → re-sort by `updated_at` descending. Silently no-ops if the thread isn't in the currently-loaded page.

**Thread view:** unchanged flow from the original PR (subscribe, filter by `thread_id`, dedup, append), just via `useEchoNotification` instead of `useEchoModel(...).channel().notification(...)`.

**Mark-as-read:** `ThreadController::show()` calls `markAsRead()` synchronously during the request — no broadcast needed, this is a plain server-side state change that takes effect on the next Dashboard load/refresh.

## 5. Testing

- **Backend:** feature test asserting the `'online'` presence channel's authorization callback returns the correct array shape. Coverage for `DashboardController`'s real unread/message counts (previously untestable since hardcoded) and `ThreadController::show()`'s new `markAsRead()` call.
- **Frontend:** no JS test infra in this repo (confirmed during the original PR) — manual/visual verification, same as before.
- **Manual verification (required before merging):** real Reverb server + real browser session, confirming: online dots appear/disappear as a second session joins/leaves, a new thread appears live on the Dashboard, an existing thread's card updates and reorders on a new message, unread counts are accurate and clear on view, and Reverb-down behavior degrades gracefully with a clear dev-console message rather than a broken page.

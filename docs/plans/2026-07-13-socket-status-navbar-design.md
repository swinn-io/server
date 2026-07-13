# Socket Status in Navbar — Design

**Status:** Approved 2026-07-13
**Source:** follow-up to the merged Reverb work (#80, #82, #83). The Reverb/WebSocket connection state is currently only logged to the dev console in `app.js`; nothing surfaces it to end users.

## 1. Goal

Give authenticated users a subtle, always-present navbar indicator showing whether the live (Reverb/WebSocket) connection is healthy, so they trust that live updates (new messages, presence) are working — and notice when they're not. Three buckets, dot + text label:

| Echo raw state | Bucket |
|----------------|--------|
| `connected` | 🟢 Live |
| `connecting` | 🟡 Connecting… |
| `failed` | 🔴 Offline |
| `disconnected` (and any other) | 🔴 Offline |

## 2. Constraints & Decisions

- **Reactive source is a small custom composable, not `@laravel/echo-vue`'s `useConnectionStatus()`.** `app.js` deliberately moved away from `useConnectionStatus()` because it relies on Vue's `onMounted`/`onUnmounted` internally and was being called at module scope. The new `useSocketStatus()` wraps `echo().connector.onConnectionChange()` — the exact mechanism `app.js` already uses and trusts — for consistency with the codebase and full control over the 4→3 bucket mapping. This mirrors the existing `useOnlinePresence()` composable's shape.
- **Authenticated users only.** The indicator lives inside `AppHeader`'s existing `v-if="auth"` cluster. Live messaging/presence is irrelevant to logged-out visitors on the login/welcome pages, so "Live" would be meaningless there.
- **Separate presentational atom.** `SocketStatus.vue` is a dumb dot+label driven purely by `tone`/`label` props, with no Echo knowledge of its own — consistent with how `AppAvatar`/`AppButton` are factored, and independently renderable/testable.
- **No debounce.** The 3-bucket collapse already smooths the common `connected → connecting → connected` blip into a brief 🟡; adding debounce would be over-engineering (YAGNI).
- **Graceful when Echo is unconfigured/unreachable.** Broadcasting is an enhancement, not a dependency — a missing `VITE_REVERB_APP_KEY` or a down Reverb server must leave the page fully usable, with the indicator simply reading Offline/Connecting. The composable guards its initial `connectionStatus()` read in a try/catch defaulting to Offline.

## 3. Components

| Component | Path | Change |
|-----------|------|--------|
| Socket-status composable | `resources/js/composables/useSocketStatus.js` (new) | Wraps `echo().connector.onConnectionChange()`; exposes a reactive `status` ref (seeded from `echo().connectionStatus()`) and a `computed` bucket `{ label, tone }`. Binds in `setup()`, unbinds via the disposer `onConnectionChange` returns on `onUnmounted`. |
| Status atom | `resources/js/components/atoms/SocketStatus.vue` (new) | Presentational dot + label, modeled on `AppAvatar`'s dot styling (`rounded-full`, colored `bg-*-500`). Props: `label` (string), `tone` (`'green' \| 'amber' \| 'red'`). No Echo knowledge. |
| Navbar | `resources/js/components/organisms/AppHeader.vue` (modified) | Calls `useSocketStatus()`; renders `<SocketStatus :label :tone />` inside the `v-if="auth"` cluster, before the avatar. |

## 4. Data Flow

```
Reverb connection state
  → echo().connector.onConnectionChange(cb)
    → status ref            (useSocketStatus)
      → computed bucket { label, tone }
        → <SocketStatus> props
          → dot + label in AppHeader (auth cluster)
```

One listener per mounted navbar, cleaned up on unmount. Initial value comes from a guarded `echo().connectionStatus()` read at composable setup.

## 5. Error Handling

- **Echo unconfigured (missing `VITE_REVERB_APP_KEY`):** `echo()` still returns a connector; the guarded initial read defaults to Offline and the page stays fully usable. The existing dev-console `console.error` in `app.js` already flags the misconfiguration for developers.
- **Reverb server down / unreachable:** connection events drive the indicator to 🔴 Offline; when the server returns, `onConnectionChange` fires again and it flips back to 🟢 Live. No page breakage either way.

## 6. Testing

- **No JS test infra in this repo** (confirmed during the prior Reverb work) → manual/visual verification, same as before:
  - Load the Dashboard with Reverb running → confirm 🟢 Live.
  - Kill the Reverb server → confirm it flips to 🔴 Offline **without breaking the page** (messages still readable/sendable).
  - Restart Reverb → confirm it returns to 🟡 Connecting… then 🟢 Live.
  - Confirm guests (login/welcome) see no indicator.
- `npm run build` passes clean; `pint`/`phpstan` remain green (no PHP touched).

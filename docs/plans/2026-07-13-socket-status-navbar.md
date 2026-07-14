# Socket Status in Navbar — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a subtle, authenticated-only navbar indicator (🟢 Live / 🟡 Connecting… / 🔴 Offline) showing the Reverb/WebSocket connection state.

**Architecture:** A small `useSocketStatus()` composable wraps `echo().connector.onConnectionChange()` (the same mechanism `app.js` already uses) and exposes a reactive status plus a computed `{ label, tone }` bucket. A dumb presentational `SocketStatus.vue` atom renders a colored dot + label from `tone`/`label` props. `AppHeader.vue` wires the composable to the atom inside its existing `v-if="auth"` cluster.

**Tech Stack:** Vue 3 (`<script setup>`, Composition API), `@laravel/echo-vue` (`echo()`), Tailwind CSS v4, Vite.

**Design doc:** `docs/plans/2026-07-13-socket-status-navbar-design.md`

**Testing note:** This repo has **no JavaScript test infrastructure** (confirmed during the prior Reverb work — the frontend is verified manually/visually; `phpunit`/`pint`/`phpstan` cover PHP only, none of which this feature touches). Do **not** scaffold a JS test harness — that is a large, unrequested change (YAGNI). Verification for each task is a `npm run build` success plus, for the final task, the manual browser checks listed in Task 5. Commit after each task.

**Branch:** `feat/socket-status-navbar` (already created; the design doc is already committed on it).

---

## Reference: what already exists

Before starting, read these so the new code matches house style:

- `resources/js/composables/useOnlinePresence.js` — the composable pattern to mirror (structure, export style).
- `resources/js/app.js` lines 13–29 — the **exact** connection-status mechanism to reuse: `echo()`, `connection.connectionStatus()`, `connection.connector.onConnectionChange(cb)`. Note the comment explaining why `@laravel/echo-vue`'s `useConnectionStatus()` is deliberately **not** used.
- `resources/js/components/atoms/AppAvatar.vue` — the dot styling to echo (`rounded-full bg-green-500 ring-2 ring-white`, size-keyed classes) and the `<script setup>` + `defineProps` + `validator` conventions.
- `resources/js/components/organisms/AppHeader.vue` — the navbar to modify; note the `v-if="auth"` cluster containing `<AppAvatar>` and the logout form.

Key facts about the Echo connection API (verified in prior work, in `node_modules/laravel-echo/dist/echo.common.js`):
- `echo().connectionStatus()` returns one of `'connected'`, `'connecting'`, `'failed'`, `'disconnected'`.
- `echo().connector.onConnectionChange(cb)` calls `cb(status)` on every change **and returns a disposer function** that unbinds the listeners when called.

---

## Task 1: `useSocketStatus()` composable

**Files:**
- Create: `resources/js/composables/useSocketStatus.js`

**Step 1: Write the composable**

Create `resources/js/composables/useSocketStatus.js`:

```js
import { ref, computed, onUnmounted } from 'vue'
import { echo } from '@laravel/echo-vue'

/**
 * Maps Echo's four raw connection states into three user-facing buckets.
 */
function toBucket(status) {
    switch (status) {
        case 'connected':
            return { label: 'Live', tone: 'green' }
        case 'connecting':
            return { label: 'Connecting…', tone: 'amber' }
        default: // 'failed', 'disconnected', or anything unexpected
            return { label: 'Offline', tone: 'red' }
    }
}

/**
 * Reactive Reverb/WebSocket connection status for the navbar indicator.
 *
 * Wraps echo().connector.onConnectionChange() — the same mechanism app.js
 * uses — rather than @laravel/echo-vue's useConnectionStatus(), which is
 * Vue-lifecycle-bound in a way the app deliberately moved away from.
 */
export function useSocketStatus() {
    const status = ref('connecting')

    let dispose = () => {}

    try {
        const connection = echo()
        status.value = connection.connectionStatus()
        dispose = connection.connector.onConnectionChange((next) => {
            status.value = next
        })
    } catch {
        // Echo unconfigured/unavailable (e.g. missing VITE_REVERB_APP_KEY):
        // degrade to Offline instead of throwing — broadcasting is an
        // enhancement, never a hard dependency of the page.
        status.value = 'disconnected'
    }

    onUnmounted(() => dispose())

    const bucket = computed(() => toBucket(status.value))

    return { status, bucket }
}
```

**Step 2: Verify it builds**

Run: `npm run build`
Expected: builds successfully; a new `useSocketStatus-*.js` chunk (or inlined) appears with no errors. (It isn't imported anywhere yet, so it may be tree-shaken out of the build — that's fine; the check here is just that the module is syntactically valid. It gets exercised for real in Task 3.)

**Step 3: Commit**

```bash
git add resources/js/composables/useSocketStatus.js
git commit -m "feat: add useSocketStatus composable for navbar connection indicator"
```

---

## Task 2: `SocketStatus.vue` presentational atom

**Files:**
- Create: `resources/js/components/atoms/SocketStatus.vue`

**Step 1: Write the atom**

Create `resources/js/components/atoms/SocketStatus.vue`. It is purely presentational — a colored dot + label driven by props, no Echo knowledge. The dot styling echoes `AppAvatar`'s dot (`rounded-full`, `bg-*-500`).

```vue
<script setup>
defineProps({
    label: { type: String, required: true },
    tone: {
        type: String,
        default: 'red',
        validator: v => ['green', 'amber', 'red'].includes(v),
    },
})
</script>

<template>
    <span class="inline-flex items-center gap-1.5 text-xs text-gray-500" :title="`Live updates: ${label}`">
        <span
            class="h-2 w-2 shrink-0 rounded-full"
            :class="{
                'bg-green-500': tone === 'green',
                'bg-amber-500': tone === 'amber',
                'bg-red-500': tone === 'red',
            }"
        />
        <span>{{ label }}</span>
    </span>
</template>
```

**Step 2: Verify it builds**

Run: `npm run build`
Expected: builds successfully, no errors. (Still not imported anywhere; wired up in Task 3.)

**Step 3: Commit**

```bash
git add resources/js/components/atoms/SocketStatus.vue
git commit -m "feat: add SocketStatus atom (dot + label)"
```

---

## Task 3: Wire into `AppHeader.vue`

**Files:**
- Modify: `resources/js/components/organisms/AppHeader.vue`

**Step 1: Add the imports and composable call**

In the `<script setup>` block of `resources/js/components/organisms/AppHeader.vue`, add the two imports and call the composable. The resulting script block:

```vue
<script setup>
import { computed } from 'vue'
import AppButton from '../atoms/AppButton.vue'
import AppAvatar from '../atoms/AppAvatar.vue'
import SocketStatus from '../atoms/SocketStatus.vue'
import { useSocketStatus } from '../../composables/useSocketStatus'

defineProps({
    auth: { type: Object, default: null },
})

const appName = computed(() => document.title)
const csrfToken = computed(
    () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
)

const { bucket } = useSocketStatus()
</script>
```

**Step 2: Render the indicator in the auth cluster**

In the template, add `<SocketStatus>` as the first child of the `v-if="auth"` cluster, before `<AppAvatar>`. The resulting cluster:

```vue
            <div v-if="auth" class="flex items-center gap-3">
                <SocketStatus :label="bucket.label" :tone="bucket.tone" />
                <AppAvatar :name="auth.name" size="sm" />
                <form method="POST" action="/logout">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <AppButton as="button" variant="ghost">Log out</AppButton>
                </form>
            </div>
```

Leave the `v-else` guest branch (`Log in` button) untouched — guests see no indicator.

**Step 3: Verify it builds**

Run: `npm run build`
Expected: builds successfully; the `AppHeader-*.js` chunk now pulls in `SocketStatus` and `useSocketStatus`. No errors.

**Step 4: Commit**

```bash
git add resources/js/components/organisms/AppHeader.vue
git commit -m "feat: show socket status in the navbar for authenticated users"
```

---

## Task 4: Confirm reactive cleanup is correct

**Files:** (no code changes — verification only)

**Step 1: Re-read `useSocketStatus.js` against the `onConnectionChange` contract**

Confirm:
- `dispose` is initialized to a no-op `() => {}` so `onUnmounted(() => dispose())` is safe even if the `try` block threw before assigning.
- `dispose` is assigned the return value of `onConnectionChange(...)`, which is the disposer (verified in `node_modules/laravel-echo/dist/echo.common.js` — `onConnectionChange` returns a function that unbinds `state_change`/`connected`/`disconnected`).
- `onUnmounted` is only ever reached inside a component `setup()` (it's called from `AppHeader`'s setup), so it registers correctly — unlike the app.js module-scope case.

**Step 2: No commit** (nothing changed). If any of the above is wrong, fix it and commit with `fix: …`.

---

## Task 5: Manual end-to-end verification

**Files:** (no code changes — verification only)

Run the app with a real Reverb server and confirm the indicator behaves. Reuse the same local setup from the Reverb work: `php artisan reverb:start`, a queue worker, and `npm run dev` (or `npm run build` + serving), logged in as a real user.

**Checks:**

1. **Live:** Load the Dashboard with Reverb running. Confirm the navbar shows a green dot + "Live" next to the avatar.
2. **Offline (graceful):** Kill the Reverb server (`kill` the `reverb:start` process). Within a few seconds confirm the indicator flips to a red dot + "Offline" **and the page stays fully usable** — existing messages still render, no console exception breaks the app.
3. **Recovery:** Restart `php artisan reverb:start`. Confirm the indicator returns to "Connecting…" (amber, briefly) then "Live" (green) without a page reload.
4. **Guests:** Open the login or welcome page while logged out. Confirm **no** indicator appears in the navbar.
5. **Build stays clean:** `npm run build` succeeds. `php artisan test --compact`, `pint --test`, and `phpstan analyse` remain green (no PHP was touched, so this is a sanity check that nothing was disturbed).

**If any check fails:** use the `superpowers:systematic-debugging` skill — check the browser console first, then the Reverb server logs, same troubleshooting order as the original Reverb PR. Do not proceed to opening the PR until all five checks pass.

**No commit** for this task unless a fix was needed.

---

## Task 6: Final verification and open PR

**Step 1: Full check**

```bash
npm run build
/usr/local/opt/php@8.3/bin/php artisan test --compact
/usr/local/opt/php@8.3/bin/php ./vendor/bin/pint --test
/usr/local/opt/php@8.3/bin/php -d memory_limit=1G ./vendor/bin/phpstan analyse
```

All must pass clean.

**Step 2: Push and open the PR**

```bash
git push -u origin feat/socket-status-navbar
gh pr create --base master --title "feat: show live socket status in the navbar" --body "$(cat <<'EOF'
## Summary
- Adds a subtle, authenticated-only navbar indicator of the Reverb/WebSocket connection state: 🟢 Live / 🟡 Connecting… / 🔴 Offline.
- New `useSocketStatus()` composable wraps `echo().connector.onConnectionChange()` — the same mechanism `app.js` already uses — rather than `@laravel/echo-vue`'s lifecycle-bound `useConnectionStatus()`, and maps Echo's four raw states into three user-facing buckets.
- New presentational `SocketStatus.vue` atom (dot + label), modeled on `AppAvatar`'s dot styling; wired into `AppHeader`'s existing `v-if="auth"` cluster so guests never see it.
- Degrades gracefully: if Reverb is unconfigured or unreachable the indicator simply reads Offline and the page stays fully usable — broadcasting is an enhancement, not a dependency.

Design doc: `docs/plans/2026-07-13-socket-status-navbar-design.md`

## Test plan
- [x] Manual: 🟢 Live with Reverb up; flips to 🔴 Offline (page still usable) when Reverb is killed; recovers to 🟡 Connecting… → 🟢 Live on restart; no indicator for logged-out guests.
- [x] `npm run build` clean; `phpunit`/`pint`/`phpstan` green (no PHP touched).
EOF
)"
```

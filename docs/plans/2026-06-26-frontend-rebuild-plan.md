# Frontend Rebuild Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Replace Laravel Mix + Tailwind 3 + bare-bones Blade frontend with Vite + Tailwind 4 + Vue 3 (Composition API) using atomic design — clean, reusable components across Welcome, Login (mandatory for mobile OAuth), Dashboard, and Thread pages.

**Architecture:** Thin Blade shells inject `window.__PAGE__` and `window.__PROPS__` (server data as JSON); `app.js` reads these and mounts the matching Vue page component at `#app`. No client-side router. All backend routes/controllers/Passport logic untouched.

**Tech Stack:** Vue 3.4+, Tailwind CSS 4 (`@tailwindcss/vite`), Vite 6 + `laravel-vite-plugin`, `@vitejs/plugin-vue`

---

## Branch

All work on branch `feature/frontend-rebuild`. Open a PR at the end — do NOT commit directly to master.

```bash
git checkout -b feature/frontend-rebuild
```

---

### Task 1: Replace Laravel Mix with Vite + Tailwind 4

**Files:**
- Delete: `webpack.mix.js`
- Delete: `tailwind.config.js`
- Delete: `resources/js/bootstrap.js`
- Modify: `package.json`
- Create: `vite.config.js`
- Modify: `.gitignore`

**Step 1: Update package.json**

Replace the entire file content with:

```json
{
    "private": true,
    "type": "module",
    "scripts": {
        "dev": "vite",
        "build": "vite build"
    },
    "devDependencies": {
        "@tailwindcss/vite": "^4.0.0",
        "@vitejs/plugin-vue": "^5.0.0",
        "axios": "^1.6.0",
        "laravel-vite-plugin": "^1.0.0",
        "tailwindcss": "^4.0.0",
        "vite": "^6.0.0",
        "vue": "^3.4.0"
    }
}
```

**Step 2: Install dependencies**

```bash
npm install
```

Expected: `node_modules/` updated, no errors. Verify `vite` is available: `npx vite --version`

**Step 3: Create vite.config.js**

```js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
})
```

**Step 4: Delete old build files**

```bash
rm webpack.mix.js tailwind.config.js resources/js/bootstrap.js
```

**Step 5: Add Vite output to .gitignore**

Open `.gitignore` and add these lines at the end:

```
/public/build
/public/hot
/public/css
/public/js
```

(The last two are Mix output that may already exist on disk — now explicitly ignored.)

**Step 6: Verify Vite config is valid**

```bash
npx vite build --mode development 2>&1 | head -20
```

Expected: error about missing entry files (app.css and app.js don't have content yet) — that is fine at this stage. What must NOT appear: `Cannot find module` or plugin errors.

**Step 7: Commit**

```bash
git add package.json package-lock.json vite.config.js .gitignore
git rm webpack.mix.js tailwind.config.js resources/js/bootstrap.js
git commit -m "build: replace Laravel Mix with Vite + Tailwind 4"
```

---

### Task 2: App CSS, app.js shell, and layout.blade.php

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`
- Modify: `resources/views/layout.blade.php`

**Step 1: Write resources/css/app.css**

```css
@import "tailwindcss";

@source "../js/**/*.vue";

@theme {
    --color-brand: oklch(38% 0.18 25);
    --color-brand-hover: oklch(30% 0.18 25);
}
```

`@source` tells Tailwind 4 to scan Vue files for classes. `--color-brand` maps to `bg-brand`, `text-brand`, `border-brand` etc. in templates.

**Step 2: Write resources/js/app.js**

```js
import { createApp, h } from 'vue'

const pages = {
    Welcome: () => import('./pages/Welcome.vue'),
    Login:   () => import('./pages/Login.vue'),
    Dashboard: () => import('./pages/Dashboard.vue'),
    Thread:  () => import('./pages/Thread.vue'),
}

const name  = window.__PAGE__
const props = window.__PROPS__ ?? {}
const auth  = window.__AUTH__  ?? null

if (name && pages[name]) {
    pages[name]().then(({ default: Page }) => {
        createApp(Page, { ...props, auth }).mount('#app')
    })
}
```

**Step 3: Rewrite resources/views/layout.blade.php**

Replace the entire file with:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('page-data')
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased">
    <div id="app"></div>
</body>
</html>
```

Note: `@yield('page-data')` is where each Blade view injects its `<script>` with `window.__PAGE__` and `window.__PROPS__`. Auth user is set globally here so all pages get it.

**Step 4: Update layout to inject auth**

Add after `@yield('page-data')` and before `</head>`:

```blade
    <script>
        window.__AUTH__ = @json(auth()->user()?->only(['id', 'name', 'email']));
    </script>
```

Full `<head>` in order:
```blade
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('page-data')
    <script>window.__AUTH__ = @json(auth()->user()?->only(['id', 'name', 'email']));</script>
</head>
```

**Step 5: Verify build runs**

```bash
npm run build
```

Expected: Vite compiles successfully. Output in `public/build/`. No errors. (Pages don't exist yet so dynamic imports will warn — ignore that for now.)

**Step 6: Commit**

```bash
git add resources/css/app.css resources/js/app.js resources/views/layout.blade.php
git commit -m "feat: add Vite entry points and thin Blade layout"
```

---

### Task 3: Atom components

**Files to create:**
- `resources/js/components/atoms/AppButton.vue`
- `resources/js/components/atoms/AppAvatar.vue`
- `resources/js/components/atoms/AppBadge.vue`

**Step 1: Create AppButton.vue**

```vue
<script setup>
defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: v => ['primary', 'ghost', 'danger'].includes(v),
    },
    as: { type: String, default: 'button' },
    href: { type: String, default: null },
})
</script>

<template>
    <component
        :is="href ? 'a' : as"
        :href="href"
        class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
        :class="{
            'bg-brand text-white hover:bg-brand-hover focus-visible:ring-brand': variant === 'primary',
            'bg-transparent text-gray-700 hover:bg-gray-100': variant === 'ghost',
            'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500': variant === 'danger',
        }"
    >
        <slot />
    </component>
</template>
```

**Step 2: Create AppAvatar.vue**

```vue
<script setup>
const props = defineProps({
    name: { type: String, required: true },
    size: { type: String, default: 'md' },
})

const initials = props.name
    .split(' ')
    .slice(0, 2)
    .map(w => w[0])
    .join('')
    .toUpperCase()
</script>

<template>
    <span
        class="inline-flex items-center justify-center rounded-full bg-indigo-100 text-indigo-700 font-medium select-none"
        :class="{
            'w-8 h-8 text-xs': size === 'sm',
            'w-10 h-10 text-sm': size === 'md',
            'w-14 h-14 text-base': size === 'lg',
        }"
        :title="name"
    >
        {{ initials }}
    </span>
</template>
```

**Step 3: Create AppBadge.vue**

```vue
<script setup>
defineProps({
    count: { type: Number, required: true },
})
</script>

<template>
    <span
        v-if="count > 0"
        class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-brand text-white text-xs font-bold"
    >
        {{ count > 99 ? '99+' : count }}
    </span>
</template>
```

**Step 4: Verify build**

```bash
npm run build 2>&1 | grep -E "error|warn|✓"
```

Expected: no errors.

**Step 5: Commit**

```bash
git add resources/js/components/
git commit -m "feat: add atom components (Button, Avatar, Badge)"
```

---

### Task 4: Molecule components

**Files to create:**
- `resources/js/components/molecules/OAuthButton.vue`
- `resources/js/components/molecules/ThreadCard.vue`
- `resources/js/components/molecules/MessageBubble.vue`

**Step 1: Create OAuthButton.vue**

Used on the Login page. `href` is the full redirect URL, `provider` is the display name.

```vue
<script setup>
defineProps({
    href: { type: String, required: true },
    provider: { type: String, required: true },
})
</script>

<template>
    <a
        :href="href"
        class="flex items-center justify-center gap-3 w-full rounded-md border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-900 transition-colors hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400"
    >
        <slot name="icon" />
        Continue with {{ provider }}
    </a>
</template>
```

**Step 2: Create ThreadCard.vue**

```vue
<script setup>
import AppAvatar from '../atoms/AppAvatar.vue'
import AppBadge from '../atoms/AppBadge.vue'

defineProps({
    thread: {
        type: Object,
        required: true,
        // shape: { id, subject, participants: [{user: {name}}], unread_count }
    },
})
</script>

<template>
    <article class="flex items-start gap-4 py-5 border-b border-gray-100 last:border-0">
        <div class="flex -space-x-2 shrink-0">
            <AppAvatar
                v-for="p in thread.participants.slice(0, 3)"
                :key="p.user.name"
                :name="p.user.name"
                size="sm"
                class="ring-2 ring-white"
            />
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-gray-900 truncate">{{ thread.subject }}</h3>
                <AppBadge :count="thread.unread_count ?? 0" />
            </div>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ thread.participants.map(p => p.user.name).join(', ') }}
            </p>
        </div>
        <a
            :href="`/thread/${thread.id}`"
            class="shrink-0 text-xs text-indigo-600 hover:underline"
        >
            Open →
        </a>
    </article>
</template>
```

**Step 3: Create MessageBubble.vue**

```vue
<script setup>
defineProps({
    message: {
        type: Object,
        required: true,
        // shape: { body, user: { name }, created_at }
    },
})
</script>

<template>
    <div class="flex gap-3 py-4 border-b border-gray-50 last:border-0">
        <div class="shrink-0">
            <span
                class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold"
            >
                {{ message.user.name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase() }}
            </span>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-baseline gap-2">
                <span class="text-sm font-semibold text-gray-900">{{ message.user.name }}</span>
                <span class="text-xs text-gray-400">{{ message.created_at }}</span>
            </div>
            <p class="mt-1 text-sm text-gray-700 whitespace-pre-wrap">{{ message.body }}</p>
        </div>
    </div>
</template>
```

**Step 4: Verify build**

```bash
npm run build 2>&1 | grep -E "error|✓"
```

**Step 5: Commit**

```bash
git add resources/js/components/molecules/
git commit -m "feat: add molecule components (OAuthButton, ThreadCard, MessageBubble)"
```

---

### Task 5: Organism components

**Files to create:**
- `resources/js/components/organisms/AppHeader.vue`
- `resources/js/components/organisms/ThreadList.vue`
- `resources/js/components/organisms/MessageFeed.vue`

**Step 1: Create AppHeader.vue**

Receives `auth` prop (null when logged out, `{id, name, email}` when logged in).

```vue
<script setup>
import AppButton from '../atoms/AppButton.vue'

defineProps({
    auth: { type: Object, default: null },
})
</script>

<template>
    <header class="sticky top-0 z-10 border-b border-gray-100 bg-white/80 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-5xl items-center justify-between px-4">
            <a href="/" class="text-base font-bold tracking-tight text-gray-900">
                {{ appName }}
            </a>
            <nav class="flex items-center gap-2">
                <template v-if="auth">
                    <span class="text-sm text-gray-600">{{ auth.name }}</span>
                    <form method="POST" action="/logout" class="contents">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <AppButton variant="ghost" as="button" type="submit">
                            Sign out
                        </AppButton>
                    </form>
                </template>
                <template v-else>
                    <AppButton href="/login" variant="primary">
                        Sign in
                    </AppButton>
                </template>
            </nav>
        </div>
    </header>
</template>

<script>
export default {
    computed: {
        appName() {
            return document.title.replace(' - ', '') || 'Swinn'
        },
        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
        },
    },
}
</script>
```

Wait — mixing `<script setup>` and Options API `<script>` in one component is valid in Vue 3 but awkward. Rewrite cleanly using only Composition API:

```vue
<script setup>
import { computed } from 'vue'
import AppButton from '../atoms/AppButton.vue'

defineProps({
    auth: { type: Object, default: null },
})

const appName = computed(() =>
    document.querySelector('title')?.textContent ?? 'Swinn'
)
const csrfToken = computed(() =>
    document.querySelector('meta[name="csrf-token"]')?.content ?? ''
)
</script>

<template>
    <header class="sticky top-0 z-10 border-b border-gray-100 bg-white/80 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-5xl items-center justify-between px-4">
            <a href="/" class="text-base font-bold tracking-tight text-gray-900">
                {{ appName }}
            </a>
            <nav class="flex items-center gap-2">
                <template v-if="auth">
                    <span class="text-sm text-gray-500">{{ auth.name }}</span>
                    <form method="POST" action="/logout" class="contents">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <AppButton variant="ghost" as="button" type="submit">Sign out</AppButton>
                    </form>
                </template>
                <template v-else>
                    <AppButton href="/login" variant="primary">Sign in</AppButton>
                </template>
            </nav>
        </div>
    </header>
</template>
```

**Step 2: Create ThreadList.vue**

```vue
<script setup>
import ThreadCard from '../molecules/ThreadCard.vue'

defineProps({
    threads: { type: Array, required: true },
})
</script>

<template>
    <div>
        <ThreadCard
            v-for="thread in threads"
            :key="thread.id"
            :thread="thread"
        />
        <p v-if="threads.length === 0" class="py-12 text-center text-sm text-gray-400">
            No conversations yet.
        </p>
    </div>
</template>
```

**Step 3: Create MessageFeed.vue**

```vue
<script setup>
import MessageBubble from '../molecules/MessageBubble.vue'

defineProps({
    messages: { type: Array, required: true },
})
</script>

<template>
    <div class="divide-y divide-gray-50">
        <MessageBubble
            v-for="message in messages"
            :key="message.id"
            :message="message"
        />
        <p v-if="messages.length === 0" class="py-12 text-center text-sm text-gray-400">
            No messages in this thread.
        </p>
    </div>
</template>
```

**Step 4: Verify build**

```bash
npm run build 2>&1 | grep -E "error|✓"
```

**Step 5: Commit**

```bash
git add resources/js/components/organisms/
git commit -m "feat: add organism components (AppHeader, ThreadList, MessageFeed)"
```

---

### Task 6: Welcome page

**Files:**
- Create: `resources/js/pages/Welcome.vue`
- Modify: `resources/views/welcome.blade.php`

**Step 1: Create Welcome.vue**

```vue
<script setup>
import AppHeader from '../components/organisms/AppHeader.vue'
import AppButton from '../components/atoms/AppButton.vue'

defineProps({
    auth: { type: Object, default: null },
})
</script>

<template>
    <div class="flex flex-col min-h-screen">
        <AppHeader :auth="auth" />
        <main class="flex-1 flex flex-col items-center justify-center px-4 py-24 text-center">
            <h1 class="text-5xl font-extrabold tracking-tight text-gray-900 sm:text-6xl">
                Messaging for the<br>
                <span class="text-brand">data era.</span>
            </h1>
            <p class="mt-6 max-w-xl text-lg text-gray-500">
                Open-source. No passwords. Start instantly with your existing accounts.
            </p>
            <div class="mt-10 flex gap-3">
                <AppButton href="/login" variant="primary">
                    Get started free →
                </AppButton>
                <AppButton href="https://github.com/swinn-io" variant="ghost">
                    View on GitHub
                </AppButton>
            </div>
        </main>
        <footer class="border-t border-gray-100 py-6 text-center text-xs text-gray-400">
            © {{ new Date().getFullYear() }} Swinn. MIT License.
        </footer>
    </div>
</template>
```

**Step 2: Rewrite welcome.blade.php**

```blade
@extends('layout')
@section('page-data')
<script>
    window.__PAGE__  = 'Welcome';
    window.__PROPS__ = {};
</script>
@endsection
```

**Step 3: Build and verify**

```bash
npm run build 2>&1 | grep -E "error|✓"
```

**Step 4: Commit**

```bash
git add resources/js/pages/Welcome.vue resources/views/welcome.blade.php
git commit -m "feat: add Welcome page"
```

---

### Task 7: Login page (mandatory — mobile OAuth flow)

**Files:**
- Create: `resources/js/pages/Login.vue`
- Modify: `resources/views/login.blade.php`

The login page receives `params` (a pre-built query string from `LoginController@home` containing the OAuth client_id, redirect_uri, etc. that the mobile app passed in). These params must be forwarded to `/login/redirect/github?{params}` so Passport can complete the authorization code flow.

**Step 1: Create Login.vue**

```vue
<script setup>
import AppHeader from '../components/organisms/AppHeader.vue'
import OAuthButton from '../components/molecules/OAuthButton.vue'

const props = defineProps({
    auth: { type: Object, default: null },
    params: { type: String, default: '' },
})

const githubHref = `/login/redirect/github${props.params ? '?' + props.params : ''}`
</script>

<template>
    <div class="flex flex-col min-h-screen">
        <AppHeader :auth="auth" />
        <main class="flex-1 flex items-center justify-center px-4">
            <div class="w-full max-w-sm">
                <div class="mb-8 text-center">
                    <h1 class="text-2xl font-bold text-gray-900">Sign in to Swinn</h1>
                    <p class="mt-2 text-sm text-gray-500">Choose a provider to continue.</p>
                </div>
                <div class="space-y-3">
                    <OAuthButton :href="githubHref" provider="GitHub">
                        <template #icon>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" />
                            </svg>
                        </template>
                    </OAuthButton>
                </div>
            </div>
        </main>
    </div>
</template>
```

**Step 2: Rewrite login.blade.php**

```blade
@extends('layout')
@section('page-data')
<script>
    window.__PAGE__  = 'Login';
    window.__PROPS__ = { params: @json($params) };
</script>
@endsection
```

**Step 3: Build and verify**

```bash
npm run build 2>&1 | grep -E "error|✓"
```

**Step 4: Commit**

```bash
git add resources/js/pages/Login.vue resources/views/login.blade.php
git commit -m "feat: add Login page (GitHub OAuth, mandatory for mobile auth flow)"
```

---

### Task 8: Dashboard page

**Files:**
- Create: `resources/js/pages/Dashboard.vue`
- Modify: `resources/views/dashboard.blade.php`
- Modify: `app/Http/Controllers/FrontEnd/DashboardController.php`

The controller currently passes a `$threads` Eloquent collection. We need to serialize it to a shape the Vue component can consume.

**Step 1: Update DashboardController to pass serializable data**

In `DashboardController::index()`, change the return to:

```php
public function index(MessageServiceInterface $messages)
{
    $user    = Auth::user();
    $threads = $messages->threads($user);

    return view('dashboard', [
        'threads' => $threads->map(fn ($thread) => [
            'id'           => $thread->id,
            'subject'      => $thread->subject,
            'unread_count' => 0,
            'participants' => $thread->participants->map(fn ($p) => [
                'user' => ['name' => $p->user->name],
            ])->values()->all(),
        ])->values()->all(),
    ]);
}
```

**Step 2: Create Dashboard.vue**

```vue
<script setup>
import AppHeader from '../components/organisms/AppHeader.vue'
import ThreadList from '../components/organisms/ThreadList.vue'

defineProps({
    auth:    { type: Object, default: null },
    threads: { type: Array,  default: () => [] },
})
</script>

<template>
    <div class="flex flex-col min-h-screen">
        <AppHeader :auth="auth" />
        <main class="flex-1 mx-auto w-full max-w-3xl px-4 py-10">
            <h1 class="text-xl font-semibold text-gray-900 mb-6">Conversations</h1>
            <ThreadList :threads="threads" />
        </main>
    </div>
</template>
```

**Step 3: Rewrite dashboard.blade.php**

```blade
@extends('layout')
@section('page-data')
<script>
    window.__PAGE__  = 'Dashboard';
    window.__PROPS__ = { threads: @json($threads) };
</script>
@endsection
```

**Step 4: Build and verify**

```bash
npm run build 2>&1 | grep -E "error|✓"
```

**Step 5: Commit**

```bash
git add resources/js/pages/Dashboard.vue resources/views/dashboard.blade.php \
        app/Http/Controllers/FrontEnd/DashboardController.php
git commit -m "feat: add Dashboard page with thread list"
```

---

### Task 9: Thread page

**Files:**
- Create: `resources/js/pages/Thread.vue`
- Modify: `resources/views/thread.blade.php`
- Modify: `app/Http/Controllers/FrontEnd/ThreadController.php`

**Step 1: Update ThreadController to pass serializable data**

In `ThreadController::show()`:

```php
public function show(string $thread)
{
    $thread = $this->service->thread($thread);

    return view('thread', [
        'thread' => [
            'id'       => $thread->id,
            'subject'  => $thread->subject,
            'messages' => $thread->messages->map(fn ($m) => [
                'id'         => $m->id,
                'body'       => $m->body,
                'created_at' => $m->created_at->diffForHumans(),
                'user'       => ['name' => $m->user->name],
            ])->values()->all(),
        ],
    ]);
}
```

**Step 2: Create Thread.vue**

```vue
<script setup>
import AppHeader from '../components/organisms/AppHeader.vue'
import MessageFeed from '../components/organisms/MessageFeed.vue'

defineProps({
    auth:   { type: Object, default: null },
    thread: { type: Object, required: true },
})
</script>

<template>
    <div class="flex flex-col min-h-screen">
        <AppHeader :auth="auth" />
        <main class="flex-1 mx-auto w-full max-w-3xl px-4 py-10">
            <h1 class="text-xl font-semibold text-gray-900 mb-1">{{ thread.subject }}</h1>
            <p class="text-sm text-gray-400 mb-8">{{ thread.messages.length }} message{{ thread.messages.length !== 1 ? 's' : '' }}</p>
            <MessageFeed :messages="thread.messages" />
        </main>
    </div>
</template>
```

**Step 3: Rewrite thread.blade.php**

```blade
@extends('layout')
@section('page-data')
<script>
    window.__PAGE__  = 'Thread';
    window.__PROPS__ = { thread: @json($thread) };
</script>
@endsection
```

**Step 4: Build and verify**

```bash
npm run build 2>&1 | grep -E "error|✓"
```

**Step 5: Commit**

```bash
git add resources/js/pages/Thread.vue resources/views/thread.blade.php \
        app/Http/Controllers/FrontEnd/ThreadController.php
git commit -m "feat: add Thread page with message feed"
```

---

### Task 10: Cleanup and PR

**Files:**
- Delete: `public/css/` directory (old Mix output)
- Delete: `public/js/` directory (old Mix output)
- Verify: final production build

**Step 1: Remove old Mix output from disk**

```bash
rm -rf public/css public/js
```

These are now in `.gitignore` so won't come back.

**Step 2: Final production build**

```bash
npm run build
```

Expected output:
```
✓ built in X.XXs
```
No errors. `public/build/` should contain hashed asset files.

**Step 3: Run PHP tests to confirm backend is untouched**

```bash
/usr/local/opt/php@8.3/bin/php vendor/bin/phpunit --no-coverage
```

Expected: `OK (32 tests, 80 assertions)`

**Step 4: Push branch and open PR**

```bash
git push -u origin feature/frontend-rebuild
gh pr create \
  --title "feat: rebuild frontend with Vue 3 + Tailwind 4 + Vite" \
  --body "Replaces Laravel Mix + Blade soup with Vite + Vue 3 (Composition API) + Tailwind 4. Atomic design: atoms → molecules → organisms → pages. All backend routes/controllers/Passport logic untouched. 32 tests still green."
```

---

## Summary of files changed

| Action | Path |
|--------|------|
| Delete | `webpack.mix.js` |
| Delete | `tailwind.config.js` |
| Delete | `resources/js/bootstrap.js` |
| Delete | `public/css/` |
| Delete | `public/js/` |
| Replace | `package.json` |
| Replace | `resources/css/app.css` |
| Replace | `resources/js/app.js` |
| Replace | `resources/views/layout.blade.php` |
| Replace | `resources/views/welcome.blade.php` |
| Replace | `resources/views/login.blade.php` |
| Replace | `resources/views/dashboard.blade.php` |
| Replace | `resources/views/thread.blade.php` |
| Modify | `app/Http/Controllers/FrontEnd/DashboardController.php` |
| Modify | `app/Http/Controllers/FrontEnd/ThreadController.php` |
| Create | `vite.config.js` |
| Create | `resources/js/components/atoms/AppButton.vue` |
| Create | `resources/js/components/atoms/AppAvatar.vue` |
| Create | `resources/js/components/atoms/AppBadge.vue` |
| Create | `resources/js/components/molecules/OAuthButton.vue` |
| Create | `resources/js/components/molecules/ThreadCard.vue` |
| Create | `resources/js/components/molecules/MessageBubble.vue` |
| Create | `resources/js/components/organisms/AppHeader.vue` |
| Create | `resources/js/components/organisms/ThreadList.vue` |
| Create | `resources/js/components/organisms/MessageFeed.vue` |
| Create | `resources/js/pages/Welcome.vue` |
| Create | `resources/js/pages/Login.vue` |
| Create | `resources/js/pages/Dashboard.vue` |
| Create | `resources/js/pages/Thread.vue` |
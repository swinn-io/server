# Frontend Rebuild Design

**Goal:** Replace the eye-bleeding Blade+Mix frontend with a clean, atomic, Vue 3 + Tailwind 4 + Vite stack. The OAuth login pages are mandatory (mobile auth flow); the rest is polish.

**Architecture:** Thin Blade shells mount Vue page components. Server data is injected as JSON props via `@json()`. No client-side router, no Inertia — standard multi-page Laravel with a proper component system on top.

**Tech Stack:** Vue 3 (Composition API), Tailwind CSS 4 (Vite plugin, CSS-first), Vite + `laravel-vite-plugin` + `@vitejs/plugin-vue`

---

## What gets deleted

- `laravel-mix` and `webpack.mix.js`
- `resources/js/bootstrap.js`
- All inline Tailwind utility soup in existing Blade views
- The doubled-up GitHub button on the login page
- `public/css/` and `public/js/` (compiled Mix output, replaced by Vite)
- `tailwind.config.js` (Tailwind 4 is CSS-first, no config file)

## What stays untouched

- All backend routes (`routes/web.php`, `routes/api.php`)
- All controllers (FrontEnd + LoginController + API)
- Passport OAuth logic
- `.env`, migrations, models

---

## Component Structure

```
resources/
  css/
    app.css                    ← @import "tailwindcss"; only
  js/
    components/
      atoms/
        AppButton.vue          ← variant prop: primary | ghost | danger
        AppAvatar.vue          ← src + name fallback to initials
        AppBadge.vue           ← count display
        AppIcon.vue            ← wraps SVG slot
      molecules/
        OAuthButton.vue        ← provider name + icon + href
        ThreadCard.vue         ← subject + participants + last activity
        MessageBubble.vue      ← body + sender + timestamp
      organisms/
        AppHeader.vue          ← logo + auth nav (login/logout/username)
        ThreadList.vue         ← list of ThreadCard
        MessageFeed.vue        ← list of MessageBubble
    pages/
      Welcome.vue              ← landing: hero + CTA
      Login.vue                ← OAuth provider buttons (GitHub mandatory)
      Dashboard.vue            ← authenticated: thread list
      Thread.vue               ← thread detail: message feed
    app.js                     ← createApp, register page component, mount
  views/
    layout.blade.php           ← <head> + @vite + AppHeader organism + <div id="app"> + footer
    welcome.blade.php          ← passes {} to Welcome.vue
    login.blade.php            ← passes { params } to Login.vue
    dashboard.blade.php        ← passes { threads } to Dashboard.vue
    thread.blade.php           ← passes { thread } to Thread.vue
```

## Data flow

Each Blade view renders a `<script>` tag with page props and identifies which Vue page to mount:

```blade
<script>window.__PAGE__ = "Dashboard"; window.__PROPS__ = @json($data);</script>
```

`app.js` reads `window.__PAGE__`, imports the matching page component, and mounts it with `window.__PROPS__` as props. One bundle, zero client-side routing.

## Build config

- `vite.config.js`: `laravel-vite-plugin` (entry: `resources/js/app.js`, `resources/css/app.css`) + `@vitejs/plugin-vue`
- `layout.blade.php`: `@vite(['resources/css/app.css', 'resources/js/app.js'])`
- No `tailwind.config.js` — Tailwind 4 auto-detects template files via `@source` in CSS

## Design tokens (Tailwind 4 CSS vars)

Defined in `app.css` under `@theme`:
- Brand red: `--color-brand: oklch(...)` 
- Neutral scale via Tailwind defaults
- Font: system stack (no external font load)
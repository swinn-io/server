import { createApp } from 'vue'
import { configureEcho, echo } from '@laravel/echo-vue'

if (import.meta.env.DEV && !import.meta.env.VITE_REVERB_APP_KEY) {
    console.error(
        '[reverb] VITE_REVERB_APP_KEY is not set — live updates (messages, presence) will not work. ' +
        'Check .env and run `php artisan reverb:install` if needed.'
    )
}

configureEcho({ broadcaster: 'reverb' })

if (import.meta.env.DEV) {
    // useConnectionStatus() relies on Vue's onMounted/onUnmounted internally, which
    // only register when called during a component's setup(). This code runs at
    // module scope, before any component mounts, so that composable's subscription
    // would silently never fire past the first tick. Bind directly on the connector
    // instead — Echo/pusher-js's connection events have no Vue-lifecycle dependency,
    // so this keeps reporting status changes for the life of the page.
    const logConnectionStatus = (status) => {
        if (status !== 'connected') {
            console.warn(`[reverb] connection status: ${status}`)
        }
    }

    const connection = echo()
    logConnectionStatus(connection.connectionStatus())
    connection.connector.onConnectionChange(logConnectionStatus)
}

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
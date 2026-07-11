import { createApp } from 'vue'
import { configureEcho } from '@laravel/echo-vue'

configureEcho({ broadcaster: 'reverb' })

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
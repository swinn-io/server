<script setup>
import AppHeader from '../components/organisms/AppHeader.vue'
import AppButton from '../components/atoms/AppButton.vue'

defineProps({
    auth: { type: Object, default: null },
})
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <AppHeader :auth="auth" />

        <main class="flex flex-1 items-center justify-center px-4">
            <div class="mx-auto max-w-2xl space-y-6 text-center">
                <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl">
                    Level-up your
                    <span class="text-brand">messaging experience</span>
                </h1>
                <p class="mx-auto max-w-md text-lg text-gray-500">
                    An open-source messaging service for the data era.
                </p>
                <div class="flex flex-col justify-center gap-3 sm:flex-row">
                    <AppButton :href="auth ? '/dashboard' : '/login'" variant="primary">
                        {{ auth ? 'Go to dashboard' : 'Get started' }}
                    </AppButton>
                    <AppButton href="https://github.com/swinn-io" variant="ghost">
                        Learn more
                    </AppButton>
                </div>
            </div>
        </main>

        <footer class="border-t border-gray-200 py-6 text-center text-sm text-gray-400">
            &copy; {{ new Date().getFullYear() }} Swinn
        </footer>
    </div>
</template>
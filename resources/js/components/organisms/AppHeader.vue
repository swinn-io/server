<script setup>
import { computed } from 'vue'
import AppButton from '../atoms/AppButton.vue'
import AppAvatar from '../atoms/AppAvatar.vue'

defineProps({
    auth: { type: Object, default: null },
})

const appName = computed(() => document.title)
const csrfToken = computed(
    () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
)
</script>

<template>
    <header class="border-b border-gray-200">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <a href="/" class="text-lg font-semibold text-gray-900">{{ appName }}</a>

            <div v-if="auth" class="flex items-center gap-3">
                <AppAvatar :name="auth.name" size="sm" />
                <form method="POST" action="/logout">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <AppButton as="button" variant="ghost">Log out</AppButton>
                </form>
            </div>
            <AppButton v-else href="/login" variant="ghost">Log in</AppButton>
        </div>
    </header>
</template>
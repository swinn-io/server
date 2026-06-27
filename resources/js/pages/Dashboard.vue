<script setup>
import AppHeader from '../components/organisms/AppHeader.vue'
import ThreadList from '../components/organisms/ThreadList.vue'

defineProps({
    auth: { type: Object, default: null },
    threads: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="min-h-screen">
        <AppHeader :auth="auth" />

        <main class="mx-auto max-w-3xl px-4 py-10">
            <h1 class="mb-6 text-2xl font-bold text-gray-900">Conversations</h1>
            <ThreadList :threads="threads" />
        </main>
    </div>
</template>
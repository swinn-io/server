<script setup>
import AppHeader from '../components/organisms/AppHeader.vue'
import MessageFeed from '../components/organisms/MessageFeed.vue'

defineProps({
    auth: { type: Object, default: null },
    thread: { type: Object, required: true },
})
</script>

<template>
    <div class="min-h-screen">
        <AppHeader :auth="auth" />

        <main class="mx-auto max-w-3xl px-4 py-10">
            <a href="/dashboard" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            <h1 class="mb-6 mt-2 text-2xl font-bold text-gray-900">{{ thread.subject }}</h1>
            <MessageFeed :messages="thread.messages" />
        </main>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useEchoModel } from '@laravel/echo-vue'
import AppHeader from '../components/organisms/AppHeader.vue'
import MessageFeed from '../components/organisms/MessageFeed.vue'

const props = defineProps({
    auth: { type: Object, default: null },
    thread: { type: Object, required: true },
})

const messages = ref(props.thread.messages)

function transform(payload) {
    return {
        id: payload.id,
        body: payload.attributes.body,
        created_at: 'just now', // freshly-arrived messages are definitionally "just now"; no relative-time lib needed
        user: {
            id: payload.attributes.user?.attributes?.id,
            name: payload.attributes.user?.attributes?.name,
        },
    }
}

onMounted(() => {
    if (!props.auth) {
        return
    }

    const { channel } = useEchoModel('App.Models.User', props.auth.id)

    channel().notification((notification) => {
        const payload = notification.payload

        if (payload?.attributes?.thread_id === props.thread.id) {
            if (!messages.value.some((m) => m.id === payload.id)) {
                messages.value.push(transform(payload))
            }
        }
    })
})
</script>

<template>
    <div class="min-h-screen">
        <AppHeader :auth="auth" />

        <main class="mx-auto max-w-3xl px-4 py-10">
            <a href="/dashboard" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            <h1 class="mb-6 mt-2 text-2xl font-bold text-gray-900">{{ thread.subject }}</h1>
            <MessageFeed :messages="messages" />
        </main>
    </div>
</template>

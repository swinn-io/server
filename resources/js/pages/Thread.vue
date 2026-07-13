<script setup>
import { ref, computed } from 'vue'
import { useEchoNotification } from '@laravel/echo-vue'
import AppHeader from '../components/organisms/AppHeader.vue'
import AppAvatar from '../components/atoms/AppAvatar.vue'
import MessageFeed from '../components/organisms/MessageFeed.vue'
import { useOnlinePresence } from '../composables/useOnlinePresence'

const props = defineProps({
    auth: { type: Object, default: null },
    thread: { type: Object, required: true },
})

const messages = ref(props.thread.messages)
const { onlineUserIds } = useOnlinePresence()

const otherParticipants = computed(() =>
    (props.thread.participants ?? []).filter((p) => p.user?.id !== props.auth?.id)
)

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

if (props.auth) {
    useEchoNotification(`App.Models.User.${props.auth.id}`, (notification) => {
        const payload = notification.payload

        if (notification.type === 'App\\Notifications\\MessageCreated' && payload?.attributes?.thread_id === props.thread.id) {
            if (!messages.value.some((m) => m.id === payload.id)) {
                messages.value.push(transform(payload))
            }
        }
    })
}
</script>

<template>
    <div class="min-h-screen">
        <AppHeader :auth="auth" />

        <main class="mx-auto max-w-3xl px-4 py-10">
            <a href="/dashboard" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</a>
            <h1 class="mb-2 mt-2 text-2xl font-bold text-gray-900">{{ thread.subject }}</h1>
            <div class="mb-6 flex items-center gap-3">
                <div class="flex -space-x-2">
                    <AppAvatar
                        v-for="p in otherParticipants"
                        :key="p.user?.id"
                        :name="p.user?.name || '?'"
                        size="sm"
                        :online="onlineUserIds.has(p.user?.id)"
                    />
                </div>
                <span class="text-sm text-gray-500">{{ thread.messages_count ?? messages.length }} messages</span>
            </div>
            <MessageFeed :messages="messages" :online-user-ids="onlineUserIds" />
        </main>
    </div>
</template>

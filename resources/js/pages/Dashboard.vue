<script setup>
import { ref, onMounted } from 'vue'
import { useEchoNotification } from '@laravel/echo-vue'
import AppHeader from '../components/organisms/AppHeader.vue'
import ThreadList from '../components/organisms/ThreadList.vue'
import { useOnlinePresence } from '../composables/useOnlinePresence'

const props = defineProps({
    auth: { type: Object, default: null },
    threads: { type: Array, default: () => [] },
})

const threads = ref(props.threads)
const { onlineUserIds } = useOnlinePresence()

function transformThread(payload) {
    return {
        id: payload.id,
        subject: payload.attributes.subject,
        updated_at: 'just now',
        unread_count: 0,
        messages_count: payload.attributes.messages?.length ?? 0,
        last_message: payload.attributes.messages?.[0]?.attributes?.body ?? null,
        participants: (payload.attributes.participants ?? []).map((p) => ({
            user: { id: p.attributes?.user?.attributes?.id, name: p.attributes?.user?.attributes?.name },
        })),
    }
}

onMounted(() => {
    if (!props.auth) {
        return
    }

    useEchoNotification(`App.Models.User.${props.auth.id}`, (notification) => {
        const payload = notification.payload
        const type = notification.type

        if (type === 'App\\Notifications\\ThreadCreated') {
            if (!threads.value.some((t) => t.id === payload.id)) {
                threads.value.unshift(transformThread(payload))
            }
            return
        }

        if (type === 'App\\Notifications\\MessageCreated') {
            const threadId = payload.attributes?.thread_id
            const index = threads.value.findIndex((t) => t.id === threadId)

            if (index === -1) {
                return
            }

            const updated = {
                ...threads.value[index],
                updated_at: 'just now',
                unread_count: (threads.value[index].unread_count ?? 0) + 1,
                messages_count: (threads.value[index].messages_count ?? 0) + 1,
                last_message: payload.attributes?.body ?? threads.value[index].last_message,
            }

            const next = threads.value.filter((_, i) => i !== index)
            next.unshift(updated)
            threads.value = next
        }
    })
})
</script>

<template>
    <div class="min-h-screen">
        <AppHeader :auth="auth" />

        <main class="mx-auto max-w-3xl px-4 py-10">
            <h1 class="mb-6 text-2xl font-bold text-gray-900">Conversations</h1>
            <ThreadList :threads="threads" :online-user-ids="onlineUserIds" />
        </main>
    </div>
</template>

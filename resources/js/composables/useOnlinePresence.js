import { ref } from 'vue'
import { useEchoPresence } from '@laravel/echo-vue'

export function useOnlinePresence() {
    const onlineUserIds = ref(new Set())

    const { channel } = useEchoPresence('online')

    channel()
        .here((users) => {
            onlineUserIds.value = new Set(users.map((u) => u.id))
        })
        .joining((user) => {
            onlineUserIds.value = new Set(onlineUserIds.value).add(user.id)
        })
        .leaving((user) => {
            const next = new Set(onlineUserIds.value)
            next.delete(user.id)
            onlineUserIds.value = next
        })

    return { onlineUserIds }
}

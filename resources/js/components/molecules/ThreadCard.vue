<script setup>
import { computed } from 'vue'
import AppAvatar from '../atoms/AppAvatar.vue'
import AppBadge from '../atoms/AppBadge.vue'

const props = defineProps({
    thread: { type: Object, required: true },
    onlineUserIds: { type: Set, default: () => new Set() },
})

const names = computed(() =>
    (props.thread.participants ?? [])
        .map(p => p.user?.name)
        .filter(Boolean)
        .join(', ')
)
</script>

<template>
    <a
        :href="`/thread/${thread.id}`"
        class="flex items-center gap-4 rounded-lg border border-gray-200 p-4 transition-colors hover:bg-gray-50"
    >
        <div class="flex -space-x-2">
            <AppAvatar
                v-for="(p, index) in (thread.participants ?? [])"
                :key="p.user?.id ?? index"
                :name="p.user?.name || '?'"
                :online="onlineUserIds.has(p.user?.id)"
            />
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-baseline justify-between gap-2">
                <p class="truncate font-medium text-gray-900">{{ thread.subject }}</p>
                <span class="shrink-0 text-xs text-gray-400">{{ thread.updated_at }}</span>
            </div>
            <p class="truncate text-sm text-gray-500">{{ names }}</p>
            <p v-if="thread.last_message" class="truncate text-sm text-gray-400">
                {{ typeof thread.last_message === 'object' ? JSON.stringify(thread.last_message) : thread.last_message }}
            </p>
            <p class="text-xs text-gray-400">{{ thread.messages_count ?? 0 }} messages</p>
        </div>
        <AppBadge :count="thread.unread_count ?? 0" />
    </a>
</template>

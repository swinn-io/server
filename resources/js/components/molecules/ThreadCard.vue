<script setup>
import { computed } from 'vue'
import AppAvatar from '../atoms/AppAvatar.vue'
import AppBadge from '../atoms/AppBadge.vue'

const props = defineProps({
    thread: { type: Object, required: true },
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
        <AppAvatar :name="names || thread.subject" />
        <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-gray-900">{{ thread.subject }}</p>
            <p class="truncate text-sm text-gray-500">{{ names }}</p>
        </div>
        <AppBadge :count="thread.unread_count ?? 0" />
    </a>
</template>
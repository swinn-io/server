<script setup>
import AppAvatar from '../atoms/AppAvatar.vue'

defineProps({
    message: { type: Object, required: true },
})
</script>

<template>
    <div class="flex gap-3">
        <AppAvatar :name="message.user?.name ?? '?'" size="sm" />
        <div class="min-w-0 flex-1">
            <div class="flex items-baseline gap-2">
                <span class="font-medium text-gray-900">{{ message.user?.name }}</span>
                <span class="text-xs text-gray-400">{{ message.created_at }}</span>
            </div>
            <p class="mt-1 whitespace-pre-wrap break-words text-gray-700">{{ message.body }}</p>
        </div>
    </div>
</template>
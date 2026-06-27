<script setup>
import { computed } from 'vue'
import AppAvatar from '../atoms/AppAvatar.vue'

const props = defineProps({
    message: { type: Object, required: true },
})

const isStructured = computed(
    () => props.message.body !== null && typeof props.message.body === 'object'
)

const text = computed(() =>
    isStructured.value
        ? JSON.stringify(props.message.body, null, 2)
        : props.message.body
)
</script>

<template>
    <div class="flex gap-3">
        <AppAvatar :name="message.user?.name ?? '?'" size="sm" />
        <div class="min-w-0 flex-1">
            <div class="flex items-baseline gap-2">
                <span class="font-medium text-gray-900">{{ message.user?.name }}</span>
                <span class="text-xs text-gray-400">{{ message.created_at }}</span>
            </div>
            <pre
                v-if="isStructured"
                class="mt-1 overflow-x-auto rounded-md bg-gray-50 p-3 text-sm text-gray-700"
            >{{ text }}</pre>
            <p v-else class="mt-1 whitespace-pre-wrap break-words text-gray-700">{{ text }}</p>
        </div>
    </div>
</template>
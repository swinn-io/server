<script setup>
import MessageBubble from '../molecules/MessageBubble.vue'

defineProps({
    messages: { type: Array, default: () => [] },
})
</script>

<template>
    <div v-if="messages.length" class="space-y-6">
        <MessageBubble v-for="message in messages" :key="message.id" :message="message" />
    </div>
    <p v-else class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-500">
        No messages yet.
    </p>
</template>
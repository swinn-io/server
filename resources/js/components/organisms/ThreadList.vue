<script setup>
import ThreadCard from '../molecules/ThreadCard.vue'

defineProps({
    threads: { type: Array, default: () => [] },
})
</script>

<template>
    <div v-if="threads.length" class="space-y-3">
        <ThreadCard v-for="thread in threads" :key="thread.id" :thread="thread" />
    </div>
    <p v-else class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-500">
        No conversations yet.
    </p>
</template>
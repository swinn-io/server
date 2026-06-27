<script setup>
import { computed } from 'vue'

const props = defineProps({
    count: { type: Number, default: 0 },
})

const label = computed(() => (props.count > 99 ? '99+' : String(props.count)))
</script>

<template>
    <span
        v-if="count > 0"
        class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand px-1.5 text-xs font-semibold text-white"
    >
        {{ label }}
    </span>
</template>
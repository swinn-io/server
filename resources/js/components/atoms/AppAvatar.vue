<script setup>
import { computed } from 'vue'

const props = defineProps({
    name: { type: String, required: true },
    size: {
        type: String,
        default: 'md',
        validator: v => ['sm', 'md', 'lg'].includes(v),
    },
})

const initials = computed(() =>
    props.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(part => part[0].toUpperCase())
        .join('')
)
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full bg-brand font-medium text-white"
        :class="{
            'h-8 w-8 text-xs': size === 'sm',
            'h-10 w-10 text-sm': size === 'md',
            'h-12 w-12 text-base': size === 'lg',
        }"
    >
        {{ initials }}
    </span>
</template>
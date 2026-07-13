<script setup>
import { computed } from 'vue'

const props = defineProps({
    name: { type: String, required: true },
    size: {
        type: String,
        default: 'md',
        validator: v => ['sm', 'md', 'lg'].includes(v),
    },
    online: { type: Boolean, default: false },
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
    <span class="relative inline-flex shrink-0">
        <span
            class="inline-flex items-center justify-center rounded-full bg-brand font-medium text-white"
            :class="{
                'h-8 w-8 text-xs': size === 'sm',
                'h-10 w-10 text-sm': size === 'md',
                'h-12 w-12 text-base': size === 'lg',
            }"
        >
            {{ initials }}
        </span>
        <span
            v-if="online"
            class="absolute bottom-0 right-0 rounded-full bg-green-500 ring-2 ring-white"
            :class="{
                'h-2 w-2': size === 'sm',
                'h-2.5 w-2.5': size === 'md',
                'h-3 w-3': size === 'lg',
            }"
        />
    </span>
</template>
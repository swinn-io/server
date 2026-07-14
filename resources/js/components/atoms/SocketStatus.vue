<script setup>
defineProps({
    label: { type: String, required: true },
    tone: {
        type: String,
        default: 'red',
        validator: v => ['green', 'amber', 'red'].includes(v),
    },
})
</script>

<template>
    <span class="inline-flex items-center gap-1.5 text-xs text-gray-500" :title="`Live updates: ${label}`">
        <span
            class="h-2 w-2 shrink-0 rounded-full"
            :class="{
                'bg-green-500': tone === 'green',
                'bg-amber-500': tone === 'amber',
                'bg-red-500': tone === 'red',
            }"
        />
        <span>{{ label }}</span>
    </span>
</template>

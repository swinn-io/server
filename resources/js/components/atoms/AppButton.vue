<script setup>
defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: v => ['primary', 'ghost', 'danger'].includes(v),
    },
    as: { type: String, default: 'button' },
    href: { type: String, default: null },
})
</script>

<template>
    <component
        :is="href ? 'a' : as"
        :href="href"
        class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
        :class="{
            'bg-brand text-white hover:bg-brand-hover focus-visible:ring-brand': variant === 'primary',
            'bg-transparent text-gray-700 hover:bg-gray-100': variant === 'ghost',
            'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500': variant === 'danger',
        }"
    >
        <slot />
    </component>
</template>
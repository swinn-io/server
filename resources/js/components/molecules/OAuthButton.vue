<script setup>
import AppButton from '../atoms/AppButton.vue'

defineProps({
    href: { type: String, required: true },
    provider: { type: String, required: true },
})
</script>

<template>
    <AppButton :href="href" variant="primary" class="w-full">
        <slot name="icon" />
        Continue with {{ provider }}
    </AppButton>
</template>
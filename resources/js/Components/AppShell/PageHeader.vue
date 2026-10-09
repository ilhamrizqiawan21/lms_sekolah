<script setup lang="ts">
defineSlots<{ actions?: () => unknown }>();
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    icon: { type: String, default: '' },
});
</script>

<template>
    <div class="page-header app-page-header">
        <div>
            <h1 class="app-page-title">
                <i v-if="icon" class="bi" :class="icon" aria-hidden="true"></i>
                {{ title }}
            </h1>
            <p v-if="subtitle" class="app-page-subtitle">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="app-page-actions">
            <slot name="actions" />
        </div>
    </div>
</template>

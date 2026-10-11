<script setup lang="ts">
defineSlots<{ actions?: () => unknown }>();
defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    /** Kept for API compatibility; the compact hero no longer renders a decorative icon. */
    icon: { type: String, default: '' },
    /** Kept for API compatibility; the hero follows the school theme instead of per-role colors. */
    tone: { type: String, default: 'primary' },
});
</script>

<template>
    <section class="dashboard-hero">
        <div class="dashboard-hero-copy">
            <span v-if="eyebrow" class="dashboard-eyebrow">{{ eyebrow }}</span>
            <h1>{{ title }}</h1>
            <p v-if="subtitle">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="dashboard-hero-actions">
            <slot name="actions" />
        </div>
    </section>
</template>

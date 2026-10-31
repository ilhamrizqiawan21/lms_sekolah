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
    <section class="dashboard-hero" :class="[`dashboard-hero-${tone}`, { 'dashboard-hero-student-aligned': tone === 'student' }]">
        <div class="dashboard-hero-mark" aria-hidden="true">
            <i class="bi" :class="icon || 'bi-grid-1x2-fill'"></i>
        </div>
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

<style scoped>
.dashboard-hero-student-aligned {
    justify-content: flex-start !important;
    column-gap: 1.1rem;
}

.dashboard-hero-student-aligned .dashboard-hero-mark {
    flex: 0 0 48px;
}
</style>

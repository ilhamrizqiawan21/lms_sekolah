<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { AppPageProps } from '../../types';

defineSlots<{ actions?: () => unknown }>();
const page = usePage<AppPageProps>();
const fotoUrl = computed(() => page.props.auth?.user?.foto_url ?? null);
defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    icon: { type: String, default: 'bi-stars' },
    tone: { type: String, default: 'primary' },
});
</script>

<template>
    <section class="dashboard-hero" :class="`dashboard-hero-${tone}`">
        <div class="dashboard-hero-copy">
            <span v-if="eyebrow" class="dashboard-eyebrow">{{ eyebrow }}</span>
            <h1>{{ title }}</h1>
            <p v-if="subtitle">{{ subtitle }}</p>
            <div v-if="$slots.actions" class="dashboard-hero-actions">
                <slot name="actions" />
            </div>
        </div>
        <div class="dashboard-hero-orbit" :class="{ 'has-photo': fotoUrl }" :aria-hidden="fotoUrl ? undefined : 'true'">
            <img v-if="fotoUrl" :src="fotoUrl" :alt="`Foto ${page.props.auth?.user?.nama_lengkap ?? 'pengguna'}`" class="dashboard-hero-photo" decoding="async">
            <i v-else class="bi" :class="icon"></i>
        </div>
    </section>
</template>

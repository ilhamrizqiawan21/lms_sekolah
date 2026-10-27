<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import type { AppPageProps } from '../../types';

defineSlots<{ actions?: () => unknown }>();
interface BreadcrumbItem { label: string; href?: string; }
const props = withDefaults(defineProps<{
    eyebrow?: string;
    title: string;
    subtitle?: string;
    icon?: string;
    breadcrumbs?: BreadcrumbItem[];
}>(), { eyebrow: '', subtitle: '', icon: '', breadcrumbs: () => [] });

const page = usePage<AppPageProps>();
const homeHref = computed(() => {
    const role = page.props.auth?.user?.role;
    return role === 'kepala_sekolah' ? '/kepsek/dashboard' : role ? `/${role}/dashboard` : '/';
});
const trail = computed(() => props.breadcrumbs.length
    ? props.breadcrumbs
    : props.eyebrow && props.eyebrow !== props.title ? [{ label: props.eyebrow }] : []);
</script>

<template>
    <div class="page-heading app-page-header">
        <div class="app-page-heading">
            <nav v-if="breadcrumbs.length" class="app-breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li><Link :href="homeHref">Beranda</Link></li>
                    <li v-for="(crumb, index) in trail" :key="`${crumb.label}-${index}`">
                        <Link v-if="crumb.href" :href="crumb.href">{{ crumb.label }}</Link>
                        <span v-else>{{ crumb.label }}</span>
                    </li>
                    <li aria-current="page">{{ title }}</li>
                </ol>
            </nav>
            <div v-if="eyebrow" class="eyebrow app-page-eyebrow">{{ eyebrow }}</div>
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

<style scoped>
.page-heading { margin-bottom: var(--space-5); }
</style>

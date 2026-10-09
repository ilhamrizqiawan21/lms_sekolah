<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SidebarLink from './SidebarLink.vue';
import { mobileNav, sidebarMenu, type MobileNavItem } from './sidebarMenu';
import type { AppPageProps, AuthUser, Capabilities, SchoolBranding, SidebarItem } from '../../types';

interface Props { open?: boolean; school: SchoolBranding; user?: AuthUser | null; capabilities?: Capabilities; }
const props = withDefaults(defineProps<Props>(), { open: false, user: null, capabilities: () => ({ has_wali_kelas: false }) });
const emit = defineEmits<{ 'open-menu': [] }>();

const page = usePage<AppPageProps>();
const menu = computed(() => sidebarMenu(props.user?.role, props.capabilities));
const mobileMenu = computed(() => mobileNav(props.user?.role));

const currentPath = computed(() => {
    const url = page.url || '/';
    return url.split('?')[0].split('#')[0] || '/';
});

function isActive(entry: SidebarItem | MobileNavItem) {
    return entry.activePrefixes?.some((prefix) => currentPath.value === prefix || currentPath.value.startsWith(`${prefix}/`));
}
</script>

<template>
    <aside id="sidebar" class="sidebar modern-sidebar" :class="{ 'sidebar-open': open }">
        <div class="sidebar-header">
            <div class="sidebar-logo-icon">
                <img :src="school.logo_url" :alt="`Logo ${school.name}`" class="app-logo-md" width="36" height="36" decoding="async">
            </div>
            <div class="sidebar-logo-text">
                <span class="sidebar-logo-title">{{ school.name }}</span>
                <span class="sidebar-logo-sub">{{ school.app_name }}</span>
            </div>
        </div>

        <nav class="sidebar-nav" aria-label="Navigasi utama">
            <ul class="sidebar-menu">
                <li v-for="(entry, index) in menu" :key="`${entry.type}-${entry.label}-${index}`">
                    <div v-if="entry.type === 'section'" class="nav-section">{{ entry.label }}</div>
                    <SidebarLink
                        v-else
                        :entry="entry"
                        :active="isActive(entry)"
                    />
                </li>
            </ul>
        </nav>
    </aside>

    <nav v-if="mobileMenu.length" class="mobile-bottom-nav" aria-label="Navigasi cepat">
        <Link
            v-for="entry in mobileMenu"
            :key="entry.href"
            :href="entry.href"
            prefetch="hover"
            class="mobile-bottom-link"
            :class="{ active: isActive(entry) }"
            :aria-current="isActive(entry) ? 'page' : undefined"
        >
            <i class="bi" :class="entry.icon" aria-hidden="true"></i>
            <span>{{ entry.label }}</span>
        </Link>
        <button
            type="button"
            class="mobile-bottom-link"
            :class="{ active: open }"
            aria-controls="sidebar"
            :aria-expanded="open"
            @click="emit('open-menu')"
        >
            <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
            <span>Menu</span>
        </button>
    </nav>
</template>

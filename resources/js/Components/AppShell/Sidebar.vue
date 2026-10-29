<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SidebarLink from './SidebarLink.vue';
import { mobileNav, sidebarMenu, type MobileNavItem } from './sidebarMenu';
import type { AppPageProps, AuthUser, Capabilities, SchoolBranding, SidebarItem, SidebarSection } from '../../types';

interface Props { open?: boolean; mobile?: boolean; school: SchoolBranding; user?: AuthUser | null; capabilities?: Capabilities; }
const props = withDefaults(defineProps<Props>(), { open: false, mobile: false, user: null, capabilities: () => ({ has_wali_kelas: false }) });
const emit = defineEmits<{ close: [] }>();
const sidebarNav = ref<HTMLElement | null>(null);
const sidebarScrollStorageKey = 'lms.sidebar.scrollTop';

const page = usePage<AppPageProps>();
const menu = computed(() => sidebarMenu(props.user?.role, props.capabilities));
const mobileMenu = computed(() => mobileNav(props.user?.role));
const menuGroups = computed(() => {
    const groups: Array<{ section: SidebarSection; items: SidebarItem[] }> = [];
    let current: { section: SidebarSection; items: SidebarItem[] } | null = null;

    for (const entry of menu.value) {
        if (entry.type === 'section') {
            current = { section: entry, items: [] };
            groups.push(current);
        } else if (current) {
            current.items.push(entry);
        }
    }

    return groups.filter((group) => group.items.length > 0);
});
const accountHref = computed(() => {
    const paths = { admin: '/admin/pengaturan-akun', guru: '/guru/pengaturan', siswa: '/siswa/pengaturan', kepala_sekolah: '/kepsek/pengaturan' };
    return props.user?.role ? paths[props.user.role] : null;
});
const userInitials = computed(() => (props.user?.nama_lengkap || 'Akun').split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase());

onMounted(() => {
    const savedPosition = sessionStorage.getItem(sidebarScrollStorageKey);
    if (savedPosition === null) return;

    sessionStorage.removeItem(sidebarScrollStorageKey);
    requestAnimationFrame(() => {
        if (sidebarNav.value) sidebarNav.value.scrollTop = Number(savedPosition) || 0;
    });
});

onBeforeUnmount(() => {
    if (sidebarNav.value) {
        sessionStorage.setItem(sidebarScrollStorageKey, String(sidebarNav.value.scrollTop));
    }
});

const currentPath = computed(() => {
    const url = page.url || '/';
    return url.split('?')[0].split('#')[0] || '/';
});

function isActive(entry: SidebarItem | MobileNavItem) {
    return entry.activePrefixes?.some((prefix) => currentPath.value === prefix || currentPath.value.startsWith(`${prefix}/`));
}

function groupIsActive(items: SidebarItem[]) {
    return items.some((entry) => isActive(entry));
}

function handleNavigation(event: MouseEvent) {
    if (props.mobile && (event.target as HTMLElement | null)?.closest('.nav-link')) {
        emit('close');
    }
}
</script>

<template>
    <aside id="sidebar" class="sidebar modern-sidebar" :class="{ 'sidebar-open': props.open, 'sidebar-closed': !props.open }">
        <div class="sidebar-header">
            <div class="sidebar-logo-icon">
                <img v-if="school.logo_url && !school.logo_url.startsWith('data:')" :src="school.logo_url" :alt="`Logo ${school.name}`" class="sidebar-logo-image" width="36" height="36" decoding="async">
                <i v-else class="bi bi-layers-fill" aria-hidden="true"></i>
            </div>
            <div class="sidebar-logo-text">
                <span class="sidebar-logo-title">{{ school.name }}</span>
                <span class="sidebar-logo-sub">{{ school.app_name }}</span>
            </div>
        </div>

        <nav ref="sidebarNav" class="sidebar-nav" aria-label="Navigasi utama" @click="handleNavigation">
            <ul class="sidebar-menu">
                <li v-for="group in menuGroups" :key="group.section.label" class="sidebar-group">
                    <details v-if="group.section.collapsible" class="sidebar-collapsible" :open="groupIsActive(group.items)">
                        <summary class="nav-section nav-section-toggle">
                            <span>{{ group.section.label }}</span>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </summary>
                        <ul class="sidebar-submenu">
                            <li v-for="entry in group.items" :key="entry.href">
                                <SidebarLink :entry="entry" :active="isActive(entry)" />
                            </li>
                        </ul>
                    </details>
                    <template v-else>
                        <div class="nav-section">{{ group.section.label }}</div>
                        <ul class="sidebar-submenu">
                            <li v-for="entry in group.items" :key="entry.href">
                                <SidebarLink :entry="entry" :active="isActive(entry)" />
                            </li>
                        </ul>
                    </template>
                </li>
            </ul>
        </nav>
        <div v-if="user" class="sidebar-profile">
            <img v-if="user.foto_url" :src="user.foto_url" :alt="`Foto ${user.nama_lengkap}`" class="sidebar-profile-avatar" width="34" height="34" decoding="async">
            <span v-else class="sidebar-profile-avatar" aria-hidden="true">{{ userInitials }}</span>
            <div class="sidebar-profile-copy"><strong>{{ user.nama_lengkap }}</strong><small>{{ user.role_label }}</small></div>
            <details class="sidebar-profile-menu">
                <summary aria-label="Buka menu profil" title="Menu profil"><i class="bi bi-three-dots" aria-hidden="true"></i></summary>
                <div class="sidebar-profile-popover">
                    <Link v-if="accountHref" :href="accountHref"><i class="bi bi-person-gear" aria-hidden="true"></i>Pengaturan akun</Link>
                    <Link href="/logout" method="post" as="button" type="button" class="sidebar-profile-logout"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Keluar</Link>
                </div>
            </details>
        </div>
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
    </nav>
</template>

<style scoped>
/* Nilai disalin dari docs/mockups/2026-ui (.sidebar, .brand, .nav-*, .profile). Prefix .app-shell dipakai agar menang atas CSS lama tanpa !important. */
.app-shell .sidebar.modern-sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    /* Keep the drawer above the scrim. On mobile it starts below the topbar so
     * the school logo is not hidden behind the fixed header. */
    z-index: 300;
    display: flex;
    width: var(--sidebar-width, 252px);
    flex-direction: column;
    padding: 22px 14px 16px;
    overflow: hidden;
    border-right: 1px solid var(--sidebar-border);
    background: var(--sidebar-navy);
    box-shadow: none;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
@media (max-width: 991.98px) {
    .app-shell .sidebar.modern-sidebar {
        top: var(--topbar-height, 60px);
        transform: translateX(-102%);
        box-shadow: none;
    }
    .app-shell .sidebar.modern-sidebar.sidebar-open {
        transform: translateX(0);
    }
}
.app-shell .sidebar.modern-sidebar {
    --sidebar-border: var(--sidebar-border-color);
    --sidebar-strong: var(--sidebar-foreground-strong);
    --sidebar-text: var(--sidebar-foreground);
    --sidebar-muted: var(--sidebar-foreground-muted);
    --sidebar-active-text: var(--sidebar-foreground-strong);
    --sidebar-active-background: var(--sidebar-active-bg);
    --sidebar-hover-background: var(--sidebar-hover);
}
@media (min-width: 992px) {
    .app-shell .sidebar.modern-sidebar.sidebar-closed {
        transform: translateX(-102%);
    }
}
@media (min-width: 992px) {
    .app-shell .sidebar.modern-sidebar {
        transform: translateX(0);
    }
}
.app-shell .sidebar.modern-sidebar .sidebar-header {
    display: flex;
    align-items: center;
    gap: 11px;
    min-height: 0;
    padding: 2px 10px 21px;
    border-bottom: 1px solid var(--sidebar-border);
    background: transparent;
}
.app-shell .sidebar.modern-sidebar .sidebar-logo-icon {
    display: grid;
    width: 37px;
    height: 37px;
    flex: 0 0 37px;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--sidebar-border);
    border-radius: 11px;
    background: var(--sidebar-active-background);
    box-shadow: none;
}
.app-shell .sidebar.modern-sidebar .sidebar-logo-image { width: 100%; height: 100%; border-radius: inherit; object-fit: contain; }
.app-shell .sidebar.modern-sidebar .sidebar-logo-icon > .bi { color: var(--sidebar-foreground-strong); font-size: 20px; line-height: 1; }
.app-shell .sidebar.modern-sidebar .sidebar-logo-text { min-width: 0; display: block; }
.app-shell .sidebar.modern-sidebar .sidebar-logo-title {
    display: block;
    overflow: hidden;
    color: var(--sidebar-strong);
    font-size: 13px;
    font-weight: 750;
    line-height: 1.25;
    letter-spacing: -0.02em;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.app-shell .sidebar.modern-sidebar .sidebar-logo-sub {
    display: block;
    margin-top: 3px;
    overflow: hidden;
    color: var(--sidebar-muted);
    font-size: var(--fs-xs);
    font-weight: 550;
    letter-spacing: 0.01em;
    line-height: 1.5;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.app-shell .sidebar.modern-sidebar .sidebar-nav {
    flex: 1;
    overflow: auto;
    padding: 14px 0 0;
    scrollbar-width: thin;
    scrollbar-color: var(--border-strong) transparent;
    mask-image: none;
    -webkit-mask-image: none;
}
.app-shell .sidebar.modern-sidebar .sidebar-menu { margin: 0; padding: 0; list-style: none; }
.app-shell .sidebar.modern-sidebar .sidebar-group { margin: 0; padding: 0; list-style: none; }
.app-shell .sidebar.modern-sidebar .sidebar-submenu { margin: 0; padding: 0; list-style: none; }
.app-shell .sidebar.modern-sidebar .nav-section {
    margin: 17px 10px 6px;
    padding: 0;
    color: var(--sidebar-muted);
    font-size: var(--fs-xs);
    font-weight: 700;
    line-height: 1.5;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 1;
}
.app-shell .sidebar.modern-sidebar .nav-section-toggle {
    display: flex;
    min-height: 32px;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
}
.app-shell .sidebar.modern-sidebar .nav-section-toggle::-webkit-details-marker { display: none; }
.app-shell .sidebar.modern-sidebar .nav-section-toggle::marker { content: ''; }
.app-shell .sidebar.modern-sidebar .nav-section-toggle i {
    color: var(--sidebar-muted);
    font-size: var(--fs-xs);
    transition: transform 0.18s ease;
}
.app-shell .sidebar.modern-sidebar .sidebar-collapsible[open] > .nav-section-toggle i { transform: rotate(180deg); }
.app-shell .sidebar.modern-sidebar .sidebar-collapsible[open] > .sidebar-submenu {
    animation: sidebar-menu-reveal 180ms var(--ease-standard) both;
}
.app-shell .sidebar.modern-sidebar .nav-section-toggle:hover { color: var(--sidebar-strong); }
.app-shell .sidebar.modern-sidebar .sidebar-menu li:first-child .nav-section { margin-top: 3px; }
.app-shell .sidebar.modern-sidebar .sidebar-menu :deep(.nav-link) {
    display: flex;
    min-height: 38px;
    align-items: center;
    gap: 11px;
    margin: 2px 0;
    padding: 8px 10px;
    border: 1px solid transparent;
    border-radius: 9px;
    background: transparent;
    color: var(--sidebar-text);
    font-size: 12px;
    font-weight: 580;
    line-height: 1.5;
    text-decoration: none;
    transform: none;
    box-shadow: none;
}
.app-shell .sidebar.modern-sidebar .sidebar-menu :deep(.nav-link i) {
    width: 16px;
    flex: 0 0 16px;
    color: var(--sidebar-muted);
    font-size: 16px;
    line-height: 1;
    text-align: center;
}
.app-shell .sidebar.modern-sidebar .sidebar-menu :deep(.nav-link:hover) { background: var(--sidebar-hover-background); color: var(--sidebar-strong); }
.app-shell .sidebar.modern-sidebar .sidebar-menu :deep(.nav-link.active) {
    border-color: color-mix(in srgb, var(--primary-300) 55%, transparent);
    background: var(--sidebar-active-background);
    color: var(--sidebar-active-text);
    font-weight: 700;
}
.app-shell .sidebar.modern-sidebar .sidebar-menu :deep(.nav-link.active i) { color: var(--sidebar-active-text); }
.app-shell .sidebar.modern-sidebar .sidebar-menu :deep(.nav-link small) {
    min-width: 22px;
    margin-left: auto;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--status-warning-bg);
    color: var(--status-warning-text);
    font-size: var(--fs-xs);
    font-weight: 750;
    text-align: center;
}
.app-shell .sidebar.modern-sidebar .sidebar-profile {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 0;
    padding: 14px 8px 0;
    border-top: 1px solid var(--sidebar-border);
}
.sidebar-profile-avatar { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; border-radius: 50%; background: var(--sidebar-hover-background); color: var(--sidebar-active-text); font-size: 12px; font-weight: 800; object-fit: cover; }
.sidebar-profile-copy { display: block; min-width: 0; font-size: var(--fs-xs); font-weight: 700; line-height: 1.5; }
.sidebar-profile-copy strong { display: block; overflow: hidden; color: var(--sidebar-strong); font-size: var(--fs-xs); font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.sidebar-profile-copy small { display: block; margin-top: 2px; overflow: hidden; color: var(--sidebar-muted); font-size: var(--fs-xs); font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
.sidebar-profile-menu { position: relative; flex: 0 0 auto; margin-left: auto; }
.sidebar-profile-menu > summary { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 8px; color: var(--sidebar-muted); cursor: pointer; list-style: none; }
.sidebar-profile-menu > summary::-webkit-details-marker { display: none; }
.sidebar-profile-menu > summary:hover, .sidebar-profile-menu[open] > summary { background: var(--sidebar-hover-background); color: var(--sidebar-strong); }
.sidebar-profile-popover { position: absolute; right: 0; bottom: calc(100% + .5rem); z-index: 10; display: grid; width: 180px; gap: .2rem; padding: .35rem; border: 1px solid var(--border-soft); border-radius: 10px; background: var(--surface-card); box-shadow: var(--refresh-shadow-float); }
.sidebar-profile-menu[open] > .sidebar-profile-popover { animation: sidebar-menu-reveal 150ms var(--ease-standard) both; }
.sidebar-profile-popover :is(a, button) { display: flex; align-items: center; gap: .55rem; width: 100%; min-height: 36px; padding: .45rem .55rem; border: 0; border-radius: 7px; background: transparent; color: var(--text-body); font: inherit; font-size: var(--fs-xs); text-align: left; text-decoration: none; }
.sidebar-profile-popover :is(a, button):hover { background: var(--surface-hover); color: var(--text-strong); }
.sidebar-profile-popover .sidebar-profile-logout { color: var(--status-danger-text); cursor: pointer; }

@keyframes sidebar-menu-reveal {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
    .sidebar-collapsible[open] > .sidebar-submenu,
    .sidebar-profile-menu[open] > .sidebar-profile-popover { animation: none; }
}

@media (min-width: 992px) {
    .app-shell:not(.sidebar-is-open) .sidebar.modern-sidebar {
        transform: translateX(-102%);
    }
}
</style>

<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { sidebarMenu } from './sidebarMenu';
import type { AcademicPeriod, AppPageProps, AuthUser, NotificationsShare, SchoolBranding, UserRole } from '../../types';

interface Props {
    title?: string;
    school: SchoolBranding;
    user?: AuthUser | null;
    academic?: AcademicPeriod | null;
    notifications?: NotificationsShare;
    sidebarOpen?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    user: null,
    academic: null,
    notifications: () => ({ route: null, mark_all_route: null, unread_count: 0, latest: [] }),
    sidebarOpen: false,
});

const page = usePage<AppPageProps>();
const semesterLabel = computed(() => props.academic?.semester === '2' ? 'Genap' : 'Ganjil');
const currentPath = computed(() => page.url.split('?')[0].split('#')[0]);

const activeNavigation = computed(() => {
    let section = 'Ruang kerja';
    for (const entry of sidebarMenu(props.user?.role, page.props.capabilities)) {
        if (entry.type === 'section') {
            section = entry.label;
        } else if (entry.activePrefixes?.some((prefix) => currentPath.value === prefix || currentPath.value.startsWith(`${prefix}/`))) {
            return { section: entry.label === 'Beranda' ? 'Ruang kerja' : section, label: entry.label };
        }
    }
    return { section, label: '' };
});

const displayTitle = computed(() => activeNavigation.value.label === 'Beranda' ? 'Beranda' : (props.title || activeNavigation.value.label || 'Beranda'));
const accountInitials = computed(() => (props.user?.nama_lengkap || 'Akun').split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase());
const userShortName = computed(() => {
    if (!props.user?.nama_lengkap) return 'Akun';
    return props.user.nama_lengkap.split(/\s+/)[0];
});

const emit = defineEmits(['toggle-sidebar', 'open-command']);

function toggleSidebar() {
    emit('toggle-sidebar');
}

function logout() {
    router.post('/logout');
}

function profileHref(role: UserRole | null | undefined): string | undefined {
    if (role === 'admin') return '/admin/pengaturan-akun';
    if (role === 'guru') return '/guru/pengaturan';
    if (role === 'siswa') return '/siswa/pengaturan';
    if (role === 'kepala_sekolah') return '/kepsek/pengaturan';
    return undefined;
}

function profileIsInertia(role: UserRole | null | undefined): boolean {
    return ['admin', 'guru', 'siswa', 'kepala_sekolah'].includes(role as string);
}
</script>

<template>
    <header
        class="topbar modern-topbar"
        :class="{ 'topbar-sidebar-open': sidebarOpen }"
    >
        <button
            class="mobile-menu topbar-toggle-btn"
            type="button"
            :aria-label="sidebarOpen ? 'Tutup menu' : 'Buka menu'"
            aria-controls="sidebar"
            :aria-expanded="sidebarOpen"
            @click="toggleSidebar"
        >
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <nav class="breadcrumbs topbar-breadcrumb" aria-label="Navigasi konteks">
            <span>{{ activeNavigation.section }}</span>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
            <strong>{{ displayTitle }}</strong>
        </nav>

        <div class="top-spacer"></div>

        <div v-if="academic" class="period-select topbar-period" :title="`Tahun Ajaran ${academic.tahun} · ${semesterLabel}`">
            <i class="bi bi-calendar3 icon" aria-hidden="true"></i>
            <span>Tahun Ajaran {{ academic.tahun }} <span class="period-sep">·</span> {{ semesterLabel }}</span>
            <i class="bi bi-chevron-down icon-chevron" aria-hidden="true"></i>
        </div>

        <button
            class="top-icon topbar-search"
            type="button"
            aria-label="Buka akses cepat"
            title="Akses cepat (/)"
            @click="emit('open-command')"
        >
            <i class="bi bi-search icon" aria-hidden="true"></i>
        </button>

        <div class="topbar-actions">
            <div v-if="notifications.route" class="dropdown">
                <button
                    class="top-icon topbar-icon-btn position-relative"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    title="Notifikasi"
                    aria-label="Notifikasi"
                >
                    <i class="bi bi-bell icon" aria-hidden="true"></i>
                    <span v-if="notifications.unread_count > 0" class="notification-dot" aria-hidden="true"></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end notification-menu">
                    <li class="dropdown-item-text d-flex justify-content-between align-items-center">
                        <strong class="notification-title">Notifikasi</strong>
                        <Link
                            v-if="notifications.unread_count > 0 && notifications.mark_all_route"
                            :href="notifications.mark_all_route"
                            method="post"
                            as="button"
                            type="button"
                            class="btn btn-link btn-sm text-decoration-none notification-mark-all"
                        >
                            Tandai semua dibaca
                        </Link>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li v-for="notification in notifications.latest ?? []" :key="notification.id">
                        <Link
                            v-if="notification.mark_read_route"
                            :href="notification.mark_read_route"
                            method="post"
                            as="button"
                            type="button"
                            class="dropdown-item notification-link"
                            :class="{ unread: !notification.is_read }"
                        >
                            <div class="notification-item-title">{{ notification.judul }}</div>
                            <div class="notification-item-message">{{ notification.pesan }}</div>
                            <small class="notification-item-time">{{ notification.created_at }}</small>
                        </Link>
                    </li>
                    <li v-if="!notifications.latest?.length">
                        <span class="dropdown-item-text text-body-secondary text-center notification-action-link">Belum ada notifikasi</span>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <Link :href="notifications.route" class="dropdown-item text-center notification-action-link">Lihat Semua Notifikasi</Link>
                    </li>
                </ul>
            </div>

            <button
                class="top-icon topbar-icon-btn theme-toggle-btn"
                type="button"
                data-theme-toggle
                aria-label="Aktifkan mode gelap"
                title="Aktifkan mode gelap"
                aria-pressed="false"
            >
                <i class="bi bi-moon-stars icon" data-theme-toggle-icon aria-hidden="true"></i>
            </button>

            <div class="dropdown">
                <button
                    class="top-profile topbar-account-btn"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="Menu akun"
                >
                    <img
                        v-if="user?.foto_url"
                        :src="user.foto_url"
                        :alt="`Foto ${user.nama_lengkap ?? 'pengguna'}`"
                        class="avatar"
                        width="32"
                        height="32"
                        decoding="async"
                    >
                    <span v-else class="avatar" aria-hidden="true">{{ accountInitials }}</span>
                    <span class="top-profile-name">{{ userShortName }}</span>
                    <i class="bi bi-chevron-down profile-chevron" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text fw-bold">{{ user?.nama_lengkap ?? '-' }}</span></li>
                    <li><span class="dropdown-item-text text-body-secondary small">{{ user?.username ?? '-' }} - {{ user?.role_label ?? '-' }}</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li class="d-sm-none">
                        <button type="button" class="dropdown-item" data-theme-toggle>
                            <i class="bi bi-moon-stars me-1" data-theme-toggle-icon aria-hidden="true"></i>
                            <span data-theme-toggle-label>Mode gelap</span>
                        </button>
                    </li>
                    <li v-if="profileHref(user?.role)">
                        <Link v-if="profileIsInertia(user?.role)" :href="profileHref(user?.role)" class="dropdown-item">
                            <i class="bi bi-person-gear me-1" aria-hidden="true"></i> Pengaturan
                        </Link>
                        <a v-else :href="profileHref(user?.role)" class="dropdown-item">
                            <i class="bi bi-person-gear me-1" aria-hidden="true"></i> Pengaturan
                        </a>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item text-danger" @click="logout">
                            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Logout
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </header>
</template>

<style scoped>
.topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    /* Keep the close control reachable while the mobile sidebar is open. */
    z-index: 500;
    display: flex;
    height: var(--topbar-height, 66px);
    align-items: center;
    gap: 14px;
    border-bottom: 1px solid var(--border-soft);
    background: var(--surface-card);
    padding: 0 18px;
    box-shadow: none;
    backdrop-filter: none;
}

/* Saat drawer terbuka, topbar hanya mengisi area di sebelah kanan sidebar.
 * Ini menjaga seluruh tinggi sidebar tetap terlihat, termasuk bagian logo. */
.topbar.topbar-sidebar-open {
    left: var(--sidebar-width, 252px);
    width: calc(100% - var(--sidebar-width, 252px));
}

@media (min-width: 992px) {
    .topbar {
        left: 0;
        width: 100%;
        padding: 0 34px;
    }
}
.mobile-menu {
    display: none;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-soft);
    border-radius: 10px;
    background: var(--surface-card);
    color: var(--text-strong);
    cursor: pointer;
    padding: 0;
}
.breadcrumbs {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    font-size: var(--fs-xs);
    white-space: nowrap;
}
.breadcrumbs strong {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--text-strong);
    font-weight: 700;
}
.breadcrumbs .bi-chevron-right {
    font-size: var(--fs-xs);
    color: var(--text-muted);
}
.top-spacer {
    flex: 1;
}
.period-select {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 36px;
    padding: 0 11px;
    border: 1px solid var(--border-soft);
    border-radius: 8px;
    background: var(--surface-card);
    color: var(--text-body);
    font-size: var(--fs-xs);
    font-weight: 650;
    white-space: nowrap;
}
.period-select .icon {
    font-size: 13px;
    color: var(--text-muted);
}
.period-sep {
    color: var(--text-muted);
    margin: 0 2px;
}
.period-select .icon-chevron {
    font-size: var(--fs-xs);
    color: var(--text-muted);
}
.top-icon {
    position: relative;
    display: grid;
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    place-items: center;
    border: 1px solid transparent;
    border-radius: 9px;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
    padding: 0;
    font-size: 15px;
}
.topbar .topbar-search {
    width: 44px;
    min-width: 44px;
    max-width: 44px;
    flex: 0 0 44px;
    padding: 0;
}
.top-icon:hover {
    background: var(--surface-subtle, #f8fafc);
    border-color: var(--border-soft);
    color: var(--text-strong);
}
.topbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.notification-dot {
    position: absolute;
    top: 6px;
    right: 7px;
    width: 7px;
    height: 7px;
    border: 1.5px solid var(--surface-card);
    border-radius: 50%;
    background: var(--accent-orange);
}
.top-profile {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 2px 6px 2px 2px;
    border: 1px solid transparent;
    border-radius: 8px;
    background: transparent;
    color: var(--text-strong);
    cursor: pointer;
    font-size: var(--fs-xs);
    font-weight: 700;
    line-height: 1;
}
.top-profile:hover {
    background: var(--surface-subtle, #f8fafc);
}
.top-profile .avatar {
    display: grid;
    width: 32px;
    height: 32px;
    flex: 0 0 32px;
    place-items: center;
    border-radius: 50%;
    background: var(--brand-soft-bg);
    color: var(--text-brand);
    font-size: var(--fs-xs);
    font-weight: 800;
    object-fit: cover;
}
.top-profile-name {
    font-size: var(--fs-xs);
    font-weight: 700;
    color: var(--text-strong);
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.profile-chevron {
    font-size: var(--fs-xs);
    color: var(--text-muted);
}
@media (max-width: 1180px) {
    .topbar {
        padding-inline: 24px;
    }
}
/* Keep the drawer trigger available for the full tablet/mobile range used by
 * the sidebar (including split-screen widths between 921px and 991px). */
@media (max-width: 991.98px) {
    .mobile-menu {
        display: inline-flex;
    }
    .topbar {
        padding-inline: 18px;
    }
}
@media (max-width: 680px) {
    .topbar {
        height: 58px;
        gap: 9px;
        padding-inline: 12px;
    }
    .breadcrumbs {
        font-size: var(--fs-xs);
    }
    .breadcrumbs > span,
    .breadcrumbs > .bi-chevron-right {
        display: none;
    }
    .period-select {
        display: none;
    }
    .top-profile-name, .profile-chevron {
        display: none;
    }
}
</style>

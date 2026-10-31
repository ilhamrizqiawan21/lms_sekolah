<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';
import { ActionQueue, Badge, DashboardHero, MetricStrip, QuickActionBar, TableWrapper } from '../../Components/UI';
import type { AppPageProps } from '../../types';
import { roleLabel } from '../../utils/roles';

interface AdminStats { total_siswa?: number; total_guru?: number; total_kelas?: number; total_mapel?: number; }
interface LoginRecord { id: number; nama_lengkap: string; role: string; login_time?: string | null; ip_address?: string | null; }
interface Announcement { id: number; judul: string; created_at?: string | null; creator?: string | null; }
interface QuickAction { label: string; href: string; icon: string; color: string; }

interface Props { statistik?: AdminStats; loginTerbaru?: LoginRecord[]; pengumuman?: Announcement[]; }
const props = withDefaults(defineProps<Props>(), { statistik: () => ({}), loginTerbaru: () => [], pengumuman: () => [] });
const page = usePage<AppPageProps>();
const welcomeTitle = computed(() => `Selamat datang, Admin LMS ${page.props.school?.name ?? 'Nama Sekolah'}`);

const metrics = computed(() => [
    { label: 'Siswa aktif', value: props.statistik.total_siswa ?? 0, icon: 'bi-mortarboard-fill', tone: 'success', href: '/admin/kelas-siswa' },
    { label: 'Guru dan staf', value: props.statistik.total_guru ?? 0, icon: 'bi-person-workspace', tone: 'primary', href: '/admin/users' },
    { label: 'Kelas', value: props.statistik.total_kelas ?? 0, icon: 'bi-building', tone: 'info', href: '/admin/kelas' },
    { label: 'Mapel', value: props.statistik.total_mapel ?? 0, icon: 'bi-book-fill', tone: 'warning', href: '/admin/mata-pelajaran' },
]);

const quickActions: QuickAction[] = [
    { label: 'Tambah User', href: '/admin/users/create', icon: 'bi-person-plus', color: 'primary' },
    { label: 'Import Siswa', href: '/admin/kelas-siswa', icon: 'bi-upload', color: 'light' },
    { label: 'Kalender', href: '/admin/kalender', icon: 'bi-calendar3', color: 'light' },
    { label: 'Log Error', href: '/admin/log-error', icon: 'bi-bug', color: 'light' },
];

const announcementItems = computed(() => props.pengumuman.map((item) => ({
    id: item.id, title: item.judul, meta: item.created_at ?? '',
    detail: item.creator ? `Dibuat oleh ${item.creator}` : '',
    icon: 'bi-megaphone-fill', accent: 'var(--accent-amber)',
})));

const focusItems = computed(() => [
    {
        id: 'users', title: `${props.statistik.total_siswa ?? 0} siswa terdaftar`,
        meta: 'Data peserta didik', detail: 'Kelola kelas dan penempatan siswa',
        href: '/admin/kelas-siswa', icon: 'bi-people-fill', accent: 'var(--accent-green)',
    },
    {
        id: 'teachers', title: `${props.statistik.total_guru ?? 0} guru dan staf`,
        meta: 'Tim pengajar sekolah', detail: 'Perbarui akses dan data pengguna',
        href: '/admin/users', icon: 'bi-person-workspace', accent: 'var(--accent-blue)',
    },
    {
        id: 'classes', title: `${props.statistik.total_kelas ?? 0} kelas aktif`,
        meta: 'Struktur akademik', detail: 'Atur kelas dan mata pelajaran',
        href: '/admin/kelas', icon: 'bi-building', accent: 'var(--accent-violet)',
    },
]);

</script>

<template>
    <Head title="Dashboard Admin" />
    <AppShell title="Dashboard Admin">
        <div class="admin-dashboard-page">
            <DashboardHero eyebrow="Data Sekolah" :title="welcomeTitle" subtitle="Cek data utama, login terbaru, dan pengumuman sekolah di sini." icon="bi-command" tone="admin">
                <template #actions><QuickActionBar :actions="quickActions" /></template>
            </DashboardHero>

            <MetricStrip :items="metrics" />

            <div class="dashboard-grid dashboard-grid-admin admin-focus-grid">
                <ActionQueue title="Fokus operasional" icon="bi-compass" :items="focusItems" empty-title="Belum ada fokus operasional" />
                <ActionQueue title="Pengumuman terbaru" icon="bi-megaphone-fill" :items="announcementItems" empty-title="Belum ada pengumuman" />
            </div>

            <div class="dashboard-grid admin-dashboard-workspace">
                <section class="workspace-panel admin-login-panel">
                    <header class="workspace-panel-header">
                        <span class="workspace-panel-title"><i class="bi bi-clock-history" aria-hidden="true"></i> Login Terbaru</span>
                        <a href="/admin/log-login" class="app-card-action-link">Lihat Semua</a>
                    </header>
                    <div class="workspace-panel-body p-0">
                        <TableWrapper stack v-if="loginTerbaru.length">
                            <table class="table table-hover mb-0 admin-login-table">
                                <thead><tr><th scope="col">Nama</th><th scope="col">Role</th><th scope="col">Waktu</th><th scope="col">IP</th></tr></thead>
                                <tbody>
                                    <tr v-for="log in loginTerbaru" :key="log.id">
                                        <td class="stack-title"><strong>{{ log.nama_lengkap }}</strong></td>
                                        <td data-label="Role"><Badge color="secondary">{{ roleLabel(log.role) }}</Badge></td>
                                        <td data-label="Waktu" class="text-body-secondary small">{{ log.login_time ?? '-' }}</td>
                                        <td data-label="IP" class="text-body-secondary small admin-login-ip">{{ log.ip_address ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </TableWrapper>
                        <ActionQueue v-else :items="[]" empty-title="Belum ada data login" icon="bi-clock-history" />
                    </div>
                </section>
            </div>
        </div>
    </AppShell>
</template>

<style scoped>
.admin-dashboard-page {
    min-width: 0;
}

.admin-dashboard-page :deep(.dashboard-hero),
.admin-dashboard-page :deep(.metric-strip),
.admin-dashboard-page :deep(.dashboard-grid),
.admin-dashboard-page :deep(.workspace-panel) {
    min-width: 0;
    max-width: 100%;
}

.admin-dashboard-workspace { align-items: stretch; }
.admin-focus-grid { align-items: stretch; }
.admin-focus-grid .action-queue { margin-bottom: 0; }
.admin-login-panel { margin-bottom: 0; }
.admin-login-table { min-width: 560px; }

/* Tablet and Windows split view: keep the hero compact and make the four
 * frequent actions a predictable two-column control group. */
@media (max-width: 991.98px) {
    .admin-dashboard-page :deep(.dashboard-hero) {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        align-items: start;
        gap: 0.75rem 1rem;
    }

    .admin-dashboard-page :deep(.dashboard-hero-mark) {
        width: 44px;
        height: 44px;
        border-radius: 0.85rem;
    }

    .admin-dashboard-page :deep(.dashboard-hero-copy) {
        min-width: 0;
    }

    .admin-dashboard-page :deep(.dashboard-hero-actions) {
        grid-column: 1 / -1;
        width: 100%;
        margin-top: 0.25rem;
    }

    .admin-dashboard-page :deep(.quick-action-bar) {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: 100%;
        gap: 0.5rem;
    }

    .admin-dashboard-page :deep(.quick-action) {
        width: 100%;
        min-width: 0;
        min-height: 42px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .admin-dashboard-page :deep(.metric-strip) {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-dashboard-workspace { grid-template-columns: minmax(0, 1fr); }
    .admin-focus-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .admin-focus-grid .action-queue { min-width: 0; }
}

@media (max-width: 767.98px) {
    .admin-login-table { min-width: 0; table-layout: fixed; }
    .admin-login-table th, .admin-login-table td { padding: .65rem .55rem; }
    .admin-login-table th:nth-child(1), .admin-login-table td:nth-child(1) { width: 38%; }
    .admin-login-table th:nth-child(2), .admin-login-table td:nth-child(2) { width: 24%; }
    .admin-login-table th:nth-child(3), .admin-login-table td:nth-child(3) { width: 38%; }
    .admin-login-ip { display: none; }
}

@media (max-width: 680px) {
    .admin-focus-grid { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 575.98px) {
    .admin-dashboard-page :deep(.dashboard-hero) { gap: 0.65rem 0.75rem; }
    .admin-dashboard-page :deep(.dashboard-hero h1) { font-size: clamp(1.45rem, 7vw, 1.8rem); }
    .admin-dashboard-page :deep(.dashboard-hero p) { font-size: 0.78rem; line-height: 1.45; }
    .admin-dashboard-page :deep(.metric-strip) { gap: 0.5rem; }
    .admin-dashboard-page :deep(.metric-tile) { min-height: 72px; padding: 0.7rem; }
    .admin-dashboard-page :deep(.metric-copy small) { line-height: 1.25; white-space: normal; }
    .admin-login-table { font-size: .76rem; }
    .admin-login-table th, .admin-login-table td { padding: .55rem .4rem; }
    .admin-login-table th:nth-child(1), .admin-login-table td:nth-child(1) { width: 43%; }
    .admin-login-table th:nth-child(2), .admin-login-table td:nth-child(2) { width: 23%; }
    .admin-login-table th:nth-child(3), .admin-login-table td:nth-child(3) { width: 34%; }
}
</style>

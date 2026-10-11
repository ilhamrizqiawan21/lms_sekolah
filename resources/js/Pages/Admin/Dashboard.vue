<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';
import { ActionQueue, Badge, DashboardHero, MetricStrip, QuickActionBar, TableWrapper } from '../../Components/UI';
import { roleLabel } from '../../utils/roles';

interface AdminStats { total_siswa?: number; total_guru?: number; total_kelas?: number; total_mapel?: number; }
interface LoginRecord { id: number; nama_lengkap: string; role: string; login_time?: string | null; ip_address?: string | null; }
interface Announcement { id: number; judul: string; created_at?: string | null; creator?: string | null; }
interface QuickAction { label: string; href: string; icon: string; color: string; }

interface Props { statistik?: AdminStats; loginTerbaru?: LoginRecord[]; pengumuman?: Announcement[]; }
const props = withDefaults(defineProps<Props>(), { statistik: () => ({}), loginTerbaru: () => [], pengumuman: () => [] });

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

</script>

<template>
    <Head title="Dashboard Admin" />
    <AppShell title="Dashboard Admin">
        <DashboardHero eyebrow="Operasional Sekolah" title="Pusat Kendali Sekolah" subtitle="Pantau data inti, aktivitas login, dan pengumuman dari satu layar operasional." icon="bi-command" tone="admin">
            <template #actions><QuickActionBar :actions="quickActions" /></template>
        </DashboardHero>

        <MetricStrip :items="metrics" />

        <div class="dashboard-grid dashboard-grid-admin admin-dashboard-workspace">
            <section class="workspace-panel">
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
            <ActionQueue title="Pengumuman Terbaru" icon="bi-megaphone-fill" :items="announcementItems" empty-title="Belum ada pengumuman" />
        </div>
    </AppShell>
</template>

<style scoped>
.admin-dashboard-workspace { align-items: stretch; }
.admin-login-table { min-width: 560px; }
@media (max-width: 767.98px) {
    .admin-dashboard-workspace { grid-template-columns: minmax(0, 1fr); }
    .admin-login-table { min-width: 0; table-layout: fixed; }
    .admin-login-table th, .admin-login-table td { padding: .65rem .55rem; }
    .admin-login-table th:nth-child(1), .admin-login-table td:nth-child(1) { width: 38%; }
    .admin-login-table th:nth-child(2), .admin-login-table td:nth-child(2) { width: 24%; }
    .admin-login-table th:nth-child(3), .admin-login-table td:nth-child(3) { width: 38%; }
    .admin-login-ip { display: none; }
}
@media (max-width: 575.98px) {
    .admin-login-table { font-size: .76rem; }
    .admin-login-table th, .admin-login-table td { padding: .55rem .4rem; }
    .admin-login-table th:nth-child(1), .admin-login-table td:nth-child(1) { width: 43%; }
    .admin-login-table th:nth-child(2), .admin-login-table td:nth-child(2) { width: 23%; }
    .admin-login-table th:nth-child(3), .admin-login-table td:nth-child(3) { width: 34%; }
}
</style>

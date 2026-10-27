<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';
import PageHeader from '../../Components/AppShell/PageHeader.vue';
import { ActionQueue, Badge, EmptyState, MetricStrip, QuickActionBar } from '../../Components/UI';
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
const initials = (name: string) => name.trim().split(/\s+/).slice(0, 2).map((part) => part[0] ?? '').join('').toUpperCase();

</script>

<template>
    <Head title="Dashboard Admin" />
    <AppShell title="Dashboard Admin">
        <PageHeader eyebrow="Operasional Sekolah" title="Pusat Kendali Sekolah" subtitle="Pantau data inti, aktivitas login, dan pengumuman dari satu layar operasional.">
            <template #actions><QuickActionBar :actions="quickActions" /></template>
        </PageHeader>

        <MetricStrip :items="metrics" />

        <div class="dashboard-grid dashboard-grid-admin admin-dashboard-workspace">
            <section class="workspace-panel admin-login-panel">
                <header class="workspace-panel-header">
                    <div><span class="workspace-panel-title">Aktivitas login</span><small class="admin-panel-subtitle">Akses terakhir ke sistem</small></div>
                    <Link href="/admin/log-login" class="app-card-action-link">Lihat semua <i class="bi bi-arrow-right" aria-hidden="true"></i></Link>
                </header>
                <div class="workspace-panel-body admin-login-body">
                    <ol v-if="loginTerbaru.length" class="admin-login-list">
                        <li v-for="log in loginTerbaru" :key="log.id" class="admin-login-item">
                            <span class="admin-login-avatar" aria-hidden="true">{{ initials(log.nama_lengkap) }}</span>
                            <span class="admin-login-person"><strong>{{ log.nama_lengkap }}</strong><small>{{ log.ip_address ?? 'Alamat IP tidak tersedia' }}</small></span>
                            <span class="admin-login-meta"><Badge color="secondary">{{ roleLabel(log.role) }}</Badge><small>{{ log.login_time ?? '-' }}</small></span>
                        </li>
                    </ol>
                    <EmptyState v-else title="Belum ada data login" icon="bi-clock-history" />
                </div>
            </section>
            <ActionQueue title="Pengumuman Terbaru" icon="bi-megaphone-fill" :items="announcementItems" empty-title="Belum ada pengumuman" />
        </div>
    </AppShell>
</template>

<style scoped>
.admin-dashboard-workspace { align-items: start; }
.admin-login-panel { margin-bottom: 0; }
.admin-panel-subtitle { display: block; margin-top: .22rem; color: var(--text-muted); font-size: .72rem; }
.admin-login-body { padding: 0; }
.admin-login-list { margin: 0; padding: 0 1rem; list-style: none; }
.admin-login-item { display: flex; align-items: center; gap: .75rem; min-width: 0; padding: .9rem 0; }
.admin-login-item + .admin-login-item { border-top: 1px solid var(--border-soft); }
.admin-login-avatar { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 38px; width: 38px; height: 38px; border-radius: 10px; background: var(--brand-soft-bg); color: var(--text-brand); font-size: .68rem; font-weight: 800; }
.admin-login-person { display: flex; flex: 1 1 auto; flex-direction: column; min-width: 0; }
.admin-login-person strong, .admin-login-person small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.admin-login-person strong { color: var(--text-strong); font-size: .82rem; }
.admin-login-person small, .admin-login-meta small { color: var(--text-muted); font-size: .7rem; }
.admin-login-meta { display: flex; flex: 0 0 auto; flex-direction: column; align-items: flex-end; gap: .25rem; }
@media (max-width: 680px) { .admin-dashboard-workspace { grid-template-columns: minmax(0, 1fr); } }
</style>

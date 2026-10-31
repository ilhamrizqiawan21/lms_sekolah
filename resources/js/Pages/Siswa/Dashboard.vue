<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import { computed } from 'vue';
import { ActionQueue, CourseCard, DashboardHero, MetricStrip, QuickActionBar } from '../../Components/UI';
import type { AppPageProps } from '../../types';
interface StudentStats { total_tugas?: number; tugas_selesai?: number; tugas_belum?: number; total_materi?: number; }
interface StudentCourse { id: number; title: string; subtitle: string; meta: string; href: string; badges?: Array<{ label: string; color?: string }>; }
interface StudentTask { id: number; judul: string; mata_pelajaran: string; batas_waktu: string; show_url?: string | null; selesai?: boolean; }
interface StudentNotification { id: number; judul: string; created_at: string; pesan: string; is_read: boolean; tipe: string; }
interface StudentAnnouncement { id: number; judul: string; created_at: string; show_url?: string | null; }
interface OnlineClass { id: number; judul: string; mata_pelajaran: string; tanggal: string; pelajaran_ke: number; workspace_url: string; }
interface StudentLinks { tugas?: string; materi?: string; jadwal_pelajaran?: string; kelas_daring?: string; notifikasi?: string; pengumuman?: string; }

interface Props { stats: StudentStats; courses?: StudentCourse[]; tugasTerbaru?: StudentTask[]; notifikasi?: StudentNotification[]; pengumuman?: StudentAnnouncement[]; kelasDaring?: OnlineClass[]; links?: StudentLinks; }
const props = withDefaults(defineProps<Props>(), { courses: () => [], tugasTerbaru: () => [], notifikasi: () => [], pengumuman: () => [], kelasDaring: () => [], links: () => ({}) });
const page = usePage<AppPageProps>();
const studentName = computed(() => page.props.auth?.user?.nama_lengkap?.trim() || 'Siswa');

const learningProgress = computed(() => {
    const total = Number(props.stats.total_tugas ?? 0);
    const completed = Number(props.stats.tugas_selesai ?? 0);

    return total > 0 ? Math.min(100, Math.round((completed / total) * 100)) : 0;
});

const iconMap = {
    tugas_baru: { icon: 'bi-journal-plus', color: 'var(--accent-blue)' }, nilai_baru: { icon: 'bi-bar-chart-fill', color: 'var(--accent-green)' },
    chat_baru: { icon: 'bi-chat-dots-fill', color: 'var(--accent-violet)' }, komentar_tugas: { icon: 'bi-chat-square-text-fill', color: 'var(--accent-amber)' },
    kumpul_tugas: { icon: 'bi-check-circle-fill', color: 'var(--accent-cyan)' }, absensi: { icon: 'bi-clipboard-check-fill', color: 'var(--accent-red)' },
};
function iconFor(type: string) { return iconMap[type as keyof typeof iconMap] ?? { icon: 'bi-bell-fill', color: 'var(--accent-slate)' }; }

const metrics = computed(() => [
    { label: 'Total tugas', value: props.stats.total_tugas ?? 0, icon: 'bi-journal-text', tone: 'primary', href: props.links.tugas },
    { label: 'Selesai', value: props.stats.tugas_selesai ?? 0, icon: 'bi-check-circle-fill', tone: 'success', href: props.links.tugas },
    { label: 'Belum', value: props.stats.tugas_belum ?? 0, icon: 'bi-exclamation-circle-fill', tone: 'warning', href: props.links.tugas },
    { label: 'Materi', value: props.stats.total_materi ?? 0, icon: 'bi-file-earmark-text-fill', tone: 'info', href: props.links.materi },
]);
const quickActions = computed(() => [
    { label: 'Tugas Saya', href: props.links.tugas || '/siswa/tugas', icon: 'bi-journal-check', color: 'primary' },
    { label: 'Jadwal', href: props.links.jadwal_pelajaran || '/siswa/jadwal-pelajaran', icon: 'bi-calendar-week', color: 'outline-secondary' },
    { label: 'Daring', href: props.links.kelas_daring || '/siswa/kelas-daring', icon: 'bi-camera-video', color: 'outline-secondary' },
    { label: 'Materi', href: props.links.materi || '/siswa/materi', icon: 'bi-file-earmark-text', color: 'outline-secondary' },
    { label: 'Nilai', href: '/siswa/nilai', icon: 'bi-bar-chart', color: 'outline-secondary' },
]);
const taskItems = computed(() => props.tugasTerbaru.map((item) => ({ id: item.id, title: item.judul, meta: item.mata_pelajaran, detail: `Deadline ${item.batas_waktu}`, href: item.show_url || props.links.tugas || '/siswa/tugas', badge: item.selesai ? 'Selesai' : 'Belum', badgeColor: item.selesai ? 'success' : 'warning text-dark', icon: item.selesai ? 'bi-check-circle' : 'bi-journal-text', accent: item.selesai ? 'var(--accent-green)' : 'var(--accent-amber)' })));
const onlineClassItems = computed(() => props.kelasDaring.map((item) => ({ id: item.id, title: item.judul, meta: item.mata_pelajaran, detail: `${item.tanggal} · Pelajaran ke-${item.pelajaran_ke}`, href: item.workspace_url, badge: 'Daring', badgeColor: 'primary', icon: 'bi-camera-video', accent: 'var(--accent-blue)' })));
const notificationItems = computed(() => props.notifikasi.map((item) => ({ id: item.id, title: item.judul, meta: item.created_at, detail: item.pesan, href: props.links.notifikasi, badge: item.is_read ? '' : 'Baru', badgeColor: 'danger', icon: iconFor(item.tipe).icon, accent: iconFor(item.tipe).color })));
const announcementItems = computed(() => props.pengumuman.map((item) => ({ id: item.id, title: item.judul, meta: item.created_at, href: item.show_url, icon: 'bi-megaphone-fill', accent: 'var(--accent-blue)' })));
</script>

<template>
    <Head title="Dashboard Siswa" />
    <AppShell title="Dashboard Siswa">
        <DashboardHero class="student-dashboard-hero" eyebrow="Hari Ini" :title="`Selamat datang, ${studentName}`" :subtitle="`${stats.tugas_belum ?? 0} tugas belum dikerjakan dan ${stats.total_materi ?? 0} materi tersedia untuk dipelajari.`" icon="bi-mortarboard" tone="student">
            <template #actions><QuickActionBar :actions="quickActions" /></template>
        </DashboardHero>
        <MetricStrip :items="metrics" />

        <section class="student-learning-summary" aria-labelledby="student-progress-title">
            <div class="student-learning-mark"><i class="bi bi-compass" aria-hidden="true"></i></div>
            <div class="student-learning-copy">
                <span class="student-learning-kicker">Progres belajar</span>
                <h2 id="student-progress-title">Lihat progres tugasmu.</h2>
                <p>{{ learningProgress }}% tugas sudah selesai dari seluruh tugas yang tersedia.</p>
            </div>
            <div class="student-learning-progress" aria-label="Progress tugas">
                <div class="progress" role="progressbar" :aria-valuenow="learningProgress" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" :style="{ width: `${learningProgress}%` }"></div>
                </div>
                <strong>{{ learningProgress }}%</strong>
            </div>
            <Link :href="links.tugas || '/siswa/tugas'" class="student-learning-action">Lihat tugas <i class="bi bi-arrow-up-right" aria-hidden="true"></i></Link>
        </section>

        <div class="dashboard-grid dashboard-grid-student student-priority-grid" aria-label="Prioritas belajar siswa">
            <ActionQueue title="Tugas Terdekat" icon="bi-journal-check" :items="taskItems" empty-title="Belum ada tugas" />
            <ActionQueue title="Kelas Daring" icon="bi-camera-video-fill" :items="onlineClassItems" empty-title="Belum ada kelas daring">
                <template #actions><Link :href="links.kelas_daring || '/siswa/kelas-daring'" class="app-card-action-link">Lihat Semua</Link></template>
            </ActionQueue>
            <ActionQueue title="Notifikasi" icon="bi-bell-fill" :items="notificationItems" empty-title="Belum ada notifikasi">
                <template v-if="notifikasi.length" #actions><Link :href="links.notifikasi" class="app-card-action-link">Lihat Semua</Link></template>
            </ActionQueue>
        </div>

        <section class="workspace-panel student-courses-panel">
            <header class="workspace-panel-header">
                <span class="workspace-panel-title"><i class="bi bi-compass" aria-hidden="true"></i> Lanjutkan Belajar</span>
                <Link :href="links.materi || '/siswa/materi'" class="app-card-action-link">Buka Materi</Link>
            </header>
            <div v-if="courses.length" class="course-card-grid">
                <CourseCard v-for="(course, index) in courses" :key="course.id" :title="course.title" :subtitle="course.subtitle" :meta="course.meta" :href="course.href" icon="bi-book" :badges="course.badges" :accent="['var(--accent-blue)', 'var(--accent-green)', 'var(--accent-amber)', 'var(--accent-red)'][index % 4]" />
            </div>
            <ActionQueue v-else :items="[]" empty-title="Belum ada kelas aktif" icon="bi-book" />
        </section>

        <ActionQueue title="Pengumuman" icon="bi-megaphone-fill" :items="announcementItems" empty-title="Tidak ada pengumuman">
            <template v-if="pengumuman.length" #actions><Link :href="links.pengumuman" class="app-card-action-link">Lihat Semua</Link></template>
        </ActionQueue>
    </AppShell>
</template>

<style scoped>
:deep(.student-dashboard-hero) {
    justify-content: flex-start;
    gap: 1.1rem;
}
:deep(.app-shell .student-dashboard-hero) {
    justify-content: flex-start !important;
    column-gap: 1.1rem;
}
:deep(.app-shell .student-dashboard-hero .dashboard-hero-mark) {
    flex: 0 0 48px;
}
:deep(.student-dashboard-hero .dashboard-hero-mark) {
    width: 48px;
    height: 48px;
}
.student-priority-grid {
    grid-template-columns: minmax(0, 1.25fr) repeat(2, minmax(0, 1fr));
    align-items: stretch;
}
.student-priority-grid .workspace-panel { height: 100%; margin-bottom: 0; }
.student-courses-panel { min-width: 0; }
.student-learning-summary {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) minmax(150px, 0.65fr) auto;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 1.1rem 1.2rem;
    border: 1px solid var(--border-soft);
    border-top: 3px solid var(--primary-500);
    border-radius: var(--panel-radius);
    background: color-mix(in srgb, var(--surface-card) 97%, var(--primary-50));
}
.student-learning-mark {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border: 1px solid color-mix(in srgb, var(--primary-500) 18%, var(--border-soft));
    border-radius: 12px;
    background: var(--brand-soft-bg);
    color: var(--text-brand);
    font-size: 1.15rem;
}
.student-learning-kicker { color: var(--text-brand); font-size: var(--fs-xs); font-weight: 800; letter-spacing: .03em; text-transform: uppercase; }
.student-learning-copy h2 { margin: .2rem 0 0; color: var(--text-strong); font-size: 1rem; font-weight: 750; }
.student-learning-copy p { margin: .25rem 0 0; color: var(--text-muted); font-size: var(--fs-xs); }
.student-learning-progress { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: .65rem; }
.student-learning-progress .progress { height: 8px; background: var(--surface-subtle); }
.student-learning-progress .progress-bar { border-radius: inherit; background: var(--app-primary); }
.student-learning-progress strong { color: var(--text-strong); font-size: var(--fs-xs); }
.student-learning-action { display: inline-flex; align-items: center; gap: .35rem; color: var(--text-brand); font-size: var(--fs-xs); font-weight: 800; text-decoration: none; white-space: nowrap; }
.student-learning-action:hover { text-decoration: underline; }
@media (max-width: 767.98px) {
    :deep(.student-dashboard-hero) { gap: 0.8rem; }
    .student-priority-grid { grid-template-columns: minmax(0, 1fr); }
    .student-courses-panel .course-card-grid { grid-template-columns: minmax(0, 1fr); }
    .student-learning-summary { grid-template-columns: auto minmax(0, 1fr); }
    .student-learning-progress { grid-column: 1 / -1; }
    .student-learning-action { grid-column: 2; justify-self: end; }
}
@media (min-width: 768px) and (max-width: 1100px) {
    .student-priority-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>

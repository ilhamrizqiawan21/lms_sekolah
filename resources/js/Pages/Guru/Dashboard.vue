<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import { ActionQueue, Card, CourseCard, DashboardHero, EmptyState, MetricStrip, QuickActionBar } from '../../Components/UI';
import { cssVar, withAlpha } from '../../utils/cssColor';
import type { AppPageProps } from '../../types';

interface GuruStats { total_kelas_mapel?: number; total_siswa?: number; }
interface Course { id: number; kelas: string; mata_pelajaran: string; semester: string; workspace_url: string; materi_count: number; tugas_count: number; }
interface GradingTask { id: number; judul: string; kelas: string; mata_pelajaran: string; total: number; url: string; }
interface MissingTask { id: number; judul: string; kelas: string; mata_pelajaran: string; belum: number; total_siswa: number; batas_waktu: string; url: string; }
interface AttendanceConcern { id: number; nama: string; kelas: string; nis: string; total_absensi: number; total_alpha: number; persen_hadir: number; url: string; }
interface AttendanceChartPoint { bulan: string; bulan_label?: string; total: number; persen_hadir: number; }
interface SubmissionChartPoint { bulan: string; bulan_label?: string; total: number; collected: number; persen_dikumpulkan: number; }

const page = usePage<AppPageProps>();
const user = page.props.auth?.user;
interface Props { statistik?: GuruStats; kelasMapel?: Course[]; tugasBelumDikumpulkan?: MissingTask[]; siswaJarangMasuk?: AttendanceConcern[]; tugasPerluDinilai?: GradingTask[]; kehadiranChart?: AttendanceChartPoint[]; pengumpulanTugasChart?: SubmissionChartPoint[]; }
const props = withDefaults(defineProps<Props>(), { statistik: () => ({}), kelasMapel: () => [], tugasBelumDikumpulkan: () => [], siswaJarangMasuk: () => [], tugasPerluDinilai: () => [], kehadiranChart: () => [], pengumpulanTugasChart: () => [] });

const kehadiranCanvas = ref<HTMLCanvasElement | null>(null);
const pengumpulanCanvas = ref<HTMLCanvasElement | null>(null);
let kehadiranChartInstance: { destroy: () => void } | null = null;
let pengumpulanChartInstance: { destroy: () => void } | null = null;
let themeObserver: MutationObserver | null = null;

function currentMonthKey() {
    const today = new Date();
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
}

const latestKehadiran = computed(() => props.kehadiranChart.find((item) => item.bulan === currentMonthKey()) || props.kehadiranChart.at(-1));
const latestPengumpulan = computed(() => props.pengumpulanTugasChart.find((item) => item.bulan === currentMonthKey()) || props.pengumpulanTugasChart.at(-1));
const averageKehadiran = computed(() => averagePercentage(props.kehadiranChart, (item) => item.persen_hadir));
const averagePengumpulan = computed(() => averagePercentage(props.pengumpulanTugasChart, (item) => item.persen_dikumpulkan));
const chartPeriodLabel = computed(() => {
    const items = props.kehadiranChart.length ? props.kehadiranChart : props.pengumpulanTugasChart;
    const first = items[0]?.bulan_label || items[0]?.bulan;
    const last = items.at(-1)?.bulan_label || items.at(-1)?.bulan;

    return first && last ? `${first} - ${last}` : 'Tahun Pelajaran';
});

function averagePercentage<T extends { total: number }>(items: T[], percentage: (item: T) => number): number {
    const filledItems = items.filter((item) => Number(item.total) > 0);
    if (!filledItems.length) {
        return 0;
    }

    const total = filledItems.reduce((sum, item) => sum + Number(percentage(item) ?? 0), 0);
    return Math.round(total / filledItems.length);
}

function chartPalette() {
    return {
        primary: cssVar('--app-primary'),
        accent: cssVar('--app-accent'),
        muted: cssVar('--text-muted'),
        border: cssVar('--gray-200'),
    };
}

async function chartJs() {
    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);
    return Chart;
}

function trendChartOptions(title: string, tooltipTitleCallback: ((items: Array<{ dataIndex?: number }>) => string) | null = null) {
    const palette = chartPalette();

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index' as const, intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    ...(tooltipTitleCallback ? { title: tooltipTitleCallback } : {}),
                    label: (context: { parsed: { y: number | null } }) => `${title}: ${context.parsed.y ?? 0}%`,
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    color: palette.muted,
                    maxRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 12,
                },
            },
            y: {
                beginAtZero: true,
                max: 100,
                grid: { color: palette.border },
                ticks: {
                    color: palette.muted,
                    precision: 0,
                    callback: (value: string | number) => `${value}%`,
                },
            },
        },
    };
}

async function renderKehadiranChart() {
    if (!kehadiranCanvas.value || !props.kehadiranChart.length) {
        kehadiranChartInstance?.destroy();
        kehadiranChartInstance = null;
        return;
    }

    const Chart = await chartJs();
    const palette = chartPalette();
    kehadiranChartInstance?.destroy();
    kehadiranChartInstance = new Chart(kehadiranCanvas.value, {
        type: 'bar',
        data: {
            labels: props.kehadiranChart.map((item) => item.bulan_label || item.bulan),
            datasets: [
                {
                    label: 'Tren Kehadiran',
                    data: props.kehadiranChart.map((item) => item.persen_hadir),
                    backgroundColor: withAlpha(palette.primary, 0.82),
                    hoverBackgroundColor: palette.primary,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 34,
                },
            ],
        },
        options: trendChartOptions('Kehadiran'),
    });
}

async function renderPengumpulanChart() {
    if (!pengumpulanCanvas.value || !props.pengumpulanTugasChart.length) {
        pengumpulanChartInstance?.destroy();
        pengumpulanChartInstance = null;
        return;
    }

    const Chart = await chartJs();
    const palette = chartPalette();
    pengumpulanChartInstance?.destroy();
    pengumpulanChartInstance = new Chart(pengumpulanCanvas.value, {
        type: 'bar',
        data: {
            labels: props.pengumpulanTugasChart.map((item) => item.bulan_label || item.bulan),
            datasets: [
                {
                    label: 'Tren Pengumpulan',
                    data: props.pengumpulanTugasChart.map((item) => item.persen_dikumpulkan),
                    backgroundColor: withAlpha(palette.accent, 0.82),
                    hoverBackgroundColor: palette.accent,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 34,
                },
            ],
        },
        options: trendChartOptions('Pengumpulan', (items) => {
            const index = items[0]?.dataIndex ?? 0;
            const month = props.pengumpulanTugasChart[index];

            return month ? `${month.bulan_label || month.bulan} (${month.total} target)` : '';
        }),
    });
}

function renderCharts() {
    return Promise.all([renderKehadiranChart(), renderPengumpulanChart()]);
}

onMounted(() => {
    nextTick(() => renderCharts());

    themeObserver = new MutationObserver(() => {
        nextTick(() => renderCharts());
    });
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme'],
    });
});

watch(() => props.kehadiranChart, () => nextTick(renderKehadiranChart), { deep: true });
watch(() => props.pengumpulanTugasChart, () => nextTick(renderPengumpulanChart), { deep: true });

onBeforeUnmount(() => {
    themeObserver?.disconnect();
    kehadiranChartInstance?.destroy();
    pengumpulanChartInstance?.destroy();
});

const totalPerluDinilai = computed(() => props.tugasPerluDinilai.reduce((total, item) => total + (item.total ?? 0), 0));
const metrics = computed(() => [
    { label: 'Kelas dan mapel', value: props.statistik.total_kelas_mapel ?? 0, icon: 'bi-diagram-3-fill', tone: 'primary', href: '/guru/materi' },
    { label: 'Siswa diajar', value: props.statistik.total_siswa ?? 0, icon: 'bi-people-fill', tone: 'info' },
    { label: 'Perlu dinilai', value: totalPerluDinilai.value, icon: 'bi-pencil-square', tone: 'warning', href: '/guru/tugas' },
    { label: 'Kehadiran rendah', value: props.siswaJarangMasuk.length, icon: 'bi-person-exclamation', tone: 'danger' },
]);
const quickActions = [
    { label: 'Buat Materi', href: '/guru/materi', icon: 'bi-file-earmark-plus', color: 'primary' },
    { label: 'Buat Tugas', href: '/guru/tugas', icon: 'bi-journal-check', color: 'light' },
    { label: 'Absensi', href: '/guru/absensi', icon: 'bi-clipboard-check', color: 'light' },
    { label: 'Chat', href: '/guru/chat', icon: 'bi-chat-dots', color: 'light' },
];
const gradingItems = computed(() => props.tugasPerluDinilai.map((item) => ({
    id: item.id,
    title: item.judul,
    meta: `${item.kelas} - ${item.mata_pelajaran}`,
    detail: 'Pengumpulan sudah masuk dan menunggu penilaian',
    href: item.url,
    badge: item.total,
    badgeColor: 'info',
    icon: 'bi-pencil-square',
    accent: 'var(--app-accent)',
})));
const missingItems = computed(() => props.tugasBelumDikumpulkan.map((item) => ({
    id: item.id,
    title: item.judul,
    meta: `${item.kelas} - ${item.mata_pelajaran}`,
    detail: `${item.belum} siswa belum mengumpulkan · deadline ${item.batas_waktu}`,
    href: item.url,
    badge: `${item.belum}/${item.total_siswa}`,
    badgeColor: 'warning text-dark',
    icon: 'bi-exclamation-circle',
    accent: 'var(--gold-500)',
})));
const attendanceItems = computed(() => props.siswaJarangMasuk.map((item) => ({
    id: item.id,
    title: item.nama,
    meta: `${item.kelas} - NIS ${item.nis}`,
    detail: `${item.total_absensi} catatan absensi · ${item.total_alpha} alpha`,
    href: item.url,
    badge: `${item.persen_hadir}%`,
    badgeColor: item.persen_hadir < 60 ? 'danger' : 'warning text-dark',
    icon: 'bi-person-exclamation',
    accent: 'var(--status-danger-text)',
})));
</script>

<template>
    <Head title="Dashboard Guru" />
    <AppShell title="Dashboard Guru">
        <DashboardHero
            eyebrow="Ruang Guru"
            :title="`Selamat datang, ${user?.nama_lengkap ?? 'Guru'}`"
            subtitle="Kelola pembelajaran, penilaian, dan tindak lanjut kelas hari ini."
            icon="bi-person-workspace"
            tone="teacher"
        >
            <template #actions><QuickActionBar :actions="quickActions" /></template>
        </DashboardHero>

        <MetricStrip :items="metrics" />

        <div class="dashboard-grid dashboard-grid-teacher teacher-dashboard-queues">
            <ActionQueue
                title="Perlu Dinilai"
                icon="bi-pencil-square"
                :items="gradingItems"
                empty-title="Tidak ada antrean nilai"
                empty-message="Semua pengumpulan yang masuk sudah dinilai."
            />
            <ActionQueue
                title="Belum Mengumpulkan"
                icon="bi-exclamation-circle"
                :items="missingItems"
                empty-title="Tidak ada tunggakan tugas"
                empty-message="Semua tugas lewat deadline sudah lengkap dikumpulkan."
            />
            <ActionQueue
                title="Siswa Perlu Perhatian"
                icon="bi-person-exclamation"
                :items="attendanceItems"
                empty-title="Kehadiran aman"
                empty-message="Belum ada siswa dengan kehadiran di bawah 75% dalam 60 hari terakhir."
            />
        </div>

        <section class="workspace-panel teacher-courses-panel">
            <header class="workspace-panel-header">
                <span class="workspace-panel-title"><i class="bi bi-book" aria-hidden="true"></i> Kelas dan Mapel Diampu</span>
                <Link href="/guru/materi" class="app-card-action-link">Kelola Materi</Link>
            </header>
            <div v-if="kelasMapel.length" class="course-card-grid">
                <CourseCard
                    v-for="item in kelasMapel"
                    :key="item.id"
                    :title="item.mata_pelajaran"
                    :subtitle="item.kelas"
                    :meta="`Semester ${item.semester}`"
                    :href="item.workspace_url"
                    icon="bi-book"
                    :badges="[{ label: `${item.materi_count} materi`, color: 'primary' }, { label: `${item.tugas_count} tugas`, color: 'info' }]"
                />
            </div>
            <ActionQueue v-else :items="[]" empty-title="Belum ada penugasan mengajar semester ini" icon="bi-book" />
        </section>

        <section class="teacher-insights" aria-labelledby="teacher-insights-title">
            <div class="teacher-section-heading">
                <div>
                    <span class="teacher-section-eyebrow">Tren</span>
                    <h2 id="teacher-insights-title">Tren kelas</h2>
                </div>
                <p>Data tahun pelajaran sampai bulan berjalan.</p>
            </div>

            <div class="teacher-trend-grid">
                <Card :title="`Tren Kehadiran (${chartPeriodLabel})`" icon="bi-bar-chart-line" body-class="teacher-trend-body">
                    <div v-if="kehadiranChart.length" class="teacher-trend-content">
                        <div class="teacher-trend-summary">
                            <div>
                                <span class="teacher-trend-label">Bulan berjalan</span>
                                <strong>{{ latestKehadiran?.persen_hadir ?? 0 }}%</strong>
                            </div>
                            <div>
                                <span class="teacher-trend-label">Rata-rata</span>
                                <strong>{{ averageKehadiran }}%</strong>
                            </div>
                        </div>
                        <div class="teacher-trend-chart">
                            <canvas ref="kehadiranCanvas" role="img" aria-label="Grafik tren persentase kehadiran siswa"></canvas>
                        </div>
                    </div>
                    <EmptyState v-else title="Belum ada data kehadiran." icon="bi-clipboard-check" />
                </Card>

                <Card :title="`Tren Pengumpulan Tugas (${chartPeriodLabel})`" icon="bi-bar-chart-line" body-class="teacher-trend-body">
                    <div v-if="pengumpulanTugasChart.length" class="teacher-trend-content">
                        <div class="teacher-trend-summary">
                            <div>
                                <span class="teacher-trend-label">Bulan berjalan</span>
                                <strong>{{ latestPengumpulan?.persen_dikumpulkan ?? 0 }}%</strong>
                            </div>
                            <div>
                                <span class="teacher-trend-label">Rata-rata</span>
                                <strong>{{ averagePengumpulan }}%</strong>
                            </div>
                        </div>
                        <div class="teacher-trend-chart">
                            <canvas ref="pengumpulanCanvas" role="img" aria-label="Grafik tren persentase pengumpulan tugas siswa"></canvas>
                        </div>
                    </div>
                    <EmptyState v-else title="Belum ada data pengumpulan tugas." icon="bi-journal-x" />
                </Card>
            </div>
        </section>
    </AppShell>
</template>

<style scoped>
.teacher-dashboard-queues {
    align-items: stretch;
    margin-bottom: 1.5rem;
}

.teacher-courses-panel {
    min-width: 0;
    margin-bottom: 1.5rem;
}

.teacher-insights {
    min-width: 0;
}

.teacher-section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.85rem;
}

.teacher-section-eyebrow {
    display: block;
    margin-bottom: 0.2rem;
    color: var(--text-brand);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.teacher-section-heading h2 {
    margin: 0;
    color: var(--text-strong);
    font-size: 1rem;
    font-weight: 800;
}

.teacher-section-heading p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.78rem;
}

.teacher-trend-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

:deep(.teacher-trend-body) {
    padding: 0.9rem;
}

.teacher-trend-content {
    display: grid;
    gap: 0.85rem;
    min-width: 0;
}

.teacher-trend-summary {
    display: grid;
    gap: 0.65rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.teacher-trend-summary > div {
    min-width: 0;
    padding: 0.65rem 0.7rem;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: var(--surface-muted);
}

.teacher-trend-label {
    display: block;
    margin-bottom: 0.2rem;
    color: var(--text-muted);
    font-size: 0.68rem;
    font-weight: 700;
    line-height: 1.2;
    text-transform: uppercase;
}

.teacher-trend-summary strong {
    display: block;
    color: var(--text-strong);
    font-size: 1.2rem;
    line-height: 1.1;
}

.teacher-trend-chart {
    position: relative;
    min-width: 0;
    height: 185px;
}

@media (max-width: 991.98px) {
    .teacher-trend-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .teacher-dashboard-queues {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .teacher-dashboard-queues > :last-child {
        grid-column: 1 / -1;
    }
}

@media (max-width: 767.98px) {
    .teacher-dashboard-queues {
        grid-template-columns: minmax(0, 1fr);
    }

    .teacher-dashboard-queues > :last-child {
        grid-column: auto;
    }

    .teacher-courses-panel .course-card-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .teacher-section-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 0.25rem;
    }

    .teacher-trend-chart {
        height: 170px;
    }
}

@media (max-width: 420px) {
    .teacher-trend-summary {
        gap: 0.5rem;
    }

    .teacher-trend-summary > div {
        padding: 0.55rem 0.6rem;
    }
}
</style>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import { ActionQueue, Card, CourseCard, DashboardHero, EmptyState, MetricStrip, QuickActionBar } from '../../Components/UI';

const page = usePage();
const user = page.props.auth?.user;
const props = defineProps({
    statistik: { type: Object, default: () => ({}) },
    kelasMapel: { type: Array, default: () => [] },
    tugasBelumDikumpulkan: { type: Array, default: () => [] },
    siswaJarangMasuk: { type: Array, default: () => [] },
    tugasPerluDinilai: { type: Array, default: () => [] },
    pengumuman: { type: Array, default: () => [] },
    notifikasi: { type: Array, default: () => [] },
    unreadNotifCount: { type: Number, default: 0 },
    kehadiranChart: { type: Array, default: () => [] },
    pengumpulanTugasChart: { type: Array, default: () => [] },
});

const kehadiranCanvas = ref(null);
const pengumpulanCanvas = ref(null);
let kehadiranChartInstance = null;
let pengumpulanChartInstance = null;

function currentMonthKey() {
    const today = new Date();
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
}

const latestKehadiran = computed(() => props.kehadiranChart.find((item) => item.bulan === currentMonthKey()) || props.kehadiranChart.at(-1));
const latestPengumpulan = computed(() => props.pengumpulanTugasChart.find((item) => item.bulan === currentMonthKey()) || props.pengumpulanTugasChart.at(-1));
const averageKehadiran = computed(() => averagePercentage(props.kehadiranChart, 'persen_hadir'));
const averagePengumpulan = computed(() => averagePercentage(props.pengumpulanTugasChart, 'persen_dikumpulkan'));
const chartPeriodLabel = computed(() => {
    const items = props.kehadiranChart.length ? props.kehadiranChart : props.pengumpulanTugasChart;
    const first = items[0]?.bulan_label || items[0]?.bulan;
    const last = items.at(-1)?.bulan_label || items.at(-1)?.bulan;

    return first && last ? `${first} - ${last}` : 'Tahun Pelajaran';
});

function averagePercentage(items, key) {
    const filledItems = items.filter((item) => Number(item.total) > 0);
    if (!filledItems.length) {
        return 0;
    }

    const total = filledItems.reduce((sum, item) => sum + Number(item[key] ?? 0), 0);
    return Math.round(total / filledItems.length);
}

async function chartJs() {
    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);
    return Chart;
}

function trendChartOptions(title, tooltipTitleCallback = null) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    ...(tooltipTitleCallback ? { title: tooltipTitleCallback } : {}),
                    label: (context) => `${title}: ${context.parsed.y}%`,
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { maxRotation: 0, autoSkip: false },
            },
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    precision: 0,
                    callback: (value) => `${value}%`,
                },
            },
        },
    };
}

async function renderKehadiranChart() {
    if (!kehadiranCanvas.value || !props.kehadiranChart.length) {
        return;
    }

    const Chart = await chartJs();
    kehadiranChartInstance?.destroy();
    kehadiranChartInstance = new Chart(kehadiranCanvas.value, {
        type: 'line',
        data: {
            labels: props.kehadiranChart.map((item) => item.bulan_label || item.bulan),
            datasets: [
                {
                    label: 'Tren Kehadiran',
                    data: props.kehadiranChart.map((item) => item.persen_hadir),
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22, 163, 74, 0.12)',
                    borderWidth: 3,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#16a34a',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.35,
                },
            ],
        },
        options: trendChartOptions('Kehadiran'),
    });
}

async function renderPengumpulanChart() {
    if (!pengumpulanCanvas.value || !props.pengumpulanTugasChart.length) {
        return;
    }

    const Chart = await chartJs();
    pengumpulanChartInstance?.destroy();
    pengumpulanChartInstance = new Chart(pengumpulanCanvas.value, {
        type: 'line',
        data: {
            labels: props.pengumpulanTugasChart.map((item) => item.bulan_label || item.bulan),
            datasets: [
                {
                    label: 'Tren Pengumpulan',
                    data: props.pengumpulanTugasChart.map((item) => item.persen_dikumpulkan),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    borderWidth: 3,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#2563eb',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.35,
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

onMounted(() => nextTick(() => { renderKehadiranChart(); renderPengumpulanChart(); }));
watch(() => props.kehadiranChart, () => nextTick(renderKehadiranChart), { deep: true });
watch(() => props.pengumpulanTugasChart, () => nextTick(renderPengumpulanChart), { deep: true });
onBeforeUnmount(() => { kehadiranChartInstance?.destroy(); pengumpulanChartInstance?.destroy(); });

const totalBelumMengumpulkan = computed(() => props.tugasBelumDikumpulkan.reduce((total, item) => total + (item.belum ?? 0), 0));
const totalPerluDinilai = computed(() => props.tugasPerluDinilai.reduce((total, item) => total + (item.total ?? 0), 0));
const metrics = computed(() => [
    { label: 'Kelas dan mapel', value: props.statistik.total_kelas_mapel ?? 0, icon: 'bi-diagram-3-fill', tone: 'primary', href: '/guru/materi' },
    { label: 'Siswa diajar', value: props.statistik.total_siswa ?? 0, icon: 'bi-people-fill', tone: 'info' },
    { label: 'Perlu dinilai', value: totalPerluDinilai.value, icon: 'bi-pencil-square', tone: 'warning', href: '/guru/tugas' },
    { label: 'Kehadiran rendah', value: props.siswaJarangMasuk.length, icon: 'bi-person-exclamation', tone: 'danger' },
]);
const quickActions = [
    { label: 'Buat Materi', href: '/guru/materi', icon: 'bi-file-earmark-plus', color: 'primary' },
    { label: 'Buat Tugas', href: '/guru/tugas', icon: 'bi-journal-plus', color: 'light' },
    { label: 'Absensi', href: '/guru/absensi', icon: 'bi-clipboard-check', color: 'light' },
    { label: 'Chat', href: '/guru/chat', icon: 'bi-chat-dots', color: 'light' },
];
const gradingItems = computed(() => props.tugasPerluDinilai.map((item) => ({ id: item.id, title: item.judul, meta: `${item.kelas} - ${item.mata_pelajaran}`, detail: 'Sudah masuk, belum dinilai', href: item.url, badge: item.total, badgeColor: 'info', icon: 'bi-pencil-square', accent: '#2563eb' })));
const missingItems = computed(() => props.tugasBelumDikumpulkan.map((item) => ({ id: item.id, title: item.judul, meta: `${item.kelas} - ${item.mata_pelajaran}`, detail: `Deadline ${item.batas_waktu}`, href: item.url, badge: `${item.belum}/${item.total_siswa}`, badgeColor: 'warning text-dark', icon: 'bi-exclamation-circle', accent: '#f59e0b' })));
const attendanceItems = computed(() => props.siswaJarangMasuk.map((item) => ({ id: item.id, title: item.nama, meta: `${item.kelas} - NIS ${item.nis}`, detail: `${item.total_absensi} catatan absensi, ${item.total_alpha} alpha`, href: item.url, badge: `${item.persen_hadir}%`, badgeColor: item.persen_hadir < 60 ? 'danger' : 'warning text-dark', icon: 'bi-person-exclamation', accent: '#dc2626' })));
</script>

<template>
    <Head title="Dashboard Guru" />
    <AppShell title="Dashboard Guru">
        <DashboardHero eyebrow="Teacher Dashboard" :title="`Selamat datang, ${user?.nama_lengkap ?? 'Guru'}`" :subtitle="`${totalPerluDinilai} pengumpulan perlu dinilai dan ${totalBelumMengumpulkan} tugas belum lengkap.`" icon="bi-person-workspace" tone="teacher">
            <template #actions><QuickActionBar :actions="quickActions" /></template>
        </DashboardHero>
        <MetricStrip :items="metrics" />

        <div class="teacher-trend-grid">
            <Card :title="`Tren Kehadiran (${chartPeriodLabel})`" icon="bi-graph-up-arrow" body-class="teacher-trend-body">
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
                        <canvas ref="kehadiranCanvas"></canvas>
                    </div>
                </div>
                <EmptyState v-else title="Belum ada data kehadiran." icon="bi-clipboard-check" />
            </Card>

            <Card :title="`Tren Pengumpulan Tugas (${chartPeriodLabel})`" icon="bi-graph-up-arrow" body-class="teacher-trend-body">
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
                        <canvas ref="pengumpulanCanvas"></canvas>
                    </div>
                </div>
                <EmptyState v-else title="Belum ada data pengumpulan tugas." icon="bi-journal-x" />
            </Card>
        </div>

        <div class="dashboard-grid dashboard-grid-teacher teacher-dashboard-queues">
            <ActionQueue title="Perlu Dinilai" icon="bi-pencil-square" :items="gradingItems" empty-title="Tidak ada antrean nilai" empty-message="Semua pengumpulan yang masuk sudah dinilai." />
            <ActionQueue title="Belum Mengumpulkan" icon="bi-exclamation-circle" :items="missingItems" empty-title="Tidak ada tunggakan tugas" empty-message="Semua tugas lewat deadline sudah lengkap dikumpulkan." />
            <ActionQueue title="Siswa Perlu Perhatian" icon="bi-person-exclamation" :items="attendanceItems" empty-title="Kehadiran aman" empty-message="Belum ada siswa dengan kehadiran di bawah 75% dalam 60 hari terakhir." />
        </div>

        <section class="workspace-panel teacher-courses-panel">
            <header class="workspace-panel-header">
                <span class="workspace-panel-title"><i class="bi bi-book" aria-hidden="true"></i> Kelas dan Mapel Diampu</span>
                <Link href="/guru/materi" class="app-card-action-link">Kelola Materi</Link>
            </header>
            <div v-if="kelasMapel.length" class="course-card-grid">
                <CourseCard v-for="item in kelasMapel" :key="item.id" :title="item.mata_pelajaran" :subtitle="item.kelas" :meta="`Semester ${item.semester}`" :href="item.workspace_url" icon="bi-book" :badges="[{ label: `${item.materi_count} materi`, color: 'primary' }, { label: `${item.tugas_count} tugas`, color: 'info' }]" />
            </div>
            <ActionQueue v-else :items="[]" empty-title="Belum ada penugasan mengajar semester ini" icon="bi-book" />
        </section>
    </AppShell>
</template>

<style scoped>
.teacher-dashboard-queues { align-items: stretch; }
.teacher-courses-panel { min-width: 0; }
.teacher-trend-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-bottom: 1.5rem;
}
.teacher-trend-body { padding: 1rem; }
.teacher-trend-content {
    display: grid;
    gap: 1rem;
    min-width: 0;
}
.teacher-trend-summary {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.teacher-trend-summary > div {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    min-width: 0;
    padding: 0.75rem;
}
.teacher-trend-label {
    color: #64748b;
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
}
.teacher-trend-summary strong {
    color: #0f172a;
    display: block;
    font-size: 1.35rem;
    line-height: 1.1;
}
.teacher-trend-chart {
    height: 220px;
    min-width: 0;
    position: relative;
}
@media (max-width: 991.98px) {
    .teacher-trend-grid { grid-template-columns: minmax(0, 1fr); }
    .teacher-dashboard-queues { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .teacher-dashboard-queues > :last-child { grid-column: 1 / -1; }
}
@media (max-width: 767.98px) {
    .teacher-dashboard-queues { grid-template-columns: minmax(0, 1fr); }
    .teacher-dashboard-queues > :last-child { grid-column: auto; }
    .teacher-courses-panel .course-card-grid { grid-template-columns: minmax(0, 1fr); }
    .teacher-trend-summary { grid-template-columns: minmax(0, 1fr); }
    .teacher-trend-chart { height: 210px; }
}
</style>

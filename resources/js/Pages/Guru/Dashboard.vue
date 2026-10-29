<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import { Card, EmptyState } from '../../Components/UI';
import { cssVar, withAlpha } from '../../utils/cssColor';
import type { AppPageProps } from '../../types';

interface GuruStats { total_kelas_mapel?: number; total_siswa?: number; }
interface Course { id: number; kelas: string; mata_pelajaran: string; semester: string; workspace_url: string; materi_count: number; tugas_count: number; }
interface GradingTask { id: number; judul: string; kelas: string; mata_pelajaran: string; total: number; url: string; }
interface MissingTask { id: number; judul: string; kelas: string; mata_pelajaran: string; belum: number; total_siswa: number; batas_waktu: string; url: string; }
interface AttendanceConcern { id: number; nama: string; kelas: string; nis: string; total_absensi: number; total_alpha: number; persen_hadir: number; url: string; }
interface AttendanceChartPoint { bulan: string; bulan_label?: string; total: number; persen_hadir: number; }
interface SubmissionChartPoint { bulan: string; bulan_label?: string; total: number; collected: number; persen_dikumpulkan: number; }
interface TodaySchedule { id: number; pelajaran_ke: number; kelas: string; mata_pelajaran: string; }

const page = usePage<AppPageProps>();
const user = page.props.auth?.user;
const todayLabel = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());
interface Props { statistik?: GuruStats; kelasMapel?: Course[]; jadwalHariIni?: TodaySchedule[]; tugasBelumDikumpulkan?: MissingTask[]; siswaJarangMasuk?: AttendanceConcern[]; tugasPerluDinilai?: GradingTask[]; kehadiranChart?: AttendanceChartPoint[]; pengumpulanTugasChart?: SubmissionChartPoint[]; }
const props = withDefaults(defineProps<Props>(), { statistik: () => ({}), kelasMapel: () => [], jadwalHariIni: () => [], tugasBelumDikumpulkan: () => [], siswaJarangMasuk: () => [], tugasPerluDinilai: () => [], kehadiranChart: () => [], pengumpulanTugasChart: () => [] });

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
        border: cssVar('--border-soft'),
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
                    backgroundColor: withAlpha(palette.primary, 0.82),
                    hoverBackgroundColor: palette.primary,
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
    { label: 'Kelas diampu', value: props.statistik.total_kelas_mapel ?? 0, icon: 'cap', href: '/guru/materi' },
    { label: 'Perlu dinilai', value: totalPerluDinilai.value, icon: 'file', href: '/guru/tugas' },
    { label: 'Siswa diampu', value: props.statistik.total_siswa ?? 0, icon: 'users', href: undefined as string | undefined },
    { label: 'Perlu perhatian', value: props.tugasBelumDikumpulkan.length + props.siswaJarangMasuk.length, icon: 'target', href: undefined as string | undefined },
]);
const iconPaths: Record<string, string[]> = {
    cap: ['m2 9 10-5 10 5-10 5z', 'M6 11v5c3 3 9 3 12 0v-5M22 9v6'],
    file: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6M8 13h8M8 17h8'],
    users: ['M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
    target: ['M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z', 'M17 12a5 5 0 1 1-10 0 5 5 0 0 1 10 0z', 'M13 12a1 1 0 1 1-2 0 1 1 0 0 1 2 0z'],
    clock: ['M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z', 'M12 7v5l3 2'],
    calendar: ['M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z', 'M16 3v4M8 3v4M3 10h18'],
    plus: ['M12 5v14M5 12h14'],
    arrow: ['M5 12h14M13 6l6 6-6 6'],
    alert: ['M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0z', 'M12 9v4M12 17h.01'],
};
function subjectInitials(name: string): string {
    const words = name.trim().split(/\s+/).filter(Boolean);
    const letters = words.length > 1 ? words.map((word) => word[0]).join('') : (words[0] ?? '').replace(/[aiueo]/gi, '');
    return (letters || name).slice(0, 3).toUpperCase();
}

type ReviewFilter = 'all' | 'urgent' | 'reviewed';
const reviewFilter = ref<ReviewFilter>('all');

const reviewItems = computed(() => props.tugasPerluDinilai.map((item, idx) => ({
    id: `nilai-${item.id}`,
    title: item.judul,
    subject: item.mata_pelajaran,
    meta: `${item.kelas} · ${item.mata_pelajaran}`,
    progress: `${item.total} pengumpulan`,
    href: item.url,
    isUrgent: idx === 0,
})));

const urgentReviewCount = computed(() => Math.min(3, reviewItems.value.length));
const reviewedCount = computed(() => 12);

const displayedReviewItems = computed(() => {
    if (reviewFilter.value === 'urgent') {
        return reviewItems.value.filter((item) => item.isUrgent);
    }
    if (reviewFilter.value === 'reviewed') {
        return [];
    }
    return reviewItems.value;
});

const currentScheduleIndex = computed(() => {
    if (!props.jadwalHariIni.length) return -1;
    return Math.min(2, props.jadwalHariIni.length - 1);
});

const classColors = ['', 'amber', 'blue'];
const attentionSignals = computed(() => [
    ...props.tugasBelumDikumpulkan.map((item) => ({
        id: `tugas-${item.id}`,
        title: `${item.belum} siswa belum mengumpulkan`,
        detail: `${item.kelas} · ${item.judul}`,
        href: item.url,
        linkLabel: 'Lihat daftar siswa',
        icon: 'alert',
        tone: 'danger',
    })),
    ...props.siswaJarangMasuk.map((item) => ({
        id: `siswa-${item.id}`,
        title: `Kehadiran ${item.nama} perlu ditinjau`,
        detail: `${item.kelas} · ${item.persen_hadir}% hadir`,
        href: item.url,
        linkLabel: 'Tinjau absensi',
        icon: 'users',
        tone: 'warning',
    })),
]);
</script>

<template>
    <Head title="Dashboard Guru" />
    <AppShell title="Dashboard Guru">
        <section class="welcome-row" aria-labelledby="welcome-title">
            <div class="welcome-copy">
                <div class="welcome-date">{{ todayLabel }}</div>
                <h1 id="welcome-title">Selamat datang, {{ user?.nama_lengkap ?? 'Guru' }}</h1>
                <p class="welcome-sub">
                    <template v-if="totalPerluDinilai">Ada <strong>{{ totalPerluDinilai }} pekerjaan</strong> yang menunggu penilaian. Berikut yang perlu Anda lihat hari ini.</template>
                    <template v-else>Semua pengumpulan sudah dinilai. Berikut ringkasan kelas Anda.</template>
                </p>
            </div>
            <div class="welcome-quick">
                <Link href="/guru/jadwal-mengajar" class="btn btn-outline-secondary gd-button">
                    <svg class="gd-icon" viewBox="0 0 24 24" aria-hidden="true"><path v-for="d in iconPaths.calendar" :key="d" :d="d" /></svg>Lihat jadwal
                </Link>
                <Link href="/guru/tugas" class="btn btn-primary gd-button">
                    <svg class="gd-icon" viewBox="0 0 24 24" aria-hidden="true"><path v-for="d in iconPaths.plus" :key="d" :d="d" /></svg>Buat tugas
                </Link>
            </div>
        </section>

        <section class="overview-strip" aria-label="Ringkasan mengajar hari ini">
            <component :is="item.href ? Link : 'div'" v-for="item in metrics" :key="item.label" :href="item.href" class="overview-item">
                <span class="overview-icon"><svg class="gd-icon" viewBox="0 0 24 24" aria-hidden="true"><path v-for="d in iconPaths[item.icon]" :key="d" :d="d" /></svg></span>
                <span><strong class="overview-value">{{ item.value }}</strong><small class="overview-label">{{ item.label }}</small></span>
            </component>
        </section>

        <div class="content-grid">
            <div class="column">
                <section class="panel" aria-labelledby="review-title">
                    <header class="panel-head">
                        <div><h2 class="panel-title" id="review-title">Perlu ditinjau</h2><span class="panel-caption">Pekerjaan siswa yang baru dikumpulkan</span></div>
                        <Link href="/guru/tugas" class="text-link">Semua tugas <svg class="gd-icon" viewBox="0 0 24 24" aria-hidden="true"><path :d="iconPaths.arrow[0]" /></svg></Link>
                    </header>
                    <div class="queue-tabs" role="tablist" aria-label="Filter pekerjaan">
                        <button class="queue-tab" type="button" role="tab" :aria-selected="reviewFilter === 'all'" @click="reviewFilter = 'all'">Semua baru <span class="tab-count">{{ reviewItems.length }}</span></button>
                        <button class="queue-tab" type="button" role="tab" :aria-selected="reviewFilter === 'urgent'" @click="reviewFilter = 'urgent'">Mendekati tenggat <span class="tab-count">{{ urgentReviewCount }}</span></button>
                        <button class="queue-tab" type="button" role="tab" :aria-selected="reviewFilter === 'reviewed'" @click="reviewFilter = 'reviewed'">Selesai ditinjau <span class="tab-count">{{ reviewedCount }}</span></button>
                    </div>
                    <div v-if="displayedReviewItems.length" class="task-list">
                        <article v-for="item in displayedReviewItems" :key="item.id" class="task-row">
                            <div class="task-main">
                                <span class="subject-mark">{{ subjectInitials(item.subject) }}</span>
                                <span class="task-copy">
                                    <strong class="task-title">{{ item.title }}</strong>
                                    <span class="task-meta">
                                        <span>{{ item.meta }}</span>
                                        <span><svg class="gd-icon" viewBox="0 0 24 24" aria-hidden="true"><path v-for="d in iconPaths.users" :key="d" :d="d" /></svg>{{ item.progress }}</span>
                                    </span>
                                </span>
                            </div>
                            <div class="task-action">
                                <span v-if="item.isUrgent" class="status urgent"><i class="status-dot"></i>Segera</span>
                                <Link v-else :href="item.href" class="btn btn-outline-secondary gd-button">Periksa</Link>
                            </div>
                        </article>
                    </div>
                    <div v-else-if="reviewFilter === 'reviewed'" class="task-list">
                        <div class="empty-state py-4 text-center">
                            <p class="text-muted small mb-0">{{ reviewedCount }} pekerjaan sudah ditinjau pada periode ini.</p>
                        </div>
                    </div>
                    <EmptyState v-else title="Tidak ada antrean nilai" message="Semua pengumpulan yang masuk sudah dinilai." icon="bi-pencil-square" />
                    <footer class="queue-footer">
                        <template v-if="reviewFilter === 'urgent'">Menampilkan pekerjaan yang paling dekat tenggatnya.</template>
                        <template v-else-if="reviewFilter === 'reviewed'">{{ reviewedCount }} pekerjaan sudah ditinjau pada periode ini.</template>
                        <template v-else>Menampilkan <strong>{{ displayedReviewItems.length }} dari {{ reviewItems.length }} pekerjaan baru</strong> · Urut berdasarkan tenggat</template>
                    </footer>
                </section>

                <section class="panel" aria-labelledby="teacher-classes-title">
                    <header class="panel-head">
                        <div><h2 class="panel-title" id="teacher-classes-title">Kelas yang saya ampu</h2><span class="panel-caption">Kelas dan mata pelajaran pada semester ini</span></div>
                        <Link href="/guru/materi" class="text-link">Semua kelas <svg class="gd-icon" viewBox="0 0 24 24" aria-hidden="true"><path :d="iconPaths.arrow[0]" /></svg></Link>
                    </header>
                    <div v-if="kelasMapel.length" class="class-list">
                        <Link v-for="(item, index) in kelasMapel" :key="item.id" :href="item.workspace_url" class="class-row">
                            <span class="class-info">
                                <i class="class-color" :class="classColors[index % 3]"></i>
                                <span>
                                    <strong class="class-name">{{ item.kelas }} · {{ item.mata_pelajaran }}</strong>
                                    <small class="class-meta">Semester {{ item.semester }} · {{ item.materi_count }} materi</small>
                                </span>
                            </span>
                            <span class="class-stat">{{ item.tugas_count }} tugas</span>
                        </Link>
                    </div>
                    <EmptyState v-else title="Belum ada penugasan mengajar semester ini" icon="bi-book" />
                </section>
            </div>

            <div class="column">
                <section class="panel" aria-labelledby="teacher-schedule-title">
                    <header class="panel-head">
                        <div><h2 class="panel-title" id="teacher-schedule-title">Jadwal hari ini</h2><span class="panel-caption">{{ todayLabel }}</span></div>
                        <Link href="/guru/jadwal-mengajar" class="text-link">Buka kalender</Link>
                    </header>
                    <div v-if="jadwalHariIni.length" class="timeline">
                        <div v-for="(item, index) in jadwalHariIni" :key="item.id" class="timeline-item" :class="{ current: index === currentScheduleIndex }">
                            <div class="timeline-time">Jam {{ item.pelajaran_ke }}</div>
                            <div class="timeline-rail"><i class="timeline-dot"></i></div>
                            <div class="timeline-copy">
                                <strong>{{ item.kelas }} · {{ item.mata_pelajaran }}</strong>
                                <small>Pelajaran ke-{{ item.pelajaran_ke }}</small>
                                <span v-if="index === currentScheduleIndex" class="timeline-current">Berikutnya</span>
                            </div>
                        </div>
                    </div>
                    <EmptyState v-else title="Tidak ada jadwal mengajar hari ini" icon="bi-calendar-check" />
                </section>

                <section class="panel" aria-labelledby="teacher-attention-title">
                    <header class="panel-head">
                        <div><h2 class="panel-title" id="teacher-attention-title">Perlu perhatian</h2><span class="panel-caption">Sinyal dari kelas yang Anda ampu</span></div>
                        <span class="tab-count">{{ attentionSignals.length }}</span>
                    </header>
                    <div v-if="attentionSignals.length" class="attention-list">
                        <div v-for="signal in attentionSignals" :key="signal.id" class="attention-row">
                            <span class="attention-icon" :class="{ red: signal.tone === 'danger' }"><svg class="gd-icon" viewBox="0 0 24 24" aria-hidden="true"><path v-for="d in iconPaths[signal.icon]" :key="d" :d="d" /></svg></span>
                            <span class="attention-copy">
                                <strong>{{ signal.title }}</strong>
                                <small>{{ signal.detail }}</small>
                                <small><Link :href="signal.href">{{ signal.linkLabel }}</Link></small>
                            </span>
                        </div>
                    </div>
                    <EmptyState v-else title="Tidak ada hal yang perlu perhatian" icon="bi-check-circle" />
                </section>
            </div>
        </div>

        <section class="teacher-insights" aria-labelledby="teacher-insights-title">
            <div class="teacher-section-heading">
                <div>
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
.gd-icon { width: 18px; height: 18px; flex: 0 0 auto; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.welcome-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 24px; }
.welcome-copy { min-width: 0; }
.welcome-date { margin-bottom: 5px; color: var(--text-muted); font-size: 12px; font-weight: 550; }
.welcome-row h1 { margin: 0; color: var(--text-strong); font-size: var(--page-heading-size); font-weight: 750; letter-spacing: -0.04em; line-height: 1.2; }
.welcome-sub { max-width: 620px; margin: 8px 0 0; color: var(--text-muted); font-size: 13px; }
.welcome-sub strong { color: var(--text-strong); font-weight: 700; }
.welcome-quick { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
.gd-button .gd-icon { width: 15px; height: 15px; }
.overview-strip { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); overflow: hidden; margin-bottom: 23px; border: 1px solid var(--border-soft); border-radius: 12px; background: var(--surface-card); }
.overview-item { display: flex; min-width: 0; align-items: center; gap: 12px; padding: 15px 17px; color: inherit; text-decoration: none; }
.overview-item + .overview-item { border-left: 1px solid var(--border-soft); }
.overview-icon { display: grid; width: 36px; height: 36px; flex: 0 0 auto; place-items: center; border-radius: 9px; background: var(--surface-muted); color: var(--primary-600); }
.overview-icon .gd-icon { width: 17px; height: 17px; }
.overview-value { display: block; color: var(--text-strong); font-size: 20px; font-weight: 760; letter-spacing: -0.05em; line-height: 1.1; font-variant-numeric: tabular-nums; }
.overview-label { display: block; margin-top: 4px; color: var(--text-muted); font-size: var(--fs-xs); font-weight: 600; white-space: nowrap; }
.content-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(270px, 0.85fr); align-items: start; gap: 18px; margin-bottom: 24px; }
.column { display: grid; min-width: 0; gap: 18px; }
.panel { min-width: 0; overflow: hidden; border: 1px solid var(--border-soft); border-radius: var(--panel-radius); background: var(--surface-card); box-shadow: var(--refresh-shadow-card); }
.panel-head { display: flex; min-height: 58px; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 17px; border-bottom: 1px solid var(--border-soft); }
.panel-title { margin: 0; color: var(--text-strong); font-size: var(--card-heading-size); font-weight: var(--fw-bold); line-height: var(--lh-base); }
.panel-caption { display: block; margin-top: var(--space-1); color: var(--text-muted); font-size: var(--card-description-size); line-height: var(--lh-base); }
.text-link { display: inline-flex; align-items: center; gap: 5px; color: var(--primary-600); font-size: var(--fs-xs); font-weight: 750; text-decoration: none; white-space: nowrap; }
.text-link:hover { color: var(--primary-800); text-decoration: underline; }
.text-link .gd-icon { width: 13px; height: 13px; }
.queue-tabs { display: flex; align-items: center; gap: 17px; padding: 0 17px; border-bottom: 1px solid var(--border-soft); }
.queue-tab { position: relative; display: flex; min-height: 43px; align-items: center; gap: 7px; padding: 0; border: 0; background: transparent; color: var(--text-muted); font-size: var(--fs-xs); font-weight: 650; cursor: pointer; }
.queue-tab[aria-selected='true'] { color: var(--text-brand); }
.queue-tab[aria-selected='true']::after { position: absolute; right: 0; bottom: -1px; left: 0; height: 2px; background: var(--primary-600); content: ''; }
.tab-count { display: grid; min-width: 19px; height: 19px; place-items: center; padding: 0 5px; border-radius: 999px; background: var(--surface-subtle); color: var(--text-muted); font-size: var(--fs-xs); font-weight: 750; }
.queue-tab[aria-selected='true'] .tab-count { background: var(--brand-soft-bg); color: var(--brand-soft-text); }
.task-list { padding: 0 17px; }
.task-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 14px; min-height: 79px; padding: 12px 0; border-bottom: 1px solid var(--border-soft); }
.task-row:last-child { border-bottom: 0; }
.task-main { display: flex; min-width: 0; align-items: center; gap: 12px; }
.subject-mark { display: grid; width: 35px; height: 35px; flex: 0 0 auto; place-items: center; border: 1px solid var(--border-soft); border-radius: 9px; background: var(--surface-muted); color: var(--text-brand); font-size: var(--fs-xs); font-weight: 800; }
.task-copy { min-width: 0; }
.task-title { display: block; overflow: hidden; color: var(--text-strong); font-size: var(--fs-xs); font-weight: 720; text-overflow: ellipsis; white-space: nowrap; }
.task-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 5px 9px; margin-top: 5px; color: var(--text-muted); font-size: var(--fs-xs); }
.task-meta span { display: inline-flex; align-items: center; gap: 4px; }
.task-meta .gd-icon { width: 12px; height: 12px; }
.task-action { min-width: 82px; display: flex; justify-content: flex-end; }
.status { display: inline-flex; min-height: 24px; align-items: center; gap: 5px; padding: 3px 8px; border-radius: 5px; background: var(--status-warning-bg); color: var(--status-warning-text); font-size: var(--fs-xs); font-weight: 700; white-space: nowrap; }
.status .status-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.status.urgent { background: var(--status-danger-bg); color: var(--status-danger-text); }
.queue-footer { padding: 11px 17px; border-top: 1px solid var(--border-soft); background: var(--surface-muted); color: var(--text-muted); font-size: var(--fs-xs); }
.queue-footer strong { color: var(--text-body); }
.timeline { padding: 4px 17px 12px; }
.timeline-item { position: relative; display: grid; grid-template-columns: 52px 15px minmax(0, 1fr); gap: 9px; min-height: 67px; padding-top: 12px; }
.timeline-time { padding-top: 1px; color: var(--text-muted); font-size: var(--fs-xs); font-weight: 650; font-variant-numeric: tabular-nums; white-space: nowrap; }
.timeline-rail { position: relative; display: flex; justify-content: center; }
.timeline-rail::after { position: absolute; top: 11px; bottom: -8px; width: 1px; background: var(--border-soft); content: ''; }
.timeline-item:last-child .timeline-rail::after { display: none; }
.timeline-dot { position: relative; z-index: 1; width: 9px; height: 9px; margin-top: 4px; border: 2px solid var(--primary-600); border-radius: 50%; background: var(--surface-card); }
.timeline-item.current .timeline-dot { border-color: var(--primary-300); background: var(--primary-600); box-shadow: 0 0 0 3px var(--primary-50); }
.timeline-current { margin-top: 2px; color: var(--text-brand); font-size: var(--fs-xs); font-weight: 750; display: block; }
.timeline-copy strong { display: block; color: var(--text-strong); font-size: var(--fs-xs); font-weight: 720; }
.timeline-copy small { display: block; margin-top: 3px; color: var(--text-muted); font-size: var(--fs-xs); }
.class-list { padding: 4px 17px 13px; }
.class-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 10px; min-height: 53px; border-bottom: 1px solid var(--border-soft); color: inherit; text-decoration: none; }
.class-row:last-child { border: 0; }
.class-info { display: flex; min-width: 0; align-items: center; gap: 9px; }
.class-color { width: 3px; height: 27px; flex: 0 0 auto; border-radius: 4px; background: var(--primary-600); }
.class-color.amber { background: var(--accent-amber); }
.class-color.blue { background: var(--accent-blue); }
.class-name { display: block; overflow: hidden; color: var(--text-strong); font-size: var(--fs-xs); font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.class-row:hover .class-name { color: var(--text-brand); }
.class-meta { display: block; margin-top: 2px; color: var(--text-muted); font-size: var(--fs-xs); }
.class-stat { color: var(--text-body); font-size: var(--fs-xs); font-weight: 650; white-space: nowrap; }
.attention-list { padding: 5px 17px 13px; }
.attention-row { display: flex; align-items: flex-start; gap: 10px; padding: 11px 0; border-bottom: 1px solid var(--border-soft); }
.attention-row:last-child { border-bottom: 0; }
.attention-icon { display: grid; width: 27px; height: 27px; flex: 0 0 auto; place-items: center; border-radius: 8px; background: var(--status-warning-bg); color: var(--status-warning-text); }
.attention-icon.red { background: var(--status-danger-bg); color: var(--status-danger-text); }
.attention-icon .gd-icon { width: 14px; height: 14px; }
.attention-copy { min-width: 0; }
.attention-copy strong { display: block; color: var(--text-strong); font-size: var(--fs-xs); font-weight: 720; }
.attention-copy small { display: block; margin-top: 3px; color: var(--text-muted); font-size: var(--fs-xs); }
.attention-copy a { color: var(--text-brand); font-weight: 700; text-decoration: none; }
.attention-copy a:hover { text-decoration: underline; }

@media (max-width: 1180px) {
    .overview-item { padding-inline: 12px; }
    .content-grid { grid-template-columns: minmax(0, 1.3fr) minmax(245px, 0.8fr); }
}
@media (max-width: 920px) {
    .content-grid { grid-template-columns: minmax(0, 1fr) minmax(260px, 0.8fr); }
}
@media (max-width: 680px) {
    .welcome-row { align-items: flex-start; flex-direction: column; gap: 15px; margin-bottom: 19px; }
    .welcome-quick { width: 100%; justify-content: flex-start; }
    .overview-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .overview-item { padding: 12px; }
    .overview-item:nth-child(3) { border-top: 1px solid var(--border-soft); border-left: 0; }
    .overview-item:nth-child(4) { border-top: 1px solid var(--border-soft); }
    .content-grid { grid-template-columns: minmax(0, 1fr); gap: 14px; }
    .column { gap: 14px; }
}
@media (max-width: 390px) {
    .panel-head { padding-inline: 13px; }
    .task-list { padding-inline: 13px; }
    .queue-tabs { gap: 11px; padding-inline: 13px; }
    .task-row { grid-template-columns: minmax(0, 1fr); gap: 9px; }
    .task-action { display: flex; justify-content: flex-end; }
    .timeline, .class-list, .attention-list { padding-inline: 13px; }
    .overview-item { gap: 8px; padding-inline: 9px; }
    .overview-icon { width: 31px; height: 31px; }
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
    font-size: var(--fs-xs);
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
}

@media (max-width: 680px) {
    .teacher-dashboard-content {
        grid-template-columns: minmax(0, 1fr);
        gap: 0.9rem;
    }

    .teacher-dashboard-column { gap: 0.9rem; }

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

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';
import { cssVar } from '../../utils/cssColor';
import { ActionQueue, Badge, Card, DashboardHero, EmptyState, MetricStrip, QuickActionBar, TableWrapper } from '../../Components/UI';
import { roleLabel } from '../../utils/roles';
interface Stats { total_siswa?: number; total_guru?: number; total_kelas?: number; total_mapel?: number; }
interface AttendanceMonth { bulan: string; bulan_label?: string; hadir: number; sakit: number; izin: number; alpha: number; persentase?: number; total?: number; }
interface SubjectAverage { nama_mapel: string; rata_rata: number | string; }
interface Announcement { id: number; judul: string; created_at: string; }
interface LoginRecord { id: number; nama_lengkap: string; role: string; login_time?: string; ip_address?: string | null; }
interface QuickAction { label: string; href: string; icon: string; color: string; }

interface Props { statistik?: Stats; absensiBulanan?: AttendanceMonth[]; rataNilaiPerMapel?: SubjectAverage[]; pengumuman?: Announcement[]; loginTerbaru?: LoginRecord[]; }
const props = withDefaults(defineProps<Props>(), { statistik: () => ({}), absensiBulanan: () => [], rataNilaiPerMapel: () => [], pengumuman: () => [], loginTerbaru: () => [] });

const metrics = computed(() => [
    { label: 'Total Siswa', value: props.statistik.total_siswa ?? 0, icon: 'bi-people-fill', tone: 'success' },
    { label: 'Total Guru', value: props.statistik.total_guru ?? 0, icon: 'bi-person-workspace', tone: 'primary' },
    { label: 'Total Kelas', value: props.statistik.total_kelas ?? 0, icon: 'bi-building', tone: 'info' },
    { label: 'Mata Pelajaran', value: props.statistik.total_mapel ?? 0, icon: 'bi-book-fill', tone: 'warning' },
]);

const quickActions: QuickAction[] = [
    { label: 'Buka statistik', href: '/kepsek/statistik', icon: 'bi-graph-up-arrow', color: 'primary' },
    { label: 'Laporan absensi', href: '/kepsek/laporan/absensi', icon: 'bi-clipboard-check', color: 'light' },
    { label: 'Kalender', href: '/kepsek/kalender', icon: 'bi-calendar3', color: 'light' },
];

const latestAttendance = computed(() => props.absensiBulanan.at(-1));
const latestAttendanceRate = computed(() => {
    const item = latestAttendance.value;
    if (!item) return 0;
    const total = Number(item.hadir) + Number(item.sakit) + Number(item.izin) + Number(item.alpha);
    return total ? Math.round((Number(item.hadir) / total) * 100) : 0;
});
const averageScore = computed(() => {
    if (!props.rataNilaiPerMapel.length) return 0;
    const total = props.rataNilaiPerMapel.reduce((sum, item) => sum + Number(item.rata_rata || 0), 0);
    return Math.round((total / props.rataNilaiPerMapel.length) * 10) / 10;
});
const pulseItems = computed(() => [
    { id: 'attendance', title: `${latestAttendanceRate.value}% kehadiran`, meta: latestAttendance.value?.bulan_label || 'Periode terbaru', detail: 'Lihat tren dan tindak lanjut absensi', href: '/kepsek/laporan/absensi', icon: 'bi-calendar2-check', accent: 'var(--accent-green)' },
    { id: 'score', title: `${averageScore.value || 0} rata-rata nilai`, meta: 'Lintas mata pelajaran', detail: 'Buka laporan capaian akademik', href: '/kepsek/laporan/nilai', icon: 'bi-bar-chart-line', accent: 'var(--accent-blue)' },
    { id: 'announcements', title: `${props.pengumuman.length} pengumuman terbaru`, meta: 'Komunikasi sekolah', detail: 'Pastikan informasi tersampaikan', href: '/kepsek/pengumuman', icon: 'bi-megaphone', accent: 'var(--accent-amber)' },
]);

const absensiCanvas = ref<HTMLCanvasElement | null>(null);
let absensiChart: { destroy: () => void } | null = null;
let themeObserver: MutationObserver | null = null;

async function renderAbsensiChart() {
    if (!absensiCanvas.value || !props.absensiBulanan.length) {
        return;
    }

    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);

    const colors = { hadir: cssVar('--accent-green'), sakit: cssVar('--accent-amber'), izin: cssVar('--accent-blue'), alpa: cssVar('--accent-red') };
    const textColor = cssVar('--text-muted');
    const borderColor = cssVar('--border-soft');

    absensiChart?.destroy();
    absensiChart = new Chart(absensiCanvas.value, {
        type: 'bar',
        data: {
            labels: props.absensiBulanan.map((item) => item.bulan_label || item.bulan),
            datasets: [
                { label: 'Hadir', data: props.absensiBulanan.map((item) => item.hadir), backgroundColor: colors.hadir },
                { label: 'Sakit', data: props.absensiBulanan.map((item) => item.sakit), backgroundColor: colors.sakit },
                { label: 'Izin', data: props.absensiBulanan.map((item) => item.izin), backgroundColor: colors.izin },
                { label: 'Alpa', data: props.absensiBulanan.map((item) => item.alpha), backgroundColor: colors.alpa },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: textColor } },
            },
            scales: {
                x: { ticks: { color: textColor }, grid: { color: borderColor } },
                y: {
                    beginAtZero: true,
                    ticks: { color: textColor, precision: 0 },
                    grid: { color: borderColor },
                },
            },
        },
    });
}


onMounted(() => {
    nextTick(renderAbsensiChart);
    themeObserver = new MutationObserver(() => nextTick(renderAbsensiChart));
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
});
watch(() => props.absensiBulanan, () => nextTick(renderAbsensiChart), { deep: true });
onBeforeUnmount(() => {
    themeObserver?.disconnect();
    absensiChart?.destroy();
});
</script>

<template>
    <Head title="Dashboard Kepala Sekolah" />

    <AppShell title="Dashboard Kepala Sekolah">
        <DashboardHero
            eyebrow="Ringkasan Sekolah"
            title="Dashboard Kepala Sekolah"
            subtitle="Lihat absensi, nilai, dan kabar terbaru sekolah di sini."
            tone="warning"
        >
            <template #actions><QuickActionBar :actions="quickActions" /></template>
        </DashboardHero>

        <MetricStrip :items="metrics" />

        <section class="principal-pulse" aria-labelledby="principal-pulse-title">
            <div class="principal-pulse-heading">
                <div>
                    <span class="dashboard-eyebrow">Kabar sekolah</span>
                    <h2 id="principal-pulse-title">Yang perlu dilihat hari ini</h2>
                </div>
                <span class="principal-pulse-period">{{ latestAttendance?.bulan_label || 'Periode terbaru' }}</span>
            </div>
            <ActionQueue :items="pulseItems" empty-title="Belum ada catatan sekolah" />
        </section>

        <div class="row">
            <div class="col-md-6 mb-4">
                <Card title="Statistik Absensi Bulanan">
                    <canvas v-if="absensiBulanan.length" ref="absensiCanvas" height="200"></canvas>
                    <EmptyState v-else title="Belum ada data absensi." icon="bi-clipboard-check" />
                </Card>
            </div>

            <div class="col-md-6 mb-4">
                <Card title="Pengumuman Terbaru" body-class="p-0">
                    <TableWrapper v-if="pengumuman.length" stack>
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Judul</th>
                                    <th scope="col">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in pengumuman" :key="item.id">
                                    <td data-label="Judul" class="stack-title">{{ item.judul }}</td>
                                    <td data-label="Tanggal" class="tabular-nums">{{ item.created_at }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada pengumuman" icon="bi-megaphone" />
                </Card>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <Card title="Rata-rata Nilai per Mata Pelajaran" body-class="p-0">
                    <TableWrapper v-if="rataNilaiPerMapel.length" stack>
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Mata Pelajaran</th>
                                    <th scope="col" class="text-center">Rata-rata</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in rataNilaiPerMapel" :key="item.nama_mapel">
                                    <td data-label="Mata Pelajaran" class="stack-title">{{ item.nama_mapel }}</td>
                                    <td data-label="Rata-rata" class="text-center fw-bold tabular-nums">{{ item.rata_rata }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada data nilai" icon="bi-bar-chart" />
                </Card>
            </div>

            <div class="col-md-6 mb-4">
                <Card title="Login Terbaru" body-class="p-0">
                    <TableWrapper v-if="loginTerbaru.length" stack :min-width="480">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Nama</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Waktu</th>
                                    <th scope="col">IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="log in loginTerbaru" :key="log.id">
                                    <td data-label="Nama" class="stack-title"><strong>{{ log.nama_lengkap }}</strong></td>
                                    <td data-label="Role"><Badge color="secondary">{{ roleLabel(log.role) }}</Badge></td>
                                    <td data-label="Waktu" class="text-body-secondary small tabular-nums">{{ log.login_time }}</td>
                                    <td data-label="IP" class="text-body-secondary small tabular-nums">{{ log.ip_address ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada data login" icon="bi-clock-history" />
                </Card>
            </div>
        </div>
    </AppShell>
</template>

<style scoped>
.principal-pulse {
    margin-bottom: 1rem;
    padding: 1.1rem;
    border: 1px solid var(--border-soft);
    border-radius: var(--panel-radius);
    background: var(--surface-card);
}
.principal-pulse-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: .75rem;
}
.principal-pulse-heading h2 { margin: .25rem 0 0; color: var(--text-strong); font-size: 1.05rem; font-weight: 750; }
.principal-pulse-period { color: var(--text-muted); font-size: var(--fs-xs); }
.principal-pulse :deep(.action-queue) { margin-bottom: 0; border: 0; border-radius: 0; box-shadow: none; }
.principal-pulse :deep(.action-queue-list) { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem; }
.principal-pulse :deep(.action-queue-item) { min-width: 0; border: 1px solid var(--border-soft); border-radius: 11px; background: var(--surface-muted); }
.principal-pulse :deep(.action-queue-item + .action-queue-item) { border-top: 1px solid var(--border-soft); }
@media (max-width: 767.98px) {
    .principal-pulse-heading { align-items: flex-start; flex-direction: column; }
    .principal-pulse :deep(.action-queue-list) { grid-template-columns: minmax(0, 1fr); }
}
</style>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { cssVar, withAlpha } from '../../../utils/cssColor';
import { Card, EmptyState, StatCard, TableWrapper } from '../../../Components/UI';
interface ClassStudents { label: string; jumlah: number; }
interface MonthlyAttendance { bulan: string; bulan_label?: string; hadir: number; total: number; persentase: number; }
interface MonthlySubmission { bulan: string; bulan_label?: string; total: number; tepat_waktu: number; terlambat: number; dinilai: number; }
interface GradeDistribution { label: string; value: number; color: string; }
interface LearningStats { total_tugas: number; total_pengumpulan: number; total_dinilai: number; persentase_dinilai: number; rata_nilai_tugas: number | null; }

interface Props { siswaPerKelas?: ClassStudents[]; totalGuru?: number; totalSiswa?: number; totalKelas?: number; absensiBulanan?: MonthlyAttendance[]; pengumpulanBulanan?: MonthlySubmission[]; distribusiNilai?: GradeDistribution[]; pembelajaran?: LearningStats; }
const props = withDefaults(defineProps<Props>(), {
    siswaPerKelas: () => [], totalGuru: 0, totalSiswa: 0, totalKelas: 0, absensiBulanan: () => [], pengumpulanBulanan: () => [], distribusiNilai: () => [],
    pembelajaran: () => ({ total_tugas: 0, total_pengumpulan: 0, total_dinilai: 0, persentase_dinilai: 0, rata_nilai_tugas: null }),
});

const siswaCanvas = ref<HTMLCanvasElement | null>(null);
const nilaiCanvas = ref<HTMLCanvasElement | null>(null);
const absensiCanvas = ref<HTMLCanvasElement | null>(null);
const pengumpulanCanvas = ref<HTMLCanvasElement | null>(null);
type ChartHandle = { destroy: () => void };
let siswaChart: ChartHandle | null = null;
let nilaiChart: ChartHandle | null = null;
let absensiChart: ChartHandle | null = null;
let pengumpulanChart: ChartHandle | null = null;

async function chartJs() {
    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);
    return Chart;
}

async function renderCharts() {
    const Chart = await chartJs();
    const primary = cssVar('--primary-500');
    const colors = { tepat: cssVar('--accent-green'), terlambat: cssVar('--accent-red'), dinilai: cssVar('--accent-blue') };

    if (siswaCanvas.value && props.siswaPerKelas.length) {
        siswaChart?.destroy();
        siswaChart = new Chart(siswaCanvas.value, {
            type: 'bar',
            data: {
                labels: props.siswaPerKelas.map((item) => item.label),
                datasets: [{
                    label: 'Jumlah Siswa',
                    data: props.siswaPerKelas.map((item) => item.jumlah),
                    backgroundColor: primary,
                    borderRadius: 8,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                    },
                },
            },
        });
    }

    if (nilaiCanvas.value && props.distribusiNilai.length) {
        nilaiChart?.destroy();
        nilaiChart = new Chart(nilaiCanvas.value, {
            type: 'doughnut',
            data: {
                labels: props.distribusiNilai.map((item) => item.label),
                datasets: [{
                    data: props.distribusiNilai.map((item) => item.value),
                    backgroundColor: props.distribusiNilai.map((item) => item.color),
                }],
            },
        });
    }

    if (absensiCanvas.value && props.absensiBulanan.length) {
        absensiChart?.destroy();
        absensiChart = new Chart(absensiCanvas.value, {
            type: 'line',
            data: {
                labels: props.absensiBulanan.map((item) => item.bulan_label || item.bulan),
                datasets: [{
                    label: 'Persentase Hadir',
                    data: props.absensiBulanan.map((item) => item.persentase),
                    borderColor: primary,
                    backgroundColor: withAlpha(primary, 0.16),
                    tension: 0.35,
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, max: 100 } },
            },
        });
    }

    if (pengumpulanCanvas.value && props.pengumpulanBulanan.length) {
        pengumpulanChart?.destroy();
        pengumpulanChart = new Chart(pengumpulanCanvas.value, {
            type: 'bar',
            data: {
                labels: props.pengumpulanBulanan.map((item) => item.bulan_label || item.bulan),
                datasets: [
                    { label: 'Tepat Waktu', data: props.pengumpulanBulanan.map((item) => item.tepat_waktu), backgroundColor: colors.tepat, borderRadius: 8 },
                    { label: 'Terlambat', data: props.pengumpulanBulanan.map((item) => item.terlambat), backgroundColor: colors.terlambat, borderRadius: 8 },
                    { label: 'Sudah Dinilai', data: props.pengumpulanBulanan.map((item) => item.dinilai), backgroundColor: colors.dinilai, borderRadius: 8 },
                ],
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                    },
                },
            },
        });
    }
}

onMounted(() => nextTick(renderCharts));
watch(() => [props.siswaPerKelas, props.distribusiNilai, props.absensiBulanan, props.pengumpulanBulanan], () => nextTick(renderCharts), { deep: true });
onBeforeUnmount(() => {
    siswaChart?.destroy();
    nilaiChart?.destroy();
    absensiChart?.destroy();
    pengumpulanChart?.destroy();
});
</script>

<template>
    <Head title="Statistik" />

    <AppShell title="Statistik">
        <PageHeader
            eyebrow="Monitoring Sekolah"
            title="Statistik Sekolah"
            subtitle="Pantau siswa, guru, pembelajaran, kehadiran, dan hasil belajar dalam satu ringkasan."
        />

        <div class="stats-grid">
            <StatCard label="Total Guru" :value="totalGuru" icon="bi-person-workspace" />
            <StatCard label="Total Kelas" :value="totalKelas" icon="bi-building" />
            <StatCard label="Total Siswa Aktif" :value="totalSiswa" icon="bi-people-fill" />
            <StatCard label="Data Nilai" :value="distribusiNilai.reduce((total, item) => total + item.value, 0)" icon="bi-bar-chart-fill" />
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <Card title="Statistik Pembelajaran">
                    <div class="learning-stat-grid">
                        <div class="learning-stat-tile">
                            <span class="learning-stat-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
                            <span class="learning-stat-copy"><span>Total Tugas Aktif</span><strong class="tabular-nums">{{ pembelajaran.total_tugas }}</strong></span>
                        </div>
                        <div class="learning-stat-tile">
                            <span class="learning-stat-icon"><i class="bi bi-inbox" aria-hidden="true"></i></span>
                            <span class="learning-stat-copy"><span>Total Pengumpulan</span><strong class="tabular-nums">{{ pembelajaran.total_pengumpulan }}</strong></span>
                        </div>
                        <div class="learning-stat-tile">
                            <span class="learning-stat-icon"><i class="bi bi-check2-square" aria-hidden="true"></i></span>
                            <span class="learning-stat-copy"><span>Sudah Dinilai</span><strong class="tabular-nums">{{ pembelajaran.total_dinilai }}</strong><small class="tabular-nums">{{ pembelajaran.persentase_dinilai }}% dari pengumpulan</small></span>
                        </div>
                        <div class="learning-stat-tile">
                            <span class="learning-stat-icon"><i class="bi bi-bar-chart" aria-hidden="true"></i></span>
                            <span class="learning-stat-copy"><span>Rata-rata Nilai Tugas</span><strong class="tabular-nums">{{ pembelajaran.rata_nilai_tugas ?? '-' }}</strong></span>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <Card title="Jumlah Siswa Per Kelas">
                    <canvas v-if="siswaPerKelas.length" ref="siswaCanvas" height="250"></canvas>
                    <EmptyState v-else title="Belum ada data siswa." icon="bi-people" />
                </Card>
            </div>

            <div class="col-md-6 mb-4">
                <Card title="Distribusi Nilai">
                    <canvas v-if="distribusiNilai.some((item) => item.value > 0)" ref="nilaiCanvas" height="250"></canvas>
                    <EmptyState v-else title="Belum ada data nilai." icon="bi-bar-chart" />
                </Card>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <Card title="Tren Kehadiran 6 Bulan Terakhir">
                    <canvas v-if="absensiBulanan.length" ref="absensiCanvas" height="220"></canvas>
                    <EmptyState v-else title="Belum ada data absensi." icon="bi-clipboard-check" />
                </Card>
            </div>

            <div class="col-lg-5 mb-4">
                <Card title="Statistik Absensi Bulanan" body-class="p-0">
                    <TableWrapper v-if="absensiBulanan.length" stack :min-width="360">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Bulan</th>
                                    <th scope="col">Hadir</th>
                                    <th scope="col">Total</th>
                                    <th scope="col">Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in absensiBulanan" :key="item.bulan">
                                    <td data-label="Bulan" class="stack-title"><strong>{{ item.bulan_label || item.bulan }}</strong></td>
                                    <td data-label="Hadir" class="tabular-nums">{{ item.hadir }}</td>
                                    <td data-label="Total" class="tabular-nums">{{ item.total }}</td>
                                    <td data-label="Kehadiran" class="tabular-nums">{{ item.persentase }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Tidak ada data" icon="bi-list-ul" />
                </Card>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <Card title="Tren Pengumpulan Tugas Bulanan">
                    <canvas v-if="pengumpulanBulanan.length" ref="pengumpulanCanvas" height="220"></canvas>
                    <EmptyState v-else title="Belum ada data pengumpulan tugas." icon="bi-journal-check" />
                </Card>
            </div>

            <div class="col-lg-5 mb-4">
                <Card title="Rekap Pengumpulan Bulanan" body-class="p-0">
                    <TableWrapper v-if="pengumpulanBulanan.length" stack :min-width="360">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Bulan</th>
                                    <th scope="col">Total</th>
                                    <th scope="col">Tepat Waktu</th>
                                    <th scope="col">Terlambat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in pengumpulanBulanan" :key="item.bulan">
                                    <td data-label="Bulan" class="stack-title"><strong>{{ item.bulan_label || item.bulan }}</strong></td>
                                    <td data-label="Total" class="tabular-nums">{{ item.total }}</td>
                                    <td data-label="Tepat Waktu" class="tabular-nums">{{ item.tepat_waktu }}</td>
                                    <td data-label="Terlambat" class="tabular-nums">{{ item.terlambat }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Tidak ada data" icon="bi-list-check" />
                </Card>
            </div>
        </div>
    </AppShell>
</template>

<style scoped>
.learning-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.65rem;
}

.learning-stat-tile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    min-height: 78px;
    padding: 0.8rem;
    border: 1px solid var(--border-soft);
    border-radius: 12px;
    background: var(--surface-subtle);
}

.learning-stat-icon {
    display: grid;
    width: 2.2rem;
    height: 2.2rem;
    flex: 0 0 auto;
    place-items: center;
    border-radius: 10px;
    background: var(--brand-soft-bg);
    color: var(--text-brand);
}

.learning-stat-copy {
    display: grid;
    min-width: 0;
    color: var(--text-muted);
    font-size: 0.78rem;
    line-height: 1.3;
}

.learning-stat-copy strong {
    color: var(--text-strong);
    font-size: 1.35rem;
    font-weight: 750;
    line-height: 1.2;
}

.learning-stat-copy small {
    margin-top: 0.2rem;
    font-size: 0.7rem;
}

@media (max-width: 991.98px) {
    .learning-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 399.98px) {
    .learning-stat-grid { gap: 0.45rem; }
    .learning-stat-tile { gap: 0.5rem; padding: 0.65rem; }
    .learning-stat-icon { width: 1.9rem; height: 1.9rem; }
    .learning-stat-copy { font-size: 0.72rem; }
}
</style>

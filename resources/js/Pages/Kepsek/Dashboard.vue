<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';
import { cssVar } from '../../utils/cssColor';
import { Badge, Card, DashboardHero, EmptyState, MetricStrip, TableWrapper } from '../../Components/UI';
import { roleLabel } from '../../utils/roles';
interface Stats { total_siswa?: number; total_guru?: number; total_kelas?: number; total_mapel?: number; }
interface AttendanceMonth { bulan: string; bulan_label?: string; hadir: number; sakit: number; izin: number; alpha: number; persentase?: number; total?: number; }
interface SubjectAverage { nama_mapel: string; rata_rata: number | string; }
interface Announcement { id: number; judul: string; created_at: string; }
interface LoginRecord { id: number; nama_lengkap: string; role: string; login_time?: string; ip_address?: string | null; }

interface Props { statistik?: Stats; absensiBulanan?: AttendanceMonth[]; rataNilaiPerMapel?: SubjectAverage[]; pengumuman?: Announcement[]; loginTerbaru?: LoginRecord[]; }
const props = withDefaults(defineProps<Props>(), { statistik: () => ({}), absensiBulanan: () => [], rataNilaiPerMapel: () => [], pengumuman: () => [], loginTerbaru: () => [] });

const metrics = computed(() => [
    { label: 'Total Siswa', value: props.statistik.total_siswa ?? 0, icon: 'bi-people-fill', tone: 'success' },
    { label: 'Total Guru', value: props.statistik.total_guru ?? 0, icon: 'bi-person-workspace', tone: 'primary' },
    { label: 'Total Kelas', value: props.statistik.total_kelas ?? 0, icon: 'bi-building', tone: 'info' },
    { label: 'Mata Pelajaran', value: props.statistik.total_mapel ?? 0, icon: 'bi-book-fill', tone: 'warning' },
]);

const absensiCanvas = ref<HTMLCanvasElement | null>(null);
let absensiChart: { destroy: () => void } | null = null;

async function renderAbsensiChart() {
    if (!absensiCanvas.value || !props.absensiBulanan.length) {
        return;
    }

    const { Chart, registerables } = await import('chart.js');
    Chart.register(...registerables);

    const colors = { hadir: cssVar('--accent-green'), sakit: cssVar('--accent-amber'), izin: cssVar('--accent-blue'), alpa: cssVar('--accent-red') };

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
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                    },
                },
            },
        },
    });
}


onMounted(() => nextTick(renderAbsensiChart));
watch(() => props.absensiBulanan, () => nextTick(renderAbsensiChart), { deep: true });
onBeforeUnmount(() => absensiChart?.destroy());
</script>

<template>
    <Head title="Dashboard Kepala Sekolah" />

    <AppShell title="Dashboard Kepala Sekolah">
        <DashboardHero
            eyebrow="Ringkasan Sekolah"
            title="Dashboard Kepala Sekolah"
            subtitle="Pantau absensi, nilai, dan aktivitas terbaru dari satu layar yang lebih ringkas."
            tone="warning"
        />

        <MetricStrip :items="metrics" />

        <div class="row">
            <div class="col-md-6 mb-4">
                <Card title="Statistik Absensi Bulanan">
                    <canvas v-if="absensiBulanan.length" ref="absensiCanvas" height="200"></canvas>
                    <EmptyState v-else title="Belum ada data absensi." icon="bi-clipboard-check" />
                </Card>
            </div>

            <div class="col-md-6 mb-4">
                <Card title="Pengumuman Terbaru" body-class="p-0">
                    <TableWrapper v-if="pengumuman.length">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Judul</th>
                                    <th scope="col">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in pengumuman" :key="item.id">
                                    <td>{{ item.judul }}</td>
                                    <td class="tabular-nums">{{ item.created_at }}</td>
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
                    <TableWrapper v-if="rataNilaiPerMapel.length">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Mata Pelajaran</th>
                                    <th scope="col" class="text-center">Rata-rata</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in rataNilaiPerMapel" :key="item.nama_mapel">
                                    <td>{{ item.nama_mapel }}</td>
                                    <td class="text-center fw-bold tabular-nums">{{ item.rata_rata }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada data nilai" icon="bi-bar-chart" />
                </Card>
            </div>

            <div class="col-md-6 mb-4">
                <Card title="Login Terbaru" body-class="p-0">
                    <TableWrapper v-if="loginTerbaru.length" :min-width="480">
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
                                    <td><strong>{{ log.nama_lengkap }}</strong></td>
                                    <td><Badge color="secondary">{{ roleLabel(log.role) }}</Badge></td>
                                    <td class="text-body-secondary small tabular-nums">{{ log.login_time }}</td>
                                    <td class="text-body-secondary small tabular-nums">{{ log.ip_address ?? '-' }}</td>
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

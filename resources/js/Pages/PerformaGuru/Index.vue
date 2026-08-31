<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '../../Components/AppShell/PageHeader.vue';
import AppShell from '../../Layouts/AppShell.vue';
import { Badge, Button, Card, EmptyState, MetricStrip, TableWrapper } from '../../Components/UI';

const props = defineProps({
    summary: { type: Object, default: () => ({ total_guru: 0, rata_skor: 0, total_tugas: 0, perlu_dinilai: 0 }) },
    teachers: { type: Array, default: () => [] },
    earlyWarnings: { type: Array, default: () => [] },
    exportUrls: { type: Object, default: () => ({}) },
});

const metrics = computed(() => [
    { label: 'Guru Aktif', value: props.summary.total_guru, icon: 'bi-person-workspace', tone: 'primary' },
    { label: 'Rata-rata Skor', value: props.summary.rata_skor, icon: 'bi-speedometer2', tone: 'success' },
    { label: 'Total Tugas', value: props.summary.total_tugas, icon: 'bi-journal-check', tone: 'warning' },
    { label: 'Perlu Dinilai', value: props.summary.perlu_dinilai, icon: 'bi-pencil-square', tone: 'danger' },
]);

function scoreColor(score) {
    if (score >= 85) return 'success';
    if (score >= 75) return 'primary';
    if (score >= 60) return 'warning text-dark';
    return 'danger';
}
</script>

<template>
    <Head title="Performa Guru" />

    <AppShell title="Performa Guru">
        <PageHeader
            title="Performa Guru"
            subtitle="Indikator evaluasi berbasis tugas, pengumpulan, nilai, feedback, dan tindak lanjut penilaian."
            icon="bi-clipboard2-data-fill"
        >
            <template #actions>
                <div class="d-flex flex-wrap gap-2">
                    <Button v-if="exportUrls.excel" :href="exportUrls.excel" color="outline-success" icon="bi-file-earmark-excel">Excel</Button>
                    <Button v-if="exportUrls.pdf" :href="exportUrls.pdf" color="outline-danger" icon="bi-file-earmark-pdf">PDF</Button>
                </div>
            </template>
        </PageHeader>

        <MetricStrip :items="metrics" />

        <div class="row g-4">
            <div class="col-12">
                <Card title="Dashboard KPI Guru" icon="bi-table" body-class="p-0">
                    <TableWrapper v-if="teachers.length">
                        <table class="table table-hover align-middle mb-0 performance-table">
                            <thead>
                                <tr>
                                    <th>Guru</th>
                                    <th>Skor</th>
                                    <th>Kelas/Mapel</th>
                                    <th>Tugas</th>
                                    <th>Pengumpulan</th>
                                    <th>Penilaian</th>
                                    <th>Feedback</th>
                                    <th>Rata Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="teacher in teachers" :key="teacher.id">
                                    <td>
                                        <strong>{{ teacher.nama }}</strong>
                                        <div class="text-muted small">@{{ teacher.username }}</div>
                                    </td>
                                    <td>
                                        <Badge :color="scoreColor(teacher.score)">{{ teacher.score }}</Badge>
                                        <div class="text-muted small">{{ teacher.kategori }}</div>
                                    </td>
                                    <td>
                                        <strong>{{ teacher.total_kelas_mapel }}</strong>
                                        <div class="text-muted small">{{ teacher.courses.slice(0, 2).join(', ') || '-' }}</div>
                                    </td>
                                    <td>{{ teacher.total_tugas }}</td>
                                    <td>
                                        {{ teacher.pengumpulan_siswa }}/{{ teacher.target_pengumpulan }}
                                        <div class="text-muted small">{{ teacher.persen_pengumpulan }}%</div>
                                    </td>
                                    <td>
                                        {{ teacher.sudah_dinilai }}
                                        <div class="text-muted small">{{ teacher.persen_dinilai }}% · {{ teacher.perlu_dinilai }} perlu</div>
                                    </td>
                                    <td>{{ teacher.persen_feedback }}%</td>
                                    <td>{{ teacher.rata_nilai_tugas ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada data guru aktif." icon="bi-person-workspace" />
                </Card>
            </div>

            <div class="col-12">
                <Card title="Early Warning Siswa" icon="bi-exclamation-triangle-fill" body-class="p-0">
                    <TableWrapper v-if="earlyWarnings.length">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th>Kelas</th>
                                    <th>Alasan</th>
                                    <th>Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="student in earlyWarnings" :key="student.id">
                                    <td><strong>{{ student.nama }}</strong><div class="text-muted small">{{ student.nis }}</div></td>
                                    <td>{{ student.kelas }}</td>
                                    <td>{{ student.reasons }}</td>
                                    <td>{{ student.average_grade ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada siswa berisiko." message="Data tugas, nilai, dan absensi belum menunjukkan sinyal risiko." icon="bi-check-circle" />
                </Card>
            </div>
        </div>
    </AppShell>
</template>

<style scoped>
.performance-table th,
.performance-table td {
    min-width: 120px;
}
</style>

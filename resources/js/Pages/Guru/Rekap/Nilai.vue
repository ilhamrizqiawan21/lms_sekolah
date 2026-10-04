<script setup lang="ts">
import { SelectInput } from '../../../Components/Form';
import type { PropType } from 'vue';
import type { Scores, Score } from '../../../types/assessment';
interface GradeRow extends Scores { id: number; rata_akhir: Score; siswa: { user: { nama_lengkap: string } | null; nis: string; kelas: { nama_kelas: string } | null } | null; kelas_mapel?: { mata_pelajaran: { nama_mapel: string } | null } | null; kelasMapel?: { mataPelajaran: { nama_mapel: string } | null } | null }
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppShell from '../../../Layouts/AppShell.vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { Badge, Button, Card, EmptyState, TableWrapper } from '../../../Components/UI';

const props = defineProps({
    title: { type: String, default: 'Rekap Nilai Siswa' },
    semester: { type: String, default: '1' },
    kelasMapel: { type: Array as PropType<{ id: number; label: string }[]>, default: () => [] },
    nilai: { type: Object as PropType<{ data: GradeRow[]; current_page: number; last_page: number; from: number | null; total: number }>, default: () => ({ data: [], current_page: 1, last_page: 1, from: 0, total: 0 }) },
});

function formatScore(value: Score | null | undefined) {
    if (value === null || value === undefined || value === '') return '-';
    return String(Math.round(Number(value)));
}

const selectedKelas = ref(new URLSearchParams(window.location.search).get('kelas_mapel_id') || '');
const selectedSemester = ref(props.semester);

function filter() {
    router.get('/guru/rekap-nilai', { kelas_mapel_id: selectedKelas.value || undefined, semester: selectedSemester.value }, { preserveState: true, replace: true });
}

function reset() {
    selectedKelas.value = '';
    router.get('/guru/rekap-nilai', { semester: selectedSemester.value }, { preserveState: true, replace: true });
}

function page(pageNumber: number) {
    router.get('/guru/rekap-nilai', {
        kelas_mapel_id: selectedKelas.value || undefined,
        semester: selectedSemester.value,
        page: pageNumber,
    }, { preserveState: true, replace: true });
}

const kelasMapelOptions = computed(() => props.kelasMapel.map((item) => ({ value: String(item.id), label: item.label })));
const semesterOptions = [{ value: '1', label: 'Semester 1' }, { value: '2', label: 'Semester 2' }];
</script>

<template>
    <AppShell title="Rekap Nilai">
        <PageHeader title="Rekap Nilai Siswa" subtitle="Rekap nilai dari kelas dan mata pelajaran yang Anda ampu." icon="bi-file-earmark-bar-graph-fill" />

        <Card class="mb-4">
            <form class="row g-3 align-items-end app-table-filter" @submit.prevent="filter">
                <div class="col-12 col-md-5">
                    <SelectInput v-model="selectedKelas" name="rekap-nilai-kelas" label="Kelas & Mata Pelajaran" placeholder="Semua Kelas & Mapel" :options="kelasMapelOptions" wrapper-class="" />
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <SelectInput v-model="selectedSemester" name="rekap-nilai-semester" label="Semester" :options="semesterOptions" wrapper-class="" />
                </div>
                <div class="col-12 col-sm-6 col-md-2 d-grid">
                    <Button color="primary" size="" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Tampilkan</Button>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <Button color="outline-secondary" size="" type="button" @click="reset">Reset</Button>
                </div>
            </form>
        </Card>

        <Card body-class="p-0">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap px-3 py-3 border-bottom">
                <strong>Data Nilai</strong>
                <Badge color="secondary">{{ nilai.total ?? 0 }} siswa</Badge>
            </div>

            <div v-if="!nilai.data?.length" class="p-5">
                <EmptyState title="Tidak ada data nilai." icon="bi-inbox" />
            </div>

            <TableWrapper v-else :min-width="1320">
                <table class="table table-hover align-middle mb-0 app-table rekap-table">
                    <colgroup>
                        <col class="col-no">
                        <col class="col-name">
                        <col class="col-class">
                        <col class="col-subject">
                        <col v-for="index in 9" :key="`score-${index}`" class="col-score">
                        <col class="col-predicate">
                    </colgroup>
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="text-center">#</th>
                            <th scope="col">Nama Siswa</th>
                            <th scope="col">Kelas</th>
                            <th scope="col">Mapel</th>
                            <th scope="col" class="text-center">SUM1</th>
                            <th scope="col" class="text-center">SUM2</th>
                            <th scope="col" class="text-center">SUM3</th>
                            <th scope="col" class="text-center">SUM4</th>
                            <th scope="col" class="text-center">Harian</th>
                            <th scope="col" class="text-center">STS</th>
                            <th scope="col" class="text-center">SAS</th>
                            <th scope="col" class="text-center">SAT</th>
                            <th scope="col" class="text-center">Rata²</th>
                            <th scope="col" class="text-center">Predikat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in nilai.data" :key="row.id">
                            <td class="text-center text-body-secondary">{{ (nilai.from || 1) + index }}</td>
                            <td class="rekap-name-cell">{{ row.siswa?.user?.nama_lengkap || row.siswa?.nis || '-' }}</td>
                            <td>{{ row.siswa?.kelas?.nama_kelas || '-' }}</td>
                            <td class="rekap-subject-cell">{{ row.kelas_mapel?.mata_pelajaran?.nama_mapel || row.kelasMapel?.mataPelajaran?.nama_mapel || '-' }}</td>
                            <td class="text-center">{{ formatScore(row.sum1) }}</td>
                            <td class="text-center">{{ formatScore(row.sum2) }}</td>
                            <td class="text-center">{{ formatScore(row.sum3) }}</td>
                            <td class="text-center">{{ formatScore(row.sum4) }}</td>
                            <td class="text-center">{{ formatScore(row.nilai_harian) }}</td>
                            <td class="text-center">{{ formatScore(row.sts) }}</td>
                            <td class="text-center">{{ formatScore(row.sas) }}</td>
                            <td class="text-center">{{ formatScore(row.sat) }}</td>
                            <td class="text-center">
                                <strong>{{ formatScore(row.rata_akhir) }}</strong>
                            </td>
                            <td class="text-center">
                                <Badge
                                    v-if="row.rata_akhir != null"
                                    :color="Number(row.rata_akhir) >= 92 ? 'success' : Number(row.rata_akhir) >= 83 ? 'primary' : Number(row.rata_akhir) >= 75 ? 'warning' : 'danger'"
                                >
                                    {{ Number(row.rata_akhir) >= 92 ? 'A' : Number(row.rata_akhir) >= 83 ? 'B' : Number(row.rata_akhir) >= 75 ? 'C' : 'D' }}
                                </Badge>
                                <span v-else>-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>

            <div v-if="nilai.last_page > 1" class="p-3 border-top d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span class="text-body-secondary small">Halaman {{ nilai.current_page }} dari {{ nilai.last_page }}</span>
                <div class="d-flex gap-2">
                    <Button color="outline-secondary" type="button" :disabled="nilai.current_page <= 1" @click="page(nilai.current_page - 1)">Sebelumnya</Button>
                    <Button color="outline-secondary" type="button" :disabled="nilai.current_page >= nilai.last_page" @click="page(nilai.current_page + 1)">Berikutnya</Button>
                </div>
            </div>
        </Card>
    </AppShell>
</template>

<style scoped>
.rekap-table {
    table-layout: fixed;
}

.rekap-table .col-no { width: 56px; }
.rekap-table .col-name { width: 250px; }
.rekap-table .col-class { width: 100px; }
.rekap-table .col-subject { width: 190px; }
.rekap-table .col-score { width: 70px; }
.rekap-table .col-predicate { width: 94px; }

.rekap-name-cell,
.rekap-subject-cell {
    white-space: normal;
    overflow-wrap: anywhere;
    line-height: 1.35;
}

@media (max-width: 767.98px) {
    .app-table th,
    .app-table td {
        font-size: 0.8rem;
    }

    .rekap-table .col-name {
        width: 220px;
    }
}
</style>

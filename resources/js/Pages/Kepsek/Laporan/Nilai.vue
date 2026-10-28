<script setup lang="ts">
import type { PropType } from 'vue';
import type { LaravelPaginator } from '../../../types/pagination';
import type { Score } from '../../../types/assessment';
import type { GradeReportRow, ReportOption, ExportUrls } from '../../../types/reports';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { SearchableSelect, SelectInput } from '../../../Components/Form';
import AppShell from '../../../Layouts/AppShell.vue';
import { Button, Card, EmptyState, Pagination, TableWrapper } from '../../../Components/UI';

const props = defineProps({
    nilai: { type: Object as PropType<LaravelPaginator<GradeReportRow>>, required: true },
    kelasOptions: { type: Array as PropType<ReportOption[]>, default: () => [] },
    mapelOptions: { type: Array as PropType<ReportOption[]>, default: () => [] },
    filters: { type: Object as PropType<Partial<Record<'kelas_id' | 'mapel_id' | 'semester', string>>>, default: () => ({}) },
    taAktif: { type: Object as PropType<{ id: number; tahun: string } | null>, default: null },
    resetUrl: { type: String, required: true },
    exportUrls: { type: Object as PropType<ExportUrls>, default: () => ({}) },
});

const filterForm = reactive({
    kelas_id: props.filters.kelas_id ?? '',
    mapel_id: props.filters.mapel_id ?? '',
    semester: props.filters.semester ?? '',
});

const semesterOptions = [
    { value: '1', label: 'Semester 1' },
    { value: '2', label: 'Semester 2' },
];

function cleanFilters() {
    return Object.fromEntries(Object.entries(filterForm).filter(([, value]) => value !== '' && value !== null));
}

function applyFilters() {
    router.get(props.resetUrl, cleanFilters(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    filterForm.kelas_id = '';
    filterForm.mapel_id = '';
    filterForm.semester = '';

    router.get(props.resetUrl, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function valueOrDash(value: Score) {
    return value ?? '-';
}

function exportUrl(format: 'excel' | 'pdf') {
    const base = props.exportUrls[format];
    const params = new URLSearchParams(cleanFilters()).toString();
    return params ? `${base}?${params}` : base;
}
</script>

<template>
    <Head title="Laporan Nilai" />

    <AppShell title="Laporan Nilai">
        <PageHeader
            title="Laporan Nilai"
            :subtitle="taAktif ? `Tahun ajaran ${taAktif.tahun}` : 'Tahun ajaran aktif belum tersedia'"
        />

        <Card title="Laporan Nilai Akhir">
            <form class="row g-3 app-table-filter mb-3" @submit.prevent="applyFilters">
                <div class="col-md-3">
                    <SearchableSelect
                        v-model="filterForm.kelas_id"
                        name="kelas_id"
                        wrapper-class="mb-0"
                        placeholder="Semua Kelas"
                        search-placeholder="Cari kelas..."
                        :options="kelasOptions"
                    />
                </div>
                <div class="col-md-3">
                    <SearchableSelect
                        v-model="filterForm.mapel_id"
                        name="mapel_id"
                        wrapper-class="mb-0"
                        placeholder="Semua Mapel"
                        search-placeholder="Cari mapel..."
                        :options="mapelOptions"
                    />
                </div>
                <div class="col-md-2">
                    <SelectInput
                        v-model="filterForm.semester"
                        name="semester"
                        wrapper-class="mb-0"
                        placeholder="Semua Semester"
                        :options="semesterOptions"
                    />
                </div>
                <div class="col-md-2">
                    <Button type="submit" color="primary" icon="bi-search" class="w-100">Filter</Button>
                </div>
                <div class="col-md-2">
                    <Button type="button" color="outline-secondary" icon="bi-arrow-clockwise" class="w-100" @click="resetFilters">Reset</Button>
                </div>
            </form>

            <div v-if="nilai.data.length" class="content-summary">
                <div>
                    <div class="content-summary-title">Ringkasan data nilai yang tersedia</div>
                    <div class="content-summary-text">Menampilkan {{ nilai.data.length }} entri hasil pencarian dengan data akhir yang dapat diekspor.</div>
                </div>
                <div class="content-summary-actions">
                    <Button :href="exportUrl('excel')" color="outline-secondary" icon="bi-file-earmark-excel" size="sm">Excel</Button>
                    <Button :href="exportUrl('pdf')" color="outline-secondary" icon="bi-file-earmark-pdf" size="sm">PDF</Button>
                </div>
            </div>

            <TableWrapper v-if="nilai.data.length" :min-width="860">
                <table class="table table-bordered table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Siswa</th>
                            <th scope="col">Kelas</th>
                            <th scope="col">Mapel</th>
                            <th scope="col">Sum 1</th>
                            <th scope="col">Sum 2</th>
                            <th scope="col">Sum 3</th>
                            <th scope="col">Sum 4</th>
                            <th scope="col">Nilai Harian</th>
                            <th scope="col">STS</th>
                            <th scope="col">SAS</th>
                            <th scope="col">SAT</th>
                            <th scope="col">Rata Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in nilai.data" :key="item.id">
                            <td data-label="Siswa" class="stack-title"><strong>{{ item.siswa }}</strong></td>
                            <td data-label="Kelas">{{ item.kelas }}</td>
                            <td data-label="Mapel">{{ item.mapel }}</td>
                            <td data-label="Sum 1" class="tabular-nums">{{ valueOrDash(item.sum1) }}</td>
                            <td data-label="Sum 2" class="tabular-nums">{{ valueOrDash(item.sum2) }}</td>
                            <td data-label="Sum 3" class="tabular-nums">{{ valueOrDash(item.sum3) }}</td>
                            <td data-label="Sum 4" class="tabular-nums">{{ valueOrDash(item.sum4) }}</td>
                            <td data-label="Nilai Harian" class="tabular-nums">{{ valueOrDash(item.nilai_harian) }}</td>
                            <td data-label="STS" class="tabular-nums">{{ valueOrDash(item.sts) }}</td>
                            <td data-label="SAS" class="tabular-nums">{{ valueOrDash(item.sas) }}</td>
                            <td data-label="SAT" class="tabular-nums">{{ valueOrDash(item.sat) }}</td>
                            <td data-label="Rata Akhir" class="tabular-nums"><strong>{{ valueOrDash(item.rata_akhir) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>

            <EmptyState v-else title="Tidak ada data nilai." icon="bi-bar-chart" />

            <template v-if="nilai.links?.length" #footer>
                <Pagination :links="nilai.links" />
            </template>
        </Card>
    </AppShell>
</template>

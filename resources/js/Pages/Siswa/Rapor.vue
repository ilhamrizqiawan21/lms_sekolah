<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/AppShell/PageHeader.vue';
import AppShell from '../../Layouts/AppShell.vue';
import { SelectInput } from '../../Components/Form';
import { Badge, Button, Card, EmptyState, TableWrapper } from '../../Components/UI';

interface Mark { mata_pelajaran: string; nilai_akhir: number | null; predikat: string | null; deskripsi: string | null; }
const props = defineProps<{
    semester: string;
    tahunAjaran: string | null;
    rapor: { nama: string; nis: string; kelas: string; catatan_wali_kelas: string | null; sakit: number; izin: number; alpha: number; nilai: Mark[] } | null;
}>();

function changeSemester(value: string) {
    router.get('/siswa/rapor', { semester: value }, { preserveState: true, replace: true });
}

function printPage() {
    window.print();
}
</script>

<template>
    <Head title="Rapor Saya" />
    <AppShell title="Rapor Saya">
        <PageHeader eyebrow="Hasil Belajar" title="Rapor Saya" subtitle="Laporan hasil belajar yang sudah difinalkan oleh wali kelas." icon="bi-file-earmark-person">
            <template #actions><Button v-if="rapor" color="outline-primary" icon="bi-printer" @click="printPage">Cetak</Button></template>
        </PageHeader>
        <Card class="mb-4"><div class="row align-items-end"><div class="col-md-3"><SelectInput :model-value="semester" name="semester" label="Semester" :options="[{ value: '1', label: 'Semester 1' }, { value: '2', label: 'Semester 2' }]" wrapper-class="mb-0" @update:model-value="changeSemester" /></div><div class="col-md-9 small text-body-secondary">Tahun Ajaran {{ tahunAjaran ?? '-' }}</div></div></Card>
        <EmptyState v-if="!rapor" title="Rapor belum tersedia" message="Wali kelas belum memfinalkan rapor untuk semester ini." icon="bi-file-earmark-x" />
        <template v-else>
            <Card class="mb-4"><div class="row"><div class="col-md-6"><div class="small text-body-secondary">Nama</div><div class="fw-semibold">{{ rapor.nama }}</div><div class="small text-body-secondary mt-2">NIS</div><div>{{ rapor.nis }}</div></div><div class="col-md-6"><div class="small text-body-secondary">Kelas</div><div class="fw-semibold">{{ rapor.kelas }}</div><div class="small text-body-secondary mt-2">Kehadiran</div><div class="d-flex flex-wrap gap-1"><Badge color="warning text-dark">Sakit {{ rapor.sakit }}</Badge><Badge color="info">Izin {{ rapor.izin }}</Badge><Badge color="danger">Alpha {{ rapor.alpha }}</Badge></div></div></div></Card>
            <Card title="Nilai Akademik" icon="bi-bar-chart-fill" body-class="p-0"><TableWrapper stack><table class="table table-hover mb-0 app-table-proportional"><thead><tr><th>Mata Pelajaran</th><th>Nilai</th><th>Predikat</th><th>Deskripsi</th></tr></thead><tbody><tr v-for="item in rapor.nilai" :key="item.mata_pelajaran"><td data-label="Mata Pelajaran" class="fw-semibold">{{ item.mata_pelajaran }}</td><td data-label="Nilai">{{ item.nilai_akhir ?? '-' }}</td><td data-label="Predikat">{{ item.predikat ?? '-' }}</td><td data-label="Deskripsi" class="text-break">{{ item.deskripsi ?? '-' }}</td></tr></tbody></table></TableWrapper></Card>
            <Card class="mt-4" title="Catatan Wali Kelas" icon="bi-chat-left-text"><p class="mb-0 text-break">{{ rapor.catatan_wali_kelas || '-' }}</p></Card>
        </template>
    </AppShell>
</template>

<style scoped>
@media print { :global(.app-sidebar), :global(.app-topbar), :global(.btn), :global(.page-header-actions) { display: none !important; } }
</style>

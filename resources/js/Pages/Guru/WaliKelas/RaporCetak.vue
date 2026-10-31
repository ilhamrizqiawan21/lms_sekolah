<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

interface ReportMark { mata_pelajaran: string; nilai_akhir: number | null; predikat: string | null; deskripsi: string | null; }
const props = defineProps<{
    waliKelas: { kelas: string; guru: string; back_url: string };
    rapor: { id: number; status: string; siswa: { nama: string; nis: string; kelas: string }; tahun_ajaran: string; semester: string; catatan_wali_kelas: string | null; sakit: number; izin: number; alpha: number; nilai: ReportMark[] };
}>();

onMounted(() => {
    document.title = `Rapor ${props.rapor.siswa.nama}`;
});

function printPage() {
    window.print();
}
</script>

<template>
    <Head :title="`Cetak Rapor — ${rapor.siswa.nama}`" />
    <main class="rapor-print-page">
        <div class="d-print-none d-flex justify-content-between align-items-center mb-4">
            <a :href="waliKelas.back_url" class="btn btn-outline-secondary">Kembali</a>
            <button type="button" class="btn btn-primary" @click="printPage"><i class="bi bi-printer me-1" aria-hidden="true"></i>Cetak</button>
        </div>
        <section class="rapor-sheet">
            <header class="text-center border-bottom pb-3 mb-4"><h1 class="h3 mb-1">LAPORAN HASIL BELAJAR</h1><div>{{ waliKelas.kelas }} · {{ rapor.tahun_ajaran }} · Semester {{ rapor.semester }}</div></header>
            <div class="row mb-4"><div class="col-6"><div><strong>Nama Siswa:</strong> {{ rapor.siswa.nama }}</div><div><strong>NIS:</strong> {{ rapor.siswa.nis }}</div></div><div class="col-6"><div><strong>Kelas:</strong> {{ rapor.siswa.kelas }}</div><div><strong>Wali Kelas:</strong> {{ waliKelas.guru }}</div></div></div>
            <h2 class="h5">A. Nilai Akademik</h2>
            <table class="table table-bordered"><thead><tr><th>No</th><th>Mata Pelajaran</th><th>Nilai Akhir</th><th>Predikat</th><th>Deskripsi</th></tr></thead><tbody><tr v-for="(item, index) in rapor.nilai" :key="item.mata_pelajaran"><td>{{ index + 1 }}</td><td>{{ item.mata_pelajaran }}</td><td>{{ item.nilai_akhir ?? '-' }}</td><td>{{ item.predikat ?? '-' }}</td><td>{{ item.deskripsi ?? '-' }}</td></tr></tbody></table>
            <h2 class="h5 mt-4">B. Kehadiran</h2><table class="table table-bordered w-auto"><tbody><tr><th>Sakit</th><td>{{ rapor.sakit }}</td></tr><tr><th>Izin</th><td>{{ rapor.izin }}</td></tr><tr><th>Alpha</th><td>{{ rapor.alpha }}</td></tr></tbody></table>
            <h2 class="h5 mt-4">C. Catatan Wali Kelas</h2><p class="border rounded p-3" style="min-height: 80px">{{ rapor.catatan_wali_kelas || '-' }}</p>
            <div class="text-end mt-5"><div>{{ waliKelas.kelas }}, ....................</div><div class="mt-5">{{ waliKelas.guru }}</div></div>
        </section>
    </main>
</template>

<style scoped>
.rapor-print-page { max-width: 1000px; margin: 0 auto; padding: 2rem; }
.rapor-sheet { background: #fff; color: #111; padding: 2rem; }
@media print { .rapor-print-page { max-width: none; padding: 0; } .rapor-sheet { padding: 0; } }
</style>

<script setup lang="ts">
import type { PropType } from 'vue';

import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppShell from '../../Layouts/AppShell.vue';
import { Card, EmptyState, TableWrapper } from '../../Components/UI';

const props = defineProps({
    type: { type: String, required: true }, title: { type: String, required: true },
    kelasList: { type: Array as PropType<{ id: number; tingkat: string | number; nama_kelas: string }[]>, default: () => [] }, kelasId: { type: [Number, String], default: null },
    semester: { type: [Number, String], default: 1 }, bulan: { type: String, default: '' }, kelasNama: { type: String, default: '' },
    rekap: { type: Array as PropType<{ nis: string; nama: string; absensi?: Record<string, string>; hadir?: number; sakit?: number; izin?: number; alpha?: number; nilai?: Record<string, number | string | null>; rata?: number | null; spiritual?: Record<string, number | null>; sosial?: Record<string, number | null> }[]>, default: () => [] }, tanggalList: { type: Array as PropType<string[]>, default: () => [] }, mapelList: { type: Array as PropType<{ id: number; kelas_mapel_id: number; nama_mapel: string }[]>, default: () => [] }, tugasList: { type: Array as PropType<{ id: number; kelasMapel?: { mataPelajaran?: { nama_mapel: string }; guru?: { nama_lengkap: string } }; sudah_kumpul: number; total_siswa: number }[]>, default: () => [] },
});
const kelasId = ref(props.kelasId);
const semester = ref(String(props.semester ?? 1));
const bulan = ref(props.bulan || '');
const semuaBulan = ref(props.type === 'absensi' && !props.bulan);

function onToggleSemuaBulan() {
    if (semuaBulan.value) {
        bulan.value = '';
    }
    reload();
}

function reload() {
    const params: Record<string, string | number | undefined> = { kelas_id: kelasId.value || undefined, semester: semester.value || undefined };
    if (props.type === 'absensi') params.bulan = bulan.value || undefined;
    router.get(window.location.pathname, params, { preserveState: true, replace: true });
}

function exportUrl(format: 'excel' | 'pdf') {
    const base = `/admin/export/${props.type}/${format}`;
    const params = new URLSearchParams();
    if (kelasId.value) params.set('kelas_id', String(kelasId.value));
    if (semester.value) params.set('semester', semester.value);
    if (props.type === 'absensi' && bulan.value) params.set('bulan', bulan.value);
    const query = params.toString();
    return query ? `${base}?${query}` : base;
}

const empty = computed(() => props.rekap.length === 0 && props.tugasList.length === 0);
</script>

<template>
    <AppShell :title="title">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><h1 class="h3 mb-1">{{ title }}</h1><p class="text-body-secondary mb-0">Rekap akademik terintegrasi untuk administrasi sekolah.</p></div>
            <div v-if="kelasId || type === 'absensi'" class="d-flex gap-2"><a :href="exportUrl('excel')" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a><a :href="exportUrl('pdf')" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a></div>
        </div>
        <Card class="mb-4"><div class="row g-3 align-items-end">
            <div class="col-md-5"><label class="form-label">Kelas</label><select v-model="kelasId" class="form-select" @change="reload"><option :value="null">{{ type === 'absensi' ? 'Semua Kelas' : 'Pilih kelas' }}</option><option v-for="k in kelasList" :key="k.id" :value="k.id">{{ k.tingkat }} {{ k.nama_kelas }}</option></select></div>
            <div class="col-md-3"><label class="form-label">Semester</label><select v-model="semester" class="form-select" @change="reload"><option value="1">Semester 1</option><option value="2">Semester 2</option></select></div>
            <div v-if="type === 'absensi'" class="col-md-3">
                <label class="form-label">Bulan</label>
                <input v-model="bulan" type="month" class="form-control" :disabled="semuaBulan" @change="reload">
                <div class="form-check mt-1">
                    <input id="admin-rekap-semua-bulan" v-model="semuaBulan" class="form-check-input" type="checkbox" @change="onToggleSemuaBulan">
                    <label class="form-check-label small" for="admin-rekap-semua-bulan">Semua Bulan</label>
                </div>
            </div>
        </div></Card>
        <div v-if="kelasNama" class="alert alert-light border mb-3"><strong>{{ kelasNama }}</strong> · Semester {{ semester }}</div>
        <Card v-if="empty" body-class="py-5"><EmptyState title="Belum ada data untuk filter yang dipilih." icon="bi-inbox" /></Card>
        <Card v-else body-class="p-0"><TableWrapper><table class="table table-hover align-middle mb-0"><thead>
            <tr v-if="type === 'absensi'"><th scope="col">NIS</th><th scope="col">Nama</th><th scope="col" v-for="t in tanggalList" :key="t" class="text-center">{{ t.slice(8) }}</th><th scope="col">H</th><th scope="col">S</th><th scope="col">I</th><th scope="col">A</th></tr>
            <tr v-else-if="type === 'nilai'"><th scope="col">NIS</th><th scope="col">Nama</th><th scope="col" v-for="m in mapelList" :key="m.kelas_mapel_id">{{ m.nama_mapel }}</th><th scope="col">Rata-rata</th></tr>
            <tr v-else-if="type === 'sikap'"><th scope="col">NIS</th><th scope="col">Nama</th><th scope="col">Taqwa</th><th scope="col">Jujur</th><th scope="col">Disiplin</th><th scope="col">Sabar</th><th scope="col">Syukur</th><th scope="col">Tawadhu</th><th scope="col">Empati</th><th scope="col">Kerja Sama</th><th scope="col">Toleransi</th><th scope="col">Percaya Diri</th><th scope="col">Komunikasi</th></tr>
            <tr v-else><th scope="col">Mata Pelajaran</th><th scope="col">Guru</th><th scope="col">Terkumpul</th><th scope="col">Total Siswa</th></tr>
        </thead><tbody>
            <template v-if="type === 'tugas'"><tr v-for="t in tugasList" :key="t.id"><td>{{ t.kelasMapel?.mataPelajaran?.nama_mapel || '-' }}</td><td>{{ t.kelasMapel?.guru?.nama_lengkap || '-' }}</td><td>{{ t.sudah_kumpul }}</td><td>{{ t.total_siswa }}</td></tr></template>
            <template v-else-if="type === 'absensi'"><tr v-for="r in rekap" :key="r.nis"><td>{{ r.nis }}</td><td>{{ r.nama }}</td><td v-for="t in tanggalList" :key="t">{{ r.absensi?.[t] ? r.absensi[t].charAt(0).toUpperCase() : '-' }}</td><td>{{ r.hadir }}</td><td>{{ r.sakit }}</td><td>{{ r.izin }}</td><td>{{ r.alpha }}</td></tr></template>
            <template v-else-if="type === 'nilai'"><tr v-for="r in rekap" :key="r.nis"><td>{{ r.nis }}</td><td>{{ r.nama }}</td><td v-for="m in mapelList" :key="m.kelas_mapel_id">{{ r.nilai?.[m.id] ?? '-' }}</td><td>{{ r.rata ?? '-' }}</td></tr></template>
            <template v-else><tr v-for="r in rekap" :key="r.nis"><td>{{ r.nis }}</td><td>{{ r.nama }}</td><td>{{ r.spiritual?.taqwa || '-' }}</td><td>{{ r.spiritual?.kejujuran || '-' }}</td><td>{{ r.spiritual?.disiplin || '-' }}</td><td>{{ r.spiritual?.sabar || '-' }}</td><td>{{ r.spiritual?.syukur || '-' }}</td><td>{{ r.spiritual?.tawadhu || '-' }}</td><td>{{ r.sosial?.empati || '-' }}</td><td>{{ r.sosial?.kerjasama || '-' }}</td><td>{{ r.sosial?.toleransi || '-' }}</td><td>{{ r.sosial?.percaya_diri || '-' }}</td><td>{{ r.sosial?.komunikasi || '-' }}</td></tr></template>
        </tbody></table></TableWrapper></Card>
    </AppShell>
</template>

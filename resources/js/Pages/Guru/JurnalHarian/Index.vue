<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { SelectInput, TextareaInput, TextInput } from '../../../Components/Form';
import { Badge, Button, Card, EmptyState, IconButton, Modal, TableWrapper } from '../../../Components/UI';

interface ClassOption { id: number; label: string; kelas: string; mata_pelajaran: string; }
interface PlanOption {
    id: number; kelas_mapel_id: number; label: string; tanggal: string;
    pertemuan_ke: number; jp: number; materi_pokok: string | null; tujuan_pembelajaran: string | null;
}
interface Attendance { hadir: number; sakit: number; izin: number; alpha: number; }
interface JournalItem {
    id: number; kelas_mapel_id: number; kelas: string; mata_pelajaran: string; tanggal: string; tanggal_label: string;
    pertemuan_ke: number; jp: number; materi_pokok: string | null; tujuan_pembelajaran: string | null;
    kegiatan_pendahuluan: string | null; kegiatan_inti: string | null; kegiatan_penutup: string | null;
    hasil_pengamatan: string | null; refleksi: string | null; ket_kehadiran: string | null;
    jumlah_siswa: number; attendance: Attendance; status: 'draft' | 'final'; rencana_label: string | null;
    update_url: string; delete_url: string;
}

interface JournalForm {
    kelas_mapel_id: string;
    rencana_pertemuan_id: string;
    tanggal: string;
    pertemuan_ke: string;
    jp: string;
    materi_pokok: string;
    tujuan_pembelajaran: string;
    kegiatan_pendahuluan: string;
    kegiatan_inti: string;
    kegiatan_penutup: string;
    hasil_pengamatan: string;
    refleksi: string;
    ket_kehadiran: string;
    status: 'draft' | 'final';
}

const props = defineProps<{
    jurnal: JournalItem[];
    kelasMapel: ClassOption[];
    rencana: PlanOption[];
    filters: { kelas_mapel_id: string; bulan: string };
    storeUrl: string;
    indexUrl: string;
}>();

const filterForm = ref({ kelas_mapel_id: props.filters.kelas_mapel_id ?? '', bulan: props.filters.bulan ?? '' });
const isModalOpen = ref(false);
const editing = ref<JournalItem | null>(null);
const form = useForm<JournalForm>(blankForm());

function blankForm(): JournalForm {
    return {
        kelas_mapel_id: '', rencana_pertemuan_id: '', tanggal: new Date().toISOString().slice(0, 10),
        pertemuan_ke: '1', jp: '1', materi_pokok: '', tujuan_pembelajaran: '', kegiatan_pendahuluan: '',
        kegiatan_inti: '', kegiatan_penutup: '', hasil_pengamatan: '', refleksi: '', ket_kehadiran: '', status: 'draft',
    };
}

function openCreate() {
    editing.value = null;
    form.defaults(blankForm());
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
}

function openEdit(item: JournalItem) {
    editing.value = item;
    form.clearErrors();
    Object.assign(form, {
        kelas_mapel_id: String(item.kelas_mapel_id), rencana_pertemuan_id: '', tanggal: item.tanggal,
        pertemuan_ke: String(item.pertemuan_ke), jp: String(item.jp), materi_pokok: item.materi_pokok ?? '',
        tujuan_pembelajaran: item.tujuan_pembelajaran ?? '', kegiatan_pendahuluan: item.kegiatan_pendahuluan ?? '',
        kegiatan_inti: item.kegiatan_inti ?? '', kegiatan_penutup: item.kegiatan_penutup ?? '',
        hasil_pengamatan: item.hasil_pengamatan ?? '', refleksi: item.refleksi ?? '', ket_kehadiran: item.ket_kehadiran ?? '',
        status: item.status,
    });
    isModalOpen.value = true;
}

function closeModal() {
    isModalOpen.value = false;
    editing.value = null;
    form.clearErrors();
}

watch(() => form.rencana_pertemuan_id, (value) => {
    if (editing.value || !value) return;
    const plan = props.rencana.find((item) => String(item.id) === String(value));
    if (!plan) return;
    form.kelas_mapel_id = String(plan.kelas_mapel_id);
    form.tanggal = plan.tanggal;
    form.pertemuan_ke = String(plan.pertemuan_ke);
    form.jp = String(plan.jp);
    form.materi_pokok = plan.materi_pokok ?? '';
    form.tujuan_pembelajaran = plan.tujuan_pembelajaran ?? '';
});

function applyFilters() {
    const filters = Object.fromEntries(Object.entries(filterForm.value).filter(([, value]) => value));
    router.get(props.indexUrl, filters, { preserveState: true, preserveScroll: true, replace: true });
}

function save() {
    const options = { preserveScroll: true, onSuccess: closeModal };
    if (editing.value) form.put(editing.value.update_url, options);
    else form.post(props.storeUrl, options);
}

function destroy(item: JournalItem) {
    if (item.status === 'final' || !window.confirm(`Hapus draft jurnal ${item.tanggal_label}?`)) return;
    router.delete(item.delete_url, { preserveScroll: true });
}

function statusColor(status: string): string { return status === 'final' ? 'success' : 'secondary'; }
</script>

<template>
    <Head title="Jurnal Harian" />
    <AppShell title="Jurnal Harian">
        <PageHeader eyebrow="Dokumentasi Pembelajaran" title="Jurnal Harian Guru" subtitle="Catat pelaksanaan pembelajaran dan pantau kehadiran dari satu halaman.">
            <template #actions><Button color="primary" icon="bi-plus-lg" @click="openCreate">Tambah Jurnal</Button></template>
        </PageHeader>

        <Card class="mb-4" title="Filter Jurnal" icon="bi-funnel-fill">
            <form class="row g-3 align-items-end" @submit.prevent="applyFilters">
                <div class="col-md-6"><SelectInput v-model="filterForm.kelas_mapel_id" name="kelas_mapel_id" label="Kelas dan Mata Pelajaran" placeholder="Semua kelas" :options="kelasMapel.map((item) => ({ value: String(item.id), label: item.label }))" wrapper-class="mb-0" /></div>
                <div class="col-md-3"><TextInput v-model="filterForm.bulan" type="month" name="bulan" label="Bulan" wrapper-class="mb-0" /></div>
                <div class="col-md-3 d-grid"><Button type="submit" color="primary" icon="bi-search">Tampilkan</Button></div>
            </form>
        </Card>

        <Card v-if="jurnal.length" title="Riwayat Jurnal" icon="bi-journal-text" body-class="p-0">
            <TableWrapper stack>
                <table class="table table-hover mb-0 app-table-proportional">
                    <thead><tr><th>Tanggal</th><th>Kelas & Mapel</th><th>Pertemuan</th><th>Materi</th><th>Kehadiran</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <tr v-for="item in jurnal" :key="item.id">
                            <td data-label="Tanggal">{{ item.tanggal_label }}</td>
                            <td data-label="Kelas & Mapel"><div class="fw-semibold">{{ item.kelas }}</div><div class="small text-body-secondary">{{ item.mata_pelajaran }}</div></td>
                            <td data-label="Pertemuan">Ke-{{ item.pertemuan_ke }} · {{ item.jp }} JP</td>
                            <td data-label="Materi" class="stack-title"><div class="text-break">{{ item.materi_pokok || '-' }}</div><div v-if="item.rencana_label" class="small text-body-secondary text-break">{{ item.rencana_label }}</div></td>
                            <td data-label="Kehadiran"><div class="d-flex flex-wrap gap-1"><Badge color="success">H {{ item.attendance.hadir }}</Badge><Badge color="warning text-dark">S {{ item.attendance.sakit }}</Badge><Badge color="info">I {{ item.attendance.izin }}</Badge><Badge color="danger">A {{ item.attendance.alpha }}</Badge></div><div class="small text-body-secondary mt-1">{{ item.jumlah_siswa }} siswa</div></td>
                            <td data-label="Status"><Badge :color="statusColor(item.status)">{{ item.status === 'final' ? 'Final' : 'Draft' }}</Badge></td>
                            <td class="stack-actions"><div class="d-flex gap-1"><IconButton icon="bi-pencil-square" color="outline-primary" label="Edit jurnal" @click="openEdit(item)" /><IconButton v-if="item.status === 'draft'" icon="bi-trash" color="outline-danger" label="Hapus draft" @click="destroy(item)" /></div></td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>
        </Card>
        <EmptyState v-else title="Belum ada jurnal" message="Buat jurnal dari rencana pertemuan Promes atau isi secara manual." icon="bi-journal-text"><template #action><Button color="primary" icon="bi-plus-lg" @click="openCreate">Tambah Jurnal</Button></template></EmptyState>

        <Modal :model-value="isModalOpen" :title="editing ? 'Edit Jurnal Harian' : 'Tambah Jurnal Harian'" size="lg" @update:model-value="closeModal">
            <form id="jurnalForm" @submit.prevent="save">
                <div class="row g-3">
                    <div class="col-md-6"><SelectInput v-model="form.kelas_mapel_id" name="kelas_mapel_id" label="Kelas dan Mata Pelajaran" required :options="kelasMapel.map((item) => ({ value: String(item.id), label: item.label }))" :error="form.errors.kelas_mapel_id" :disabled="!!editing" /></div>
                    <div class="col-md-6"><SelectInput v-model="form.rencana_pertemuan_id" name="rencana_pertemuan_id" label="Ambil dari Rencana Pertemuan (opsional)" placeholder="Isi manual" :options="rencana.filter((item) => !form.kelas_mapel_id || String(item.kelas_mapel_id) === String(form.kelas_mapel_id)).map((item) => ({ value: String(item.id), label: item.label }))" :error="form.errors.rencana_pertemuan_id" :disabled="!!editing" /></div>
                    <div class="col-md-4"><TextInput v-model="form.tanggal" name="tanggal" type="date" label="Tanggal" required :error="form.errors.tanggal" /></div>
                    <div class="col-md-4"><TextInput v-model="form.pertemuan_ke" name="pertemuan_ke" type="number" min="1" label="Pertemuan ke-" required :error="form.errors.pertemuan_ke" /></div>
                    <div class="col-md-4"><TextInput v-model="form.jp" name="jp" type="number" min="1" max="40" label="JP" required :error="form.errors.jp" /></div>
                    <div class="col-md-6"><TextareaInput v-model="form.materi_pokok" name="materi_pokok" label="Materi Pokok" :rows="3" :error="form.errors.materi_pokok" /></div>
                    <div class="col-md-6"><TextareaInput v-model="form.tujuan_pembelajaran" name="tujuan_pembelajaran" label="Tujuan Pembelajaran" :rows="3" :error="form.errors.tujuan_pembelajaran" /></div>
                    <div class="col-md-4"><TextareaInput v-model="form.kegiatan_pendahuluan" name="kegiatan_pendahuluan" label="Kegiatan Pendahuluan" :rows="3" :error="form.errors.kegiatan_pendahuluan" /></div>
                    <div class="col-md-4"><TextareaInput v-model="form.kegiatan_inti" name="kegiatan_inti" label="Kegiatan Inti" :rows="3" :error="form.errors.kegiatan_inti" /></div>
                    <div class="col-md-4"><TextareaInput v-model="form.kegiatan_penutup" name="kegiatan_penutup" label="Kegiatan Penutup" :rows="3" :error="form.errors.kegiatan_penutup" /></div>
                    <div class="col-md-6"><TextareaInput v-model="form.hasil_pengamatan" name="hasil_pengamatan" label="Hasil Pengamatan" :rows="3" :error="form.errors.hasil_pengamatan" /></div>
                    <div class="col-md-6"><TextareaInput v-model="form.refleksi" name="refleksi" label="Refleksi Guru" :rows="3" :error="form.errors.refleksi" /></div>
                    <div class="col-md-8"><TextareaInput v-model="form.ket_kehadiran" name="ket_kehadiran" label="Catatan Kehadiran" :rows="2" :error="form.errors.ket_kehadiran" /></div>
                    <div class="col-md-4"><SelectInput v-model="form.status" name="status" label="Status" required :options="[{ value: 'draft', label: 'Draft' }, { value: 'final', label: 'Final' }]" :error="form.errors.status" /></div>
                </div>
            </form>
            <template #footer><div class="d-flex justify-content-end gap-2"><Button color="outline-secondary" @click="closeModal">Batal</Button><Button type="submit" form="jurnalForm" color="primary" :loading="form.processing">Simpan Jurnal</Button></div></template>
        </Modal>
    </AppShell>
</template>

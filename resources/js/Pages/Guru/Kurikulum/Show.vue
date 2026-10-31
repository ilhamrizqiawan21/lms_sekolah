<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { SelectInput, TextareaInput, TextInput } from '../../../Components/Form';
import { Badge, Button, Card, EmptyState, IconButton, Modal, TableWrapper } from '../../../Components/UI';

interface CpItem {
    id: number;
    kode: string | null;
    elemen: string | null;
    deskripsi: string;
    urutan: number;
    update_url: string;
    delete_url: string;
}

interface TpItem {
    id: number;
    capaian_pembelajaran_id: number | null;
    capaian_kode: string | null;
    kode: string | null;
    urutan: number;
    tujuan: string;
    materi_pokok: string | null;
    indikator_ketercapaian: string | null;
    alokasi_jp: number;
    estimasi_pertemuan: number;
    bentuk_asesmen: string | null;
    update_url: string;
    delete_url: string;
}

interface KurikulumDetail {
    id: number;
    nama: string;
    jenis_kurikulum: string;
    fase: string | null;
    semester: string;
    status: string;
    deskripsi: string | null;
    mata_pelajaran: string;
    tahun_ajaran: string;
    kelas_mapel: { id: number; label: string }[];
    capaian_pembelajaran: CpItem[];
    tujuan_pembelajaran: TpItem[];
}

const props = defineProps<{
    kurikulum: KurikulumDetail;
    updateUrl: string;
    deleteUrl: string;
    cpStoreUrl: string;
    tpStoreUrl: string;
    promesUrl: string;
}>();

const isCpModalOpen = ref(false);
const isTpModalOpen = ref(false);
const editingCp = ref<CpItem | null>(null);
const editingTp = ref<TpItem | null>(null);

const cpForm = useForm({
    kode: '',
    elemen: '',
    deskripsi: '',
    urutan: 1,
});

const tpForm = useForm({
    capaian_pembelajaran_id: '' as number | string,
    kode: '',
    urutan: 1,
    tujuan: '',
    materi_pokok: '',
    indikator_ketercapaian: '',
    alokasi_jp: 0,
    estimasi_pertemuan: 1,
    bentuk_asesmen: '',
});

const cpOptions = () => props.kurikulum.capaian_pembelajaran.map((item) => ({
    value: item.id,
    label: (item.kode ? item.kode + ' — ' : '') + 'CP ' + item.urutan,
}));

function openCreateCp() {
    editingCp.value = null;
    cpForm.reset();
    cpForm.clearErrors();
    cpForm.urutan = props.kurikulum.capaian_pembelajaran.length + 1;
    isCpModalOpen.value = true;
}

function openEditCp(item: CpItem) {
    editingCp.value = item;
    cpForm.clearErrors();
    cpForm.kode = item.kode ?? '';
    cpForm.elemen = item.elemen ?? '';
    cpForm.deskripsi = item.deskripsi;
    cpForm.urutan = item.urutan;
    isCpModalOpen.value = true;
}

function closeCpModal() {
    isCpModalOpen.value = false;
    editingCp.value = null;
    cpForm.clearErrors();
}

function saveCp() {
    const options = { preserveScroll: true, onSuccess: () => closeCpModal() };
    if (editingCp.value) {
        cpForm.put(editingCp.value.update_url, options);
    } else {
        cpForm.post(props.cpStoreUrl, options);
    }
}

function destroyCp(item: CpItem) {
    if (!window.confirm('Hapus CP ' + (item.kode || item.urutan) + '? TP yang terkait akan tetap tersimpan tanpa tautan CP.')) {
        return;
    }
    router.delete(item.delete_url, { preserveScroll: true });
}

function openCreateTp() {
    editingTp.value = null;
    tpForm.reset();
    tpForm.clearErrors();
    tpForm.urutan = props.kurikulum.tujuan_pembelajaran.length + 1;
    tpForm.estimasi_pertemuan = 1;
    isTpModalOpen.value = true;
}

function openEditTp(item: TpItem) {
    editingTp.value = item;
    tpForm.clearErrors();
    tpForm.capaian_pembelajaran_id = item.capaian_pembelajaran_id ?? '';
    tpForm.kode = item.kode ?? '';
    tpForm.urutan = item.urutan;
    tpForm.tujuan = item.tujuan;
    tpForm.materi_pokok = item.materi_pokok ?? '';
    tpForm.indikator_ketercapaian = item.indikator_ketercapaian ?? '';
    tpForm.alokasi_jp = item.alokasi_jp;
    tpForm.estimasi_pertemuan = item.estimasi_pertemuan;
    tpForm.bentuk_asesmen = item.bentuk_asesmen ?? '';
    isTpModalOpen.value = true;
}

function closeTpModal() {
    isTpModalOpen.value = false;
    editingTp.value = null;
    tpForm.clearErrors();
}

function saveTp() {
    const options = { preserveScroll: true, onSuccess: () => closeTpModal() };
    if (editingTp.value) {
        tpForm.put(editingTp.value.update_url, options);
    } else {
        tpForm.post(props.tpStoreUrl, options);
    }
}

function destroyTp(item: TpItem) {
    if (!window.confirm('Hapus tujuan pembelajaran ' + (item.kode || item.urutan) + '?')) {
        return;
    }
    router.delete(item.delete_url, { preserveScroll: true });
}

function destroyKurikulum() {
    if (!window.confirm('Hapus kurikulum ini beserta CP dan TP di dalamnya?')) {
        return;
    }
    router.delete(props.deleteUrl);
}

function statusColor(status: string): string {
    return {
        draft: 'secondary',
        diajukan: 'warning text-dark',
        disetujui: 'info',
        aktif: 'success',
        arsip: 'dark',
    }[status] ?? 'secondary';
}
</script>

<template>
    <Head :title="'Kurikulum — ' + kurikulum.nama" />

    <AppShell title="Detail Kurikulum">
        <PageHeader
            eyebrow="Perencanaan Pembelajaran"
            :title="kurikulum.nama"
            :subtitle="kurikulum.mata_pelajaran + ' · ' + kurikulum.tahun_ajaran + ' · Semester ' + kurikulum.semester"
            icon="bi-journal-bookmark-fill"
        >
            <template #actions>
                <Link href="/guru/kurikulum" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali
                </Link>
            </template>
        </PageHeader>

        <Card class="mb-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <Badge :color="statusColor(kurikulum.status)">{{ kurikulum.status }}</Badge>
                        <Badge color="primary">{{ kurikulum.jenis_kurikulum }}</Badge>
                        <Badge v-if="kurikulum.fase" color="info">Fase {{ kurikulum.fase }}</Badge>
                    </div>
                    <p v-if="kurikulum.deskripsi" class="mb-2 text-body-secondary">{{ kurikulum.deskripsi }}</p>
                    <div class="small text-body-secondary">Kelas: {{ kurikulum.kelas_mapel.map((item) => item.label).join(', ') || '-' }}</div>
                </div>
                <div class="d-flex gap-2">
                    <Link :href="promesUrl" class="btn btn-primary">
                        <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Promes
                    </Link>
                    <Button color="outline-danger" @click="destroyKurikulum">
                        <i class="bi bi-trash me-1" aria-hidden="true"></i>Hapus
                    </Button>
                </div>
            </div>
        </Card>

        <Card class="mb-4" body-class="p-0">
            <template #header>
                <div class="d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="h5 mb-1"><i class="bi bi-bullseye me-2" aria-hidden="true"></i>Capaian Pembelajaran</h2>
                        <p class="small text-body-secondary mb-0">Definisikan capaian utama yang menjadi dasar penyusunan TP.</p>
                    </div>
                    <Button color="primary" size="sm" icon="bi-plus-lg" @click="openCreateCp">Tambah CP</Button>
                </div>
            </template>
            <TableWrapper stack v-if="kurikulum.capaian_pembelajaran.length">
                <table class="table table-hover mb-0 app-table-proportional">
                    <thead><tr><th>No</th><th>Kode / Elemen</th><th>Deskripsi</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <tr v-for="item in kurikulum.capaian_pembelajaran" :key="item.id">
                            <td data-label="No">{{ item.urutan }}</td>
                            <td data-label="Kode / Elemen">
                                <div class="fw-semibold">{{ item.kode || 'CP ' + item.urutan }}</div>
                                <div class="small text-body-secondary">{{ item.elemen || '-' }}</div>
                            </td>
                            <td data-label="Deskripsi" class="text-break">{{ item.deskripsi }}</td>
                            <td class="stack-actions">
                                <div class="d-flex gap-1">
                                    <IconButton icon="bi-pencil-square" color="outline-primary" label="Edit CP" @click="openEditCp(item)" />
                                    <IconButton icon="bi-trash" color="outline-danger" label="Hapus CP" @click="destroyCp(item)" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>
            <EmptyState v-else title="Belum ada Capaian Pembelajaran" message="Tambahkan CP sebelum menyusun Tujuan Pembelajaran." icon="bi-bullseye" />
        </Card>

        <Card body-class="p-0">
            <template #header>
                <div class="d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="h5 mb-1"><i class="bi bi-list-ol me-2" aria-hidden="true"></i>Tujuan Pembelajaran / ATP</h2>
                        <p class="small text-body-secondary mb-0">Susun urutan tujuan, materi, alokasi JP, dan bentuk asesmen.</p>
                    </div>
                    <Button color="primary" size="sm" icon="bi-plus-lg" @click="openCreateTp">Tambah TP</Button>
                </div>
            </template>
            <TableWrapper stack v-if="kurikulum.tujuan_pembelajaran.length">
                <table class="table table-hover mb-0 app-table-proportional">
                    <thead><tr><th>No</th><th>Tujuan & Materi</th><th>CP</th><th>Alokasi</th><th>Asesmen</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <tr v-for="item in kurikulum.tujuan_pembelajaran" :key="item.id">
                            <td data-label="No">{{ item.urutan }}</td>
                            <td data-label="Tujuan & Materi" class="stack-title">
                                <div class="fw-semibold">{{ item.kode || 'TP ' + item.urutan }} — {{ item.tujuan }}</div>
                                <div v-if="item.materi_pokok" class="small text-body-secondary mt-1">Materi: {{ item.materi_pokok }}</div>
                            </td>
                            <td data-label="CP"><Badge v-if="item.capaian_kode" color="info">{{ item.capaian_kode }}</Badge><span v-else class="text-body-secondary">-</span></td>
                            <td data-label="Alokasi"><div>{{ item.alokasi_jp }} JP</div><div class="small text-body-secondary">{{ item.estimasi_pertemuan }} pertemuan</div></td>
                            <td data-label="Asesmen">{{ item.bentuk_asesmen || '-' }}</td>
                            <td class="stack-actions">
                                <div class="d-flex gap-1">
                                    <IconButton icon="bi-pencil-square" color="outline-primary" label="Edit TP" @click="openEditTp(item)" />
                                    <IconButton icon="bi-trash" color="outline-danger" label="Hapus TP" @click="destroyTp(item)" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>
            <EmptyState v-else title="Belum ada Tujuan Pembelajaran" message="Tambahkan TP untuk mulai membangun ATP." icon="bi-list-ol" />
        </Card>

        <Modal :model-value="isCpModalOpen" :title="editingCp ? 'Edit Capaian Pembelajaran' : 'Tambah Capaian Pembelajaran'" size="lg" @update:model-value="closeCpModal">
            <form id="cpForm" @submit.prevent="saveCp">
                <div class="row g-3">
                    <div class="col-md-4"><TextInput v-model="cpForm.kode" name="kode" label="Kode CP (Opsional)" placeholder="Contoh: CP-1" :error="cpForm.errors.kode" /></div>
                    <div class="col-md-8"><TextInput v-model="cpForm.elemen" name="elemen" label="Elemen (Opsional)" placeholder="Contoh: Al-Qur'an, Bilangan, Literasi" :error="cpForm.errors.elemen" /></div>
                    <div class="col-md-3"><TextInput v-model="cpForm.urutan" name="urutan" type="number" min="1" label="Urutan" required :error="cpForm.errors.urutan" /></div>
                    <div class="col-md-9"><TextareaInput v-model="cpForm.deskripsi" name="deskripsi" label="Deskripsi CP" placeholder="Tuliskan capaian pembelajaran..." :rows="4" required :error="cpForm.errors.deskripsi" /></div>
                </div>
            </form>
            <template #footer>
                <Button type="button" color="outline-secondary" @click="closeCpModal">Batal</Button>
                <Button type="submit" form="cpForm" color="primary" :disabled="cpForm.processing">{{ cpForm.processing ? 'Menyimpan...' : 'Simpan CP' }}</Button>
            </template>
        </Modal>

        <Modal :model-value="isTpModalOpen" :title="editingTp ? 'Edit Tujuan Pembelajaran' : 'Tambah Tujuan Pembelajaran'" size="lg" @update:model-value="closeTpModal">
            <form id="tpForm" @submit.prevent="saveTp">
                <div class="row g-3">
                    <div class="col-md-3"><TextInput v-model="tpForm.kode" name="kode" label="Kode TP (Opsional)" placeholder="TP-1" :error="tpForm.errors.kode" /></div>
                    <div class="col-md-3"><TextInput v-model="tpForm.urutan" name="urutan" type="number" min="1" label="Urutan" required :error="tpForm.errors.urutan" /></div>
                    <div class="col-md-6"><SelectInput v-model="tpForm.capaian_pembelajaran_id" name="capaian_pembelajaran_id" label="Capaian Pembelajaran" placeholder="Tanpa tautan CP" :options="cpOptions()" :error="tpForm.errors.capaian_pembelajaran_id" wrapper-class="" /></div>
                    <div class="col-12"><TextareaInput v-model="tpForm.tujuan" name="tujuan" label="Tujuan Pembelajaran" placeholder="Peserta didik mampu..." :rows="3" required :error="tpForm.errors.tujuan" /></div>
                    <div class="col-md-6"><TextareaInput v-model="tpForm.materi_pokok" name="materi_pokok" label="Materi Pokok" :rows="3" :error="tpForm.errors.materi_pokok" /></div>
                    <div class="col-md-6"><TextareaInput v-model="tpForm.indikator_ketercapaian" name="indikator_ketercapaian" label="Indikator Ketercapaian" :rows="3" :error="tpForm.errors.indikator_ketercapaian" /></div>
                    <div class="col-md-4"><TextInput v-model="tpForm.alokasi_jp" name="alokasi_jp" type="number" min="0" label="Alokasi JP" required :error="tpForm.errors.alokasi_jp" /></div>
                    <div class="col-md-4"><TextInput v-model="tpForm.estimasi_pertemuan" name="estimasi_pertemuan" type="number" min="1" label="Estimasi Pertemuan" required :error="tpForm.errors.estimasi_pertemuan" /></div>
                    <div class="col-md-4"><TextInput v-model="tpForm.bentuk_asesmen" name="bentuk_asesmen" label="Bentuk Asesmen" placeholder="Tugas, praktik, sumatif..." :error="tpForm.errors.bentuk_asesmen" /></div>
                </div>
            </form>
            <template #footer>
                <Button type="button" color="outline-secondary" @click="closeTpModal">Batal</Button>
                <Button type="submit" form="tpForm" color="primary" :disabled="tpForm.processing">{{ tpForm.processing ? 'Menyimpan...' : 'Simpan TP' }}</Button>
            </template>
        </Modal>
    </AppShell>
</template>

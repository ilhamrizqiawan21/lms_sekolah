<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { SelectInput, TextareaInput, TextInput } from '../../../Components/Form';
import { Badge, Button, Card, EmptyState, IconButton, Modal, TableWrapper } from '../../../Components/UI';

interface ClassItem {
    id: number;
    label: string;
    kelas: string;
    rencana_count: number;
}

interface TpOption {
    id: number;
    kode: string | null;
    urutan: number;
    tujuan: string;
    alokasi_jp: number;
}

interface PlanItem {
    id: number;
    kelas_mapel_id: number;
    kelas: string;
    mata_pelajaran: string;
    pertemuan_ke: number;
    tanggal_rencana: string;
    tanggal_label: string;
    tujuan_pembelajaran_id: number | null;
    tp_label: string;
    jp: number;
    status: string;
    tanggal_realisasi: string | null;
    catatan: string | null;
    update_url: string;
    delete_url: string;
}

const props = defineProps<{
    kurikulum: {
        id: number;
        nama: string;
        mata_pelajaran: string;
        tahun_ajaran: string;
        semester: string;
        kelas_mapel: ClassItem[];
        tujuan_pembelajaran: TpOption[];
    };
    rencana: PlanItem[];
    summary: { total: number; jp: number; direncanakan: number; terlaksana: number };
    defaultPeriod: { start: string; end: string };
    generateUrl: string;
    packageUrl: string;
}>();

const statusOptions = [
    { value: 'direncanakan', label: 'Direncanakan' },
    { value: 'terlaksana', label: 'Terlaksana' },
    { value: 'ditunda', label: 'Ditunda' },
    { value: 'digeser', label: 'Digeser' },
];

const generateForm = useForm({
    start_date: props.defaultPeriod.start,
    end_date: props.defaultPeriod.end,
    jp_per_pertemuan: 2,
    kelas_mapel_ids: props.kurikulum.kelas_mapel.map((item) => item.id),
    replace: false,
});
const generateErrors = generateForm.errors as Record<string, string | undefined>;

const isEditOpen = ref(false);
const editing = ref<PlanItem | null>(null);
const editForm = useForm({
    tanggal_rencana: '',
    tujuan_pembelajaran_id: '' as number | string,
    jp: 1,
    status: 'direncanakan',
    tanggal_realisasi: '',
    catatan: '',
});

function toggleClass(id: number, checked: boolean) {
    if (checked && !generateForm.kelas_mapel_ids.includes(id)) {
        generateForm.kelas_mapel_ids.push(id);
    } else if (!checked) {
        generateForm.kelas_mapel_ids = generateForm.kelas_mapel_ids.filter((item) => item !== id);
    }
}

function generate() {
    generateForm.post(props.generateUrl, { preserveScroll: true });
}

function openEdit(item: PlanItem) {
    editing.value = item;
    editForm.clearErrors();
    editForm.tanggal_rencana = item.tanggal_rencana;
    editForm.tujuan_pembelajaran_id = item.tujuan_pembelajaran_id ?? '';
    editForm.jp = item.jp;
    editForm.status = item.status;
    editForm.tanggal_realisasi = item.tanggal_realisasi ?? '';
    editForm.catatan = item.catatan ?? '';
    isEditOpen.value = true;
}

function closeEdit() {
    isEditOpen.value = false;
    editing.value = null;
    editForm.clearErrors();
}

function saveEdit() {
    if (!editing.value) {
        return;
    }
    editForm.put(editing.value.update_url, {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
}

function destroy(item: PlanItem) {
    if (!window.confirm('Hapus rencana pertemuan ' + item.pertemuan_ke + ' untuk kelas ' + item.kelas + '?')) {
        return;
    }
    router.delete(item.delete_url, { preserveScroll: true });
}

function downloadPackage(item: ClassItem) {
    const query = new URLSearchParams({ kelas_mapel_id: String(item.id) });
    window.location.href = `${props.packageUrl}?${query.toString()}`;
}

function statusColor(status: string): string {
    return {
        direncanakan: 'secondary',
        terlaksana: 'success',
        ditunda: 'warning text-dark',
        digeser: 'info',
    }[status] ?? 'secondary';
}
</script>

<template>
    <Head :title="'Promes — ' + kurikulum.nama" />

    <AppShell title="Promes">
        <PageHeader
            eyebrow="Perencanaan Pembelajaran"
            title="Promes & Rencana Pertemuan"
            :subtitle="kurikulum.nama + ' · ' + kurikulum.mata_pelajaran + ' · Semester ' + kurikulum.semester"
            icon="bi-calendar3"
        >
            <template #actions>
                <Link :href="'/guru/kurikulum/' + kurikulum.id" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke ATP
                </Link>
            </template>
        </PageHeader>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><Card><div class="small text-body-secondary">Total Pertemuan</div><div class="fs-3 fw-bold">{{ summary.total }}</div></Card></div>
            <div class="col-md-3"><Card><div class="small text-body-secondary">Total JP</div><div class="fs-3 fw-bold">{{ summary.jp }}</div></Card></div>
            <div class="col-md-3"><Card><div class="small text-body-secondary">Direncanakan</div><div class="fs-3 fw-bold text-secondary">{{ summary.direncanakan }}</div></Card></div>
            <div class="col-md-3"><Card><div class="small text-body-secondary">Terlaksana</div><div class="fs-3 fw-bold text-success">{{ summary.terlaksana }}</div></Card></div>
        </div>

        <Card class="mb-4" title="Buat Rencana Otomatis" icon="bi-magic">
            <p class="small text-body-secondary">Tanggal akan mengikuti jadwal mengajar kelas-mapel dan melewati hari libur sekolah. Alokasi TP diproses sesuai urutan dan alokasi JP.</p>
            <form @submit.prevent="generate">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3"><TextInput v-model="generateForm.start_date" name="start_date" type="date" label="Mulai" required :error="generateForm.errors.start_date" /></div>
                    <div class="col-md-3"><TextInput v-model="generateForm.end_date" name="end_date" type="date" label="Selesai" required :error="generateForm.errors.end_date" /></div>
                    <div class="col-md-3"><TextInput v-model="generateForm.jp_per_pertemuan" name="jp_per_pertemuan" type="number" min="1" max="40" label="JP per Pertemuan" required :error="generateForm.errors.jp_per_pertemuan" /></div>
                    <div class="col-md-3"><Button type="submit" color="primary" class="w-100" :disabled="generateForm.processing || !generateForm.kelas_mapel_ids.length"><i class="bi bi-magic me-1" aria-hidden="true"></i>{{ generateForm.processing ? 'Membuat...' : 'Buat Rencana' }}</Button></div>
                </div>
                <div v-if="generateErrors.rencana" class="alert alert-danger py-2 small mt-3 mb-0">{{ generateErrors.rencana }}</div>
                <div class="form-check mt-3">
                    <input id="replaceDraft" v-model="generateForm.replace" class="form-check-input" type="checkbox">
                    <label for="replaceDraft" class="form-check-label small">Ganti rencana berstatus draft yang sudah ada</label>
                </div>
            </form>
        </Card>

        <Card class="mb-4" title="Kelas yang Menggunakan ATP" icon="bi-people-fill">
            <div class="row g-2">
                <div v-for="item in kurikulum.kelas_mapel" :key="item.id" class="col-md-6">
                    <label class="border rounded p-2 d-flex align-items-start gap-2 h-100">
                        <input class="form-check-input mt-1" type="checkbox" :checked="generateForm.kelas_mapel_ids.includes(item.id)" @change="toggleClass(item.id, ($event.target as HTMLInputElement).checked)">
                        <span class="flex-grow-1"><span class="d-block fw-semibold">{{ item.label }}</span><span class="small text-body-secondary">{{ item.rencana_count }} rencana tersimpan</span></span>
                        <Button type="button" color="outline-primary" size="sm" icon="bi-file-earmark-zip" @click.prevent="downloadPackage(item)">Unduh Paket</Button>
                    </label>
                </div>
            </div>
            <div v-if="generateForm.errors.kelas_mapel_ids" class="alert alert-danger py-2 small mt-3 mb-0">{{ generateForm.errors.kelas_mapel_ids }}</div>
        </Card>

        <Card v-if="rencana.length" title="Daftar Rencana Pertemuan" icon="bi-list-check" body-class="p-0">
            <TableWrapper stack>
                <table class="table table-hover mb-0 app-table-proportional">
                    <thead><tr><th>Tanggal</th><th>Kelas</th><th>Pertemuan</th><th>Tujuan Pembelajaran</th><th>JP</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <tr v-for="item in rencana" :key="item.id">
                            <td data-label="Tanggal">{{ item.tanggal_label }}</td>
                            <td data-label="Kelas">{{ item.kelas }}</td>
                            <td data-label="Pertemuan">Pertemuan {{ item.pertemuan_ke }}</td>
                            <td data-label="Tujuan Pembelajaran" class="stack-title">
                                <div class="fw-semibold text-break">{{ item.tp_label }}</div>
                                <div v-if="item.catatan" class="small text-body-secondary">{{ item.catatan }}</div>
                            </td>
                            <td data-label="JP">{{ item.jp }} JP</td>
                            <td data-label="Status"><Badge :color="statusColor(item.status)">{{ item.status }}</Badge></td>
                            <td class="stack-actions">
                                <div class="d-flex gap-1">
                                    <IconButton icon="bi-pencil-square" color="outline-primary" label="Edit rencana" @click="openEdit(item)" />
                                    <IconButton v-if="item.status === 'direncanakan'" icon="bi-trash" color="outline-danger" label="Hapus rencana" @click="destroy(item)" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>
        </Card>
        <EmptyState v-else title="Belum ada rencana pertemuan" message="Pilih kelas-mapel, periode, dan JP per pertemuan lalu buat rencana otomatis." icon="bi-calendar-plus" />

        <Modal :model-value="isEditOpen" title="Edit Rencana Pertemuan" size="lg" @update:model-value="closeEdit">
            <form id="rencanaEditForm" @submit.prevent="saveEdit">
                <div class="row g-3">
                    <div class="col-md-4"><TextInput v-model="editForm.tanggal_rencana" name="tanggal_rencana" type="date" label="Tanggal Rencana" required :error="editForm.errors.tanggal_rencana" /></div>
                    <div class="col-md-4"><TextInput v-model="editForm.jp" name="jp" type="number" min="1" max="40" label="JP" required :error="editForm.errors.jp" /></div>
                    <div class="col-md-4"><SelectInput v-model="editForm.status" name="status" label="Status" required :options="statusOptions" :error="editForm.errors.status" wrapper-class="" /></div>
                    <div class="col-md-8"><SelectInput v-model="editForm.tujuan_pembelajaran_id" name="tujuan_pembelajaran_id" label="Tujuan Pembelajaran" required :options="kurikulum.tujuan_pembelajaran.map((item) => ({ value: item.id, label: (item.kode ? item.kode + ' — ' : '') + item.tujuan }))" :error="editForm.errors.tujuan_pembelajaran_id" wrapper-class="" /></div>
                    <div class="col-md-4"><TextInput v-model="editForm.tanggal_realisasi" name="tanggal_realisasi" type="date" label="Tanggal Realisasi" :error="editForm.errors.tanggal_realisasi" /></div>
                    <div class="col-12"><TextareaInput v-model="editForm.catatan" name="catatan" label="Catatan" :rows="3" :error="editForm.errors.catatan" /></div>
                </div>
            </form>
            <template #footer>
                <Button type="button" color="outline-secondary" @click="closeEdit">Batal</Button>
                <Button type="submit" form="rencanaEditForm" color="primary" :disabled="editForm.processing">{{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}</Button>
            </template>
        </Modal>
    </AppShell>
</template>

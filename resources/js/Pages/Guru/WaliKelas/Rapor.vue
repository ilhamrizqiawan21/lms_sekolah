<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { SelectInput, TextareaInput } from '../../../Components/Form';
import { Badge, Button, Card, EmptyState, Modal, TableWrapper } from '../../../Components/UI';

interface ReportMark { id: number; mata_pelajaran: string; nilai_akhir: number | null; predikat: string | null; deskripsi: string | null; }
interface Report { id: number; status: 'draft' | 'final'; catatan_wali_kelas: string | null; sakit: number; izin: number; alpha: number; nilai: ReportMark[]; update_url: string; print_url: string; }
interface Student { id: number; nis: string; nama: string; status: 'belum' | 'draft' | 'final'; sakit: number; izin: number; alpha: number; rapor: Report | null; }
interface EditForm { catatan_wali_kelas: string; status: 'draft' | 'final'; nilai: Record<string, { deskripsi: string }>; }

const props = defineProps<{
    waliKelas: { id: number; kelas: string; tahun_ajaran: string };
    semester: string;
    students: Student[];
    generateUrl: string;
    indexUrl: string;
}>();

const semesterForm = useForm({ semester: props.semester });
const editForm = useForm<EditForm>({ catatan_wali_kelas: '', status: 'draft', nilai: {} });
const semesterErrors = semesterForm.errors as Record<string, string | undefined>;
const editErrors = editForm.errors as Record<string, string | undefined>;
const editing = ref<Student | null>(null);
const isModalOpen = ref(false);

function generate() {
    semesterForm.post(props.generateUrl, { preserveScroll: true });
}

function changeSemester() {
    router.get(props.indexUrl, { semester: semesterForm.semester }, { preserveState: true, replace: true });
}

function openEdit(student: Student) {
    if (!student.rapor || student.rapor.status === 'final') return;
    editing.value = student;
    editForm.clearErrors();
    editForm.catatan_wali_kelas = student.rapor.catatan_wali_kelas ?? '';
    editForm.status = student.rapor.status;
    editForm.nilai = Object.fromEntries(student.rapor.nilai.map((item) => [String(item.id), { deskripsi: item.deskripsi ?? '' }]));
    isModalOpen.value = true;
}

function closeModal() {
    isModalOpen.value = false;
    editing.value = null;
    editForm.clearErrors();
}

function save() {
    if (!editing.value?.rapor) return;
    editForm.put(editing.value.rapor.update_url, { preserveScroll: true, onSuccess: closeModal });
}

function statusColor(status: string): string { return { final: 'success', draft: 'warning text-dark', belum: 'secondary' }[status] ?? 'secondary'; }
function statusLabel(status: string): string { return status === 'belum' ? 'Belum dibuat' : status === 'final' ? 'Final' : 'Draft'; }
</script>

<template>
    <Head title="e-Rapor" />
    <AppShell title="e-Rapor">
        <PageHeader eyebrow="Wali Kelas" title="e-Rapor Siswa" :subtitle="`${waliKelas.kelas} · ${waliKelas.tahun_ajaran}`" icon="bi-file-earmark-person">
            <template #actions><Button :href="`/guru/wali-kelas`" color="outline-secondary" icon="bi-arrow-left">Kembali</Button></template>
        </PageHeader>

        <Card class="mb-4" title="Buat Draft e-Rapor" icon="bi-magic">
            <form class="row g-3 align-items-end" @submit.prevent="generate">
                <div class="col-md-3"><SelectInput v-model="semesterForm.semester" name="semester" label="Semester" required :options="[{ value: '1', label: 'Semester 1' }, { value: '2', label: 'Semester 2' }]" wrapper-class="mb-0" @change="changeSemester" /></div>
                <div class="col-md-5"><p class="small text-body-secondary mb-0">Nilai, predikat, deskripsi awal, dan rekap kehadiran diambil dari data LMS. Deskripsi dan catatan wali dapat disempurnakan sebelum difinalkan.</p></div>
                <div class="col-md-4 d-grid"><Button type="submit" color="primary" icon="bi-magic" :loading="semesterForm.processing">Buat / Perbarui Draft</Button></div>
            </form>
            <div v-if="semesterErrors.rapor" class="alert alert-danger py-2 mt-3 mb-0">{{ semesterErrors.rapor }}</div>
        </Card>

        <Card v-if="students.length" title="Daftar Rapor Siswa" icon="bi-people-fill" body-class="p-0">
            <TableWrapper stack>
                <table class="table table-hover mb-0 app-table-proportional">
                    <thead><tr><th>Siswa</th><th>Status</th><th>Kehadiran</th><th>Nilai Mapel</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <tr v-for="student in students" :key="student.id">
                            <td data-label="Siswa" class="stack-title"><div class="fw-semibold">{{ student.nama }}</div><div class="small text-body-secondary">{{ student.nis }}</div></td>
                            <td data-label="Status"><Badge :color="statusColor(student.status)">{{ statusLabel(student.status) }}</Badge></td>
                            <td data-label="Kehadiran"><span class="small">S {{ student.sakit }} · I {{ student.izin }} · A {{ student.alpha }}</span></td>
                            <td data-label="Nilai Mapel">{{ student.rapor?.nilai.length ?? 0 }} mapel</td>
                            <td class="stack-actions"><div class="d-flex flex-wrap gap-1"><Button v-if="student.rapor" :href="student.rapor.print_url" color="outline-secondary" icon="bi-printer">Cetak</Button><Button v-if="student.rapor?.status === 'draft'" color="outline-primary" icon="bi-pencil-square" @click="openEdit(student)">Edit</Button></div></td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>
        </Card>
        <EmptyState v-else title="Belum ada siswa aktif" message="Kelas wali ini belum memiliki siswa aktif." icon="bi-people" />

        <Modal :model-value="isModalOpen" :title="editing ? `Edit Rapor — ${editing.nama}` : 'Edit Rapor'" size="lg" @update:model-value="closeModal">
            <form id="raporForm" @submit.prevent="save">
                <div v-if="editing?.rapor" class="mb-3 d-flex flex-wrap gap-2"><Badge color="success">Sakit {{ editing.rapor.sakit }}</Badge><Badge color="info">Izin {{ editing.rapor.izin }}</Badge><Badge color="danger">Alpha {{ editing.rapor.alpha }}</Badge></div>
                <div v-if="editing?.rapor" class="row g-3">
                    <div v-for="item in editing.rapor.nilai" :key="item.id" class="col-12 border-bottom pb-3">
                        <div class="d-flex justify-content-between gap-3 mb-2"><div class="fw-semibold">{{ item.mata_pelajaran }}</div><div><Badge color="primary">{{ item.nilai_akhir ?? '-' }}</Badge><span v-if="item.predikat" class="small text-body-secondary ms-2">{{ item.predikat }}</span></div></div>
                        <TextareaInput v-model="editForm.nilai[String(item.id)].deskripsi" :name="`nilai_${item.id}_deskripsi`" label="Deskripsi rapor" :rows="2" :error="editErrors[`nilai.${item.id}.deskripsi`]" wrapper-class="mb-0" />
                    </div>
                </div>
                <TextareaInput v-model="editForm.catatan_wali_kelas" name="catatan_wali_kelas" label="Catatan Wali Kelas" :rows="4" :error="editForm.errors.catatan_wali_kelas" />
                <SelectInput v-model="editForm.status" name="status" label="Status" required :options="[{ value: 'draft', label: 'Simpan sebagai Draft' }, { value: 'final', label: 'Final / Kunci Rapor' }]" :error="editForm.errors.status" />
                <div v-if="editErrors.rapor" class="alert alert-danger py-2">{{ editErrors.rapor }}</div>
            </form>
            <template #footer><div class="d-flex justify-content-end gap-2"><Button color="outline-secondary" @click="closeModal">Batal</Button><Button type="submit" form="raporForm" color="primary" :loading="editForm.processing">Simpan</Button></div></template>
        </Modal>
    </AppShell>
</template>

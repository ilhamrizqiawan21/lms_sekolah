<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { SelectInput, TextareaInput, TextInput } from '../../../Components/Form';
import { Badge, Button, Card, EmptyState, IconButton, Modal, TableWrapper } from '../../../Components/UI';

interface KelasMapelOption {
    id: number;
    label: string;
    kelas: string;
    mata_pelajaran: string;
    mapel_id: number;
    tahun_ajaran_id: number;
    tahun_ajaran: string;
    semester: string;
}

interface KurikulumItem {
    id: number;
    nama: string;
    jenis_kurikulum: string;
    fase: string | null;
    semester: string;
    status: string;
    deskripsi: string | null;
    mata_pelajaran: string;
    tahun_ajaran: string;
    kelas: string[];
    kelas_mapel_ids: number[];
    cp_count: number;
    tp_count: number;
    show_url: string;
    update_url: string;
    delete_url: string;
}

interface KurikulumForm {
    tahun_ajaran_id: number | string;
    mapel_id: number | string;
    semester: string;
    kelas_mapel_ids: number[];
    jenis_kurikulum: string;
    fase: string;
    nama: string;
    deskripsi: string;
    status: string;
}

const props = defineProps<{
    kurikulum: KurikulumItem[];
    kelasMapel: KelasMapelOption[];
    storeUrl: string;
}>();

const curriculumOptions = [
    { value: 'merdeka', label: 'Kurikulum Merdeka' },
    { value: 'kbc', label: 'KBC' },
    { value: 'kurtilas', label: 'Kurikulum 2013' },
    { value: 'lainnya', label: 'Lainnya' },
];

const statusLabels: Record<string, string> = {
    draft: 'Draft',
    diajukan: 'Diajukan',
    disetujui: 'Disetujui',
    aktif: 'Aktif',
    arsip: 'Arsip',
};

const isModalOpen = ref(false);
const editing = ref<KurikulumItem | null>(null);

function blankForm(): KurikulumForm {
    return {
        tahun_ajaran_id: '',
        mapel_id: '',
        semester: '1',
        kelas_mapel_ids: [],
        jenis_kurikulum: 'merdeka',
        fase: '',
        nama: '',
        deskripsi: '',
        status: 'draft',
    };
}

const form = useForm<KurikulumForm>(blankForm());

const selectedScope = computed(() => {
    const selected = props.kelasMapel.find((item) => item.id === form.kelas_mapel_ids[0]);
    return selected ?? null;
});

const compatibleKelasMapel = computed(() => props.kelasMapel.filter((item) => {
    const scope = selectedScope.value;
    return !scope
        || (item.mapel_id === scope.mapel_id
            && item.tahun_ajaran_id === scope.tahun_ajaran_id
            && item.semester === scope.semester);
}));

function openCreate() {
    editing.value = null;
    form.defaults(blankForm());
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
}

function openEdit(item: KurikulumItem) {
    editing.value = item;
    form.clearErrors();
    const scope = props.kelasMapel.find((option) => item.kelas_mapel_ids.includes(option.id));
    form.tahun_ajaran_id = scope?.tahun_ajaran_id ?? '';
    form.mapel_id = scope?.mapel_id ?? '';
    form.semester = item.semester;
    form.kelas_mapel_ids = [...item.kelas_mapel_ids];
    form.jenis_kurikulum = item.jenis_kurikulum;
    form.fase = item.fase ?? '';
    form.nama = item.nama;
    form.deskripsi = item.deskripsi ?? '';
    form.status = item.status;
    isModalOpen.value = true;
}

function closeModal() {
    isModalOpen.value = false;
    editing.value = null;
    form.clearErrors();
}

function syncScope() {
    if (!selectedScope.value) {
        form.tahun_ajaran_id = '';
        form.mapel_id = '';
        return;
    }

    form.tahun_ajaran_id = selectedScope.value.tahun_ajaran_id;
    form.mapel_id = selectedScope.value.mapel_id;
    form.semester = selectedScope.value.semester;
    form.kelas_mapel_ids = form.kelas_mapel_ids.filter((id) => compatibleKelasMapel.value.some((item) => item.id === id));
}

function toggleKelasMapel(id: number, checked: boolean) {
    if (checked && !form.kelas_mapel_ids.includes(id)) {
        form.kelas_mapel_ids.push(id);
    } else if (!checked) {
        form.kelas_mapel_ids = form.kelas_mapel_ids.filter((item) => item !== id);
    }
    syncScope();
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (editing.value) {
        form.put(editing.value.update_url, options);
    } else {
        form.post(props.storeUrl, options);
    }
}

function destroy(item: KurikulumItem) {
    if (!window.confirm('Hapus kurikulum "' + item.nama + '" beserta CP dan TP di dalamnya?')) {
        return;
    }

    router.delete(item.delete_url, { preserveScroll: true });
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
    <Head title="Kurikulum" />

    <AppShell title="Kurikulum">
        <PageHeader
            eyebrow="Perencanaan Pembelajaran"
            title="Kurikulum"
            subtitle="Susun ATP, Capaian Pembelajaran, dan Tujuan Pembelajaran berdasarkan kelas yang Anda ajar."
        >
            <template #actions>
                <Button color="primary" icon="bi-plus-lg" @click="openCreate">Tambah Kurikulum</Button>
            </template>
        </PageHeader>

        <Card v-if="kurikulum.length" title="Kurikulum Anda" icon="bi-journal-bookmark-fill" body-class="p-0">
            <TableWrapper stack>
                <table class="table table-hover mb-0 app-table-proportional">
                    <thead>
                        <tr>
                            <th>Kurikulum</th>
                            <th>Mapel & Tahun</th>
                            <th>Kelas</th>
                            <th>Isi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in kurikulum" :key="item.id">
                            <td data-label="Kurikulum" class="stack-title">
                                <Link :href="item.show_url" class="fw-semibold text-decoration-none">{{ item.nama }}</Link>
                                <div class="small text-body-secondary mt-1">
                                    {{ item.jenis_kurikulum === 'merdeka' ? 'Kurikulum Merdeka' : item.jenis_kurikulum.toUpperCase() }}
                                    <span v-if="item.fase"> · Fase {{ item.fase }}</span>
                                    · Semester {{ item.semester }}
                                </div>
                            </td>
                            <td data-label="Mapel & Tahun">
                                <div>{{ item.mata_pelajaran }}</div>
                                <div class="small text-body-secondary">{{ item.tahun_ajaran }}</div>
                            </td>
                            <td data-label="Kelas">{{ item.kelas.join(', ') || '-' }}</td>
                            <td data-label="Isi">
                                <div class="d-flex flex-wrap gap-1">
                                    <Badge color="primary">{{ item.cp_count }} CP</Badge>
                                    <Badge color="info">{{ item.tp_count }} TP</Badge>
                                </div>
                            </td>
                            <td data-label="Status"><Badge :color="statusColor(item.status)">{{ statusLabels[item.status] ?? item.status }}</Badge></td>
                            <td class="stack-actions">
                                <div class="d-flex gap-1">
                                    <Link :href="item.show_url" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-list-check me-1" aria-hidden="true"></i>Kelola
                                    </Link>
                                    <IconButton icon="bi-pencil-square" color="outline-secondary" label="Edit kurikulum" @click="openEdit(item)" />
                                    <IconButton icon="bi-trash" color="outline-danger" label="Hapus kurikulum" @click="destroy(item)" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>
        </Card>

        <EmptyState
            v-else
            title="Belum ada kurikulum"
            message="Buat kurikulum pertama untuk mulai menyusun Capaian Pembelajaran dan Tujuan Pembelajaran."
            icon="bi-journal-bookmark"
        >
            <template #action><Button color="primary" icon="bi-plus-lg" @click="openCreate">Tambah Kurikulum</Button></template>
        </EmptyState>

        <Modal :model-value="isModalOpen" :title="editing ? 'Edit Kurikulum' : 'Tambah Kurikulum'" size="lg" @update:model-value="closeModal">
            <form id="kurikulumForm" @submit.prevent="save">
                <div class="row g-3">
                    <div class="col-md-8">
                        <TextInput v-model="form.nama" name="nama" label="Nama Kurikulum / ATP" placeholder="Misal: ATP PAI Fase D Semester 1" required :error="form.errors.nama" />
                    </div>
                    <div class="col-md-4">
                        <SelectInput v-model="form.jenis_kurikulum" name="jenis_kurikulum" label="Jenis Kurikulum" required :options="curriculumOptions" :error="form.errors.jenis_kurikulum" wrapper-class="" />
                    </div>
                    <div class="col-md-4">
                        <TextInput v-model="form.fase" name="fase" label="Fase (Opsional)" placeholder="Contoh: D" maxlength="20" :error="form.errors.fase" />
                    </div>
                    <div class="col-md-8">
                        <TextareaInput v-model="form.deskripsi" name="deskripsi" label="Deskripsi (Opsional)" placeholder="Catatan singkat tentang cakupan kurikulum..." :rows="2" :error="form.errors.deskripsi" />
                    </div>
                </div>

                <div class="border rounded p-3 mt-4">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h3 class="h6 mb-1">Kelas dan Mata Pelajaran</h3>
                            <p class="small text-body-secondary mb-0">Semua pilihan harus satu mapel, tahun ajaran, dan semester.</p>
                        </div>
                        <Badge v-if="selectedScope" color="info">{{ selectedScope.mata_pelajaran }} · Sem. {{ selectedScope.semester }}</Badge>
                    </div>
                    <div v-if="form.errors.kelas_mapel_ids" class="alert alert-danger py-2 small">{{ form.errors.kelas_mapel_ids }}</div>
                    <div v-if="kelasMapel.length" class="row g-2">
                        <div v-for="option in kelasMapel" :key="option.id" class="col-md-6">
                            <label class="border rounded p-2 d-flex align-items-start gap-2 h-100">
                                <input
                                    class="form-check-input mt-1"
                                    type="checkbox"
                                    :checked="form.kelas_mapel_ids.includes(option.id)"
                                    :disabled="!compatibleKelasMapel.some((item) => item.id === option.id)"
                                    @change="toggleKelasMapel(option.id, ($event.target as HTMLInputElement).checked)"
                                />
                                <span>
                                    <span class="d-block fw-semibold">{{ option.kelas }}</span>
                                    <span class="small text-body-secondary">{{ option.mata_pelajaran }} · {{ option.tahun_ajaran }} · Sem. {{ option.semester }}</span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <p v-else class="small text-body-secondary mb-0">Belum ada penugasan kelas-mapel aktif untuk akun Anda.</p>
                </div>
            </form>
            <template #footer>
                <Button type="button" color="outline-secondary" @click="closeModal">Batal</Button>
                <Button type="submit" form="kurikulumForm" color="primary" :disabled="form.processing || !form.kelas_mapel_ids.length">
                    {{ form.processing ? 'Menyimpan...' : editing ? 'Simpan Perubahan' : 'Buat Kurikulum' }}
                </Button>
            </template>
        </Modal>
    </AppShell>
</template>

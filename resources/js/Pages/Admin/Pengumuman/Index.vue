<script setup lang="ts">
import { Badge, Button, Card, EmptyState } from '../../../Components/UI';
import type { PropType } from 'vue';
import type { Announcement } from '../../../types/announcements';
import type { AppPageProps } from '../../../types/inertia';
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppShell from '../../../Layouts/AppShell.vue';
import { FileInput, SelectInput, TextareaInput, TextInput } from '../../../Components/Form';

const page = usePage<AppPageProps>();
const props = defineProps({
    pengumuman: { type: Object as PropType<{ data: Announcement[] }>, default: () => ({ data: [] }) },
    kelas: { type: Array as PropType<{ id: number; nama_kelas: string }[]>, default: () => [] },
    targetKelasOptions: { type: Array as PropType<{ id: number; tingkat: string | number; nama_kelas: string }[]>, default: () => [] },
    routePrefix: { type: String, default: 'admin.pengumuman' },
    storeUrl: { type: String, default: '/admin/pengumuman' },
});

const showForm = ref(false);
const editingId = ref<number | null>(null);
const editingUpdateUrl = ref<string | null>(null);
const fileInputKey = ref(0);
const form = useForm({
    judul: '',
    isi: '',
    target: 'semua',
    target_kelas_ids: [] as number[],
    is_public_login: false,
    public_file: null as File | null,
    remove_public_file: false,
});
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const targetOptions = computed(() => [
    ...(isAdmin.value ? [{ value: 'semua', label: 'Semua' }, { value: 'guru', label: 'Guru' }, { value: 'siswa', label: 'Siswa' }] : []),
    { value: 'kelas_mapel', label: 'Kelas tertentu' },
]);
const canPublish = computed(() => ['admin', 'guru'].includes(page.props.auth?.user?.role ?? ''));

function resetForm() {
    form.reset();
    form.clearErrors();
    editingId.value = null;
    editingUpdateUrl.value = null;
    fileInputKey.value += 1;
}

function openCreate() {
    resetForm();
    showForm.value = true;
}

function openEdit(item: Announcement) {
    form.clearErrors();
    form.judul = item.judul ?? '';
    form.isi = item.isi ?? '';
    form.target = item.target ?? 'semua';
    form.target_kelas_ids = Array.isArray(item.target_kelas_ids) ? [...item.target_kelas_ids] : [];
    form.is_public_login = Boolean(item.is_public_login);
    form.public_file = null;
    form.remove_public_file = false;
    fileInputKey.value += 1;
    editingId.value = item.id;
    editingUpdateUrl.value = item.update_url;
    showForm.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function submit() {
    if (form.processing) {
        return;
    }

    if (editingId.value && editingUpdateUrl.value) {
        form.put(editingUpdateUrl.value, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                resetForm();
                showForm.value = false;
            },
        });
        return;
    }

    form.post(props.storeUrl, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            resetForm();
            showForm.value = false;
        },
    });
}

function formatFileSize(bytes: number | null) {
    if (!bytes) return '';
    const kb = bytes / 1024;
    return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.ceil(kb)} KB`;
}

async function remove(item: Announcement) {
    const confirmed = await window.confirmDialog?.('Hapus pengumuman ini?', {
        title: 'Hapus Pengumuman',
        confirmText: 'Ya, hapus',
        danger: true,
    });

    if (!confirmed) return;

    form.delete(item.delete_url, { preserveScroll: true });
}
</script>

<template>
    <AppShell title="Pengumuman">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Pengumuman</h1>
                <p class="text-body-secondary mb-0">Kelola informasi resmi sekolah dan distribusi kepada pengguna.</p>
            </div>
            <Button color="primary" size="" v-if="canPublish" type="button" @click="showForm ? (showForm = false) : openCreate()">
                <i class="bi bi-plus-lg me-1"></i>{{ showForm ? 'Tutup Form' : 'Buat Pengumuman' }}
            </Button>
        </div>

        <Card v-if="showForm" class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">{{ editingId ? 'Edit Pengumuman' : 'Pengumuman Baru' }}</h5>
                    <Badge v-if="editingId" color="warning">Mode edit</Badge>
                </div>
                <form @submit.prevent="submit">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <TextInput v-model="form.judul" name="judul" label="Judul" maxlength="200" required wrapper-class="" :error="form.errors.judul" />
                        </div>
                        <div class="col-md-4">
                            <SelectInput v-model="form.target" name="target" label="Target" :options="targetOptions" wrapper-class="" />
                        </div>
                        <div v-if="form.target === 'kelas_mapel'" class="col-12">
                            <label class="form-label">Kelas Tujuan</label>
                            <select v-model="form.target_kelas_ids" class="form-select" multiple size="5">
                                <option v-for="kelasItem in targetKelasOptions" :key="kelasItem.id" :value="kelasItem.id">
                                    {{ kelasItem.tingkat }} {{ kelasItem.nama_kelas }}
                                </option>
                            </select>
                            <div v-if="form.errors.target_kelas_ids" class="text-danger small mt-1">{{ form.errors.target_kelas_ids }}</div>
                        </div>
                        <div class="col-12">
                            <TextareaInput v-model="form.isi" name="isi" label="Isi" :rows="6" required wrapper-class="" :error="form.errors.isi" />
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input
                                    id="is-public-login"
                                    v-model="form.is_public_login"
                                    class="form-check-input"
                                    type="checkbox"
                                >
                                <label class="form-check-label" for="is-public-login">
                                    Tampilkan di halaman login
                                </label>
                            </div>
                            <div class="form-text">
                                Info yang dicentang akan terlihat oleh siapa pun yang membuka halaman login.
                            </div>
                        </div>
                        <div class="col-12">
                            <FileInput
                                :key="fileInputKey"
                                v-model="form.public_file"
                                name="public_file"
                                label="Lampiran papan login"
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx,.doc,.docx"
                                accept-label="PDF, gambar, Excel, atau Word"
                                max-size="5MB"
                                :error="form.errors.public_file"
                                help="Opsional. File hanya bisa diunduh dari halaman login jika pengumuman ditampilkan publik."
                            />
                            <div v-if="editingId && pengumuman.data?.find((item) => item.id === editingId)?.attachment" class="border rounded p-3 bg-light">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <span class="small">
                                        <i class="bi bi-paperclip me-1" aria-hidden="true"></i>
                                        {{ pengumuman.data.find((item) => item.id === editingId)?.attachment?.name }}
                                    </span>
                                    <div class="form-check mb-0">
                                        <input id="remove-public-file" v-model="form.remove_public_file" class="form-check-input" type="checkbox">
                                        <label class="form-check-label small" for="remove-public-file">Hapus lampiran</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <Button color="outline-secondary" size="" type="button" @click="resetForm(); showForm = false">Batal</Button>
                            <Button color="primary" size="" :disabled="form.processing" type="submit">
                                {{ form.processing ? 'Menyimpan...' : (editingId ? 'Simpan Perubahan' : 'Publikasikan') }}
                            </Button>
                        </div>
                    </div>
                </form>
        </Card>

        <Card v-if="!pengumuman.data?.length" body-class="py-5">
                <EmptyState title="Belum ada pengumuman." icon="bi-megaphone" />
        </Card>
        <div v-else class="d-grid gap-3">
            <Card v-for="item in pengumuman.data" :key="item.id">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <h5 class="mb-1">{{ item.judul }}</h5>
                            <div class="small text-body-secondary">{{ item.creator?.nama_lengkap || '-' }} · {{ new Date(item.created_at).toLocaleDateString('id-ID') }}</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            <Badge v-if="item.is_public_login" color="success" class="align-self-start">Login publik</Badge>
                            <Badge color="secondary" class="align-self-start">{{ item.target }}</Badge>
                        </div>
                    </div>
                    <p class="mt-3 mb-3 text-secondary u-ws-pre-line">{{ item.isi }}</p>
                    <div v-if="item.attachment" class="mb-3">
                        <a v-if="item.attachment.url" :href="item.attachment.url" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ item.attachment.name }}
                            <span v-if="formatFileSize(item.attachment.size)" class="text-body-secondary">({{ formatFileSize(item.attachment.size) }})</span>
                        </a>
                        <Badge v-else color="secondary">
                            <i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ item.attachment.name }}
                        </Badge>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <Link :href="item.show_url" class="btn btn-sm btn-outline-primary">Detail</Link>
                        <Button color="outline-warning" v-if="item.can_edit" type="button" @click="openEdit(item)">Edit</Button>
                        <Button color="outline-danger" v-if="item.can_delete" type="button" @click="remove(item)">Hapus</Button>
                    </div>
            </Card>
        </div>
    </AppShell>
</template>

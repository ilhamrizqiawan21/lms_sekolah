<script setup lang="ts">
import { Badge, Button, Card, EmptyState } from '../../../Components/UI';
import type { PropType } from 'vue';
import type { Announcement } from '../../../types/announcements';
import type { AppPageProps } from '../../../types/inertia';
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppShell from '../../../Layouts/AppShell.vue';
import { formatDate, targetLabel } from '../../../utils/announcements';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { FileInput, SelectInput, TextareaInput, TextInput } from '../../../Components/Form';

const page = usePage<AppPageProps>();
const props = defineProps({
    pengumuman: { type: Object as PropType<{ data: Announcement[]; total?: number }>, default: () => ({ data: [], total: 0 }) },
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
    { value: 'semua_kelas', label: 'Semua kelas' },
    { value: 'kelas_mapel', label: 'Kelas tertentu' },
]);

const allKelasIds = () => props.targetKelasOptions.map((k) => k.id);
form.transform((data) => data.target === 'semua_kelas'
    ? { ...data, target: 'kelas_mapel', target_kelas_ids: allKelasIds() }
    : data);
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
    if (form.target === 'kelas_mapel' && allKelasIds().length > 0 && allKelasIds().every((id) => form.target_kelas_ids.includes(id))) {
        form.target = 'semua_kelas';
    }
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

function footerLabel(item: Announcement) {
    if (item.target === 'kelas_mapel') return 'Kelas tertentu';
    return item.is_public_login ? 'Dipublikasikan di halaman login' : 'Pengumuman internal';
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
    <Head title="Pengumuman" />

    <AppShell title="Pengumuman">
        <PageHeader eyebrow="Aktivitas" title="Pengumuman" subtitle="Informasi resmi sekolah dan distribusinya kepada pengguna.">
            <template v-if="canPublish" #actions>
                <Button color="primary" size="" type="button" :icon="showForm ? 'bi-x-lg' : 'bi-plus-lg'" @click="showForm ? (showForm = false) : openCreate()">
                    {{ showForm ? 'Tutup Form' : 'Buat Pengumuman' }}
                </Button>
            </template>
        </PageHeader>

        <Card v-if="showForm" class="announcement-form-card mb-4">
                <div class="announcement-form-header">
                    <div>
                        <h2>{{ editingId ? 'Edit Pengumuman' : 'Pengumuman Baru' }}</h2>
                        <p>Tentukan penerima, isi informasi, dan lampiran bila diperlukan.</p>
                    </div>
                    <Badge v-if="editingId" color="warning">Mode edit</Badge>
                </div>
                <form @submit.prevent="submit">
                    <div class="row g-3 announcement-form-fields">
                        <div class="col-md-8">
                            <TextInput v-model="form.judul" name="judul" label="Judul" maxlength="200" required wrapper-class="" :error="form.errors.judul" />
                        </div>
                        <div class="col-md-4">
                            <SelectInput v-model="form.target" name="target" label="Target" :options="targetOptions" wrapper-class="" />
                        </div>
                        <div v-if="form.target === 'kelas_mapel'" class="col-12">
                            <label class="form-label" for="target-kelas">Kelas tujuan</label>
                            <select id="target-kelas" v-model="form.target_kelas_ids" class="form-select" multiple size="5">
                                <option v-for="kelasItem in targetKelasOptions" :key="kelasItem.id" :value="kelasItem.id">
                                    {{ kelasItem.tingkat }} {{ kelasItem.nama_kelas }}
                                </option>
                            </select>
                            <div class="form-text">Pilih satu atau lebih kelas. Gunakan Ctrl atau Command untuk memilih beberapa.</div>
                            <div v-if="form.errors.target_kelas_ids" class="text-danger small mt-1">{{ form.errors.target_kelas_ids }}</div>
                        </div>
                        <div class="col-12">
                            <TextareaInput v-model="form.isi" name="isi" label="Isi" :rows="6" required wrapper-class="" :error="form.errors.isi" />
                        </div>
                        <div class="col-12">
                            <div class="announcement-public-row">
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
                            <div v-if="editingId && pengumuman.data?.find((item) => item.id === editingId)?.attachment" class="border rounded p-3 announcement-attachment-box">
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
                        <div class="col-12 announcement-form-actions">
                            <Button color="outline-secondary" size="" type="button" @click="resetForm(); showForm = false">Batal</Button>
                            <Button color="primary" size="" :disabled="form.processing" type="submit">
                                {{ form.processing ? 'Menyimpan...' : (editingId ? 'Simpan Perubahan' : 'Publikasikan') }}
                            </Button>
                        </div>
                    </div>
                </form>
        </Card>

        <div class="announcement-list-heading">
            <h2 class="h5 mb-0">Pengumuman terbaru</h2>
            <Badge color="secondary">{{ pengumuman.total ?? pengumuman.data?.length ?? 0 }} pengumuman</Badge>
        </div>

        <Card v-if="!pengumuman.data?.length" body-class="py-5">
                <EmptyState title="Belum ada pengumuman." icon="bi-megaphone" />
        </Card>
        <div v-else class="announcement-list">
            <Card v-for="item in pengumuman.data" :key="item.id" class="announcement-card">
                    <div class="announcement-top">
                        <div class="announcement-heading">
                            <h3>{{ item.judul }}</h3>
                            <div class="announcement-meta">
                                <span><i class="bi bi-person" aria-hidden="true"></i>{{ item.creator?.nama_lengkap || '-' }}</span>
                                <span aria-hidden="true">·</span>
                                <span><i class="bi bi-clock" aria-hidden="true"></i>{{ formatDate(item.created_at) }}</span>
                            </div>
                        </div>
                        <div class="announcement-badges">
                            <Badge v-if="item.is_public_login" color="success" class="align-self-start">
                                <i class="bi bi-eye me-1" aria-hidden="true"></i>Login publik
                            </Badge>
                            <Badge color="secondary" class="align-self-start">{{ targetLabel(item.target) }}</Badge>
                        </div>
                    </div>
                    <p class="announcement-body u-ws-pre-line">{{ item.isi }}</p>
                    <div v-if="item.attachment" class="announcement-attachment">
                        <a v-if="item.attachment.url" :href="item.attachment.url" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer">
                            <i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ item.attachment.name }}
                            <span v-if="formatFileSize(item.attachment.size)" class="text-body-secondary">({{ formatFileSize(item.attachment.size) }})</span>
                        </a>
                        <Badge v-else color="secondary">
                            <i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ item.attachment.name }}
                        </Badge>
                    </div>
                    <div class="announcement-footer">
                        <span>{{ footerLabel(item) }}</span>
                        <div class="announcement-actions">
                            <Link :href="item.show_url" class="btn btn-sm btn-outline-secondary">Detail</Link>
                            <Button v-if="item.can_edit" color="outline-success" icon="bi-pencil" type="button" @click="openEdit(item)">Edit</Button>
                            <Button v-if="item.can_delete" color="outline-danger" icon="bi-trash" type="button" @click="remove(item)">Hapus</Button>
                        </div>
                    </div>
            </Card>
        </div>
    </AppShell>
</template>

<style scoped>
.announcement-form-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.9rem;
    border-bottom: 1px solid var(--border-soft);
}

.announcement-form-header h2,
.announcement-list-heading h2 {
    margin: 0;
    color: var(--text-strong);
    font-size: 0.95rem;
    font-weight: 750;
}

.announcement-form-header p {
    margin: 0.25rem 0 0;
    color: var(--text-muted);
    font-size: 0.78rem;
}

.announcement-public-row {
    padding: 0.9rem;
    border: 1px solid var(--border-soft);
    border-radius: 10px;
    background: var(--surface-subtle);
}

.announcement-public-row .form-text {
    margin-left: 2.4rem;
}

.announcement-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding-top: 0.9rem;
    border-top: 1px solid var(--border-soft);
}

.announcement-list-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.announcement-list {
    display: grid;
    gap: 0.8rem;
}

.announcement-card {
    margin-bottom: 0;
}

.announcement-card :deep(.card-body) {
    padding: 1.1rem 1.2rem;
}

.announcement-top,
.announcement-footer {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.announcement-heading {
    min-width: 0;
}

.announcement-heading h3 {
    margin: 0;
    color: var(--text-strong);
    font-size: 0.95rem;
    font-weight: 750;
}

.announcement-meta,
.announcement-meta span {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.announcement-meta {
    flex-wrap: wrap;
    margin-top: 0.3rem;
    color: var(--text-muted);
    font-size: 0.75rem;
}

.announcement-badges,
.announcement-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.4rem;
}

.announcement-attachment-box {
    background: var(--surface-subtle);
    border-color: var(--border-soft) !important;
}

.announcement-actions :deep(.btn-outline-danger) {
    border-color: transparent;
    background: var(--status-danger-bg);
    color: var(--status-danger-text);
}

.announcement-actions :deep(.btn-outline-danger:hover) {
    background: color-mix(in srgb, var(--status-danger-bg) 80%, black);
    color: var(--status-danger-text);
}

.announcement-body {
    margin: 0.9rem 0;
    color: var(--text-body);
    font-size: 0.85rem;
    line-height: 1.7;
}

.announcement-attachment {
    margin-bottom: 0.8rem;
}

.announcement-footer {
    align-items: center;
    margin-top: 0.9rem;
    padding-top: 0.8rem;
    border-top: 1px solid var(--border-soft);
    color: var(--text-body);
    font-size: 0.82rem;
}

@media (max-width: 640px) {
    .announcement-top,
    .announcement-footer {
        flex-direction: column;
    }

    .announcement-badges,
    .announcement-actions {
        justify-content: flex-start;
    }

    .announcement-actions {
        width: 100%;
    }

    .announcement-actions > * {
        flex: 1;
    }
}
</style>

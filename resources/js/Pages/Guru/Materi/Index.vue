<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { FileInput, TextareaInput, TextInput } from '../../../Components/Form';
import AppShell from '../../../Layouts/AppShell.vue';
import { Button, Card, EmptyState } from '../../../Components/UI';

interface KelasMapelOption { id: number; label: string }
interface MateriItem { id: number; judul: string; deskripsi_ringkas?: string | null; kelas: string; mata_pelajaran: string; tanggal: string; download_url?: string | null; delete_url?: string | null }
interface MateriForm { kelas_mapel_ids: number[]; judul: string; deskripsi: string; file_materi: File | null }
interface Props { kelasMapel?: KelasMapelOption[]; materi?: MateriItem[]; storeUrl: string }

const props = withDefaults(defineProps<Props>(), { kelasMapel: () => [], materi: () => [] });

const fileInputKey = ref(0);
const materiSearch = ref('');
const selectedKelas = ref('');
const form = useForm<MateriForm>({
    kelas_mapel_ids: [],
    judul: '',
    deskripsi: '',
    file_materi: null,
});
const uploadPercentage = computed(() => form.progress?.percentage ?? 0);
const kelasFilters = computed(() => [...new Set(props.materi.map((item) => item.kelas).filter(Boolean))]);
const filteredMateri = computed(() => {
    const keyword = materiSearch.value.trim().toLocaleLowerCase('id-ID');

    return props.materi.filter((item) => {
        const matchesSearch = !keyword || `${item.judul} ${item.deskripsi_ringkas ?? ''} ${item.mata_pelajaran}`
            .toLocaleLowerCase('id-ID')
            .includes(keyword);
        const matchesClass = !selectedKelas.value || item.kelas === selectedKelas.value;

        return matchesSearch && matchesClass;
    });
});

function isImage(item: MateriItem): boolean {
    const target = `${item.download_url ?? ''} ${item.judul}`.toLowerCase();
    return target.includes('.jpg') || target.includes('.jpeg') || target.includes('.png');
}

function submit(): void {
    if (form.processing) {
        return;
    }

    form.post(props.storeUrl, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            fileInputKey.value += 1;
        },
    });
}

function resetUpload(): void {
    form.reset();
    form.clearErrors();
    fileInputKey.value += 1;
}

function kelasOptionParts(label: string) {
    const [kelas, ...detail] = label.split(' · ');
    return { kelas, detail: detail.join(' · ') };
}

async function destroy(item: MateriItem): Promise<void> {
    const confirmed = await window.confirmDialog?.('Hapus materi ini?', {
        title: 'Hapus Materi',
        confirmText: 'Ya, hapus',
        danger: true,
    });

    if (!confirmed || !item.delete_url) return;

    router.delete(item.delete_url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Materi" />

    <AppShell title="Materi">
        <PageHeader
            eyebrow="Mengajar"
            title="Materi Pembelajaran"
            subtitle="Unggah bahan ajar dan bagikan ke kelas yang Anda ampu."
        />

        <div v-if="kelasMapel.length" class="teacher-materi-page">
            <section class="card teacher-materi-upload">
                <header class="teacher-materi-card-header">
                    <div class="teacher-materi-card-heading">
                        <h2>Upload Materi</h2>
                        <p>Isi materi sekali, lalu pilih satu atau beberapa kelas tujuan.</p>
                    </div>
                </header>
                <form class="teacher-materi-form-body" @submit.prevent="submit">
                    <TextInput v-model="form.judul" name="judul" label="Judul materi" placeholder="Contoh: Sistem Pernapasan Manusia" maxlength="150" required :error="form.errors.judul" />
                    <TextareaInput v-model="form.deskripsi" name="deskripsi" label="Deskripsi · opsional" placeholder="Jelaskan ringkasan materi atau arahan untuk siswa" :rows="3" :error="form.errors.deskripsi" />

                    <div class="mb-3">
                        <label class="form-label">Kelas tujuan <span class="text-danger">*</span></label>
                        <div class="assignment-list">
                            <label v-for="item in kelasMapel" :key="item.id" class="assignment-option">
                                <input v-model="form.kelas_mapel_ids" class="form-check-input" type="checkbox" :value="item.id">
                                <span><strong>{{ kelasOptionParts(item.label).kelas }}</strong><small v-if="kelasOptionParts(item.label).detail">{{ kelasOptionParts(item.label).detail }}</small></span>
                            </label>
                        </div>
                        <small class="teacher-materi-hint">Pilih kelas yang akan menerima materi ini.</small>
                        <div v-if="form.errors.kelas_mapel_ids" class="text-danger small mt-1">{{ form.errors.kelas_mapel_ids }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="file_materi">File materi <span class="text-danger">*</span></label>
                        <div class="teacher-materi-upload-zone">
                            <span class="teacher-materi-upload-icon"><i class="bi bi-upload" aria-hidden="true"></i></span>
                            <span class="teacher-materi-upload-copy"><strong>{{ form.file_materi?.name || 'Pilih file materi' }}</strong><small>JPG, JPEG, atau PDF · maksimal 5 MB</small></span>
                            <FileInput
                                :key="fileInputKey"
                                v-model="form.file_materi"
                                name="file_materi"
                                wrapper-class="teacher-materi-file-control"
                                accept=".jpg,.jpeg,.pdf,image/jpeg,application/pdf"
                                required
                                :error="form.errors.file_materi"
                            />
                        </div>
                    </div>

                    <div v-if="form.processing && form.progress" class="progress teacher-materi-progress" role="progressbar" :aria-valuenow="uploadPercentage" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" :style="{ width: `${uploadPercentage}%` }">{{ uploadPercentage }}%</div>
                    </div>
                    <div class="teacher-materi-form-actions">
                        <Button type="button" color="outline-secondary" size="" :disabled="form.processing" @click="resetUpload">Kosongkan</Button>
                        <Button type="submit" color="primary" size="" icon="bi-upload" :disabled="form.processing">
                            {{ form.processing ? 'Mengupload...' : 'Upload Materi' }}
                        </Button>
                    </div>
                </form>
            </section>

            <section class="card teacher-materi-library">
                <header class="teacher-materi-card-header teacher-materi-library-header">
                    <div class="teacher-materi-card-heading"><h2>Materi yang diunggah</h2><p>Materi dari seluruh kelas mapel yang Anda ampu.</p></div>
                    <span class="teacher-materi-count">{{ materi.length }} materi</span>
                </header>

                <div v-if="materi.length" class="teacher-materi-filters">
                    <label class="teacher-materi-search"><i class="bi bi-search" aria-hidden="true"></i><input v-model="materiSearch" type="search" placeholder="Cari judul materi" aria-label="Cari judul materi"></label>
                    <select v-model="selectedKelas" class="form-select" aria-label="Filter kelas">
                        <option value="">Semua kelas</option>
                        <option v-for="kelas in kelasFilters" :key="kelas" :value="kelas">{{ kelas }}</option>
                    </select>
                </div>

                <template v-if="filteredMateri.length">
                    <div class="teacher-materi-table-scroll">
                        <table class="teacher-materi-table">
                            <thead><tr><th>Judul materi</th><th>Kelas</th><th>Mata pelajaran</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <tr v-for="item in filteredMateri" :key="item.id">
                                    <td><span class="teacher-materi-title-cell"><span class="teacher-materi-file-icon" :class="{ 'is-image': isImage(item) }"><i class="bi" :class="isImage(item) ? 'bi-file-earmark-image' : 'bi-file-earmark-text'" aria-hidden="true"></i></span><span><strong>{{ item.judul }}</strong><small v-if="item.deskripsi_ringkas">{{ item.deskripsi_ringkas }}</small></span></span></td>
                                    <td><span class="teacher-materi-class">{{ item.kelas }}</span></td>
                                    <td>{{ item.mata_pelajaran }}</td>
                                    <td class="teacher-materi-date">{{ item.tanggal }}</td>
                                    <td><div class="teacher-materi-actions"><a v-if="item.download_url" :href="item.download_url" class="btn btn-sm btn-outline-success" target="_blank" rel="noopener noreferrer"><i class="bi bi-download" aria-hidden="true"></i> Unduh</a><Button v-if="item.delete_url" type="button" color="outline-danger" icon="bi-trash" @click="destroy(item)">Hapus</Button></div></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="teacher-materi-list">
                        <article v-for="item in filteredMateri" :key="item.id" class="teacher-materi-item">
                            <div class="teacher-materi-item-heading"><span class="teacher-materi-file-icon" :class="{ 'is-image': isImage(item) }"><i class="bi" :class="isImage(item) ? 'bi-file-earmark-image' : 'bi-file-earmark-text'" aria-hidden="true"></i></span><div class="teacher-materi-item-copy"><strong>{{ item.judul }}</strong><span v-if="item.deskripsi_ringkas">{{ item.deskripsi_ringkas }}</span></div></div>
                            <dl class="teacher-materi-meta"><div><dt>Kelas</dt><dd><span class="teacher-materi-class">{{ item.kelas }}</span></dd></div><div><dt>Mapel</dt><dd>{{ item.mata_pelajaran }}</dd></div><div><dt>Tanggal</dt><dd>{{ item.tanggal }}</dd></div></dl>
                            <div class="teacher-materi-actions"><a v-if="item.download_url" :href="item.download_url" class="btn btn-sm btn-outline-success" target="_blank" rel="noopener noreferrer"><i class="bi bi-download" aria-hidden="true"></i> Unduh</a><Button v-if="item.delete_url" type="button" color="outline-danger" icon="bi-trash" @click="destroy(item)">Hapus</Button></div>
                        </article>
                    </div>
                </template>
                <EmptyState v-else :title="materi.length ? 'Materi tidak ditemukan' : 'Belum ada materi'" icon="bi-file-earmark-text" />
                <footer v-if="materi.length" class="teacher-materi-footer">Menampilkan {{ filteredMateri.length }} dari {{ materi.length }} materi</footer>
            </section>
        </div>

        <Card v-else>
            <EmptyState title="Anda belum memiliki penugasan mengajar semester ini" icon="bi-book" />
        </Card>
    </AppShell>
</template>

<style scoped>
.teacher-materi-page {
    display: grid;
    grid-template-columns: minmax(280px, 0.82fr) minmax(0, 1.48fr);
    align-items: start;
    gap: 1.1rem;
}

.teacher-materi-page > .card {
    min-width: 0;
    margin-bottom: 0;
}

.teacher-materi-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border-bottom: 1px solid var(--border-soft);
}

.teacher-materi-card-heading {
    min-width: 0;
    flex: 1 1 auto;
}

.teacher-materi-card-header h2 {
    margin: 0;
    color: var(--text-strong);
    font-size: 0.95rem;
    font-weight: 750;
}

.teacher-materi-card-header p {
    margin: 0.2rem 0 0;
    color: var(--text-muted);
    font-size: 0.75rem;
}

.teacher-materi-form-body {
    padding: 1.1rem;
}

.assignment-list {
    display: grid;
    max-height: 192px;
    gap: 0.4rem;
    overflow: auto;
    padding: 2px;
}

.assignment-option {
    display: flex;
    gap: 0.6rem;
    align-items: flex-start;
    padding: 0.6rem 0.7rem;
    border: 1px solid var(--border-soft);
    border-radius: 8px;
    background: var(--surface-card);
    cursor: pointer;
}

.assignment-option strong,
.assignment-option small {
    display: block;
}

.assignment-option strong {
    color: var(--text-strong);
    font-size: 0.77rem;
}

.assignment-option small,
.teacher-materi-hint {
    color: var(--text-muted);
    font-size: 0.7rem;
}

.assignment-option small {
    margin-top: 0.1rem;
}

.teacher-materi-hint {
    display: block;
    margin-top: 0.3rem;
}

.assignment-option:hover,
.assignment-option:focus-within,
.assignment-option:has(input:checked) {
    border-color: var(--primary-300);
    background: var(--brand-soft-bg, #eef2ff);
}

.teacher-materi-upload-zone {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-height: 82px;
    padding: 0.7rem;
    border: 1px dashed #b8c0d0;
    border-radius: 10px;
    background: #fbfcff;
}

.teacher-materi-upload-icon {
    display: grid;
    width: 38px;
    height: 38px;
    flex: none;
    place-items: center;
    border-radius: 9px;
    background: var(--brand-soft-bg, #eef2ff);
    color: var(--text-brand);
}

.teacher-materi-upload-copy {
    display: grid;
    min-width: 0;
    gap: 0.2rem;
    flex: 1;
}

.teacher-materi-upload-copy strong {
    overflow: hidden;
    color: var(--text-strong);
    font-size: 0.75rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.teacher-materi-upload-copy small {
    color: var(--text-muted);
    font-size: 0.66rem;
}

.teacher-materi-upload-zone :deep(.teacher-materi-file-control) {
    min-width: 0;
    max-width: 170px;
}

.teacher-materi-upload-zone :deep(input[type='file']) {
    min-height: 0;
    font-size: 0.65rem;
}

.teacher-materi-form-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--border-soft);
}

.teacher-materi-progress {
    margin-bottom: 0.7rem;
}

.teacher-materi-count {
    padding: 0.25rem 0.6rem;
    border: 1px solid var(--border-soft);
    border-radius: 999px;
    color: var(--text-muted);
    font-size: 0.7rem;
    white-space: nowrap;
}

.teacher-materi-filters {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(100px, 0.2fr);
    gap: 0.5rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border-soft);
}

.teacher-materi-search {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
    padding: 0 0.6rem;
    border: 1px solid var(--border-strong, #d0d5dd);
    border-radius: 8px;
    background: var(--surface-card);
    color: var(--text-muted);
}

.teacher-materi-search input {
    width: 100%;
    min-width: 0;
    height: 37px;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text-body);
    font-size: 0.75rem;
}

.teacher-materi-table-scroll {
    overflow-x: auto;
}

.teacher-materi-table {
    width: 100%;
    min-width: 650px;
    border-collapse: collapse;
    text-align: left;
}

.teacher-materi-table th {
    padding: 0.65rem 0.75rem;
    border-bottom: 1px solid var(--border-soft);
    background: var(--surface-subtle);
    color: var(--text-muted);
    font-size: 0.7rem;
    font-weight: 700;
    white-space: nowrap;
}

.teacher-materi-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-soft);
    color: var(--text-body);
    font-size: 0.76rem;
    vertical-align: middle;
}

.teacher-materi-table tr:last-child td {
    border-bottom: 0;
}

.teacher-materi-title-cell {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 180px;
}

.teacher-materi-title-cell > span:last-child {
    display: grid;
    min-width: 0;
    gap: 0.15rem;
}

.teacher-materi-title-cell strong,
.teacher-materi-title-cell small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.teacher-materi-title-cell strong {
    color: var(--text-strong);
}

.teacher-materi-title-cell small {
    color: var(--text-muted);
}

.teacher-materi-class {
    display: inline-flex;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    background: var(--brand-soft-bg, #eef2ff);
    color: var(--text-brand);
    font-size: 0.7rem;
    font-weight: 700;
    white-space: nowrap;
}

.teacher-materi-date {
    white-space: nowrap;
}

.teacher-materi-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--border-soft);
    color: var(--text-muted);
    font-size: 0.72rem;
}

.teacher-materi-list {
    display: none;
    gap: 0.75rem;
}

.teacher-materi-item {
    display: grid;
    gap: 0.8rem;
    min-width: 0;
    padding: 0.9rem;
    border: 1px solid var(--border-soft);
    border-radius: var(--radius-md);
    background: var(--surface-card);
}

.teacher-materi-item-heading {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.65rem;
}

.teacher-materi-file-icon {
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    place-items: center;
    border-radius: 10px;
    border: 1px solid var(--status-danger-bg, #f3c6c2);
    background: var(--status-danger-bg, #fef3f2);
    color: var(--status-danger-text, #b42318);
    font-size: 1rem;
}

.teacher-materi-file-icon.is-image {
    border-color: var(--brand-soft-bg);
    background: var(--brand-soft-bg);
    color: var(--text-brand);
}

.teacher-materi-item-copy {
    display: grid;
    min-width: 0;
    gap: 0.15rem;
}

.teacher-materi-item-copy strong,
.teacher-materi-item-copy span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.teacher-materi-item-copy strong {
    color: var(--text-strong);
    font-size: 0.88rem;
}

.teacher-materi-item-copy span {
    color: var(--text-muted);
    font-size: 0.76rem;
}

.teacher-materi-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
    margin: 0;
    padding: 0 0 0.75rem;
    border-bottom: 1px solid var(--border-soft);
}

.teacher-materi-meta dt {
    color: var(--text-muted);
    font-size: 0.7rem;
    font-weight: 500;
}

.teacher-materi-meta dd {
    overflow: hidden;
    margin: 0.15rem 0 0;
    color: var(--text-body);
    font-size: 0.76rem;
    font-weight: 650;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.teacher-materi-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

@media (max-width: 1180px) {
    .teacher-materi-upload-zone {
        flex-wrap: wrap;
    }

    .teacher-materi-upload-zone :deep(.teacher-materi-file-control) {
        width: 100%;
        max-width: none;
    }
}

@media (max-width: 900px) {
    .teacher-materi-page {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 680px) {
    .teacher-materi-filters {
        grid-template-columns: minmax(0, 1fr);
    }

    .teacher-materi-table-scroll {
        display: none;
    }

    .teacher-materi-list {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 0.6rem;
        padding: 0.8rem;
    }

    .teacher-materi-meta {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .teacher-materi-meta > div {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.3rem;
    }

    .teacher-materi-meta > div:last-child {
        grid-column: 1 / -1;
    }
}
</style>

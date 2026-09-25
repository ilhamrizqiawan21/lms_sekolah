<script setup lang="ts">
import type { PropType } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { TextareaInput, TextInput } from '../../../Components/Form';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, DashboardHero, EmptyState, IconButton, TableWrapper } from '../../../Components/UI';

interface OpsiItem {
    id?: number;
    teks_opsi: string;
    is_benar: boolean;
    urutan?: number;
}

interface SoalItem {
    id: number;
    mapel_id: number | null;
    mapel: { id: number; nama_mapel: string; kode: string } | null;
    pertanyaan: string;
    topik: string | null;
    kesulitan: 'mudah' | 'sedang' | 'sulit';
    kategori_nilai: string | null;
    created_at: string | null;
    opsi: OpsiItem[];
}

const props = defineProps({
    soalList: { type: Array as PropType<SoalItem[]>, default: () => [] },
    mataPelajaran: { type: Array as PropType<{ id: number; nama_mapel: string; kode: string }[]>, default: () => [] },
    topics: { type: Array as PropType<string[]>, default: () => [] },
    filters: {
        type: Object as PropType<{
            mapel_id: string | number;
            topik: string;
            kesulitan: string;
            kategori_nilai: string;
            search: string;
        }>,
        default: () => ({ mapel_id: '', topik: '', kesulitan: '', kategori_nilai: '', search: '' }),
    },
});

const filterForm = ref({
    mapel_id: props.filters.mapel_id || '',
    topik: props.filters.topik || '',
    kesulitan: props.filters.kesulitan || '',
    kategori_nilai: props.filters.kategori_nilai || '',
    search: props.filters.search || '',
});

const isModalOpen = ref(false);
const editingSoal = ref<SoalItem | null>(null);

const form = useForm({
    mapel_id: '' as string | number,
    pertanyaan: '',
    topik: '',
    kesulitan: 'sedang' as 'mudah' | 'sedang' | 'sulit',
    kategori_nilai: '' as string,
    opsi: [
        { teks_opsi: '', is_benar: true },
        { teks_opsi: '', is_benar: false },
        { teks_opsi: '', is_benar: false },
        { teks_opsi: '', is_benar: false },
    ] as { teks_opsi: string; is_benar: boolean }[],
});

function applyFilter() {
    router.get('/guru/soal-bank', filterForm.value, {
        preserveState: true,
        preserveScroll: true,
    });
}

function resetFilter() {
    filterForm.value = {
        mapel_id: '',
        topik: '',
        kesulitan: '',
        kategori_nilai: '',
        search: '',
    };
    applyFilter();
}

function openCreateModal() {
    editingSoal.value = null;
    form.reset();
    form.clearErrors();
    form.mapel_id = props.mataPelajaran[0]?.id ?? '';
    form.pertanyaan = '';
    form.topik = '';
    form.kesulitan = 'sedang';
    form.kategori_nilai = '';
    form.opsi = [
        { teks_opsi: '', is_benar: true },
        { teks_opsi: '', is_benar: false },
        { teks_opsi: '', is_benar: false },
        { teks_opsi: '', is_benar: false },
    ];
    isModalOpen.value = true;
}

function openEditModal(soal: SoalItem) {
    editingSoal.value = soal;
    form.clearErrors();
    form.mapel_id = soal.mapel_id ?? '';
    form.pertanyaan = soal.pertanyaan;
    form.topik = soal.topik ?? '';
    form.kesulitan = soal.kesulitan;
    form.kategori_nilai = soal.kategori_nilai ?? '';
    form.opsi = (soal.opsi && soal.opsi.length >= 2)
        ? soal.opsi.map((o) => ({ teks_opsi: o.teks_opsi, is_benar: Boolean(o.is_benar) }))
        : [
            { teks_opsi: '', is_benar: true },
            { teks_opsi: '', is_benar: false },
        ];
    isModalOpen.value = true;
}

function closeModal() {
    isModalOpen.value = false;
    editingSoal.value = null;
    form.reset();
    form.clearErrors();
}

function setBenarIndex(index: number) {
    form.opsi.forEach((o, i) => {
        o.is_benar = (i === index);
    });
}

function addOpsi() {
    if (form.opsi.length < 5) {
        form.opsi.push({ teks_opsi: '', is_benar: false });
    }
}

function removeOpsi(index: number) {
    if (form.opsi.length > 2) {
        const wasBenar = form.opsi[index]?.is_benar;
        form.opsi.splice(index, 1);
        if (wasBenar && form.opsi.length > 0) {
            form.opsi[0].is_benar = true;
        }
    }
}

function saveSoal() {
    if (editingSoal.value) {
        form.put(`/guru/soal-bank/${editingSoal.value.id}`, {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post('/guru/soal-bank', {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
}

async function destroySoal(soal: SoalItem) {
    const confirmed = await window.confirmDialog?.('Hapus soal ini dari bank soal?', {
        title: 'Hapus Soal',
        confirmText: 'Ya, hapus',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    router.delete(`/guru/soal-bank/${soal.id}`, {
        preserveScroll: true,
    });
}

const kesulitanBadgeColor = (kesulitan: string) => {
    switch (kesulitan) {
        case 'mudah': return 'success';
        case 'sulit': return 'danger';
        default: return 'warning text-dark';
    }
};
</script>

<template>
    <Head title="Bank Soal" />

    <AppShell title="Bank Soal">
        <DashboardHero
            eyebrow="CBT & Ujian"
            title="Bank Soal Guru"
            subtitle="Kelola bank soal pilihan ganda yang dapat digunakan ulang saat membuat ujian CBT."
            icon="bi-collection-fill"
            tone="teacher"
        >
            <template #actions>
                <Button color="primary" icon="bi-plus-lg" @click="openCreateModal">
                    Tambah Soal
                </Button>
            </template>
        </DashboardHero>

        <!-- Filter Card -->
        <Card title="Filter Bank Soal" icon="bi-funnel-fill" class="mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Mata Pelajaran</label>
                    <select v-model="filterForm.mapel_id" class="form-select form-select-sm" @change="applyFilter">
                        <option value="">Semua Mapel</option>
                        <option v-for="mapel in mataPelajaran" :key="mapel.id" :value="mapel.id">
                            {{ mapel.nama_mapel }} ({{ mapel.kode }})
                        </option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Tingkat Kesulitan</label>
                    <select v-model="filterForm.kesulitan" class="form-select form-select-sm" @change="applyFilter">
                        <option value="">Semua</option>
                        <option value="mudah">Mudah</option>
                        <option value="sedang">Sedang</option>
                        <option value="sulit">Sulit</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Kategori Nilai</label>
                    <select v-model="filterForm.kategori_nilai" class="form-select form-select-sm" @change="applyFilter">
                        <option value="">Semua</option>
                        <option value="NH">Nilai Harian (NH)</option>
                        <option value="STS">STS</option>
                        <option value="SAS">SAS</option>
                        <option value="SAT">SAT</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Cari Pertanyaan / Topik</label>
                    <input
                        v-model="filterForm.search"
                        type="text"
                        class="form-control form-control-sm"
                        placeholder="Ketik kata kunci..."
                        @keyup.enter="applyFilter"
                    />
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <Button color="primary" class="w-100" @click="applyFilter">
                        Filter
                    </Button>
                    <Button color="light" @click="resetFilter">
                        Reset
                    </Button>
                </div>
            </div>
        </Card>

        <!-- Soal List Table -->
        <Card title="Daftar Soal" icon="bi-list-check" body-class="p-0">
            <div v-if="soalList.length" class="p-3 border-bottom bg-light-subtle d-flex justify-content-between align-items-center">
                <span class="text-muted small">Total {{ soalList.length }} butir soal tersedia di bank soal Anda.</span>
            </div>

            <TableWrapper v-if="soalList.length">
                <table class="table table-hover mb-0 app-table-proportional">
                    <colgroup>
                        <col style="width: 4%">
                        <col style="width: 42%">
                        <col style="width: 14%">
                        <col style="width: 12%">
                        <col style="width: 10%">
                        <col style="width: 8%">
                        <col style="width: 10%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Pertanyaan & Opsi</th>
                            <th>Mata Pelajaran</th>
                            <th>Topik</th>
                            <th>Kesulitan</th>
                            <th>Kategori</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(soal, index) in soalList" :key="soal.id">
                            <td>{{ index + 1 }}</td>
                            <td>
                                <div class="fw-semibold text-break mb-1">{{ soal.pertanyaan }}</div>
                                <div class="small text-muted ps-2 border-start">
                                    <div
                                        v-for="(opsi, oIdx) in soal.opsi"
                                        :key="opsi.id || oIdx"
                                        :class="opsi.is_benar ? 'text-success fw-bold' : ''"
                                        class="d-flex align-items-center gap-1"
                                    >
                                        <i v-if="opsi.is_benar" class="bi bi-check-circle-fill text-success" />
                                        <i v-else class="bi bi-circle text-muted" style="font-size: 0.75rem;" />
                                        <span>{{ String.fromCharCode(65 + oIdx) }}. {{ opsi.teks_opsi }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span v-if="soal.mapel">{{ soal.mapel.nama_mapel }}</span>
                                <span v-else class="text-muted fst-italic">Semua Mapel</span>
                            </td>
                            <td>
                                <span v-if="soal.topik" class="badge bg-light text-dark border">{{ soal.topik }}</span>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td>
                                <Badge :color="kesulitanBadgeColor(soal.kesulitan)">
                                    {{ soal.kesulitan }}
                                </Badge>
                            </td>
                            <td>
                                <Badge v-if="soal.kategori_nilai" color="info">
                                    {{ soal.kategori_nilai }}
                                </Badge>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <IconButton
                                        icon="bi-pencil-square"
                                        color="outline-primary"
                                        label="Edit Soal"
                                        @click="openEditModal(soal)"
                                    />
                                    <IconButton
                                        icon="bi-trash"
                                        color="outline-danger"
                                        label="Hapus Soal"
                                        @click="destroySoal(soal)"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>

            <EmptyState
                v-else
                title="Belum ada soal di Bank Soal"
                message="Klik 'Tambah Soal' untuk membuat butir soal pilihan ganda baru."
                icon="bi-collection"
            />
        </Card>

        <!-- Modal Form Soal -->
        <div v-if="isModalOpen" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil-square me-2 text-primary" />
                            {{ editingSoal ? 'Edit Soal' : 'Tambah Soal ke Bank' }}
                        </h5>
                        <button type="button" class="btn-close" @click="closeModal" />
                    </div>

                    <form @submit.prevent="saveSoal">
                        <div class="modal-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Mata Pelajaran</label>
                                    <select v-model="form.mapel_id" class="form-select" :class="{ 'is-invalid': form.errors.mapel_id }">
                                        <option value="">-- Umum / Tanpa Mapel --</option>
                                        <option v-for="mapel in mataPelajaran" :key="mapel.id" :value="mapel.id">
                                            {{ mapel.nama_mapel }} ({{ mapel.kode }})
                                        </option>
                                    </select>
                                    <div v-if="form.errors.mapel_id" class="invalid-feedback">{{ form.errors.mapel_id }}</div>
                                </div>

                                <div class="col-md-6">
                                    <TextInput
                                        name="topik"
                                        v-model="form.topik"
                                        label="Topik / Bab (Opsional)"
                                        placeholder="Misal: Aljabar, Ekosistem..."
                                        :error="form.errors.topik"
                                    />
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">Tingkat Kesulitan</label>
                                    <select v-model="form.kesulitan" class="form-select" :class="{ 'is-invalid': form.errors.kesulitan }">
                                        <option value="mudah">Mudah</option>
                                        <option value="sedang">Sedang</option>
                                        <option value="sulit">Sulit</option>
                                    </select>
                                    <div v-if="form.errors.kesulitan" class="invalid-feedback">{{ form.errors.kesulitan }}</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Kategori Nilai Default (Opsional)</label>
                                    <select v-model="form.kategori_nilai" class="form-select" :class="{ 'is-invalid': form.errors.kategori_nilai }">
                                        <option value="">-- Tidak Ditentukan --</option>
                                        <option value="NH">Nilai Harian (NH)</option>
                                        <option value="STS">STS</option>
                                        <option value="SAS">SAS</option>
                                        <option value="SAT">SAT</option>
                                    </select>
                                    <div v-if="form.errors.kategori_nilai" class="invalid-feedback">{{ form.errors.kategori_nilai }}</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <TextareaInput
                                    name="pertanyaan"
                                    v-model="form.pertanyaan"
                                    label="Pertanyaan Soal"
                                    placeholder="Tuliskan teks pertanyaan soal..."
                                    :rows="3"
                                    required
                                    :error="form.errors.pertanyaan"
                                />
                            </div>

                            <div class="border rounded p-3 bg-light-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <label class="form-label fw-bold mb-0">
                                        Opsi Jawaban (Pilihan Ganda)
                                    </label>
                                    <Button
                                        v-if="form.opsi.length < 5"
                                        type="button"
                                        size="sm"
                                        color="outline-primary"
                                        icon="bi-plus-circle"
                                        @click="addOpsi"
                                    >
                                        Tambah Opsi
                                    </Button>
                                </div>

                                <div v-if="form.errors.opsi" class="alert alert-danger py-2 small mb-3">
                                    {{ form.errors.opsi }}
                                </div>

                                <div class="d-flex flex-column gap-2">
                                    <div
                                        v-for="(opsi, idx) in form.opsi"
                                        :key="idx"
                                        class="p-2 border rounded bg-white"
                                        :class="opsi.is_benar ? 'border-success bg-success-subtle' : ''"
                                    >
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <div class="form-check mb-0">
                                                <input
                                                    :id="`opsi-radio-${idx}`"
                                                    type="radio"
                                                    class="form-check-input"
                                                    name="is_benar_choice"
                                                    :checked="opsi.is_benar"
                                                    @change="setBenarIndex(idx)"
                                                />
                                                <label :for="`opsi-radio-${idx}`" class="form-check-label fw-bold small">
                                                    Pilihan {{ String.fromCharCode(65 + idx) }}
                                                    <span v-if="opsi.is_benar" class="badge bg-success ms-1">Kunci Jawaban</span>
                                                </label>
                                            </div>

                                            <div class="ms-auto" v-if="form.opsi.length > 2">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger p-0 px-1"
                                                    title="Hapus opsi"
                                                    @click="removeOpsi(idx)"
                                                >
                                                    <i class="bi bi-x" />
                                                </button>
                                            </div>
                                        </div>

                                        <input
                                            v-model="opsi.teks_opsi"
                                            type="text"
                                            class="form-control form-control-sm"
                                            :placeholder="`Teks jawaban pilihan ${String.fromCharCode(65 + idx)}`"
                                            required
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <Button type="button" color="light" @click="closeModal">
                                Batal
                            </Button>
                            <Button type="submit" color="primary" :disabled="form.processing">
                                {{ editingSoal ? 'Simpan Perubahan' : 'Tambahkan Soal' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppShell>
</template>

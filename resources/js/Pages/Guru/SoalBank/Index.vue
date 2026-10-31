<script setup lang="ts">
import type { PropType } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { SelectInput, TextareaInput, TextInput } from '../../../Components/Form';
import AppShell from '../../../Layouts/AppShell.vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { Badge, Button, Card, EmptyState, IconButton, Modal, TableWrapper } from '../../../Components/UI';

interface OpsiItem {
    id?: number;
    teks_opsi: string;
    pasangan_teks?: string;
    is_benar: boolean;
    urutan?: number;
}

type QuestionType = 'pilihan_ganda' | 'menjodohkan' | 'benar_salah' | 'uraian';

interface SoalItem {
    id: number;
    mapel_id: number | null;
    mapel: { id: number; nama_mapel: string; kode: string } | null;
    pertanyaan: string;
    pembahasan: string | null;
    tipe_soal: QuestionType;
    level_kognitif: string | null;
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

const mapelOptions = computed(() => props.mataPelajaran.map((mapel) => ({ value: mapel.id, label: `${mapel.nama_mapel} (${mapel.kode})` })));
const tipeSoalOptions = [
    { value: 'pilihan_ganda', label: 'Pilihan Ganda' },
    { value: 'benar_salah', label: 'Benar / Salah' },
    { value: 'menjodohkan', label: 'Menjodohkan' },
    { value: 'uraian', label: 'Uraian' },
];
const levelKognitifOptions = [
    { value: 'C1', label: 'C1 — Mengingat' },
    { value: 'C2', label: 'C2 — Memahami' },
    { value: 'C3', label: 'C3 — Menerapkan' },
    { value: 'C4', label: 'C4 — Menganalisis' },
    { value: 'C5', label: 'C5 — Mengevaluasi' },
    { value: 'C6', label: 'C6 — Mencipta' },
];
const kesulitanOptions = [{ value: 'mudah', label: 'Mudah' }, { value: 'sedang', label: 'Sedang' }, { value: 'sulit', label: 'Sulit' }];
const kategoriOptions = [{ value: 'NH', label: 'Nilai Harian (NH)' }, { value: 'STS', label: 'STS' }, { value: 'SAS', label: 'SAS' }, { value: 'SAT', label: 'SAT' }];

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
    pembahasan: '',
    tipe_soal: 'pilihan_ganda' as QuestionType,
    level_kognitif: '' as string,
    topik: '',
    kesulitan: 'sedang' as 'mudah' | 'sedang' | 'sulit',
    kategori_nilai: '' as string,
    opsi: [
        { teks_opsi: '', pasangan_teks: '', is_benar: true },
        { teks_opsi: '', pasangan_teks: '', is_benar: false },
        { teks_opsi: '', pasangan_teks: '', is_benar: false },
        { teks_opsi: '', pasangan_teks: '', is_benar: false },
    ] as { teks_opsi: string; pasangan_teks: string; is_benar: boolean }[],
});

const requiresOptions = computed(() => ['pilihan_ganda', 'benar_salah', 'menjodohkan'].includes(form.tipe_soal));

function optionsForType(type: QuestionType): { teks_opsi: string; pasangan_teks: string; is_benar: boolean }[] {
    if (type === 'benar_salah') {
        return [
            { teks_opsi: 'Benar', pasangan_teks: '', is_benar: true },
            { teks_opsi: 'Salah', pasangan_teks: '', is_benar: false },
        ];
    }

    if (type === 'pilihan_ganda') {
        return [
            { teks_opsi: '', pasangan_teks: '', is_benar: true },
            { teks_opsi: '', pasangan_teks: '', is_benar: false },
            { teks_opsi: '', pasangan_teks: '', is_benar: false },
            { teks_opsi: '', pasangan_teks: '', is_benar: false },
        ];
    }

    if (type === 'menjodohkan') {
        return [
            { teks_opsi: '', pasangan_teks: '', is_benar: false },
            { teks_opsi: '', pasangan_teks: '', is_benar: false },
        ];
    }

    return [];
}

function handleTipeSoalChange() {
    form.opsi = optionsForType(form.tipe_soal as QuestionType);
}

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
    form.pembahasan = '';
    form.tipe_soal = 'pilihan_ganda';
    form.level_kognitif = '';
    form.topik = '';
    form.kesulitan = 'sedang';
    form.kategori_nilai = '';
    form.opsi = optionsForType('pilihan_ganda');
    isModalOpen.value = true;
}

function openEditModal(soal: SoalItem) {
    editingSoal.value = soal;
    form.clearErrors();
    form.mapel_id = soal.mapel_id ?? '';
    form.pertanyaan = soal.pertanyaan;
    form.pembahasan = soal.pembahasan ?? '';
    form.tipe_soal = soal.tipe_soal ?? 'pilihan_ganda';
    form.level_kognitif = soal.level_kognitif ?? '';
    form.topik = soal.topik ?? '';
    form.kesulitan = soal.kesulitan;
    form.kategori_nilai = soal.kategori_nilai ?? '';
    form.opsi = (soal.opsi && soal.opsi.length >= 2)
        ? soal.opsi.map((o) => ({ teks_opsi: o.teks_opsi, pasangan_teks: o.pasangan_teks ?? '', is_benar: Boolean(o.is_benar) }))
        : optionsForType(form.tipe_soal as QuestionType);
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
    if (['pilihan_ganda', 'menjodohkan'].includes(form.tipe_soal) && form.opsi.length < 5) {
        form.opsi.push({ teks_opsi: '', pasangan_teks: '', is_benar: false });
    }
}

function removeOpsi(index: number) {
    if (['pilihan_ganda', 'menjodohkan'].includes(form.tipe_soal) && form.opsi.length > 2) {
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
        <PageHeader
            eyebrow="Ujian CBT"
            title="Bank Soal"
            subtitle="Kelola bank soal beserta tipe, bab, dan level kognitif untuk fondasi ujian CBT."
        >
            <template #actions>
                <Button color="primary" icon="bi-plus-lg" @click="openCreateModal">
                    Tambah Soal
                </Button>
            </template>
        </PageHeader>

        <!-- Filter Card -->
        <Card title="Filter Bank Soal" icon="bi-funnel-fill" class="mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <SelectInput v-model="filterForm.mapel_id" name="filter_mapel_id" label="Mata Pelajaran" placeholder="Semua Mapel" :options="mapelOptions" wrapper-class="" class="form-select-sm" @change="applyFilter" />
                </div>
                <div class="col-md-2">
                    <SelectInput v-model="filterForm.kesulitan" name="filter_kesulitan" label="Tingkat Kesulitan" placeholder="Semua" :options="kesulitanOptions" wrapper-class="" class="form-select-sm" @change="applyFilter" />
                </div>
                <div class="col-md-2">
                    <SelectInput v-model="filterForm.kategori_nilai" name="filter_kategori_nilai" label="Kategori Nilai" placeholder="Semua" :options="kategoriOptions" wrapper-class="" class="form-select-sm" @change="applyFilter" />
                </div>
                <div class="col-md-3">
                    <TextInput v-model="filterForm.search" name="filter_search" label="Cari Pertanyaan / Topik" placeholder="Ketik kata kunci..." wrapper-class="" class="form-control-sm" @keyup.enter="applyFilter" />
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <Button color="primary" class="w-100" @click="applyFilter">
                        Filter
                    </Button>
                    <Button color="outline-secondary" @click="resetFilter">
                        Reset
                    </Button>
                </div>
            </div>
        </Card>

        <!-- Soal List Table -->
        <Card title="Daftar Soal" icon="bi-list-check" body-class="p-0">
            <div v-if="soalList.length" class="p-3 border-bottom bg-light-subtle d-flex justify-content-between align-items-center">
                <span class="text-body-secondary small">Total {{ soalList.length }} butir soal tersedia di bank soal Anda.</span>
            </div>

            <TableWrapper stack v-if="soalList.length">
                <table class="table table-hover mb-0 app-table-proportional">
                    <colgroup>
                        <col class="u-w-4pct">
                        <col class="u-w-42pct">
                        <col class="u-w-14pct">
                        <col class="u-w-12pct">
                        <col class="u-w-10pct">
                        <col class="u-w-8pct">
                        <col class="u-w-10pct">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Pertanyaan & Opsi</th>
                            <th scope="col">Mata Pelajaran</th>
                            <th scope="col">Bab</th>
                            <th scope="col">Kesulitan</th>
                            <th scope="col">Kategori</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(soal, index) in soalList" :key="soal.id">
                            <td class="stack-hide">{{ index + 1 }}</td>
                            <td class="stack-title">
                                <div class="fw-semibold text-break mb-1">{{ soal.pertanyaan }}</div>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    <Badge color="primary">{{ tipeSoalOptions.find((item) => item.value === soal.tipe_soal)?.label ?? 'Pilihan Ganda' }}</Badge>
                                    <Badge v-if="soal.level_kognitif" color="info">{{ soal.level_kognitif }}</Badge>
                                </div>
                                <div class="small text-body-secondary ps-2 border-start">
                                    <template v-if="soal.opsi?.length">
                                        <div
                                            v-for="(opsi, oIdx) in soal.opsi"
                                            :key="opsi.id || oIdx"
                                            :class="opsi.is_benar ? 'text-success fw-bold' : ''"
                                            class="d-flex align-items-center gap-1"
                                        >
                                            <i v-if="opsi.is_benar" class="bi bi-check-circle-fill text-success" />
                                            <i v-else class="bi bi-circle text-body-secondary" style="font-size: 0.75rem;" />
                                            <span>
                                                {{ String.fromCharCode(65 + oIdx) }}. {{ opsi.teks_opsi }}
                                                <template v-if="soal.tipe_soal === 'menjodohkan' && opsi.pasangan_teks">&nbsp;→&nbsp; {{ opsi.pasangan_teks }}</template>
                                            </span>
                                        </div>
                                    </template>
                                    <span v-else-if="soal.tipe_soal === 'uraian'" class="fst-italic">Jawaban siswa berupa uraian teks.</span>
                                    <span v-else class="fst-italic">Belum ada opsi jawaban.</span>
                                </div>
                            </td>
                            <td data-label="Mata Pelajaran">
                                <span v-if="soal.mapel">{{ soal.mapel.nama_mapel }}</span>
                                <span v-else class="text-body-secondary fst-italic">Semua Mapel</span>
                            </td>
                            <td data-label="Bab">
                                <Badge v-if="soal.topik" color="secondary">{{ soal.topik }}</Badge>
                                <span v-else class="text-body-secondary">-</span>
                            </td>
                            <td data-label="Kesulitan">
                                <Badge :color="kesulitanBadgeColor(soal.kesulitan)">
                                    {{ soal.kesulitan }}
                                </Badge>
                            </td>
                            <td data-label="Kategori">
                                <Badge v-if="soal.kategori_nilai" color="info">
                                    {{ soal.kategori_nilai }}
                                </Badge>
                                <span v-else class="text-body-secondary">-</span>
                            </td>
                            <td class="stack-actions">
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
                message="Klik 'Tambah Soal' untuk membuat butir soal dengan tipe dan metadata yang sesuai."
                icon="bi-collection"
            />
        </Card>

        <!-- Modal Form Soal -->
        <Modal :model-value="isModalOpen" :title="editingSoal ? 'Edit Soal' : 'Tambah Soal ke Bank'" size="lg" @update:model-value="closeModal">
            <form id="soalBankForm" @submit.prevent="saveSoal">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <SelectInput v-model="form.mapel_id" name="mapel_id" label="Mata Pelajaran" placeholder="-- Umum / Tanpa Mapel --" :options="mapelOptions" :error="form.errors.mapel_id" wrapper-class="" />
                                </div>

                                <div class="col-md-6">
                                    <TextInput
                                        name="topik"
                                        v-model="form.topik"
                                        label="Bab (Opsional)"
                                        placeholder="Misal: Aljabar, Ekosistem..."
                                        :error="form.errors.topik"
                                    />
                                </div>

                                <div class="col-md-6">
                                    <SelectInput v-model="form.tipe_soal" name="tipe_soal" label="Tipe Soal" required :options="tipeSoalOptions" :error="form.errors.tipe_soal" wrapper-class="" @change="handleTipeSoalChange" />
                                </div>

                                <div class="col-md-6">
                                    <SelectInput v-model="form.level_kognitif" name="level_kognitif" label="Level Kognitif (Opsional)" placeholder="-- Belum ditentukan --" :options="levelKognitifOptions" :error="form.errors.level_kognitif" wrapper-class="" />
                                </div>

                                <div class="col-md-6">
                                    <SelectInput v-model="form.kesulitan" name="kesulitan" label="Tingkat Kesulitan" required :options="kesulitanOptions" :error="form.errors.kesulitan" wrapper-class="" />
                                </div>

                                <div class="col-md-6">
                                    <SelectInput v-model="form.kategori_nilai" name="kategori_nilai" label="Kategori Nilai Default (Opsional)" placeholder="-- Tidak Ditentukan --" :options="kategoriOptions" :error="form.errors.kategori_nilai" wrapper-class="" />
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
                                <TextareaInput
                                    name="pembahasan"
                                    v-model="form.pembahasan"
                                    label="Pembahasan / Umpan Balik (opsional)"
                                    placeholder="Jelaskan kunci atau alasan jawaban. Akan ditampilkan kepada siswa setelah ujian dikumpulkan."
                                    :error="form.errors.pembahasan"
                                    :rows="3"
                                />
                            </div>

                            <div v-if="requiresOptions" class="border rounded p-3 bg-light-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <label class="form-label fw-bold mb-0">
                                        {{ form.tipe_soal === 'benar_salah' ? 'Opsi Jawaban (Benar / Salah)' : form.tipe_soal === 'menjodohkan' ? 'Subsoal Menjodohkan' : 'Opsi Jawaban (Pilihan Ganda)' }}
                                    </label>
                                    <Button
                                        v-if="['pilihan_ganda', 'menjodohkan'].includes(form.tipe_soal) && form.opsi.length < 5"
                                        type="button"
                                        size="sm"
                                        color="outline-primary"
                                        icon="bi-plus-circle"
                                        @click="addOpsi"
                                    >
                                        Tambah Opsi
                                    </Button>
                                </div>

                                <div v-if="form.tipe_soal === 'menjodohkan'" class="alert alert-info py-2 small mb-3">
                                    <i class="bi bi-shuffle me-1" aria-hidden="true" />
                                    Setiap baris adalah satu nomor soal. Sisi kiri diisi pertanyaan, sisi kanan diisi kunci pasangan. Saat siswa mengerjakan, pilihan di sisi kanan dapat diacak.
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
                                        <div v-if="form.tipe_soal !== 'menjodohkan'" class="d-flex align-items-center gap-2 mb-1">
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
                                                    <Badge v-if="opsi.is_benar" color="success" class="ms-1">Kunci Jawaban</Badge>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                            <span v-if="form.tipe_soal === 'menjodohkan'" class="small fw-semibold text-primary">
                                                Subsoal {{ idx + 1 }}
                                            </span>
                                            <div class="ms-auto" v-if="['pilihan_ganda', 'menjodohkan'].includes(form.tipe_soal) && form.opsi.length > 2">
                                                <Button color="outline-danger" class="p-0 px-1" type="button" title="Hapus opsi" @click="removeOpsi(idx)">
                                                    <i class="bi bi-x" />
                                                </Button>
                                            </div>
                                        </div>

                                        <div v-if="form.tipe_soal === 'menjodohkan'" class="row g-2">
                                            <div class="col-md-6">
                                                <label :for="`pasangan-kiri-${idx}`" class="form-label small mb-1">Pertanyaan (sisi kiri)</label>
                                                <input
                                                    :id="`pasangan-kiri-${idx}`"
                                                    v-model="opsi.teks_opsi"
                                                    type="text"
                                                    class="form-control form-control-sm"
                                                    placeholder="Misal: Ibukota Indonesia adalah ..."
                                                    required
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <label :for="`pasangan-kanan-${idx}`" class="form-label small mb-1">Kunci pasangan (sisi kanan)</label>
                                                <input
                                                    :id="`pasangan-kanan-${idx}`"
                                                    v-model="opsi.pasangan_teks"
                                                    type="text"
                                                    class="form-control form-control-sm"
                                                    placeholder="Misal: Jakarta"
                                                    required
                                                />
                                            </div>
                                        </div>
                                        <input
                                            v-else
                                            v-model="opsi.teks_opsi"
                                            type="text"
                                            class="form-control form-control-sm"
                                            :placeholder="`Teks jawaban pilihan ${String.fromCharCode(65 + idx)}`"
                                            required
                                        />
                                    </div>
                                </div>
                            </div>

                            <div v-else class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                                Soal uraian akan dijawab siswa dalam bentuk teks. Penilaian dilakukan secara manual oleh guru setelah jawaban dikumpulkan.
                            </div>
            </form>

            <template #footer>
                <Button type="button" color="outline-secondary" @click="closeModal">Batal</Button>
                <Button type="submit" form="soalBankForm" color="primary" :disabled="form.processing">
                    {{ editingSoal ? 'Simpan Perubahan' : 'Tambahkan Soal' }}
                </Button>
            </template>
        </Modal>
    </AppShell>
</template>

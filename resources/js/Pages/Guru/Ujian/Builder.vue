<script setup lang="ts">
import type { PropType } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { TextareaInput, TextInput } from '../../../Components/Form';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, DashboardHero, EmptyState } from '../../../Components/UI';

interface BankSoalItem {
    id: number;
    mapel_id: number | null;
    mapel_nama: string | null;
    pertanyaan: string;
    topik: string | null;
    kesulitan: string;
    kategori_nilai: string | null;
    opsi_count: number;
}

interface ExistingSoal {
    id: number;
    soal_bank_id: number;
    poin: number;
    urutan: number;
    pertanyaan?: string;
    topik?: string | null;
    kesulitan?: string;
}

interface UjianData {
    id: number;
    judul: string;
    deskripsi: string | null;
    durasi_menit: number;
    kategori_nilai: string;
    waktu_mulai: string | null;
    waktu_selesai: string | null;
    acak_soal: boolean;
    acak_opsi: boolean;
    soal: ExistingSoal[];
}

const props = defineProps({
    kelasMapel: {
        type: Object as PropType<{ id: number; kelas: string; mata_pelajaran: string; mapel_id: number | null }>,
        required: true,
    },
    ujian: { type: Object as PropType<UjianData | null>, default: null },
    soalBank: { type: Array as PropType<BankSoalItem[]>, default: () => [] },
    topics: { type: Array as PropType<string[]>, default: () => [] },
    submitUrl: { type: String, required: true },
    isEdit: { type: Boolean, default: false },
    hasActiveAttempts: { type: Boolean, default: false },
});

const form = useForm({
    judul: props.ujian?.judul ?? '',
    deskripsi: props.ujian?.deskripsi ?? '',
    durasi_menit: props.ujian?.durasi_menit ?? 60,
    kategori_nilai: props.ujian?.kategori_nilai ?? 'NH',
    waktu_mulai: props.ujian?.waktu_mulai ?? '',
    waktu_selesai: props.ujian?.waktu_selesai ?? '',
    acak_soal: props.ujian ? Boolean(props.ujian.acak_soal) : true,
    acak_opsi: props.ujian ? Boolean(props.ujian.acak_opsi) : true,
    soal: (props.ujian?.soal ?? []).map((s) => ({
        soal_bank_id: s.soal_bank_id,
        poin: s.poin || 1,
    })) as { soal_bank_id: number; poin: number }[],
});

const bankFilter = ref({
    topik: '',
    kesulitan: '',
    mapelOnly: true,
    search: '',
});

const filteredBank = computed(() => {
    let list = props.soalBank;

    if (bankFilter.value.mapelOnly && props.kelasMapel.mapel_id) {
        list = list.filter((s) => !s.mapel_id || s.mapel_id === props.kelasMapel.mapel_id);
    }

    if (bankFilter.value.topik) {
        list = list.filter((s) => s.topik === bankFilter.value.topik);
    }

    if (bankFilter.value.kesulitan) {
        list = list.filter((s) => s.kesulitan === bankFilter.value.kesulitan);
    }

    if (bankFilter.value.search.trim()) {
        const kw = bankFilter.value.search.trim().toLowerCase();
        list = list.filter((s) =>
            s.pertanyaan.toLowerCase().includes(kw) || (s.topik && s.topik.toLowerCase().includes(kw))
        );
    }

    return list;
});

const selectedSoalIds = computed({
    get: () => new Set(form.soal.map((s) => s.soal_bank_id)),
    set: () => {},
});

function isSelected(soalBankId: number): boolean {
    return selectedSoalIds.value.has(soalBankId);
}

function toggleSoal(soal: BankSoalItem) {
    if (props.hasActiveAttempts) {
        return;
    }

    const index = form.soal.findIndex((s) => s.soal_bank_id === soal.id);
    if (index >= 0) {
        form.soal.splice(index, 1);
    } else {
        form.soal.push({
            soal_bank_id: soal.id,
            poin: 1,
        });
    }
}

function selectAllFiltered() {
    if (props.hasActiveAttempts) {
        return;
    }

    filteredBank.value.forEach((soal) => {
        if (!selectedSoalIds.value.has(soal.id)) {
            form.soal.push({
                soal_bank_id: soal.id,
                poin: 1,
            });
        }
    });
}

function clearAllSelected() {
    if (props.hasActiveAttempts) {
        return;
    }
    form.soal = [];
}

function getPoin(soalBankId: number): number {
    const found = form.soal.find((s) => s.soal_bank_id === soalBankId);
    return found ? found.poin : 1;
}

function setPoin(soalBankId: number, poin: number) {
    const found = form.soal.find((s) => s.soal_bank_id === soalBankId);
    if (found) {
        found.poin = Number(poin) || 1;
    }
}

function distributePointsEvenly() {
    if (form.soal.length === 0) return;
    const each = Math.round((100 / form.soal.length) * 100) / 100;
    form.soal.forEach((s) => {
        s.poin = each;
    });
}

const totalPoin = computed(() => {
    return form.soal.reduce((sum, s) => sum + Number(s.poin || 0), 0);
});

function submit() {
    if (props.isEdit) {
        form.put(props.submitUrl, {
            preserveScroll: true,
        });
    } else {
        form.post(props.submitUrl, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="`${isEdit ? 'Edit Ujian' : 'Buat Ujian Baru'}: ${kelasMapel.mata_pelajaran}`" />

    <AppShell title="Builder Ujian">
        <DashboardHero
            eyebrow="Ujian & CBT"
            :title="isEdit ? 'Edit Pengaturan Ujian' : 'Buat Ujian CBT Baru'"
            :subtitle="`${kelasMapel.mata_pelajaran} - ${kelasMapel.kelas}. Pilih butir soal dari bank soal dan atur bobot poin serta durasi.`"
            icon="bi-pencil-square"
            tone="teacher"
        >
            <template #actions>
                <Button :href="`/guru/ujian/${kelasMapel.id}/list`" color="light" icon="bi-arrow-left">
                    Kembali ke Daftar
                </Button>
            </template>
        </DashboardHero>

        <div v-if="hasActiveAttempts" class="alert alert-warning d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5" />
            <div>
                <strong>Ujian sedang berjalan atau sudah dikerjakan siswa.</strong>
                Daftar soal dan bobot poin terkunci untuk menjaga keadilan penilaian. Anda hanya dapat mengubah judul, deskripsi, dan jadwal ujian.
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="row g-4">
                <!-- Left: Configuration Details -->
                <div class="col-lg-5">
                    <Card title="Pengaturan Ujian" icon="bi-sliders" class="sticky-top" style="top: 80px;">
                        <div class="mb-3">
                            <TextInput
                                name="judul"
                                v-model="form.judul"
                                label="Judul Ujian"
                                placeholder="Misal: Penilaian Harian Bab 1, STS Ganjil..."
                                required
                                :error="form.errors.judul"
                            />
                        </div>

                        <div class="mb-3">
                            <TextareaInput
                                name="deskripsi"
                                v-model="form.deskripsi"
                                label="Petunjuk / Deskripsi (Opsional)"
                                placeholder="Petunjuk pengerjaan bagi siswa..."
                                :rows="2"
                                :error="form.errors.deskripsi"
                            />
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label required">Durasi (Menit)</label>
                                <input
                                    v-model.number="form.durasi_menit"
                                    type="number"
                                    min="1"
                                    max="600"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.durasi_menit }"
                                    :disabled="hasActiveAttempts"
                                    required
                                />
                                <div v-if="form.errors.durasi_menit" class="invalid-feedback">{{ form.errors.durasi_menit }}</div>
                            </div>

                            <div class="col-6">
                                <label class="form-label required">Kategori Nilai</label>
                                <select
                                    v-model="form.kategori_nilai"
                                    class="form-select"
                                    :class="{ 'is-invalid': form.errors.kategori_nilai }"
                                    :disabled="hasActiveAttempts"
                                    required
                                >
                                    <option value="NH">Nilai Harian (NH)</option>
                                    <option value="STS">STS</option>
                                    <option value="SAS">SAS</option>
                                    <option value="SAT">SAT</option>
                                </select>
                                <div v-if="form.errors.kategori_nilai" class="invalid-feedback">{{ form.errors.kategori_nilai }}</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Waktu Mulai Buka</label>
                                <input
                                    v-model="form.waktu_mulai"
                                    type="datetime-local"
                                    class="form-control form-control-sm"
                                    :class="{ 'is-invalid': form.errors.waktu_mulai }"
                                />
                                <div class="form-text small text-muted">Kosongkan jika langsung buka.</div>
                                <div v-if="form.errors.waktu_mulai" class="invalid-feedback">{{ form.errors.waktu_mulai }}</div>
                            </div>

                            <div class="col-6">
                                <label class="form-label">Waktu Selesai Tutup</label>
                                <input
                                    v-model="form.waktu_selesai"
                                    type="datetime-local"
                                    class="form-control form-control-sm"
                                    :class="{ 'is-invalid': form.errors.waktu_selesai }"
                                />
                                <div class="form-text small text-muted">Kosongkan jika tanpa batas.</div>
                                <div v-if="form.errors.waktu_selesai" class="invalid-feedback">{{ form.errors.waktu_selesai }}</div>
                            </div>
                        </div>

                        <div class="border rounded p-3 mb-4 bg-light-subtle">
                            <label class="form-label fw-bold mb-2">Pengacakan</label>
                            <div class="form-check form-switch mb-2">
                                <input
                                    id="switch-acak-soal"
                                    v-model="form.acak_soal"
                                    class="form-check-input"
                                    type="checkbox"
                                    :disabled="hasActiveAttempts"
                                />
                                <label class="form-check-label" for="switch-acak-soal">
                                    Acak urutan soal untuk tiap siswa
                                </label>
                            </div>
                            <div class="form-check form-switch">
                                <input
                                    id="switch-acak-opsi"
                                    v-model="form.acak_opsi"
                                    class="form-check-input"
                                    type="checkbox"
                                    :disabled="hasActiveAttempts"
                                />
                                <label class="form-check-label" for="switch-acak-opsi">
                                    Acak urutan opsi jawaban (A, B, C, D)
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center border-top pt-3">
                            <div>
                                <div class="small text-muted">Terpilih: <strong>{{ form.soal.length }} soal</strong></div>
                                <div class="small text-muted">Total Bobot: <strong>{{ totalPoin }} poin</strong></div>
                            </div>
                            <Button type="submit" color="primary" icon="bi-check-lg" :disabled="form.processing || form.soal.length === 0">
                                {{ isEdit ? 'Simpan Ujian' : 'Terbitkan Ujian' }}
                            </Button>
                        </div>
                    </Card>
                </div>

                <!-- Right: Question Selector Panel -->
                <div class="col-lg-7">
                    <Card title="Pilih Soal dari Bank Soal" icon="bi-collection-play-fill">
                        <div v-if="form.errors.soal" class="alert alert-danger py-2 small mb-3">
                            {{ form.errors.soal }}
                        </div>

                        <!-- Filter toolbar -->
                        <div class="row g-2 mb-3 align-items-center">
                            <div class="col-md-5">
                                <input
                                    v-model="bankFilter.search"
                                    type="text"
                                    class="form-control form-control-sm"
                                    placeholder="Cari teks soal..."
                                />
                            </div>
                            <div class="col-md-4">
                                <select v-model="bankFilter.topik" class="form-select form-select-sm">
                                    <option value="">Semua Topik</option>
                                    <option v-for="t in topics" :key="t" :value="t">{{ t }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select v-model="bankFilter.kesulitan" class="form-select form-select-sm">
                                    <option value="">Semua Kesulitan</option>
                                    <option value="mudah">Mudah</option>
                                    <option value="sedang">Sedang</option>
                                    <option value="sulit">Sulit</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div class="d-flex gap-2">
                                <Button
                                    v-if="!hasActiveAttempts"
                                    type="button"
                                    size="sm"
                                    color="light"
                                    icon="bi-check-all"
                                    @click="selectAllFiltered"
                                >
                                    Pilih Semua Tampil
                                </Button>
                                <Button
                                    v-if="!hasActiveAttempts && form.soal.length > 0"
                                    type="button"
                                    size="sm"
                                    color="light"
                                    icon="bi-x"
                                    @click="clearAllSelected"
                                >
                                    Batal Semua
                                </Button>
                            </div>

                            <div v-if="!hasActiveAttempts && form.soal.length > 0">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    title="Bagi poin rata sehingga total 100"
                                    @click="distributePointsEvenly"
                                >
                                    <i class="bi bi-calculator me-1" />
                                    Bagi Rata (100 Poin)
                                </button>
                            </div>
                        </div>

                        <!-- List of questions -->
                        <div v-if="filteredBank.length" class="d-flex flex-column gap-2" style="max-height: 600px; overflow-y: auto;">
                            <div
                                v-for="soal in filteredBank"
                                :key="soal.id"
                                class="p-3 border rounded transition-all"
                                :class="isSelected(soal.id) ? 'border-primary bg-primary-subtle' : 'bg-white'"
                            >
                                <div class="d-flex align-items-start gap-3">
                                    <div class="form-check mt-1">
                                        <input
                                            :id="`soal-check-${soal.id}`"
                                            type="checkbox"
                                            class="form-check-input"
                                            :checked="isSelected(soal.id)"
                                            :disabled="hasActiveAttempts"
                                            @change="toggleSoal(soal)"
                                        />
                                    </div>

                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            <Badge v-if="soal.topik" color="secondary">{{ soal.topik }}</Badge>
                                            <Badge :color="soal.kesulitan === 'mudah' ? 'success' : soal.kesulitan === 'sulit' ? 'danger' : 'warning text-dark'">
                                                {{ soal.kesulitan }}
                                            </Badge>
                                            <Badge v-if="soal.kategori_nilai" color="info">{{ soal.kategori_nilai }}</Badge>
                                            <span class="small text-muted ms-auto">{{ soal.opsi_count }} opsi</span>
                                        </div>

                                        <label :for="`soal-check-${soal.id}`" class="d-block cursor-pointer fw-semibold text-break mb-2">
                                            {{ soal.pertanyaan }}
                                        </label>

                                        <!-- Poin setting if selected -->
                                        <div v-if="isSelected(soal.id)" class="d-flex align-items-center gap-2 mt-2 pt-2 border-top border-primary-subtle">
                                            <span class="small fw-semibold text-primary">Bobot Poin:</span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                max="100"
                                                class="form-control form-control-sm"
                                                style="width: 80px;"
                                                :value="getPoin(soal.id)"
                                                :disabled="hasActiveAttempts"
                                                @input="setPoin(soal.id, Number(($event.target as HTMLInputElement).value))"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <EmptyState
                            v-else
                            title="Tidak ada soal ditemukan"
                            message="Tidak ada soal di bank soal yang sesuai filter. Buat soal baru di Bank Soal terlebih dahulu."
                            icon="bi-journal-x"
                        />
                    </Card>
                </div>
            </div>
        </form>
    </AppShell>
</template>

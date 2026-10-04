<script setup lang="ts">
import type { PropType } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { Button, Modal } from '../../../Components/UI';

interface OpsiItem {
    id: number;
    urutan: number;
    teks_opsi: string;
}

interface SoalAttemptItem {
    attempt_jawaban_id: number;
    urutan: number;
    pertanyaan: string;
    ragu_ragu: boolean;
    jawaban_opsi_id: number | null;
    opsi: OpsiItem[];
}

const props = defineProps({
    attempt: {
        type: Object as PropType<{
            id: number;
            status: 'in_progress' | 'submitted' | 'expired';
            sisa_detik: number;
            waktu_mulai: string | null;
            waktu_selesai: string | null;
            tab_switches_count: number;
        }>,
        required: true,
    },
    ujian: {
        type: Object as PropType<{
            id: number;
            judul: string;
            deskripsi: string | null;
            durasi_menit: number;
            kelas: string;
            mata_pelajaran: string;
        }>,
        required: true,
    },
    soalList: {
        type: Array as PropType<SoalAttemptItem[]>,
        required: true,
    },
    submitUrl: { type: String, required: true },
    simpanJawabanUrl: { type: String, required: true },
    logTabSwitchUrl: { type: String, required: true },
});

const currentIndex = ref(0);
const sisaDetik = ref(props.attempt.sisa_detik);
const isSubmitting = ref(false);
const showConfirmModal = ref(false);
const tabSwitches = ref(props.attempt.tab_switches_count);

// Keep local state for responsive UI
const localAnswers = ref<{ [attemptJawabanId: number]: { opsiId: number | null; ragu: boolean } }>({});

props.soalList.forEach((s) => {
    localAnswers.value[s.attempt_jawaban_id] = {
        opsiId: s.jawaban_opsi_id,
        ragu: Boolean(s.ragu_ragu),
    };
});

const currentSoal = computed(() => props.soalList[currentIndex.value] ?? null);

const totalSoal = computed(() => props.soalList.length);

const dijawabCount = computed(() => {
    return Object.values(localAnswers.value).filter((a) => a.opsiId !== null).length;
});

const raguCount = computed(() => {
    return Object.values(localAnswers.value).filter((a) => a.ragu).length;
});

const belumDijawabCount = computed(() => {
    return totalSoal.value - dijawabCount.value;
});

// Format timer
const formattedTime = computed(() => {
    const total = Math.max(0, sisaDetik.value);
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;

    const pad = (n: number) => String(n).padStart(2, '0');
    if (h > 0) {
        return `${pad(h)}:${pad(m)}:${pad(s)}`;
    }
    return `${pad(m)}:${pad(s)}`;
});

let timerInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    timerInterval = setInterval(() => {
        if (sisaDetik.value > 0) {
            sisaDetik.value -= 1;
        } else {
            if (timerInterval) clearInterval(timerInterval);
            autoSubmitOnTimeUp();
        }
    }, 1000);

    // Tab switch listener
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    if (timerInterval) clearInterval(timerInterval);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});

function handleVisibilityChange() {
    if (document.visibilityState === 'hidden') {
        tabSwitches.value += 1;
        router.post(
            props.logTabSwitchUrl,
            {},
            {
                preserveScroll: true,
                preserveState: true,
            }
        );
    }
}

function selectOpsi(opsiId: number) {
    if (!currentSoal.value || isSubmitting.value) return;

    const ajId = currentSoal.value.attempt_jawaban_id;
    localAnswers.value[ajId].opsiId = opsiId;

    saveCurrentAnswer();
}

function toggleRagu() {
    if (!currentSoal.value || isSubmitting.value) return;

    const ajId = currentSoal.value.attempt_jawaban_id;
    localAnswers.value[ajId].ragu = !localAnswers.value[ajId].ragu;

    saveCurrentAnswer();
}

function saveCurrentAnswer() {
    if (!currentSoal.value) return;

    const ajId = currentSoal.value.attempt_jawaban_id;
    const item = localAnswers.value[ajId];

    router.post(
        props.simpanJawabanUrl,
        {
            attempt_jawaban_id: ajId,
            jawaban_opsi_id: item.opsiId,
            ragu_ragu: item.ragu,
        },
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
}

function nextSoal() {
    if (currentIndex.value < totalSoal.value - 1) {
        currentIndex.value += 1;
    }
}

function prevSoal() {
    if (currentIndex.value > 0) {
        currentIndex.value -= 1;
    }
}

function goToSoal(idx: number) {
    currentIndex.value = idx;
}

function openConfirmModal() {
    showConfirmModal.value = true;
}

function closeConfirmModal() {
    showConfirmModal.value = false;
}

function submitUjian() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    router.post(
        props.submitUrl,
        {},
        {
            preserveScroll: false,
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
}

function autoSubmitOnTimeUp() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    router.post(props.submitUrl);
}
</script>

<template>
    <Head :title="`Ujian: ${ujian.judul}`" />

    <AppShell title="Pengerjaan Ujian">
        <!-- Top Header Bar for CBT -->
        <div class="card border-0 shadow-sm mb-4 bg-primary text-white">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="badge bg-white text-primary mb-1">
                            {{ ujian.mata_pelajaran }} - {{ ujian.kelas }}
                        </div>
                        <h4 class="h5 fw-bold mb-0 text-white">{{ ujian.judul }}</h4>
                    </div>

                    <!-- Countdown Timer -->
                    <div class="d-flex align-items-center gap-3">
                        <div
                            class="px-3 py-2 rounded-3 fw-bold d-flex align-items-center gap-2"
                            :class="sisaDetik < 300 ? 'bg-danger text-white animate__animated animate__pulse animate__infinite' : 'bg-white text-dark'"
                        >
                            <i class="bi bi-clock-fill" :class="sisaDetik < 300 ? 'text-white' : 'text-primary'" />
                            <span class="fs-5 font-monospace">{{ formattedTime }}</span>
                        </div>

                        <Button color="warning" size="" class="fw-bold"
                            type="button"
                           
                            @click="openConfirmModal"
                        >
                            <i class="bi bi-check2-circle me-1" />
                            Selesai Ujian
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left: Soal & Opsi Content -->
            <div class="col-lg-8">
                <div v-if="currentSoal" class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div class="fw-bold fs-6">
                            Soal No. <span class="badge bg-primary fs-6">{{ currentIndex + 1 }}</span>
                            <span class="text-body-secondary fw-normal"> dari {{ totalSoal }}</span>
                        </div>

                        <div>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="localAnswers[currentSoal.attempt_jawaban_id]?.ragu ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning'"
                                @click="toggleRagu"
                            >
                                <i class="bi bi-flag-fill me-1" />
                                {{ localAnswers[currentSoal.attempt_jawaban_id]?.ragu ? 'Ragu-ragu (Aktif)' : 'Ragu-ragu' }}
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <!-- Pertanyaan -->
                        <div class="fs-5 mb-4 text-dark lh-base u-ws-pre-wrap">
                            {{ currentSoal.pertanyaan }}
                        </div>

                        <!-- Opsi Jawaban -->
                        <div class="d-flex flex-column gap-3 mb-4">
                            <div
                                v-for="(opsi, oIdx) in currentSoal.opsi"
                                :key="opsi.id"
                                class="p-3 border rounded-3 cursor-pointer transition-all d-flex align-items-start gap-3"
                                :class="{
                                    'border-primary bg-primary-subtle text-primary fw-semibold': localAnswers[currentSoal.attempt_jawaban_id]?.opsiId === opsi.id,
                                    'hover-bg-light': localAnswers[currentSoal.attempt_jawaban_id]?.opsiId !== opsi.id,
                                }"
                                style="cursor: pointer;"
                                @click="selectOpsi(opsi.id)"
                            >
                                <div
                                    class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold border u-sq-32px"
                                    :class="localAnswers[currentSoal.attempt_jawaban_id]?.opsiId === opsi.id ? 'bg-primary text-white border-primary' : 'bg-light text-dark'"
                                   
                                >
                                    {{ String.fromCharCode(65 + oIdx) }}
                                </div>
                                <div class="pt-1 flex-grow-1 u-ws-pre-wrap">
                                    {{ opsi.teks_opsi }}
                                </div>
                            </div>
                        </div>

                        <!-- Prev / Next Controls -->
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <Button
                                type="button"
                                color="outline-secondary"
                                icon="bi-arrow-left"
                                :disabled="currentIndex === 0"
                                @click="prevSoal"
                            >
                                Sebelumnya
                            </Button>

                            <Button
                                v-if="currentIndex < totalSoal - 1"
                                type="button"
                                color="primary"
                                @click="nextSoal"
                            >
                                Selanjutnya
                                <i class="bi bi-arrow-right ms-1" />
                            </Button>

                            <Button
                                v-else
                                type="button"
                                color="success"
                                icon="bi-check2-circle"
                                @click="openConfirmModal"
                            >
                                Kumpulkan Jawaban
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Nomor Soal Grid Navigator -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top: 80px;">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold">
                            <i class="bi bi-grid-3x3-gap-fill me-1 text-primary" />
                            Navigasi Nomor Soal
                        </h6>
                    </div>

                    <div class="card-body p-3">
                        <!-- Legend -->
                        <div class="d-flex flex-wrap gap-2 small mb-3 pb-2 border-bottom">
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-soft-success p-1 u-sq-12px" />
                                <span>Sudah ({{ dijawabCount }})</span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-warning p-1 u-sq-12px" />
                                <span>Ragu ({{ raguCount }})</span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-light text-dark border p-1 u-sq-12px" />
                                <span>Belum ({{ belumDijawabCount }})</span>
                            </div>
                        </div>

                        <!-- Grid -->
                        <div class="d-grid gap-2" style="grid-template-columns: repeat(5, 1fr);">
                            <button
                                v-for="(soal, sIdx) in soalList"
                                :key="soal.attempt_jawaban_id"
                                type="button"
                                class="btn btn-sm fw-bold position-relative p-2"
                                :class="{
                                    'btn-primary ring-2': currentIndex === sIdx,
                                    'btn-warning text-dark': currentIndex !== sIdx && localAnswers[soal.attempt_jawaban_id]?.ragu,
                                    'btn-success': currentIndex !== sIdx && !localAnswers[soal.attempt_jawaban_id]?.ragu && localAnswers[soal.attempt_jawaban_id]?.opsiId !== null,
                                    'btn-outline-secondary': currentIndex !== sIdx && !localAnswers[soal.attempt_jawaban_id]?.ragu && localAnswers[soal.attempt_jawaban_id]?.opsiId === null,
                                }"
                                @click="goToSoal(sIdx)"
                            >
                                {{ sIdx + 1 }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Submit -->
        <Modal :model-value="showConfirmModal" title="Konfirmasi Selesai Ujian" @update:model-value="closeConfirmModal">
                        <p class="mb-3">
                            Apakah Anda yakin ingin menyelesaikan ujian ini? Jawaban yang sudah dikumpulkan tidak dapat diubah kembali.
                        </p>

                        <div class="bg-light p-3 rounded mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Total Soal:</span>
                                <span class="fw-bold">{{ totalSoal }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1 text-success">
                                <span>Sudah Dijawab:</span>
                                <span class="fw-bold">{{ dijawabCount }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1 text-warning">
                                <span>Ditandai Ragu-ragu:</span>
                                <span class="fw-bold">{{ raguCount }}</span>
                            </div>
                            <div class="d-flex justify-content-between text-danger">
                                <span>Belum Dijawab:</span>
                                <span class="fw-bold">{{ belumDijawabCount }}</span>
                            </div>
                        </div>

                        <div v-if="belumDijawabCount > 0" class="alert alert-warning py-2 small mb-0">
                            <i class="bi bi-exclamation-triangle me-1" />
                            Masih ada {{ belumDijawabCount }} soal yang belum Anda jawab!
                        </div>

            <template #footer>
                <Button type="button" color="light" size="" @click="closeConfirmModal">Kembali Kerjakan</Button>
                <Button type="button" color="primary" size="" :loading="isSubmitting" @click="submitUjian">
                    {{ isSubmitting ? 'Mengirim...' : 'Ya, Selesaikan Ujian' }}
                </Button>
            </template>
        </Modal>
    </AppShell>
</template>

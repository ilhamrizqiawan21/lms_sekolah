<script setup lang="ts">
import type { PropType } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button, Modal } from '../../../Components/UI';
import { useExamGuard } from '../../../composables/useExamGuard';
import { csrfHeaders } from '../../../utils/csrf';

interface OpsiItem {
    id: number;
    urutan: number;
    teks_opsi: string;
}

interface PasanganItem {
    id: number;
    urutan: number;
    teks_pasangan: string | null;
}

type TipeSoal = 'pilihan_ganda' | 'benar_salah' | 'menjodohkan' | 'uraian';

interface SoalAttemptItem {
    attempt_jawaban_id: number;
    urutan: number;
    pertanyaan: string;
    tipe_soal: TipeSoal;
    ragu_ragu: boolean;
    jawaban_opsi_id: number | null;
    jawaban_teks: string | null;
    jawaban_pasangan: Record<string, number>;
    opsi: OpsiItem[];
    pasangan: PasanganItem[];
}

interface AnswerState {
    opsiId: number | null;
    jawabanTeks: string;
    pasangan: Record<string, number>;
    ragu: boolean;
}

const props = defineProps({
    attempt: {
        type: Object as PropType<{
            id: number;
            status: 'sedang_mengerjakan' | 'selesai' | 'waktu_habis' | 'in_progress' | 'submitted' | 'expired';
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
    antiCheat: {
        type: Object as PropType<{
            aktif: boolean;
            maks_pelanggaran: number | null;
            wajib_fullscreen: boolean;
            pelanggaran: number;
        }>,
        required: true,
    },
    eventUrl: { type: String, required: true },
    heartbeatUrl: { type: String, required: true },
});

const currentIndex = ref(0);
const sisaDetik = ref(props.attempt.sisa_detik);
const isSubmitting = ref(false);
const isSaving = ref(false);
const saveError = ref<string | null>(null);
const showConfirmModal = ref(false);
// Anti-cheat: klien melaporkan, server yang memutuskan pelanggaran dan kumpul otomatis (lihat CbtAntiCheatService).
const {
    report: laporkanEvent,
    maksPelanggaran,
    peringatan,
    tutupPeringatan,
    perluLayarPenuh,
    layarPenuhError,
    masukLayarPenuh,
} = useExamGuard({
    enabled: props.antiCheat.aktif,
    wajibFullscreen: props.antiCheat.wajib_fullscreen,
    eventUrl: props.eventUrl,
    heartbeatUrl: props.heartbeatUrl,
    initialPelanggaran: props.antiCheat.pelanggaran,
    maksPelanggaran: props.antiCheat.maks_pelanggaran,
    // Server sudah menutup attempt (mis. melewati batas pelanggaran); arahkan ke Hasil kecuali sedang submit manual.
    onAttemptEnded: () => {
        if (!isSubmitting.value) router.reload();
    },
});

function onSalin(event: Event) {
    if (!props.antiCheat.aktif) return;
    event.preventDefault();
    laporkanEvent('salin');
}

function onKlikKanan(event: Event) {
    if (!props.antiCheat.aktif) return;
    event.preventDefault();
    laporkanEvent('klik_kanan');
}
let pendingSavePromise: Promise<void> | null = null;
const draftStorageKey = `lms:cbt:attempt:${props.attempt.id}:jawaban`;
const pendingAnswers = ref(new Map<number, AnswerState>());
const offlineMode = ref(typeof navigator !== 'undefined' && !navigator.onLine);
let syncingAnswers = false;
let retrySyncTimer: ReturnType<typeof setInterval> | null = null;

// Keep local state for responsive UI
const localAnswers = ref<{ [attemptJawabanId: number]: AnswerState }>({});

props.soalList.forEach((s) => {
    localAnswers.value[s.attempt_jawaban_id] = {
        opsiId: s.jawaban_opsi_id,
        jawabanTeks: s.jawaban_teks ?? '',
        pasangan: { ...s.jawaban_pasangan },
        ragu: Boolean(s.ragu_ragu),
    };
});

function answersEqual(left: AnswerState, right: AnswerState): boolean {
    const leftPairs = Object.entries(left.pasangan).sort(([a], [b]) => a.localeCompare(b));
    const rightPairs = Object.entries(right.pasangan).sort(([a], [b]) => a.localeCompare(b));

    return left.opsiId === right.opsiId
        && left.jawabanTeks === right.jawabanTeks
        && left.ragu === right.ragu
        && JSON.stringify(leftPairs) === JSON.stringify(rightPairs);
}

function persistDraft() {
    if (typeof window === 'undefined') return;

    try {
        window.localStorage.setItem(draftStorageKey, JSON.stringify(localAnswers.value));
    } catch {
        // Storage may be unavailable in private browsing; server sync remains the source of truth.
    }
}

function clearDraft() {
    if (typeof window === 'undefined') return;

    try {
        window.localStorage.removeItem(draftStorageKey);
    } catch {
        // Ignore storage cleanup failures.
    }
}

function hydrateDraft() {
    if (typeof window === 'undefined') return;

    try {
        const raw = window.localStorage.getItem(draftStorageKey);
        if (!raw) return;

        const stored = JSON.parse(raw) as Record<string, Partial<AnswerState>>;
        Object.entries(stored).forEach(([id, answer]) => {
            const attemptJawabanId = Number(id);
            if (!localAnswers.value[attemptJawabanId] || typeof answer !== 'object' || answer === null) return;

            const hydrated: AnswerState = {
                opsiId: typeof answer.opsiId === 'number' ? answer.opsiId : null,
                jawabanTeks: typeof answer.jawabanTeks === 'string' ? answer.jawabanTeks : '',
                pasangan: answer.pasangan && typeof answer.pasangan === 'object' ? answer.pasangan as Record<string, number> : {},
                ragu: Boolean(answer.ragu),
            };
            localAnswers.value[attemptJawabanId] = hydrated;
            pendingAnswers.value.set(attemptJawabanId, hydrated);
        });
    } catch {
        // Ignore a corrupt draft and continue with the server state.
    }
}

hydrateDraft();

function queueAnswer(attemptJawabanId: number) {
    const answer = localAnswers.value[attemptJawabanId];
    if (!answer) return;

    pendingAnswers.value = new Map(pendingAnswers.value).set(attemptJawabanId, { ...answer });
    persistDraft();
}

function removeSyncedAnswer(attemptJawabanId: number, snapshot: AnswerState) {
    const current = pendingAnswers.value.get(attemptJawabanId);
    if (!current || !answersEqual(current, snapshot)) return;

    const next = new Map(pendingAnswers.value);
    next.delete(attemptJawabanId);
    pendingAnswers.value = next;
}

async function flushAnswerQueue(): Promise<void> {
    if (syncingAnswers || (typeof navigator !== 'undefined' && !navigator.onLine)) return;

    syncingAnswers = true;
    try {
        for (const [attemptJawabanId, snapshot] of pendingAnswers.value.entries()) {
            const response = await fetch(props.simpanJawabanUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: JSON.stringify({
                    attempt_jawaban_id: attemptJawabanId,
                    jawaban_opsi_id: snapshot.opsiId,
                    jawaban_teks: snapshot.jawabanTeks || null,
                    jawaban_pasangan: snapshot.pasangan,
                    ragu_ragu: snapshot.ragu,
                }),
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => null);
                saveError.value = payload?.message ?? 'Jawaban belum tersinkron. Sistem akan mencoba lagi.';
                break;
            }

            removeSyncedAnswer(attemptJawabanId, snapshot);
            saveError.value = null;
        }
    } catch {
        saveError.value = 'Koneksi terputus. Jawaban disimpan sementara di perangkat dan akan dikirim saat koneksi pulih.';
    } finally {
        syncingAnswers = false;
    }
}

function onOnline() {
    offlineMode.value = false;
    void flushAnswerQueue();
}

function onOffline() {
    offlineMode.value = true;
    saveError.value = 'Koneksi terputus. Jawaban baru tetap disimpan sementara di perangkat.';
}

const currentSoal = computed(() => props.soalList[currentIndex.value] ?? null);

const totalSoal = computed(() => props.soalList.length);

const dijawabCount = computed(() => {
    return props.soalList.filter((soal) => {
        const answer = localAnswers.value[soal.attempt_jawaban_id];
        if (!answer) return false;
        if (soal.tipe_soal === 'uraian') return answer.jawabanTeks.trim().length > 0;
        if (soal.tipe_soal === 'menjodohkan') return Object.keys(answer.pasangan).length > 0;
        return answer.opsiId !== null;
    }).length;
});

const raguCount = computed(() => {
    return Object.values(localAnswers.value).filter((a) => a.ragu).length;
});

const belumDijawabCount = computed(() => {
    return totalSoal.value - dijawabCount.value;
});

function isSoalDijawab(soal: SoalAttemptItem): boolean {
    const answer = localAnswers.value[soal.attempt_jawaban_id];
    if (!answer) return false;
    if (soal.tipe_soal === 'uraian') return answer.jawabanTeks.trim().length > 0;
    if (soal.tipe_soal === 'menjodohkan') return Object.keys(answer.pasangan).length > 0;

    return answer.opsiId !== null;
}

// Format timer
const formattedTime = computed(() => {
    const total = Math.max(0, Math.floor(sisaDetik.value));
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

    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
    retrySyncTimer = setInterval(() => void flushAnswerQueue(), 10_000);
    void flushAnswerQueue();
});

onBeforeUnmount(() => {
    if (timerInterval) clearInterval(timerInterval);
    if (retrySyncTimer) clearInterval(retrySyncTimer);
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
});

function selectOpsi(opsiId: number) {
    if (!currentSoal.value || currentSoal.value.tipe_soal === 'uraian' || currentSoal.value.tipe_soal === 'menjodohkan' || isSubmitting.value) return;

    const ajId = currentSoal.value.attempt_jawaban_id;
    localAnswers.value[ajId].opsiId = opsiId;

    void saveCurrentAnswer();
}

function updateJawabanTeks(value: string) {
    if (!currentSoal.value || currentSoal.value.tipe_soal !== 'uraian' || isSubmitting.value) return;

    const attemptJawabanId = currentSoal.value.attempt_jawaban_id;
    localAnswers.value[attemptJawabanId].jawabanTeks = value;
    // Keep the latest keystrokes in the offline queue too, so a timer expiry
    // while the textarea still has focus cannot drop the essay answer.
    queueAnswer(attemptJawabanId);
}

function saveJawabanTeks() {
    if (!currentSoal.value || currentSoal.value.tipe_soal !== 'uraian') return;

    void saveCurrentAnswer();
}

function selectPasangan(kiriId: number, kananId: string) {
    if (!currentSoal.value || currentSoal.value.tipe_soal !== 'menjodohkan' || isSubmitting.value) return;

    const pasangan = { ...localAnswers.value[currentSoal.value.attempt_jawaban_id].pasangan };
    const parsedKananId = Number(kananId);
    Object.keys(pasangan).forEach((key) => {
        if (pasangan[key] === parsedKananId && Number(key) !== kiriId) delete pasangan[key];
    });

    if (parsedKananId > 0) pasangan[String(kiriId)] = parsedKananId;
    else delete pasangan[String(kiriId)];

    localAnswers.value[currentSoal.value.attempt_jawaban_id].pasangan = pasangan;
    void saveCurrentAnswer();
}

function handleOpsiKeydown(event: KeyboardEvent, opsiIndex: number) {
    if (!currentSoal.value) return;

    if (!['ArrowDown', 'ArrowRight', 'ArrowUp', 'ArrowLeft'].includes(event.key)) return;

    event.preventDefault();
    const direction = event.key === 'ArrowDown' || event.key === 'ArrowRight' ? 1 : -1;
    const nextIndex = (opsiIndex + direction + currentSoal.value.opsi.length) % currentSoal.value.opsi.length;
    const nextOpsi = currentSoal.value.opsi[nextIndex];
    selectOpsi(nextOpsi.id);

    requestAnimationFrame(() => {
        document.querySelector<HTMLElement>(`[data-opsi-index="${nextIndex}"]`)?.focus();
    });
}

function toggleRagu() {
    if (!currentSoal.value || isSubmitting.value) return;

    const ajId = currentSoal.value.attempt_jawaban_id;
    localAnswers.value[ajId].ragu = !localAnswers.value[ajId].ragu;

    void saveCurrentAnswer();
}

function saveCurrentAnswer(): Promise<void> {
    if (!currentSoal.value) return Promise.resolve();

    const ajId = currentSoal.value.attempt_jawaban_id;
    queueAnswer(ajId);

    isSaving.value = true;
    const task = flushAnswerQueue().finally(() => {
        isSaving.value = false;
    });

    pendingSavePromise = task;
    return task;
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

async function submitUjian() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    if (pendingSavePromise) {
        await pendingSavePromise;
    }

    await flushAnswerQueue();
    if (pendingAnswers.value.size > 0) {
        isSubmitting.value = false;
        saveError.value = 'Masih ada jawaban yang belum tersinkron. Pulihkan koneksi internet sebelum mengumpulkan ujian.';
        return;
    }

    router.post(
        props.submitUrl,
        {},
        {
            preserveScroll: false,
            onSuccess: clearDraft,
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
}

async function autoSubmitOnTimeUp() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    if (pendingSavePromise) {
        await pendingSavePromise;
    }

    router.post(props.submitUrl, {}, { onSuccess: clearDraft });
}
</script>

<template>
    <Head :title="`Ujian: ${ujian.judul}`" />

    <div class="exam-page">
        <div :inert="perluLayarPenuh">
        <!-- Top Header Bar for CBT -->
        <div class="card border mb-3 cbt-shell">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="badge bg-secondary-subtle text-secondary-emphasis mb-1">
                            {{ ujian.mata_pelajaran }} &bull; {{ ujian.kelas }}
                        </div>
                        <h1 class="h5 fw-bold mb-0">{{ ujian.judul }}</h1>
                    </div>

                    <!-- Countdown Timer -->
                    <div class="d-flex align-items-center gap-2">
                        <span v-if="isSaving || pendingAnswers.size" class="small" :class="offlineMode ? 'text-warning' : 'text-body-secondary'" role="status">
                            <i class="bi me-1" :class="offlineMode ? 'bi-cloud-slash' : 'bi-cloud-arrow-up'" aria-hidden="true" />
                            {{ offlineMode ? 'Tersimpan lokal, menunggu koneksi...' : 'Menyimpan...' }}
                        </span>
                        <div
                            class="px-3 py-1 rounded border fw-bold d-flex align-items-center gap-2"
                            :class="sisaDetik < 300 ? 'bg-danger text-white border-danger' : 'bg-body text-body'"
                        >
                            <i class="bi bi-clock" :class="sisaDetik < 300 ? 'text-white' : 'text-primary'" />
                            <span class="fs-5 tabular-nums font-monospace">{{ formattedTime }}</span>
                        </div>

                        <Button
                            color="warning"
                            size=""
                            class="fw-bold text-dark"
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

        <div v-if="saveError" class="alert alert-danger py-2 small mb-3" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-1" aria-hidden="true" />
            {{ saveError }}
        </div>

        <div v-if="peringatan" class="alert alert-warning d-flex justify-content-between align-items-start gap-2 py-2 small mb-3" role="alert">
            <span><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true" />{{ peringatan }}</span>
            <button type="button" class="btn-close" aria-label="Tutup peringatan" @click="tutupPeringatan" />
        </div>
        <div v-if="layarPenuhError" class="alert alert-info py-2 small mb-3" role="status">
            {{ layarPenuhError }}
        </div>

        <div class="row g-4">
            <!-- Left: Soal & Opsi Content -->
            <div class="col-lg-8">
                <div v-if="currentSoal" class="card border">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <div class="fw-bold fs-6">
                            Soal No. <span class="badge bg-primary fs-6">{{ currentIndex + 1 }}</span>
                            <span class="text-body-secondary fw-normal"> dari {{ totalSoal }}</span>
                        </div>

                        <div>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="localAnswers[currentSoal.attempt_jawaban_id]?.ragu ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning'"
                                :aria-pressed="Boolean(localAnswers[currentSoal.attempt_jawaban_id]?.ragu)"
                                @click="toggleRagu"
                            >
                                <i class="bi bi-flag-fill me-1" />
                                {{ localAnswers[currentSoal.attempt_jawaban_id]?.ragu ? 'Ragu-ragu (Aktif)' : 'Ragu-ragu' }}
                            </button>
                        </div>
                    </div>

                    <!-- Penghalang kasual (mudah dilewati lewat DevTools); setiap upaya tetap dicatat server. -->
                    <div
                        class="card-body p-4"
                        @copy="onSalin"
                        @contextmenu="onKlikKanan"
                    >
                        <!-- Pertanyaan -->
                        <div class="fs-5 mb-4 text-strong lh-base u-ws-pre-wrap">
                            {{ currentSoal.pertanyaan }}
                        </div>

                        <!-- Pilihan Ganda / Benar-Salah -->
                        <div
                            v-if="currentSoal.tipe_soal === 'pilihan_ganda' || currentSoal.tipe_soal === 'benar_salah'"
                            class="d-flex flex-column gap-3 mb-4"
                            role="radiogroup"
                            :aria-label="`Pilihan jawaban soal nomor ${currentIndex + 1}`"
                        >
                            <div
                                v-for="(opsi, oIdx) in currentSoal.opsi"
                                :key="opsi.id"
                                role="radio"
                                :tabindex="localAnswers[currentSoal.attempt_jawaban_id]?.opsiId === opsi.id || (!localAnswers[currentSoal.attempt_jawaban_id]?.opsiId && oIdx === 0) ? 0 : -1"
                                :data-opsi-index="oIdx"
                                :aria-checked="localAnswers[currentSoal.attempt_jawaban_id]?.opsiId === opsi.id"
                                class="p-3 border rounded-3 cursor-pointer transition-all d-flex align-items-start gap-3"
                                :class="{
                                    'border-primary bg-primary-subtle text-primary fw-semibold': localAnswers[currentSoal.attempt_jawaban_id]?.opsiId === opsi.id,
                                    'hover-bg-light': localAnswers[currentSoal.attempt_jawaban_id]?.opsiId !== opsi.id,
                                }"
                                @click="selectOpsi(opsi.id)"
                                @keydown.enter.prevent="selectOpsi(opsi.id)"
                                @keydown.space.prevent="selectOpsi(opsi.id)"
                                @keydown="handleOpsiKeydown($event, oIdx)"
                            >
                                <div
                                    class="exam-option-letter rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold border u-sq-32px"
                                    :class="localAnswers[currentSoal.attempt_jawaban_id]?.opsiId === opsi.id ? 'bg-primary text-white border-primary' : ''"
                                >
                                    {{ String.fromCharCode(65 + oIdx) }}
                                </div>
                                <div class="pt-1 flex-grow-1 u-ws-pre-wrap">
                                    {{ opsi.teks_opsi }}
                                </div>
                            </div>
                        </div>

                        <!-- Menjodohkan -->
                        <div v-else-if="currentSoal.tipe_soal === 'menjodohkan'" class="mb-4">
                            <div class="alert alert-info py-2 small">
                                <i class="bi bi-info-circle me-1" aria-hidden="true" />
                                Pilih pasangan yang sesuai untuk setiap subsoal. Satu pilihan hanya dapat digunakan sekali.
                            </div>
                            <div class="d-flex flex-column gap-3">
                                <div v-for="(opsi, idx) in currentSoal.opsi" :key="opsi.id" class="matching-row border rounded-3 p-3">
                                    <div class="small text-body-secondary mb-1">Subsoal {{ idx + 1 }}</div>
                                    <div class="fw-semibold mb-2 u-ws-pre-wrap">{{ opsi.teks_opsi }}</div>
                                    <select
                                        class="form-select"
                                        :value="localAnswers[currentSoal.attempt_jawaban_id]?.pasangan[String(opsi.id)] ?? ''"
                                        :aria-label="`Pasangan untuk subsoal ${idx + 1}`"
                                        @change="selectPasangan(opsi.id, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option value="">Pilih pasangan...</option>
                                        <option v-for="pasangan in currentSoal.pasangan" :key="pasangan.id" :value="pasangan.id">
                                            {{ pasangan.teks_pasangan }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Essay -->
                        <div v-else class="mb-4">
                            <label :for="`jawaban-essay-${currentSoal.attempt_jawaban_id}`" class="form-label fw-semibold">
                                Jawaban Anda
                            </label>
                            <textarea
                                :id="`jawaban-essay-${currentSoal.attempt_jawaban_id}`"
                                class="form-control"
                                rows="8"
                                maxlength="20000"
                                :value="localAnswers[currentSoal.attempt_jawaban_id]?.jawabanTeks ?? ''"
                                placeholder="Tuliskan jawaban Anda..."
                                @input="updateJawabanTeks(($event.target as HTMLTextAreaElement).value)"
                                @blur="saveJawabanTeks"
                            />
                            <div class="d-flex justify-content-between mt-2 small text-body-secondary">
                                <span>Jawaban essay akan dinilai manual oleh guru.</span>
                                <span>{{ (localAnswers[currentSoal.attempt_jawaban_id]?.jawabanTeks ?? '').length }}/20.000</span>
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
                <div class="card border sticky-top exam-question-nav">
                    <div class="card-header py-3">
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
                                <span class="badge bg-body text-body border p-1 u-sq-12px" />
                                <span>Belum ({{ belumDijawabCount }})</span>
                            </div>
                        </div>

                        <!-- Grid -->
                        <div class="d-grid gap-2 exam-question-grid">
                            <button
                                v-for="(soal, sIdx) in soalList"
                                :key="soal.attempt_jawaban_id"
                                type="button"
                                class="btn btn-sm fw-bold position-relative p-2"
                                :class="{
                                    'btn-primary ring-2': currentIndex === sIdx,
                                    'btn-warning text-dark': currentIndex !== sIdx && localAnswers[soal.attempt_jawaban_id]?.ragu,
                                    'btn-success': currentIndex !== sIdx && !localAnswers[soal.attempt_jawaban_id]?.ragu && isSoalDijawab(soal),
                                    'btn-outline-secondary': currentIndex !== sIdx && !localAnswers[soal.attempt_jawaban_id]?.ragu && !isSoalDijawab(soal),
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

                        <div class="exam-summary-surface p-3 rounded mb-3">
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
        </div>
        <!-- Wajib layar penuh: menutupi soal sampai siswa masuk (atau kembali ke) layar penuh. -->
        <div v-if="perluLayarPenuh" class="exam-guard-overlay" role="dialog" aria-modal="true" aria-labelledby="exam-guard-title">
            <div class="exam-guard-panel">
                <i class="bi bi-fullscreen fs-2 text-primary" aria-hidden="true" />
                <h2 id="exam-guard-title" class="h5 fw-bold mt-2">Ujian ini wajib dikerjakan dalam layar penuh</h2>
                <p class="text-body-secondary mb-3">
                    Keluar dari layar penuh dicatat sebagai pelanggaran<template v-if="maksPelanggaran"> (batas {{ maksPelanggaran }} kali, setelah itu jawaban dikumpulkan otomatis)</template>.
                    Waktu ujian tetap berjalan.
                </p>
                <button type="button" class="btn btn-primary" @click="masukLayarPenuh">
                    <i class="bi bi-arrows-fullscreen me-1" aria-hidden="true" />Masuk layar penuh
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.exam-page {
    position: fixed;
    inset: 0;
    z-index: 2000;
    overflow: auto;
    min-height: 100dvh;
    padding: clamp(0.75rem, 2vw, 2rem);
    background: var(--surface-muted, #f5f7fb);
    color: var(--text-body);
}

.exam-page .cbt-shell {
    margin-inline: auto;
}

.exam-question-nav {
    top: 1rem;
}

.exam-question-grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

.exam-option-letter:not(.bg-primary) {
    background: var(--surface-subtle);
    color: var(--text-body);
}

.matching-row {
    background: var(--surface-subtle);
}

.exam-summary-surface {
    background: var(--surface-muted);
    color: var(--text-body);
}

.exam-guard-overlay {
    position: fixed;
    inset: 0;
    z-index: 4000;
    display: grid;
    place-items: center;
    padding: 1.5rem;
    background: rgba(7, 15, 29, 0.94);
    color: #f8fafc;
    text-align: center;
}

.exam-guard-panel {
    width: min(100%, 32rem);
    padding: clamp(1.5rem, 5vw, 3rem);
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 1rem;
    background: #101c31;
    box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, 0.4);
}

.exam-guard-panel p {
    color: #cbd5e1;
}

@media (max-width: 991.98px) {
    .exam-page {
        padding: 0.75rem;
    }

    .exam-question-nav {
        position: static;
    }
}

@media (max-width: 575.98px) {
    .exam-page {
        padding: 0.5rem;
    }

    .exam-page .card-body {
        padding: 1rem !important;
    }

    .exam-question-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
</style>

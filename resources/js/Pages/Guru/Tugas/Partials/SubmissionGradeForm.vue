<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '../../../../Components/UI';

const props = defineProps({
    item: { type: Object, required: true },
    compact: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const nilai = ref(props.item.nilai_input ?? props.item.nilai ?? '');
const catatan = ref(props.item.catatan ?? '');
const localError = ref('');
const saveState = ref('idle');
const lastSavedAt = ref(null);
let autosaveTimer = null;
let activeSaveId = 0;
let savedSnapshot = snapshot();

const isSaving = computed(() => saveState.value === 'saving');
const saveLabel = computed(() => {
    if (saveState.value === 'pending') return 'Menunggu autosave';
    if (saveState.value === 'saving') return 'Menyimpan...';
    if (saveState.value === 'saved') return 'Tersimpan';
    if (saveState.value === 'error') return 'Gagal tersimpan';

    return '';
});

watch(
    () => props.item,
    (item) => {
        nilai.value = item.nilai_input ?? item.nilai ?? '';
        catatan.value = item.catatan ?? '';
        localError.value = '';
        saveState.value = 'idle';
        savedSnapshot = snapshot();
    },
);

watch([nilai, catatan], () => {
    scheduleAutosave();
});

onBeforeUnmount(() => {
    clearTimeout(autosaveTimer);
});

function snapshot() {
    return JSON.stringify({
        nilai: String(nilai.value ?? '').trim(),
        catatan: String(catatan.value ?? '').trim(),
    });
}

function isBlank() {
    return String(nilai.value ?? '').trim() === '' && String(catatan.value ?? '').trim() === '';
}

function validateDraft() {
    localError.value = '';

    const nilaiText = normalizedNilai();
    if (nilaiText === '') {
        return true;
    }

    const numericValue = Number(nilaiText);
    if (Number.isNaN(numericValue) || numericValue < 0 || numericValue > 100) {
        localError.value = 'Nilai harus antara 0 sampai 100.';
        saveState.value = 'error';
        return false;
    }

    return true;
}

function normalizedNilai() {
    return String(nilai.value ?? '').trim().replace(',', '.');
}

function normalizePastedScore(value) {
    const score = String(value ?? '').trim().replace(',', '.');
    return score === '' ? '' : score;
}

function pastedScores(event) {
    const text = event.clipboardData?.getData('text/plain') ?? '';
    if (!/[\r\n\t]/.test(text)) {
        return [];
    }

    return text
        .split(/\r\n|\n|\r/)
        .flatMap((row) => row.split('\t'))
        .map((cell) => normalizePastedScore(cell))
        .filter((score) => score !== '');
}

function pasteScoresIntoRows(event) {
    if (!props.compact) {
        return;
    }

    const scores = pastedScores(event);
    if (scores.length <= 1) {
        return;
    }

    event.preventDefault();

    const inputs = Array.from(document.querySelectorAll('.js-assignment-score-input'));
    const startIndex = inputs.indexOf(event.currentTarget);
    if (startIndex < 0) {
        return;
    }

    scores.forEach((score, index) => {
        const input = inputs[startIndex + index];
        if (!input) {
            return;
        }

        input.value = score;
        input.dispatchEvent(new InputEvent('input', { bubbles: true, data: score, inputType: 'insertFromPaste' }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

function scheduleAutosave() {
    clearTimeout(autosaveTimer);

    if (snapshot() === savedSnapshot) {
        saveState.value = 'idle';
        return;
    }

    if (isBlank()) {
        saveState.value = 'idle';
        return;
    }

    if (!validateDraft()) {
        return;
    }

    saveState.value = 'pending';
    autosaveTimer = setTimeout(() => saveNow(), 700);
}

async function saveNow() {
    clearTimeout(autosaveTimer);
    localError.value = '';

    if (isBlank()) {
        localError.value = 'Isi nilai atau komentar/perbaikan terlebih dahulu.';
        saveState.value = 'idle';
        return;
    }

    if (!validateDraft()) {
        return;
    }

    const saveId = ++activeSaveId;
    saveState.value = 'saving';

    try {
        const response = await fetch(props.item.nilai_url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                nilai: normalizedNilai(),
                catatan: String(catatan.value ?? '').trim(),
            }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errors = data.errors ?? {};
            throw new Error(errors.nilai?.[0] || errors.catatan?.[0] || data.message || 'Nilai gagal disimpan.');
        }

        if (saveId !== activeSaveId) {
            return;
        }

        savedSnapshot = snapshot();
        saveState.value = 'saved';
        lastSavedAt.value = data.saved_at ?? new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        Object.assign(props.item, {
            id: data.id ?? props.item.id,
            status: data.status ?? props.item.status,
            nilai: data.nilai ?? props.item.nilai,
            nilai_input: data.nilai_input ?? props.item.nilai_input,
            catatan: data.catatan ?? props.item.catatan,
            penalty_terlambat: data.penalty_terlambat ?? props.item.penalty_terlambat,
        });
    } catch (error) {
        if (saveId !== activeSaveId) {
            return;
        }

        localError.value = error.message || 'Nilai gagal disimpan.';
        saveState.value = 'error';
    }
}
</script>

<template>
    <form v-if="compact" class="d-inline" @submit.prevent="saveNow">
        <div class="input-group input-group-sm" style="width:130px">
            <input
                v-model="nilai"
                type="text"
                inputmode="decimal"
                name="nilai"
                class="form-control form-control-sm js-assignment-score-input"
                autocomplete="off"
                pattern="^\\d{1,3}([,.]\\d{1,2})?$"
                @paste="pasteScoresIntoRows"
            >
            <Button type="submit" color="success" size="sm" :disabled="isSaving" title="Simpan nilai" aria-label="Simpan nilai">
                <span v-if="isSaving" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <i v-else class="bi bi-check" aria-hidden="true"></i>
            </Button>
        </div>
        <small v-if="saveLabel || localError" class="autosave-status d-block mt-1" :class="{ 'text-danger': saveState === 'error', 'text-success': saveState === 'saved', 'text-muted': saveState !== 'error' && saveState !== 'saved' }">
            {{ localError || saveLabel }}
        </small>
    </form>

    <form v-else class="w-100" @submit.prevent="saveNow">
        <div class="row g-2">
            <div class="col-md-4">
                <input
                    v-model="nilai"
                    type="text"
                    inputmode="decimal"
                    name="nilai"
                    class="form-control"
                    autocomplete="off"
                    pattern="^\\d{1,3}([,.]\\d{1,2})?$"
                    placeholder="Nilai"
                >
                <div v-if="Number(item.penalty_terlambat || 0) > 0" class="form-text text-danger">
                    Nilai akhir {{ item.nilai }} setelah penalti {{ item.penalty_terlambat }} poin.
                </div>
            </div>
            <div class="col-md-5">
                <input
                    v-model="catatan"
                    type="text"
                    name="catatan"
                    class="form-control"
                    placeholder="Komentar/perbaikan, boleh tanpa nilai"
                >
                <div class="form-text">
                    Komentar tanpa nilai akan mengembalikan tugas ke siswa sebagai perlu perbaikan.
                </div>
            </div>
            <div class="col-md-3">
                <Button type="submit" color="success" size="" class="w-100" :disabled="isSaving">
                    <span v-if="isSaving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    <i v-else class="bi bi-check me-1" aria-hidden="true"></i> Simpan
                </Button>
            </div>
        </div>
        <div class="autosave-footer mt-2">
            <span v-if="localError" class="text-danger small">{{ localError }}</span>
            <span v-else-if="saveLabel" class="small" :class="{ 'text-success': saveState === 'saved', 'text-muted': saveState !== 'saved' }">
                {{ saveLabel }}<template v-if="lastSavedAt"> {{ lastSavedAt }}</template>
            </span>
        </div>
    </form>
</template>

<style scoped>
.autosave-status,
.autosave-footer {
    min-height: 1rem;
}
</style>

<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Button } from '../../../../Components/UI';

const props = defineProps({
    item: { type: Object, required: true },
    compact: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const form = useForm({
    nilai: props.item.nilai_input ?? props.item.nilai ?? '',
    catatan: props.item.catatan ?? '',
});
const localError = ref('');

watch(
    () => props.item,
    (item) => {
        form.nilai = item.nilai_input ?? item.nilai ?? '';
        form.catatan = item.catatan ?? '';
        localError.value = '';
        form.clearErrors();
    },
);

function submit() {
    localError.value = '';

    if (!props.compact && String(form.nilai ?? '').trim() === '' && String(form.catatan ?? '').trim() === '') {
        localError.value = 'Isi nilai atau komentar/perbaikan terlebih dahulu.';
        return;
    }

    form.post(props.item.nilai_url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <form v-if="compact" class="d-inline" @submit.prevent="submit">
        <div class="input-group input-group-sm" style="width:130px">
            <input
                v-model="form.nilai"
                type="number"
                name="nilai"
                class="form-control form-control-sm"
                min="0"
                max="100"
                step="0.01"
            >
            <Button type="submit" color="success" size="sm" :disabled="form.processing" title="Simpan nilai" aria-label="Simpan nilai">
                <i class="bi bi-check" aria-hidden="true"></i>
            </Button>
        </div>
    </form>

    <form v-else class="w-100" @submit.prevent="submit">
        <div class="row g-2">
            <div class="col-md-4">
                <input
                    v-model="form.nilai"
                    type="number"
                    name="nilai"
                    class="form-control"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="Nilai"
                >
                <div v-if="Number(item.penalty_terlambat || 0) > 0" class="form-text text-danger">
                    Nilai akhir {{ item.nilai }} setelah penalti {{ item.penalty_terlambat }} poin.
                </div>
            </div>
            <div class="col-md-5">
                <input
                    v-model="form.catatan"
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
                <Button type="submit" color="success" size="" class="w-100" :disabled="form.processing">
                    <i class="bi bi-check me-1" aria-hidden="true"></i> Simpan
                </Button>
            </div>
        </div>
        <div v-if="localError || form.errors.nilai || form.errors.catatan" class="text-danger small mt-2">
            {{ localError || form.errors.nilai || form.errors.catatan }}
        </div>
    </form>
</template>

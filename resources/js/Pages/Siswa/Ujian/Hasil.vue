<script setup lang="ts">
import type { PropType } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, DashboardHero } from '../../../Components/UI';

interface AttemptSummary {
    id: number;
    status: string;
    skor_total: number;
    skor_maksimal: number;
    skor_100: number;
    waktu_mulai: string | null;
    waktu_submit: string | null;
    total_soal: number;
    benar_count: number;
    salah_count: number;
    tidak_dijawab_count: number;
}

const props = defineProps({
    attempt: {
        type: Object as PropType<AttemptSummary>,
        required: true,
    },
    ujian: {
        type: Object as PropType<{
            id: number;
            judul: string;
            mata_pelajaran: string;
            kategori_nilai: string;
        }>,
        required: true,
    },
    daftarUjianUrl: { type: String, required: true },
});
</script>

<template>
    <Head :title="`Hasil Ujian: ${ujian.judul}`" />

    <AppShell title="Hasil Ujian CBT">
        <DashboardHero
            eyebrow="Evaluasi & Nilai"
            :title="ujian.judul"
            :subtitle="`Mata Pelajaran: ${ujian.mata_pelajaran} &bull; Kategori Nilai: ${ujian.kategori_nilai}`"
            icon="bi-award-fill"
            tone="student"
        >
            <template #actions>
                <Button :href="daftarUjianUrl" color="light" icon="bi-arrow-left">
                    Kembali ke Daftar Ujian
                </Button>
            </template>
        </DashboardHero>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <Card title="Hasil Pengerjaan Ujian" icon="bi-trophy-fill">
                    <!-- Status Banner -->
                    <div class="text-center py-4 border-bottom mb-4">
                        <div class="mb-2">
                            <Badge
                                class="p-2 px-3 fs-6"
                                :color="attempt.status === 'selesai' ? 'success' : 'warning'"
                            >
                                <i class="bi me-1" :class="attempt.status === 'selesai' ? 'bi-check-circle-fill' : 'bi-clock-history'" />
                                {{ attempt.status === 'selesai' ? 'Ujian Berhasil Dikumpulkan' : 'Waktu Ujian Telah Habis' }}
                            </Badge>
                        </div>

                        <span class="text-body-secondary small">Nilai Akhir Ujian (Skala 100)</span>
                        <h1 class="display-3 fw-bold text-primary mb-1">{{ attempt.skor_100 }}</h1>
                        <p class="text-body-secondary small mb-0">
                            Total Poin Diperoleh: {{ attempt.skor_total }} dari {{ attempt.skor_maksimal }} Poin
                        </p>
                    </div>

                    <!-- Breakdown Statistics -->
                    <div class="row g-3 text-center mb-4">
                        <div class="col-4">
                            <div class="p-3 border rounded bg-success-subtle">
                                <i class="bi bi-check-circle-fill text-success fs-4 d-block mb-1" />
                                <span class="small text-body-secondary">Jawaban Benar</span>
                                <h4 class="fw-bold text-success mb-0">{{ attempt.benar_count }}</h4>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 border rounded bg-danger-subtle">
                                <i class="bi bi-x-circle-fill text-danger fs-4 d-block mb-1" />
                                <span class="small text-body-secondary">Jawaban Salah</span>
                                <h4 class="fw-bold text-danger mb-0">{{ attempt.salah_count }}</h4>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 border rounded bg-light-subtle">
                                <i class="bi bi-dash-circle text-body-secondary fs-4 d-block mb-1" />
                                <span class="small text-body-secondary">Tidak Dijawab</span>
                                <h4 class="fw-bold text-body-secondary mb-0">{{ attempt.tidak_dijawab_count }}</h4>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="p-3 bg-light rounded border mb-4 small text-body-secondary">
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span>Waktu Mulai:</span>
                            <span class="fw-semibold text-dark">{{ attempt.waktu_mulai ?? '-' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span>Waktu Dikumpulkan:</span>
                            <span class="fw-semibold text-dark">{{ attempt.waktu_submit ?? '-' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>Total Butir Soal:</span>
                            <span class="fw-semibold text-dark">{{ attempt.total_soal }} Soal</span>
                        </div>
                    </div>

                    <div class="alert alert-info d-flex align-items-center gap-2 mb-4 small">
                        <i class="bi bi-info-circle-fill fs-5 flex-shrink-0" />
                        <div>
                            Skor ujian ini telah otomatis tercatat ke dalam nilai <strong>{{ ujian.kategori_nilai }}</strong> pada rekap <strong>Nilai Akhir</strong> mata pelajaran Anda.
                        </div>
                    </div>

                    <div class="text-center">
                        <Button :href="daftarUjianUrl" color="primary" icon="bi-arrow-left">
                            Kembali ke Daftar Ujian
                        </Button>
                    </div>
                </Card>
            </div>
        </div>
    </AppShell>
</template>

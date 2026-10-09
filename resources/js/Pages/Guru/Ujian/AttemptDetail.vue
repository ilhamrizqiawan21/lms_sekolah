<script setup lang="ts">
import type { PropType } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, DashboardHero } from '../../../Components/UI';

interface OpsiItem {
    id: number;
    teks_opsi: string;
    is_benar: boolean;
}

interface JawabanItem {
    jawaban_id: number;
    ujian_soal_id: number;
    pertanyaan: string;
    topik: string | null;
    kesulitan: string;
    poin_maksimal: number;
    poin_didapat: number | null;
    is_benar: boolean | null;
    soal_bank_opsi_id: number | null;
    dijawab_pada: string | null;
    opsi: OpsiItem[];
}

defineProps({
    kelasMapel: {
        type: Object as PropType<{ id: number; kelas: string; mata_pelajaran: string }>,
        required: true,
    },
    ujian: {
        type: Object as PropType<{ id: number; judul: string; kategori_nilai: string }>,
        required: true,
    },
    attempt: {
        type: Object as PropType<{
            id: number;
            siswa: { nis: string; nama: string };
            status: string;
            skor_total: number | null;
            skor_maksimal: number | null;
            skor_100: number | null;
            waktu_mulai: string | null;
            waktu_submit: string | null;
            tab_switch_count: number;
            tab_switch_log: string[];
        }>,
        required: true,
    },
    jawaban: { type: Array as PropType<JawabanItem[]>, default: () => [] },
    backUrl: { type: String, required: true },
});
</script>

<template>
    <Head :title="`Detail Jawaban: ${attempt.siswa.nama} - ${ujian.judul}`" />

    <AppShell title="Detail Jawaban Ujian">
        <DashboardHero
            eyebrow="Review Attempt Siswa"
            :title="attempt.siswa.nama"
            :subtitle="`NIS: ${attempt.siswa.nis} &bull; Ujian: ${ujian.judul} (${kelasMapel.mata_pelajaran} - ${kelasMapel.kelas})`"
            icon="bi-person-check-fill"
            tone="teacher"
        >
            <template #actions>
                <Button :href="backUrl" color="light" icon="bi-arrow-left">
                    Kembali ke Rekap Hasil
                </Button>
            </template>
        </DashboardHero>

        <div class="row g-4 mb-4">
            <!-- Summary stats -->
            <div class="col-md-8">
                <Card title="Ringkasan Pengerjaan" icon="bi-info-circle-fill">
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <span class="text-body-secondary small">Status Attempt</span>
                            <div class="mt-1">
                                <Badge :color="attempt.status === 'selesai' ? 'success' : attempt.status === 'waktu_habis' ? 'danger' : 'primary'">
                                    {{ attempt.status.replace('_', ' ').toUpperCase() }}
                                </Badge>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-body-secondary small">Skor Skala 100</span>
                            <h4 class="fw-bold text-primary mb-0 mt-1">
                                {{ attempt.skor_100 ?? 0 }}
                                <span class="fs-6 text-body-secondary fw-normal">({{ attempt.skor_total ?? 0 }} / {{ attempt.skor_maksimal ?? 0 }} poin)</span>
                            </h4>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-body-secondary small">Waktu Submit</span>
                            <div class="fw-semibold mt-1">{{ attempt.waktu_submit ?? '-' }}</div>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- Tab switch audit log -->
            <div class="col-md-4">
                <Card title="Log Pindah Tab (Anti-Cheat)" icon="bi-window-stack">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <Badge :color="attempt.tab_switch_count >= 3 ? 'danger' : attempt.tab_switch_count > 0 ? 'warning' : 'success'">
                            {{ attempt.tab_switch_count }}x Pindah Tab Terdeteksi
                        </Badge>
                    </div>

                    <div v-if="attempt.tab_switch_log && attempt.tab_switch_log.length > 0" class="small text-body-secondary" style="max-height: 120px; overflow-y: auto;">
                        <ul class="list-unstyled mb-0">
                            <li v-for="(logTime, idx) in attempt.tab_switch_log" :key="idx" class="border-bottom py-1">
                                <i class="bi bi-clock me-1 text-secondary" />
                                {{ new Date(logTime).toLocaleString('id-ID') }}
                            </li>
                        </ul>
                    </div>
                    <div v-else class="small text-body-secondary fst-italic">
                        Siswa tidak pernah berpindah tab selama pengerjaan.
                    </div>
                </Card>
            </div>
        </div>

        <!-- Question and Answer Review -->
        <h5 class="fw-bold mb-3">
            <i class="bi bi-list-check me-2 text-primary" />
            Review Jawaban Per Soal
        </h5>

        <div class="d-flex flex-column gap-3">
            <div
                v-for="(item, index) in jawaban"
                :key="item.jawaban_id"
                class="card border shadow-none"
            >
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="fw-bold">
                        Soal Nomor {{ index + 1 }}
                        <Badge v-if="item.topik" color="secondary" class="ms-2">{{ item.topik }}</Badge>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-body-secondary">Bobot: {{ item.poin_maksimal }} Poin</span>
                        <Badge v-if="item.is_benar === true" color="success">
                            <i class="bi bi-check-circle-fill me-1" />
                            Benar (+{{ item.poin_didapat }})
                        </Badge>
                        <Badge v-else-if="item.is_benar === false" color="danger">
                            <i class="bi bi-x-circle-fill me-1" />
                            Salah (0)
                        </Badge>
                        <Badge v-else color="warning text-dark">
                            Tidak Dijawab (0)
                        </Badge>
                    </div>
                </div>

                <div class="card-body">
                    <div class="fw-semibold text-break mb-3">{{ item.pertanyaan }}</div>

                    <div class="d-flex flex-column gap-2">
                        <div
                            v-for="(opsi, oIdx) in item.opsi"
                            :key="opsi.id"
                            class="p-2 px-3 border rounded d-flex align-items-center justify-content-between"
                            :class="{
                                'border-success bg-success-subtle fw-semibold': opsi.is_benar,
                                'border-danger bg-danger-subtle': !opsi.is_benar && opsi.id === item.soal_bank_opsi_id,
                            }"
                        >
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold">{{ String.fromCharCode(65 + oIdx) }}.</span>
                                <span>{{ opsi.teks_opsi }}</span>
                            </div>

                            <div class="d-flex gap-1">
                                <Badge v-if="opsi.id === item.soal_bank_opsi_id" color="primary">
                                    Pilihan Siswa
                                </Badge>
                                <Badge v-if="opsi.is_benar" color="success">
                                    Kunci Jawaban Benar
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppShell>
</template>


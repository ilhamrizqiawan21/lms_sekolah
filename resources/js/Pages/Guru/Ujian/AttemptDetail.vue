<script setup lang="ts">
import type { PropType } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import AppShell from '../../../Layouts/AppShell.vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { Badge, Button, Card } from '../../../Components/UI';

interface OpsiItem {
    id: number;
    teks_opsi: string;
    pasangan_teks: string | null;
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
    tipe_soal: 'pilihan_ganda' | 'benar_salah' | 'menjodohkan' | 'uraian';
    jawaban_teks: string | null;
    jawaban_pasangan: Record<string, number>;
    dijawab_pada: string | null;
    grade_url: string;
    opsi: OpsiItem[];
    riwayat_penilaian: Array<{
        poin_sebelumnya: number | null;
        poin_diberikan: number;
        catatan: string | null;
        penilai: string;
        waktu: string | null;
    }>;
    catatan_penilaian: string | null;
}

interface AttemptEvent {
    jenis: string;
    waktu: string | null;
    durasi_detik: number | null;
}

const eventLabel: Record<string, string> = {
    tab_hidden: 'Berpindah tab',
    window_blur: 'Keluar dari jendela ujian',
    kembali: 'Kembali ke halaman ujian',
    salin: 'Mencoba menyalin teks soal',
    klik_kanan: 'Klik kanan pada soal',
    heartbeat_gap: 'Tidak ada sinyal dari halaman ujian',
    fullscreen_exit: 'Keluar dari layar penuh',
};

function formatDurasi(detik: number): string {
    const menit = Math.floor(detik / 60);
    const sisa = detik % 60;
    return menit > 0 ? `${menit} mnt ${sisa} dtk` : `${sisa} dtk`;
}

function eventText(event: AttemptEvent): string {
    const label = eventLabel[event.jenis] ?? event.jenis;
    return event.jenis === 'heartbeat_gap' && event.durasi_detik ? `${label} selama ${formatDurasi(event.durasi_detik)}` : label;
}

const props = defineProps({
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
            alasan_selesai: string | null;
            pelanggaran: number;
            maks_pelanggaran: number | null;
            toleransi_detik: number;
            tab_switch_log: string[];
            events: AttemptEvent[];
        }>,
        required: true,
    },
    jawaban: { type: Array as PropType<JawabanItem[]>, default: () => [] },
    backUrl: { type: String, required: true },
});

const nilaiEssay = reactive<Record<number, number>>(
    Object.fromEntries(
        props.jawaban
            .filter((item) => item.tipe_soal === 'uraian')
            .map((item) => [item.jawaban_id, item.poin_didapat ?? 0]),
    ),
);
const gradingId = ref<number | null>(null);
const catatanEssay = reactive<Record<number, string>>(
    Object.fromEntries(
        props.jawaban
            .filter((item) => item.tipe_soal === 'uraian')
            .map((item) => [item.jawaban_id, item.riwayat_penilaian.at(-1)?.catatan ?? '']),
    ),
);

function simpanNilaiEssay(item: JawabanItem) {
    gradingId.value = item.jawaban_id;
    router.put(item.grade_url, { poin_didapat: Number(nilaiEssay[item.jawaban_id] ?? 0), catatan: catatanEssay[item.jawaban_id] || null }, {
        preserveScroll: true,
        onFinish: () => {
            gradingId.value = null;
        },
    });
}

function pasanganTerpilih(item: JawabanItem, kiriId: number): OpsiItem | null {
    const kananId = item.jawaban_pasangan?.[String(kiriId)];
    return item.opsi.find((opsi) => opsi.id === Number(kananId)) ?? null;
}

function pasanganBenar(item: JawabanItem, kiriId: number): boolean {
    return Number(item.jawaban_pasangan?.[String(kiriId)]) === kiriId;
}
</script>

<template>
    <Head :title="`Detail Jawaban: ${attempt.siswa.nama} - ${ujian.judul}`" />

    <AppShell title="Detail Jawaban Ujian">
        <PageHeader
            eyebrow="Hasil Ujian"
            :title="attempt.siswa.nama"
            :subtitle="`NIS: ${attempt.siswa.nis} &bull; Ujian: ${ujian.judul} (${kelasMapel.mata_pelajaran} - ${kelasMapel.kelas})`"
        >
            <template #actions>
                <Button :href="backUrl" color="outline-secondary" icon="bi-arrow-left">
                    Kembali ke Rekap Hasil
                </Button>
            </template>
        </PageHeader>

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
                                <div v-if="attempt.alasan_selesai === 'pelanggaran'" class="small text-danger mt-1">
                                    Dikumpulkan otomatis: pelanggaran mencapai batas ({{ attempt.maks_pelanggaran }}).
                                </div>
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

                    <div v-if="attempt.pelanggaran > 0 || attempt.maks_pelanggaran" class="small mb-2">
                        Pelanggaran terhitung: <strong>{{ attempt.pelanggaran }}</strong><template v-if="attempt.maks_pelanggaran"> dari batas {{ attempt.maks_pelanggaran }}</template>
                        <span class="text-body-secondary">(keluar lebih dari {{ attempt.toleransi_detik }} detik, atau keluar layar penuh)</span>
                    </div>

                    <div v-if="attempt.events.length > 0" class="small text-body-secondary" style="max-height: 160px; overflow-y: auto;">
                        <ul class="list-unstyled mb-0">
                            <li v-for="(event, idx) in attempt.events" :key="idx" class="border-bottom py-1">
                                <i class="bi bi-clock me-1 text-secondary" aria-hidden="true" />
                                <span class="tabular-nums">{{ event.waktu }}</span>
                                &middot; {{ eventText(event) }}
                            </li>
                        </ul>
                    </div>
                    <div v-else-if="attempt.tab_switch_log && attempt.tab_switch_log.length > 0" class="small text-body-secondary" style="max-height: 120px; overflow-y: auto;">
                        <ul class="list-unstyled mb-0">
                            <li v-for="(logTime, idx) in attempt.tab_switch_log" :key="idx" class="border-bottom py-1">
                                <i class="bi bi-clock me-1 text-secondary" aria-hidden="true" />
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
                        <Badge v-if="item.tipe_soal === 'uraian' && item.poin_didapat === null" color="warning text-dark">
                            <i class="bi bi-pencil-square me-1" />
                            Belum Dinilai
                        </Badge>
                        <Badge v-else-if="item.is_benar === true" color="success">
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

                    <div v-if="item.tipe_soal === 'uraian'" class="mb-3">
                        <div class="small text-body-secondary mb-1">Jawaban essay siswa</div>
                        <div class="p-3 border rounded bg-light-subtle text-break u-ws-pre-wrap">
                            {{ item.jawaban_teks || 'Siswa tidak mengisi jawaban.' }}
                        </div>
                        <div class="d-flex flex-wrap align-items-end gap-2 mt-3">
                            <div>
                                <label :for="`nilai-essay-${item.jawaban_id}`" class="form-label small mb-1">Nilai (maks. {{ item.poin_maksimal }})</label>
                                <input
                                    :id="`nilai-essay-${item.jawaban_id}`"
                                    v-model.number="nilaiEssay[item.jawaban_id]"
                                    type="number"
                                    min="0"
                                    :max="item.poin_maksimal"
                                    step="0.01"
                                    class="form-control form-control-sm"
                                    style="width: 8rem;"
                                />
                            </div>
                            <div class="flex-grow-1" style="min-width: 16rem;">
                                <label :for="`catatan-essay-${item.jawaban_id}`" class="form-label small mb-1">Catatan penilaian (opsional)</label>
                                <input
                                    :id="`catatan-essay-${item.jawaban_id}`"
                                    v-model="catatanEssay[item.jawaban_id]"
                                    type="text"
                                    maxlength="2000"
                                    class="form-control form-control-sm"
                                    placeholder="Contoh: Argumen sudah tepat, tetapi kurang contoh."
                                />
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                color="primary"
                                icon="bi-check-lg"
                                :loading="gradingId === item.jawaban_id"
                                @click="simpanNilaiEssay(item)"
                            >
                                Simpan Nilai
                            </Button>
                        </div>
                        <div v-if="item.riwayat_penilaian.length" class="mt-3 small">
                            <div class="fw-semibold text-body-secondary mb-1">Riwayat penilaian</div>
                            <div v-for="(riwayat, riwayatIndex) in item.riwayat_penilaian" :key="`${item.jawaban_id}-${riwayatIndex}`" class="border-start border-2 ps-2 mb-1">
                                <span>{{ riwayat.waktu ?? '-' }} · {{ riwayat.penilai }} · {{ riwayat.poin_diberikan }} poin</span>
                                <span v-if="riwayat.catatan" class="d-block text-body-secondary">{{ riwayat.catatan }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="item.tipe_soal === 'menjodohkan'" class="d-flex flex-column gap-2">
                        <div v-for="(opsi, oIdx) in item.opsi" :key="opsi.id" class="p-2 px-3 border rounded">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <span><strong>{{ oIdx + 1 }}.</strong> {{ opsi.teks_opsi }}</span>
                                <span>
                                    <span class="text-body-secondary">Jawaban: </span>
                                    <strong>{{ pasanganTerpilih(item, opsi.id)?.pasangan_teks ?? 'Belum dipilih' }}</strong>
                                    <Badge :color="pasanganBenar(item, opsi.id) ? 'success' : 'danger'" class="ms-2">
                                        {{ pasanganBenar(item, opsi.id) ? 'Benar' : 'Salah' }}
                                    </Badge>
                                </span>
                            </div>
                            <div class="small text-body-secondary mt-1">Kunci: {{ opsi.pasangan_teks ?? '-' }}</div>
                        </div>
                    </div>

                    <div v-else class="d-flex flex-column gap-2">
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


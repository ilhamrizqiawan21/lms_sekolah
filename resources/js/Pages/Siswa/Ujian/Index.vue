<script setup lang="ts">
import type { PropType } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppShell from '../../../Layouts/AppShell.vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { Badge, Button, Card, EmptyState, TableWrapper } from '../../../Components/UI';

interface AttemptData {
    id: number;
    status: 'belum_mulai' | 'sedang_mengerjakan' | 'selesai' | 'waktu_habis';
    skor_total: number | null;
    skor_maksimal: number | null;
    skor_100: number | null;
    waktu_mulai: string | null;
    waktu_submit: string | null;
    action_url: string;
}

interface UjianItem {
    id: number;
    judul: string;
    deskripsi: string | null;
    durasi_menit: number;
    kategori_nilai: string;
    waktu_mulai: string | null;
    waktu_selesai: string | null;
    is_buka: boolean;
    total_soal: number;
    mata_pelajaran: string;
    guru: string;
    attempt: AttemptData | null;
    mulai_url: string;
}

defineProps({
    ujianList: { type: Array as PropType<UjianItem[]>, default: () => [] },
});

async function mulaiUjian(item: UjianItem) {
    const confirmed = await window.confirmDialog?.(`Mulai mengerjakan ujian "${item.judul}"? Waktu pengerjaan (${item.durasi_menit} menit) akan langsung berjalan.`, {
        title: 'Mulai Ujian CBT',
        confirmText: 'Mulai Sekarang',
        danger: false,
    });

    if (!confirmed) {
        return;
    }

    router.post(item.mulai_url);
}
</script>

<template>
    <Head title="Ujian CBT Saya" />

    <AppShell title="Ujian CBT Saya">
        <PageHeader
            eyebrow="Evaluasi & CBT"
            title="Ujian CBT Saya"
            subtitle="Daftar ujian daring terjadwal. Pastikan koneksi internet stabil sebelum menekan tombol Mulai."
        >
            <template #actions>
                <Button href="/siswa/tugas" color="outline-secondary" icon="bi-journal-check">Tugas Saya</Button>
            </template>
        </PageHeader>

        <Card title="Daftar Ujian CBT" body-class="p-0">
            <TableWrapper v-if="ujianList.length" stack :min-width="720">
                <table class="table table-hover mb-0 app-table-proportional">
                    <colgroup class="d-none d-md-table-column-group">
                        <col class="u-w-25pct">
                        <col class="u-w-18pct">
                        <col class="u-w-10pct">
                        <col class="u-w-15pct">
                        <col class="u-w-17pct">
                        <col class="u-w-15pct">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Ujian & Guru</th>
                            <th scope="col">Mata Pelajaran</th>
                            <th scope="col">Durasi</th>
                            <th scope="col">Jadwal Pelaksanaan</th>
                            <th scope="col">Status Pengerjaan</th>
                            <th scope="col" class="text-md-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in ujianList" :key="item.id">
                            <td data-label="Ujian & Guru">
                                <div class="fw-bold stack-title">{{ item.judul }}</div>
                                <div class="small text-body-secondary">{{ item.guru }} &bull; {{ item.total_soal }} Soal</div>
                            </td>
                            <td data-label="Mata Pelajaran">
                                <div>{{ item.mata_pelajaran }}</div>
                                <Badge color="primary">{{ item.kategori_nilai }}</Badge>
                            </td>
                            <td data-label="Durasi">{{ item.durasi_menit }} Menit</td>
                            <td data-label="Jadwal">
                                <div v-if="item.waktu_mulai || item.waktu_selesai" class="small">
                                    <div>Mulai: {{ item.waktu_mulai ?? 'Bebas' }}</div>
                                    <div class="text-body-secondary">Selesai: {{ item.waktu_selesai ?? 'Bebas' }}</div>
                                </div>
                                <Badge v-else color="secondary">Terbuka</Badge>
                            </td>
                            <td data-label="Status">
                                <div v-if="item.attempt">
                                    <div v-if="item.attempt.status === 'selesai'">
                                        <Badge color="success">Selesai</Badge>
                                        <div class="small fw-bold text-primary mt-1 tabular-nums">
                                            Nilai: {{ item.attempt.skor_100 ?? '-' }}
                                        </div>
                                    </div>
                                    <div v-else-if="item.attempt.status === 'waktu_habis'">
                                        <Badge color="danger">Waktu Habis</Badge>
                                        <div class="small fw-bold text-primary mt-1 tabular-nums">
                                            Nilai: {{ item.attempt.skor_100 ?? '-' }}
                                        </div>
                                    </div>
                                    <div v-else-if="item.attempt.status === 'sedang_mengerjakan'">
                                        <Badge color="primary">Sedang Berlangsung</Badge>
                                    </div>
                                </div>
                                <div v-else>
                                    <Badge v-if="item.is_buka" color="warning text-dark">Belum Dikerjakan</Badge>
                                    <Badge v-else color="secondary">Belum Dibuka / Selesai</Badge>
                                </div>
                            </td>
                            <td data-label="Aksi" class="stack-actions text-md-end">
                                <div v-if="item.attempt">
                                    <Button
                                        v-if="item.attempt.status === 'sedang_mengerjakan'"
                                        :href="item.attempt.action_url"
                                        color="warning"
                                        icon="bi-play-circle"
                                        size="sm"
                                    >
                                        Lanjutkan
                                    </Button>
                                    <Button
                                        v-else
                                        :href="item.attempt.action_url"
                                        color="outline-secondary"
                                        icon="bi-eye"
                                        size="sm"
                                    >
                                        Lihat Hasil
                                    </Button>
                                </div>
                                <div v-else>
                                    <Button
                                        color="primary"
                                        icon="bi-pencil-fill"
                                        size="sm"
                                        :disabled="!item.is_buka"
                                        @click="mulaiUjian(item)"
                                    >
                                        Mulai Ujian
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>

            <EmptyState
                v-else
                title="Tidak ada ujian CBT"
                message="Belum ada ujian online yang tersedia untuk kelas Anda saat ini."
                icon="bi-journal-check"
            />
        </Card>
    </AppShell>
</template>


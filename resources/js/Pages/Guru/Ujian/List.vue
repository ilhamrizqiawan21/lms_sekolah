<script setup lang="ts">
import { TextInput } from '../../../Components/Form';
import type { PropType } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, DashboardHero, EmptyState, IconButton, TableWrapper } from '../../../Components/UI';

interface UjianItem {
    id: number;
    kelas_mapel_id: number;
    judul: string;
    deskripsi: string;
    durasi_menit: number;
    kategori_nilai: string;
    waktu_mulai: string | null;
    waktu_selesai: string | null;
    is_buka: boolean;
    total_soal: number;
    total_attempts: number;
    selesai_attempts: number;
    total_siswa: number;
    progress_percent: number;
    hasil_url: string | null;
    edit_url: string | null;
    delete_url: string;
}

const props = defineProps({
    kelasMapel: {
        type: Object as PropType<{ id: number; kelas: string; mata_pelajaran: string; semester: string }>,
        required: true,
    },
    ujian: { type: Array as PropType<UjianItem[]>, default: () => [] },
    totalSiswa: { type: Number, default: 0 },
    createUrl: { type: String, required: true },
});

const search = ref('');

const filteredUjian = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) {
        return props.ujian;
    }
    return props.ujian.filter((item) =>
        [item.judul, item.deskripsi, item.kategori_nilai].filter(Boolean).join(' ').toLowerCase().includes(keyword)
    );
});

async function destroyUjian(item: UjianItem) {
    const confirmed = await window.confirmDialog?.('Hapus ujian CBT ini?', {
        title: 'Hapus Ujian',
        confirmText: 'Ya, hapus',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    router.delete(item.delete_url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Ujian CBT: ${kelasMapel.mata_pelajaran} - ${kelasMapel.kelas}`" />

    <AppShell title="Ujian (CBT)">
        <DashboardHero
            eyebrow="Kelas & Mapel"
            :title="`Ujian CBT: ${kelasMapel.mata_pelajaran}`"
            :subtitle="`${kelasMapel.kelas} (Semester ${kelasMapel.semester}) - Buat dan kelola ujian CBT untuk kelas ini.`"
            icon="bi-pencil-square"
            tone="teacher"
        >
            <template #actions>
                <div class="d-flex gap-2">
                    <Button href="/guru/ujian" color="light" icon="bi-arrow-left">
                        Kembali
                    </Button>
                    <Button :href="createUrl" color="primary" icon="bi-plus-lg">
                        Buat Ujian Baru
                    </Button>
                </div>
            </template>
        </DashboardHero>

        <Card title="Daftar Ujian CBT Kelas" icon="bi-journal-text" body-class="p-0">
            <div class="p-3 border-bottom bg-light-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <TextInput v-model="search" name="search_ujian" placeholder="Cari judul ujian..." aria-label="Cari judul ujian" wrapper-class="u-maxw-250px" class="form-control-sm" />
                </div>
                <div class="text-body-secondary small">
                    Total {{ ujian.length }} ujian CBT &bull; {{ totalSiswa }} siswa terdaftar
                </div>
            </div>

            <TableWrapper v-if="filteredUjian.length">
                <table class="table table-hover mb-0 app-table-proportional">
                    <colgroup>
                        <col class="u-w-28pct">
                        <col class="u-w-10pct">
                        <col class="u-w-10pct">
                        <col class="u-w-18pct">
                        <col class="u-w-18pct">
                        <col class="u-w-16pct">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Judul & Deskripsi</th>
                            <th scope="col">Kategori</th>
                            <th scope="col">Durasi</th>
                            <th scope="col">Jadwal Akses</th>
                            <th scope="col">Pengerjaan Siswa</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in filteredUjian" :key="item.id">
                            <td>
                                <div class="fw-bold">{{ item.judul }}</div>
                                <div class="small text-body-secondary">{{ item.total_soal }} Soal Pilihan Ganda</div>
                                <div v-if="item.deskripsi" class="small text-body-secondary text-truncate u-maxw-300px">
                                    {{ item.deskripsi }}
                                </div>
                            </td>
                            <td>
                                <Badge color="info">{{ item.kategori_nilai }}</Badge>
                            </td>
                            <td>{{ item.durasi_menit }} Menit</td>
                            <td>
                                <div v-if="item.waktu_mulai || item.waktu_selesai" class="small">
                                    <div>Mulai: {{ item.waktu_mulai ?? 'Bebas' }}</div>
                                    <div class="text-body-secondary">Selesai: {{ item.waktu_selesai ?? 'Bebas' }}</div>
                                </div>
                                <Badge v-else color="secondary">Selalu Buka</Badge>
                            </td>
                            <td>
                                <div class="small fw-semibold mb-1">
                                    {{ item.selesai_attempts }} / {{ item.total_siswa }} Selesai
                                </div>
                                <div class="progress u-h-6px">
                                    <div
                                        class="progress-bar bg-success"
                                        role="progressbar"
                                        :style="{ width: `${item.progress_percent}%` }"
                                    />
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <Button
                                        v-if="item.hasil_url"
                                        :href="item.hasil_url"
                                        size="sm"
                                        color="info"
                                        icon="bi-bar-chart"
                                    >
                                        Hasil
                                    </Button>
                                    <IconButton
                                        v-if="item.edit_url"
                                        :href="item.edit_url"
                                        icon="bi-pencil"
                                        color="outline-secondary"
                                        label="Edit Ujian"
                                    />
                                    <IconButton
                                        icon="bi-trash"
                                        color="outline-danger"
                                        label="Hapus Ujian"
                                        @click="destroyUjian(item)"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>

            <EmptyState
                v-else
                title="Belum ada ujian CBT untuk kelas ini"
                message="Klik 'Buat Ujian Baru' untuk menyusun ujian dari Bank Soal."
                icon="bi-pencil-square"
            />
        </Card>
    </AppShell>
</template>

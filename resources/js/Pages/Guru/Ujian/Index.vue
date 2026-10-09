<script setup lang="ts">
import { TextInput } from '../../../Components/Form';
import type { PropType } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, DashboardHero, EmptyState, IconButton, MetricStrip, TableWrapper } from '../../../Components/UI';

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
    kelas: string;
    mata_pelajaran: string;
    hasil_url: string | null;
    edit_url: string | null;
    delete_url: string;
}

interface KelasMapelOption {
    id: number;
    kelas: string;
    mata_pelajaran: string;
    semester: string;
    label: string;
    href: string;
}

const props = defineProps({
    kelasMapel: { type: Array as PropType<KelasMapelOption[]>, default: () => [] },
    ujian: { type: Array as PropType<UjianItem[]>, default: () => [] },
    metrics: {
        type: Object as PropType<{ total_ujian: number; ujian_aktif: number; total_selesai: number }>,
        default: () => ({ total_ujian: 0, ujian_aktif: 0, total_selesai: 0 }),
    },
});

const courseSearch = ref('');

const filteredCourses = computed(() => {
    const keyword = courseSearch.value.trim().toLowerCase();
    if (!keyword) {
        return props.kelasMapel;
    }
    return props.kelasMapel.filter((item) =>
        [item.kelas, item.mata_pelajaran, item.label].filter(Boolean).join(' ').toLowerCase().includes(keyword)
    );
});

const metricsData = computed(() => [
    { label: 'Total Ujian CBT', value: props.metrics.total_ujian, icon: 'bi-pencil-square', tone: 'primary' },
    { label: 'Ujian Aktif/Buka', value: props.metrics.ujian_aktif, icon: 'bi-clock-history', tone: 'info' },
    { label: 'Siswa Selesai', value: props.metrics.total_selesai, icon: 'bi-check-circle-fill', tone: 'success' },
    { label: 'Kelas Mapel', value: props.kelasMapel.length, icon: 'bi-diagram-3-fill', tone: 'secondary' },
]);

async function destroyUjian(item: UjianItem) {
    const confirmed = await window.confirmDialog?.('Hapus ujian ini?', {
        title: 'Hapus Ujian CBT',
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
    <Head title="Ujian (CBT)" />

    <AppShell title="Ujian (CBT)">
        <DashboardHero
            eyebrow="CBT & Penilaian Online"
            title="Ujian CBT Guru"
            subtitle="Susun dan pantau ujian online pilihan ganda dengan batas waktu, acak soal/opsi, dan autograding."
            icon="bi-pencil-square"
            tone="teacher"
        >
            <template #actions>
                <Button href="/guru/soal-bank" color="light" icon="bi-collection">
                    Kelola Bank Soal
                </Button>
            </template>
        </DashboardHero>

        <MetricStrip :metrics="metricsData" class="mb-4" />

        <!-- Kelas Mapel Selector -->
        <Card title="Pilih Kelas & Mapel" icon="bi-grid-3x3-gap-fill" class="mb-4">
            <div class="mb-3">
                <TextInput v-model="courseSearch" name="search_kelas_mapel" placeholder="Cari kelas atau mata pelajaran..." aria-label="Cari kelas atau mata pelajaran" wrapper-class="" class="form-control-sm" />
            </div>

            <div class="row g-3">
                <div v-for="course in filteredCourses" :key="course.id" class="col-md-4 col-lg-3">
                    <div class="card h-100 border shadow-none hover-shadow transition-all">
                        <div class="card-body d-flex flex-column">
                            <Badge color="primary" class="align-self-start mb-2">
                                Semester {{ course.semester }}
                            </Badge>
                            <h6 class="fw-bold mb-1">{{ course.mata_pelajaran }}</h6>
                            <p class="text-body-secondary small mb-3">{{ course.kelas }}</p>
                            <div class="mt-auto">
                                <Button :href="course.href" color="primary" size="sm" icon="bi-arrow-right" class="w-100">
                                    Buka Ujian Kelas
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Semua Ujian Terbaru -->
        <Card title="Daftar Ujian Terbaru" icon="bi-list-task" body-class="p-0">
            <TableWrapper v-if="ujian.length">
                <table class="table table-hover mb-0 app-table-proportional">
                    <colgroup>
                        <col class="u-w-25pct">
                        <col class="u-w-18pct">
                        <col class="u-w-8pct">
                        <col class="u-w-10pct">
                        <col class="u-w-15pct">
                        <col class="u-w-12pct">
                        <col class="u-w-12pct">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Judul Ujian</th>
                            <th scope="col">Kelas & Mapel</th>
                            <th scope="col">Kategori</th>
                            <th scope="col">Durasi</th>
                            <th scope="col">Jadwal Buka</th>
                            <th scope="col">Progres Siswa</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in ujian" :key="item.id">
                            <td>
                                <div class="fw-bold">{{ item.judul }}</div>
                                <div class="small text-body-secondary">{{ item.total_soal }} Butir Soal</div>
                            </td>
                            <td>
                                <div>{{ item.mata_pelajaran }}</div>
                                <div class="small text-body-secondary">{{ item.kelas }}</div>
                            </td>
                            <td>
                                <Badge color="info">{{ item.kategori_nilai }}</Badge>
                            </td>
                            <td>{{ item.durasi_menit }} Menit</td>
                            <td>
                                <div v-if="item.waktu_mulai || item.waktu_selesai" class="small">
                                    <div>{{ item.waktu_mulai ?? 'Sekarang' }}</div>
                                    <div class="text-body-secondary">s/d {{ item.waktu_selesai ?? 'Seterusnya' }}</div>
                                </div>
                                <Badge v-else color="secondary">Tanpa Batas Jadwal</Badge>
                            </td>
                            <td>
                                <div class="small fw-semibold mb-1">
                                    {{ item.selesai_attempts }} / {{ item.total_siswa }} Siswa
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
                title="Belum ada ujian CBT"
                message="Pilih salah satu kelas di atas untuk mulai membuat ujian CBT."
                icon="bi-pencil-square"
            />
        </Card>
    </AppShell>
</template>

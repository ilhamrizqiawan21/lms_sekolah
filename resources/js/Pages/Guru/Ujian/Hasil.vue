<script setup lang="ts">
import type { PropType } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppShell from '../../../Layouts/AppShell.vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { Badge, Button, Card, EmptyState, StatCard, TableWrapper } from '../../../Components/UI';

interface AttemptItem {
    id: number;
    siswa: {
        id: number;
        nama_lengkap: string;
        nis: string | null;
        nisn: string | null;
    };
    status: 'in_progress' | 'submitted' | 'expired';
    waktu_mulai: string | null;
    waktu_selesai: string | null;
    durasi_pengerjaan: string;
    total_soal: number;
    total_benar: number;
    total_salah: number;
    nilai: number;
    tab_switches_count: number;
    detail_url: string;
}

interface BelumItem {
    id: number;
    nama_lengkap: string;
    nis: string | null;
    nisn: string | null;
}

const props = defineProps({
    kelasMapel: {
        type: Object as PropType<{ id: number; kelas: string; mata_pelajaran: string }>,
        required: true,
    },
    ujian: {
        type: Object as PropType<{
            id: number;
            judul: string;
            deskripsi: string | null;
            durasi_menit: number;
            kategori_nilai: string;
            total_soal: number;
        }>,
        required: true,
    },
    stats: {
        type: Object as PropType<{
            total_peserta: number;
            sudah_mengerjakan: number;
            belum_mengerjakan: number;
            rata_rata: number;
            tertinggi: number;
            terendah: number;
        }>,
        required: true,
    },
    attempts: {
        type: Array as PropType<AttemptItem[]>,
        default: () => [],
    },
    belumMengerjakan: {
        type: Array as PropType<BelumItem[]>,
        default: () => [],
    },
    exportExcelUrl: { type: String, required: true },
    exportPdfUrl: { type: String, required: true },
});

const activeTab = ref<'selesai' | 'belum'>('selesai');
const searchQuery = ref('');

const filteredAttempts = computed(() => {
    if (!searchQuery.value.trim()) return props.attempts;
    const q = searchQuery.value.toLowerCase();
    return props.attempts.filter(
        (a) =>
            a.siswa.nama_lengkap.toLowerCase().includes(q) ||
            (a.siswa.nis && a.siswa.nis.toLowerCase().includes(q))
    );
});

const filteredBelum = computed(() => {
    if (!searchQuery.value.trim()) return props.belumMengerjakan;
    const q = searchQuery.value.toLowerCase();
    return props.belumMengerjakan.filter(
        (b) =>
            b.nama_lengkap.toLowerCase().includes(q) ||
            (b.nis && b.nis.toLowerCase().includes(q))
    );
});

function statusBadgeColor(status: string) {
    if (status === 'submitted') return 'success';
    if (status === 'expired') return 'warning';
    return 'info';
}

function statusLabel(status: string) {
    if (status === 'submitted') return 'Selesai';
    if (status === 'expired') return 'Waktu Habis';
    return 'Sedang Mengerjakan';
}
</script>

<template>
    <Head :title="`Hasil CBT: ${ujian.judul} - ${kelasMapel.mata_pelajaran}`" />

    <AppShell title="Hasil Ujian CBT">
        <PageHeader
            eyebrow="Hasil Ujian"
            :title="ujian.judul"
            :subtitle="`${kelasMapel.mata_pelajaran} - ${kelasMapel.kelas} | Kategori: ${ujian.kategori_nilai} | Durasi: ${ujian.durasi_menit} Menit | ${ujian.total_soal} Soal`"
        >
            <template #actions>
                <div class="d-flex gap-2 flex-wrap">
                    <Button :href="`/guru/ujian/${kelasMapel.id}/list`" color="outline-secondary" icon="bi-arrow-left">
                        Kembali ke Daftar
                    </Button>
                    <a :href="exportExcelUrl" class="btn btn-outline-secondary">
                        <i class="bi bi-file-earmark-excel me-1" />
                        Export Excel
                    </a>
                    <a :href="exportPdfUrl" class="btn btn-outline-secondary" target="_blank">
                        <i class="bi bi-file-earmark-pdf me-1" />
                        Export PDF
                    </a>
                </div>
            </template>
        </PageHeader>

        <!-- Stats Overview -->
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-6">
                <StatCard
                    label="Total Peserta"
                    :value="String(stats.total_peserta)"
                    icon="bi-people"
                />
            </div>
            <div class="col-md-2 col-6">
                <StatCard
                    label="Sudah Mengerjakan"
                    :value="String(stats.sudah_mengerjakan)"
                    icon="bi-check-circle"
                />
            </div>
            <div class="col-md-2 col-6">
                <StatCard
                    label="Belum Mengerjakan"
                    :value="String(stats.belum_mengerjakan)"
                    icon="bi-hourglass"
                />
            </div>
            <div class="col-md-2 col-6">
                <StatCard
                    label="Rata-rata Nilai"
                    :value="String(stats.rata_rata)"
                    icon="bi-calculator"
                />
            </div>
            <div class="col-md-2 col-6">
                <StatCard
                    label="Nilai Tertinggi"
                    :value="String(stats.tertinggi)"
                    icon="bi-arrow-up-circle"
                />
            </div>
            <div class="col-md-2 col-6">
                <StatCard
                    label="Nilai Terendah"
                    :value="String(stats.terendah)"
                    icon="bi-arrow-down-circle"
                />
            </div>
        </div>

        <!-- Main Card with Tabs -->
        <Card>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <button
                            class="nav-link"
                            :class="{ active: activeTab === 'selesai' }"
                            @click="activeTab = 'selesai'"
                        >
                            <i class="bi bi-person-check me-1" />
                            Sudah Mengerjakan ({{ attempts.length }})
                        </button>
                    </li>
                    <li class="nav-item">
                        <button
                            class="nav-link"
                            :class="{ active: activeTab === 'belum' }"
                            @click="activeTab = 'belum'"
                        >
                            <i class="bi bi-person-x me-1" />
                            Belum Mengerjakan ({{ belumMengerjakan.length }})
                        </button>
                    </li>
                </ul>

                <div class="input-group u-maxw-280px">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-body-secondary" />
                    </span>
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="form-control border-start-0"
                        placeholder="Cari nama atau NIS..."
                    />
                </div>
            </div>

            <!-- Tab: Sudah Mengerjakan -->
            <div v-if="activeTab === 'selesai'">
                <TableWrapper stack v-if="filteredAttempts.length > 0">
                    <table class="table table-hover align-middle mb-0">
                        <colgroup>
                            <col class="u-w-5pct">
                            <col class="u-w-25pct">
                            <col class="u-w-12pct">
                            <col class="u-w-14pct">
                            <col class="u-w-12pct">
                            <col class="u-w-12pct">
                            <col class="u-w-10pct">
                            <col class="u-w-10pct">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Nama Siswa / NIS</th>
                                <th scope="col">Status</th>
                                <th scope="col">Waktu Pengerjaan</th>
                                <th scope="col">Benar / Salah</th>
                                <th scope="col">Nilai Akhir</th>
                                <th scope="col">Tab Switch</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(att, idx) in filteredAttempts" :key="att.id">
                                <td class="stack-hide">{{ idx + 1 }}</td>
                                <td class="stack-title">
                                    <div class="fw-bold">{{ att.siswa.nama_lengkap }}</div>
                                    <div class="small text-body-secondary">NIS: {{ att.siswa.nis ?? '-' }}</div>
                                </td>
                                <td data-label="Status">
                                    <Badge :color="statusBadgeColor(att.status)">
                                        {{ statusLabel(att.status) }}
                                    </Badge>
                                </td>
                                <td data-label="Waktu Pengerjaan">
                                    <div class="small">{{ att.durasi_pengerjaan }}</div>
                                    <div class="small text-body-secondary">{{ att.waktu_selesai ?? att.waktu_mulai }}</div>
                                </td>
                                <td data-label="Benar / Salah">
                                    <span class="text-success fw-bold">{{ att.total_benar }} B</span> /
                                    <span class="text-danger fw-bold">{{ att.total_salah }} S</span>
                                    <div class="small text-body-secondary">dari {{ att.total_soal }} soal</div>
                                </td>
                                <td data-label="Nilai Akhir">
                                    <div class="fs-5 fw-bold" :class="att.nilai >= 75 ? 'text-success' : 'text-danger'">
                                        {{ att.nilai }}
                                    </div>
                                </td>
                                <td data-label="Tab Switch">
                                    <Badge
                                        v-if="att.tab_switches_count > 0"
                                        color="warning"
                                        :title="`${att.tab_switches_count} kali keluar tab selama ujian`"
                                    >
                                        <i class="bi bi-exclamation-triangle me-1" />
                                        {{ att.tab_switches_count }}x
                                    </Badge>
                                    <span v-else class="text-body-secondary small">0</span>
                                </td>
                                <td class="stack-actions">
                                    <Link :href="att.detail_url" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1" />
                                        Detail
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </TableWrapper>

                <EmptyState
                    v-else
                    title="Belum ada pengerjaan"
                    message="Belum ada siswa yang mulai atau menyelesaikan ujian ini."
                    icon="bi-clipboard-x"
                />
            </div>

            <!-- Tab: Belum Mengerjakan -->
            <div v-else>
                <TableWrapper stack v-if="filteredBelum.length > 0">
                    <table class="table table-hover align-middle mb-0">
                        <colgroup>
                            <col class="u-w-5pct">
                            <col class="u-w-45pct">
                            <col class="u-w-25pct">
                            <col class="u-w-25pct">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Nama Lengkap</th>
                                <th scope="col">NIS</th>
                                <th scope="col">NISN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(siswa, idx) in filteredBelum" :key="siswa.id">
                                <td class="stack-hide">{{ idx + 1 }}</td>
                                <td class="fw-bold stack-title">{{ siswa.nama_lengkap }}</td>
                                <td data-label="NIS">{{ siswa.nis ?? '-' }}</td>
                                <td data-label="NISN">{{ siswa.nisn ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </TableWrapper>

                <EmptyState
                    v-else
                    title="Semua siswa sudah mengerjakan!"
                    message="Seluruh siswa yang terdaftar di kelas ini telah menyelesaikan ujian."
                    icon="bi-trophy"
                />
            </div>
        </Card>
    </AppShell>
</template>

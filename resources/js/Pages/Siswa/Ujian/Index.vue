<script setup lang="ts">
import type { PropType } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
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
    anticheat_aktif?: boolean;
    maks_pelanggaran?: number | null;
    wajib_fullscreen?: boolean;
    mata_pelajaran: string;
    guru: string;
    status_label?: string;
    can_start?: boolean;
    can_continue?: boolean;
    can_view_hasil?: boolean;
    attempt_id?: number | null;
    mulai_url: string;
    kerjakan_url?: string | null;
    hasil_url?: string | null;
    attempt?: AttemptData | null;
}

const props = defineProps({
    ujianList: { type: Array as PropType<UjianItem[]>, default: () => [] },
});

const totalUjian = computed(() => props.ujianList.length);
const sedangBerlangsung = computed(() => props.ujianList.filter((item) => item.can_continue || item.attempt?.status === 'sedang_mengerjakan').length);
const tersedia = computed(() => props.ujianList.filter((item) => item.can_start || (!item.attempt && item.is_buka)).length);
const selesai = computed(() => props.ujianList.filter((item) => item.can_view_hasil || item.attempt?.status === 'selesai' || item.attempt?.status === 'waktu_habis').length);

function statusBadgeColor(item: UjianItem): string {
    if (item.can_continue || item.attempt?.status === 'sedang_mengerjakan') return 'primary';
    if (item.can_view_hasil || item.attempt?.status === 'selesai' || item.attempt?.status === 'waktu_habis') {
        return item.status_label?.startsWith('Waktu Habis') || item.attempt?.status === 'waktu_habis' ? 'danger' : 'success';
    }
    return item.is_buka ? 'warning' : 'secondary';
}

function aturanPengawasan(item: UjianItem): string {
    if (!item.anticheat_aktif) return '';
    const aturan = ['Perpindahan tab atau jendela dicatat dan dapat dilihat guru.'];
    if (item.wajib_fullscreen) aturan.push('Ujian wajib dikerjakan dalam layar penuh.');
    if (item.maks_pelanggaran) aturan.push(`Jika terjadi ${item.maks_pelanggaran} pelanggaran, jawaban dikumpulkan otomatis.`);
    return ` ${aturan.join(' ')}`;
}

async function mulaiUjian(item: UjianItem) {
    const confirmed = await window.confirmDialog?.(`Mulai mengerjakan ujian "${item.judul}"? Waktu pengerjaan (${item.durasi_menit} menit) akan langsung berjalan.${aturanPengawasan(item)}`, {
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
            subtitle="Kelola ujian daring Anda dari satu tempat dan lanjutkan pengerjaan yang masih aktif."
        >
            <template #actions>
                <Button href="/siswa/tugas" color="outline-secondary" icon="bi-journal-check">Tugas Saya</Button>
            </template>
        </PageHeader>

        <section class="cbt-welcome-panel mb-4" aria-labelledby="cbt-welcome-title">
            <div class="cbt-welcome-icon" aria-hidden="true"><i class="bi bi-pencil-square" /></div>
            <div class="cbt-welcome-copy">
                <span class="app-page-eyebrow">Sebelum mulai</span>
                <h2 id="cbt-welcome-title">Siapkan koneksi dan fokus Anda</h2>
                <p>Beberapa ujian memakai anti-cheat dan fullscreen. Waktu mulai berjalan setelah Anda menekan tombol mulai.</p>
            </div>
            <div class="cbt-welcome-rules" aria-label="Aturan umum ujian">
                <span><i class="bi bi-wifi" aria-hidden="true" /> Koneksi stabil</span>
                <span><i class="bi bi-stopwatch" aria-hidden="true" /> Satu kesempatan</span>
                <span><i class="bi bi-phone" aria-hidden="true" /> Bisa dari HP</span>
            </div>
        </section>

        <div class="row g-3 mb-4" aria-label="Ringkasan ujian">
            <div class="col-6 col-lg-3">
                <div class="cbt-summary-card">
                    <span class="cbt-summary-label">Total ujian</span>
                    <strong class="cbt-summary-value tabular-nums">{{ totalUjian }}</strong>
                    <i class="bi bi-journal-text cbt-summary-icon" aria-hidden="true" />
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="cbt-summary-card cbt-summary-card-primary">
                    <span class="cbt-summary-label">Tersedia</span>
                    <strong class="cbt-summary-value tabular-nums">{{ tersedia }}</strong>
                    <i class="bi bi-play-circle cbt-summary-icon" aria-hidden="true" />
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="cbt-summary-card cbt-summary-card-warning">
                    <span class="cbt-summary-label">Berlangsung</span>
                    <strong class="cbt-summary-value tabular-nums">{{ sedangBerlangsung }}</strong>
                    <i class="bi bi-hourglass-split cbt-summary-icon" aria-hidden="true" />
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="cbt-summary-card cbt-summary-card-success">
                    <span class="cbt-summary-label">Selesai</span>
                    <strong class="cbt-summary-value tabular-nums">{{ selesai }}</strong>
                    <i class="bi bi-check2-circle cbt-summary-icon" aria-hidden="true" />
                </div>
            </div>
        </div>

        <Card :title="`Daftar Ujian${totalUjian ? ` (${totalUjian})` : ''}`" body-class="p-0">
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
                        <tr v-for="item in ujianList" :key="item.id" class="cbt-exam-row">
                            <td data-label="Ujian & Guru" class="cbt-title-cell stack-title">
                                <div class="fw-bold stack-title">{{ item.judul }}</div>
                                <div class="small text-body-secondary">{{ item.guru }} &bull; {{ item.total_soal }} Soal</div>
                                <p v-if="item.deskripsi" class="small text-body-secondary cbt-description mb-2">{{ item.deskripsi }}</p>
                                <div class="cbt-tags" aria-label="Pengaturan pengawasan">
                                    <Badge :color="item.anticheat_aktif ? 'info' : 'secondary'">
                                        <i class="bi" :class="item.anticheat_aktif ? 'bi-shield-check' : 'bi-shield'" aria-hidden="true" />
                                        {{ item.anticheat_aktif ? 'Anti-cheat aktif' : 'Pengawasan standar' }}
                                    </Badge>
                                    <Badge v-if="item.anticheat_aktif && item.wajib_fullscreen" color="primary">
                                        <i class="bi bi-fullscreen" aria-hidden="true" /> Fullscreen wajib
                                    </Badge>
                                </div>
                            </td>
                            <td data-label="Mata Pelajaran" class="cbt-meta-cell">
                                <div class="cbt-cell-value">
                                    <div>{{ item.mata_pelajaran }}</div>
                                    <Badge color="primary">{{ item.kategori_nilai }}</Badge>
                                </div>
                            </td>
                            <td data-label="Durasi" class="cbt-meta-cell">
                                <span class="cbt-cell-value">{{ item.durasi_menit }} Menit</span>
                            </td>
                            <td data-label="Jadwal" class="cbt-meta-cell">
                                <div v-if="item.waktu_mulai || item.waktu_selesai" class="small cbt-cell-value cbt-schedule-value">
                                    <div>Mulai: {{ item.waktu_mulai ?? 'Bebas' }}</div>
                                    <div class="text-body-secondary">Selesai: {{ item.waktu_selesai ?? 'Bebas' }}</div>
                                </div>
                                <span v-else class="cbt-cell-value"><Badge color="secondary">Terbuka</Badge></span>
                            </td>
                            <td data-label="Status" class="cbt-meta-cell">
                                <div v-if="item.status_label" class="cbt-cell-value">
                                    <Badge :color="statusBadgeColor(item)">{{ item.status_label }}</Badge>
                                </div>
                                <div v-else-if="item.attempt" class="cbt-cell-value">
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
                                <div v-else class="cbt-cell-value">
                                    <Badge v-if="item.is_buka" color="warning">Belum Dikerjakan</Badge>
                                    <Badge v-else color="secondary">Belum Dibuka / Selesai</Badge>
                                </div>
                            </td>
                            <td data-label="Aksi" class="stack-actions text-md-end">
                                <div v-if="item.can_continue && item.kerjakan_url">
                                    <Button :href="item.kerjakan_url" color="warning" icon="bi-play-circle" size="sm">Lanjutkan</Button>
                                </div>
                                <div v-else-if="item.can_view_hasil && item.hasil_url">
                                    <Button :href="item.hasil_url" color="outline-secondary" icon="bi-eye" size="sm">Lihat Hasil</Button>
                                </div>
                                <div v-else-if="item.attempt">
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
                                        :disabled="item.can_start !== undefined ? !item.can_start : !item.is_buka"
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

<style scoped>
.cbt-welcome-panel {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 1rem;
    padding: clamp(1rem, 2vw, 1.35rem);
    border: 1px solid var(--border-soft);
    border-radius: 1rem;
    background: linear-gradient(135deg, var(--surface-card), var(--surface-muted));
    box-shadow: 0 0.5rem 1.5rem rgba(15, 23, 42, 0.05);
}

.cbt-welcome-icon {
    display: grid;
    width: 3rem;
    height: 3rem;
    place-items: center;
    border-radius: 0.85rem;
    background: var(--surface-brand-subtle, rgba(37, 99, 235, 0.12));
    color: var(--text-brand, #2563eb);
    font-size: 1.35rem;
}

.cbt-welcome-copy h2 {
    margin: 0.15rem 0 0.25rem;
    color: var(--text-strong);
    font-size: 1rem;
    font-weight: 750;
    text-wrap: balance;
}

.cbt-welcome-copy p {
    max-width: 48rem;
    margin: 0;
    color: var(--text-muted);
    font-size: var(--fs-sm);
    text-wrap: pretty;
}

.cbt-welcome-rules {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.45rem;
    color: var(--text-muted);
    font-size: var(--fs-caption);
}

.cbt-welcome-rules span {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.55rem;
    border: 1px solid var(--border-soft);
    border-radius: 999px;
    background: var(--surface-card);
    white-space: nowrap;
}

.cbt-summary-card {
    position: relative;
    min-height: 6.5rem;
    overflow: hidden;
    padding: 1rem;
    border: 1px solid var(--border-soft);
    border-radius: 0.85rem;
    background: var(--surface-card);
}

.cbt-summary-card-primary { border-color: rgba(37, 99, 235, 0.25); }
.cbt-summary-card-warning { border-color: rgba(245, 158, 11, 0.3); }
.cbt-summary-card-success { border-color: rgba(16, 185, 129, 0.3); }

.cbt-summary-label {
    display: block;
    color: var(--text-muted);
    font-size: var(--fs-caption);
    font-weight: 650;
}

.cbt-summary-value {
    display: block;
    margin-top: 0.35rem;
    color: var(--text-strong);
    font-size: 1.55rem;
    line-height: 1;
}

.cbt-summary-icon {
    position: absolute;
    right: 0.9rem;
    bottom: 0.8rem;
    color: var(--text-brand, #2563eb);
    font-size: 1.45rem;
    opacity: 0.2;
}

.cbt-summary-card-warning .cbt-summary-icon { color: #d97706; }
.cbt-summary-card-success .cbt-summary-icon { color: #059669; }

.cbt-title-cell {
    white-space: normal !important;
    overflow-wrap: anywhere;
}

.cbt-cell-value {
    min-width: 0;
}

.cbt-description {
    max-width: 28rem;
    text-wrap: pretty;
}

.cbt-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.cbt-tags :deep(.badge) {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

@media (max-width: 767.98px) {
    .app-table-stack tbody tr.cbt-exam-row {
        margin: 0.75rem;
        padding: 0.95rem 1rem;
        border: 1px solid var(--border-soft);
        border-radius: 0.85rem;
        background: var(--surface-card);
        box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.05);
    }

    .app-table-stack tbody tr.cbt-exam-row:last-child {
        border-bottom: 1px solid var(--border-soft);
    }

    .app-table-stack td.cbt-title-cell {
        padding-bottom: 0.75rem !important;
        border-bottom: 1px solid var(--border-soft) !important;
    }

    .app-table-stack td.cbt-title-cell::before {
        display: none;
    }

    .app-table-stack td.cbt-title-cell > .stack-title {
        margin-bottom: 0.2rem;
        font-size: 1rem;
        line-height: 1.35;
        text-wrap: balance;
    }

    .app-table-stack td.cbt-title-cell .cbt-description {
        display: -webkit-box;
        margin-top: 0.35rem;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .app-table-stack td.cbt-meta-cell {
        align-items: flex-start;
        padding-block: 0.45rem !important;
    }

    .app-table-stack td.cbt-meta-cell::before {
        padding-top: 0.15rem;
    }

    .app-table-stack td.cbt-meta-cell .cbt-cell-value {
        display: flex;
        flex: 1 1 auto;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.25rem 0.45rem;
        text-align: right;
    }

    .app-table-stack td.cbt-meta-cell .cbt-cell-value > div {
        max-width: 100%;
        overflow-wrap: anywhere;
    }

    .app-table-stack td.cbt-meta-cell .cbt-schedule-value > div {
        flex-basis: 100%;
    }

    .app-table-stack td.stack-actions {
        margin-top: 0.35rem;
        padding-top: 0.8rem !important;
        border-top: 1px solid var(--border-soft) !important;
    }

    .app-table-stack td.stack-actions > div,
    .app-table-stack td.stack-actions .btn {
        width: 100%;
    }

    .app-table-stack td.stack-actions .btn {
        justify-content: center;
        min-height: 2.75rem;
    }

    .cbt-welcome-panel {
        grid-template-columns: auto minmax(0, 1fr);
    }

    .cbt-welcome-rules {
        grid-column: 1 / -1;
        justify-content: flex-start;
    }
}
</style>


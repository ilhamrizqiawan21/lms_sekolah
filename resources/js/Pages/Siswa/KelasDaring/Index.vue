<script setup lang="ts">
import type { PropType } from 'vue';

import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, EmptyState, MetricStrip, TableWrapper } from '../../../Components/UI';

interface DaringSession {
    id: number;
    judul: string;
    tanggal: string;
    pelajaran_ke: number;
    status: string;
    meeting_url: string;
    is_upcoming: boolean;
    mata_pelajaran: string;
    guru: string;
    deskripsi: string | null;
    workspace_url: string | null;
    sudah_presensi?: boolean;
    can_presensi?: boolean;
    presensi_url?: string;
}

const props = defineProps({
    kelas: { type: Object as PropType<{ nama: string }>, required: true },
    courses: { type: Array as PropType<{ id: number; label: string; url: string }[]>, default: () => [] },
    selectedCourseId: { type: [Number, String], default: null },
    sessions: { type: Array as PropType<DaringSession[]>, default: () => [] },
    links: { type: Object as PropType<{ jadwal?: string; all?: string }>, default: () => ({}) },
});

const processingPresensiId = ref<number | null>(null);

function doPresensi(session: DaringSession) {
    if (!session.presensi_url || processingPresensiId.value !== null) return;
    processingPresensiId.value = session.id;
    router.post(session.presensi_url, {}, {
        preserveScroll: true,
        onFinish: () => {
            processingPresensiId.value = null;
        },
    });
}

const upcomingCount = computed(() => props.sessions.filter((item) => item.is_upcoming).length);
const metrics = computed(() => [
    { label: 'Sesi tersedia', value: props.sessions.length, icon: 'bi-camera-video-fill', tone: 'primary' },
    { label: 'Akan datang', value: upcomingCount.value, icon: 'bi-calendar-check-fill', tone: 'success' },
    { label: 'Mata pelajaran', value: props.courses.length, icon: 'bi-book-fill', tone: 'info' },
]);

function statusColor(status: string) {
    if (status === 'selesai') return 'success';
    if (status === 'dibatalkan') return 'danger';
    return 'primary';
}
</script>

<template>
    <Head title="Kelas Daring" />

    <AppShell title="Kelas Daring">
        <PageHeader
            eyebrow="Pembelajaran"
            title="Kelas Daring"
            :subtitle="`Kelas ${kelas.nama}`"
        />

        <MetricStrip :items="metrics" />

        <section class="workspace-panel mb-4">
            <header class="workspace-panel-header">
                <span class="workspace-panel-title"><i class="bi bi-funnel" aria-hidden="true"></i> Filter Mata Pelajaran</span>
                <Link :href="links.jadwal" class="app-card-action-link">Lihat Jadwal</Link>
            </header>
            <div class="course-filter-list">
                <Link :href="links.all" class="course-filter" :class="{ active: !selectedCourseId }">Semua</Link>
                <Link
                    v-for="course in courses"
                    :key="course.id"
                    :href="course.url"
                    class="course-filter"
                    :class="{ active: Number(selectedCourseId) === Number(course.id) }"
                >
                    {{ course.label }}
                </Link>
            </div>
        </section>

        <Card title="Daftar Sesi" body-class="p-0">
            <TableWrapper v-if="sessions.length" stack :min-width="820">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Sesi</th>
                            <th scope="col">Jadwal</th>
                            <th scope="col">Status</th>
                            <th scope="col">Presensi</th>
                            <th scope="col" class="text-md-end">Akses</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="session in sessions" :key="session.id">
                            <td data-label="Sesi">
                                <strong class="stack-title">{{ session.judul }}</strong>
                                <div class="text-body-secondary small">{{ session.mata_pelajaran }} &bull; {{ session.guru }}</div>
                                <div v-if="session.deskripsi" class="text-body-secondary small">{{ session.deskripsi }}</div>
                            </td>
                            <td data-label="Jadwal">
                                {{ session.tanggal }}
                                <div class="text-body-secondary small">Pelajaran ke-{{ session.pelajaran_ke }}</div>
                            </td>
                            <td data-label="Status">
                                <Badge :color="statusColor(session.status)">{{ session.status }}</Badge>
                            </td>
                            <td data-label="Presensi">
                                <Badge v-if="session.sudah_presensi" color="success" class="d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Hadir
                                </Badge>
                                <Button
                                    v-else-if="session.can_presensi"
                                    color="primary"
                                    size="sm"
                                    icon="bi-fingerprint"
                                    :disabled="processingPresensiId === session.id"
                                    @click="doPresensi(session)"
                                >
                                    {{ processingPresensiId === session.id ? 'Menyimpan...' : 'Presensi Hadir' }}
                                </Button>
                                <span v-else class="text-body-secondary small">-</span>
                            </td>
                            <td data-label="Akses" class="stack-actions text-md-end">
                                <a
                                    v-if="session.status === 'terjadwal'"
                                    :href="session.meeting_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn btn-sm btn-primary"
                                >
                                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                    Buka Link
                                </a>
                                <Link v-else-if="session.workspace_url" :href="session.workspace_url" class="btn btn-sm btn-outline-secondary">
                                    Ruang Kelas
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>
            <EmptyState
                v-else
                title="Belum ada kelas daring"
                message="Sesi daring akan tampil setelah guru menjadwalkan link meeting."
                icon="bi-camera-video"
            />
        </Card>
    </AppShell>
</template>

<style scoped>
.course-filter-list {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}

.course-filter {
    display: inline-flex;
    align-items: center;
    min-height: 34px;
    border: 1px solid var(--gray-200);
    border-radius: 999px;
    padding: .35rem .75rem;
    text-decoration: none;
    color: var(--text-body);
    background: var(--surface-card);
}

.course-filter.active,
.course-filter:hover {
    border-color: var(--primary-500);
    background: color-mix(in srgb, var(--primary-500) 12%, var(--surface-card));
    color: var(--text-brand);
}
</style>

<script setup lang="ts">
import { computed, type PropType } from 'vue';

import { Head, Link } from '@inertiajs/vue3';
import AppShell from '../../../Layouts/AppShell.vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { Badge, Button, Card, EmptyState, TableWrapper } from '../../../Components/UI';

interface TugasItem { id: number; show_url: string; judul: string; workspace_url: string | null; mata_pelajaran: string; batas_waktu: string; status: string | null; nilai: string | number | null }

const props = defineProps({
    tugas: { type: Array as PropType<TugasItem[]>, default: () => [] },
});

// A task is still open when nothing was submitted yet: no submission row, or a row in status `belum`.
const openTasks = computed(() => props.tugas.filter((item) => !item.status || item.status === 'belum').length);

const statusMap: Record<string, { color: string; label: string }> = {
    belum: { color: 'warning', label: 'Belum dikumpulkan' },
    sudah: { color: 'info', label: 'Dikumpulkan' },
    terlambat: { color: 'danger', label: 'Terlambat' },
    dinilai: { color: 'success', label: 'Dinilai' },
    perlu_perbaikan: { color: 'warning', label: 'Perlu perbaikan' },
};

function statusColor(status: string | null) {
    return statusMap[status ?? '']?.color ?? 'warning';
}

function statusLabel(status: string | null) {
    return statusMap[status ?? '']?.label ?? (status ? status.replace(/_/g, ' ') : 'Belum dikumpulkan');
}

function hasNilai(nilai: TugasItem['nilai']) {
    return nilai !== null && nilai !== '' && nilai !== '-';
}
</script>

<template>
    <Head title="Tugas Saya" />

    <AppShell title="Tugas Saya">
        <PageHeader
            title="Tugas Saya"
            :subtitle="openTasks ? `${openTasks} tugas belum dikumpulkan.` : 'Semua tugas sudah dikumpulkan.'"
        >
            <template #actions>
                <Button href="/siswa/materi" color="outline-secondary" icon="bi-file-earmark-text">Materi</Button>
            </template>
        </PageHeader>

        <Card v-if="tugas.length" body-class="p-0">
            <!-- Desktop/tablet: ringkas dalam tabel -->
            <TableWrapper class="d-none d-md-block" :scroll-hint="false" :min-width="640">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Tugas</th>
                            <th scope="col">Tenggat</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Nilai</th>
                            <th scope="col" class="text-end"><span class="visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in tugas" :key="item.id">
                            <td>
                                <Link :href="item.show_url" class="task-title">{{ item.judul }}</Link>
                                <div class="task-meta">
                                    <Link v-if="item.workspace_url" :href="item.workspace_url" class="task-meta-link">{{ item.mata_pelajaran }}</Link>
                                    <span v-else>{{ item.mata_pelajaran }}</span>
                                </div>
                            </td>
                            <td class="text-nowrap">{{ item.batas_waktu }}</td>
                            <td><Badge :color="statusColor(item.status)">{{ statusLabel(item.status) }}</Badge></td>
                            <td class="text-end fw-semibold">{{ hasNilai(item.nilai) ? item.nilai : '–' }}</td>
                            <td class="text-end">
                                <Button :href="item.show_url" color="outline-secondary">Buka</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>

            <!-- HP: daftar kartu; seluruh kartu dapat diketuk -->
            <ul class="task-list d-md-none">
                <li v-for="item in tugas" :key="item.id">
                    <Link :href="item.show_url" class="task-card">
                        <span class="task-card-main">
                            <span class="task-title">{{ item.judul }}</span>
                            <span class="task-meta">{{ item.mata_pelajaran }} · Tenggat {{ item.batas_waktu }}</span>
                        </span>
                        <span class="task-card-side">
                            <Badge :color="statusColor(item.status)">{{ statusLabel(item.status) }}</Badge>
                            <span v-if="hasNilai(item.nilai)" class="task-score">Nilai {{ item.nilai }}</span>
                        </span>
                        <i class="bi bi-chevron-right task-card-chevron" aria-hidden="true"></i>
                    </Link>
                </li>
            </ul>
        </Card>

        <Card v-else>
            <EmptyState title="Belum ada tugas" message="Tugas dari guru akan muncul di sini." icon="bi-journal" />
        </Card>
    </AppShell>
</template>

<style scoped>
.task-title {
    color: var(--text-strong);
    font-weight: 650;
    text-decoration: none;
}
a.task-title:hover { color: var(--text-brand); text-decoration: underline; }
.task-meta {
    margin-top: 0.15rem;
    color: var(--text-muted);
    font-size: 0.8rem;
}
.task-meta-link { color: inherit; text-decoration: none; }
.task-meta-link:hover { color: var(--text-brand); text-decoration: underline; }

.task-list {
    margin: 0;
    padding: 0;
    list-style: none;
}
.task-list li + li { border-top: 1px solid var(--border-soft); }
.task-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    color: inherit;
    text-decoration: none;
}
.task-card:hover,
.task-card:focus-visible { background: var(--surface-subtle); }
.task-card-main {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-width: 0;
}
.task-card-main .task-title { overflow-wrap: anywhere; }
.task-card-side {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}
.task-score { color: var(--text-strong); font-size: 0.8rem; font-weight: 650; }
.task-card-chevron { color: var(--text-muted); }
</style>

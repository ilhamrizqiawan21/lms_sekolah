<script setup lang="ts">
import type { PropType } from 'vue';

import { Head } from '@inertiajs/vue3';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, DashboardHero, EmptyState, QuickActionBar, TableWrapper } from '../../../Components/UI';

const props = defineProps({
    tugas: { type: Array as PropType<{ id: number; show_url: string; judul: string; workspace_url: string | null; mata_pelajaran: string; batas_waktu: string; status: string | null; nilai: string | number | null }[]>, default: () => [] },
});

const openTasks = () => props.tugas.filter((item) => !item.status).length;

const statusMap: Record<string, { color: string; label: string }> = {
    belum: { color: 'warning text-dark', label: 'Belum Dikumpul' },
    sudah: { color: 'success', label: 'Sudah' },
    terlambat: { color: 'danger', label: 'Terlambat' },
    dinilai: { color: 'primary', label: 'Dinilai' },
    perlu_perbaikan: { color: 'warning', label: 'Perlu Perbaikan' },
};

function statusColor(status: string | null) {
    return statusMap[status ?? '']?.color ?? 'warning text-dark';
}

function statusLabel(status: string | null) {
    return statusMap[status ?? '']?.label ?? (status ? status.replace(/\b\w/g, (char) => char.toUpperCase()) : 'Belum Dikumpul');
}
</script>

<template>
    <Head title="Tugas Saya" />

    <AppShell title="Tugas Saya">
        <DashboardHero
            eyebrow="Pembelajaran Saya"
            title="Tugas Saya"
            :subtitle="`${openTasks()} tugas belum dikumpulkan. Buka detail tugas untuk mengirim jawaban.`"
            icon="bi-journal-check"
            tone="student"
        >
            <template #actions>
                <QuickActionBar :actions="[{ label: 'Materi', href: '/siswa/materi', icon: 'bi-file-earmark-text', color: 'light' }]" />
            </template>
        </DashboardHero>

        <Card title="Daftar Tugas" icon="bi-journal-text" body-class="p-0">
            <div v-if="tugas.length" class="p-3 border-bottom bg-light-subtle">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="text-body-secondary small">Tugas yang sudah dikumpulkan dan yang masih menunggu.</span>
                    <Badge color="primary">{{ openTasks() }} belum dikumpulkan</Badge>
                </div>
            </div>
            <TableWrapper v-if="tugas.length">
                <table class="table table-hover mb-0 app-table-proportional">
                    <colgroup>
                        <col class="u-w-30pct">
                        <col class="u-w-16pct">
                        <col class="u-w-12pct">
                        <col class="u-w-13pct">
                        <col class="u-w-9pct">
                        <col class="u-w-20pct">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Judul</th>
                            <th scope="col">Mapel</th>
                            <th scope="col">Deadline</th>
                            <th scope="col">Status</th>
                            <th scope="col">Nilai</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in tugas" :key="item.id">
                            <td class="app-table-judul"><a :href="item.show_url" class="text-decoration-none fw-bold">{{ item.judul }}</a></td>
                            <td>
                                <a v-if="item.workspace_url" :href="item.workspace_url" class="text-decoration-none">{{ item.mata_pelajaran }}</a>
                                <span v-else>{{ item.mata_pelajaran }}</span>
                            </td>
                            <td>{{ item.batas_waktu }}</td>
                            <td><Badge :color="statusColor(item.status)">{{ statusLabel(item.status) }}</Badge></td>
                            <td>{{ item.nilai }}</td>
                            <td>
                                <Button :href="item.show_url" color="info" icon="bi-eye">Detail</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>
            <EmptyState v-else title="Belum ada tugas" icon="bi-journal" />
        </Card>
    </AppShell>
</template>

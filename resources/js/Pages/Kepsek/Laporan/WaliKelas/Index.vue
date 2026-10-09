<script setup lang="ts">
import type { PropType } from 'vue';
import type { LaravelPaginator } from '../../../../types/pagination';

import type { HomeroomReportRow } from '../../../../types/reports';
import { Head } from '@inertiajs/vue3';
import PageHeader from '../../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../../Layouts/AppShell.vue';
import { Badge, Button, Card, EmptyState, Pagination, TableWrapper } from '../../../../Components/UI';

defineProps({
    waliKelas: { type: Object as PropType<LaravelPaginator<HomeroomReportRow>>, required: true },
});
</script>

<template>
    <Head title="Laporan Wali Kelas" />

    <AppShell title="Laporan Wali Kelas">
        <PageHeader title="Laporan Wali Kelas" />

        <Card title="Daftar Wali Kelas Aktif" body-class="p-0">
            <TableWrapper v-if="waliKelas.data.length" :min-width="640">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Kelas</th>
                            <th scope="col">Wali Kelas</th>
                            <th scope="col">Tahun Ajaran</th>
                            <th scope="col">Absensi</th>
                            <th scope="col">Pertemuan</th>
                            <th scope="col">Penanganan</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in waliKelas.data" :key="item.id">
                            <td data-label="Kelas" class="stack-title"><strong>{{ item.kelas }}</strong></td>
                            <td data-label="Wali Kelas">{{ item.guru }}</td>
                            <td data-label="Tahun Ajaran">{{ item.tahun_ajaran }}</td>
                            <td data-label="Absensi" class="tabular-nums">{{ item.absensi_count }}</td>
                            <td data-label="Pertemuan" class="tabular-nums">{{ item.pertemuan_count }}</td>
                            <td data-label="Penanganan">
                                <Badge color="warning text-dark" class="tabular-nums">{{ item.penanganan_aktif_count }} aktif</Badge>
                                <span class="text-body-secondary small tabular-nums"> / {{ item.penanganan_siswa_count }} total</span>
                            </td>
                            <td data-label="Aksi" class="stack-actions">
                                <Button :href="item.show_url" color="outline-primary" icon="bi-eye" size="sm">Detail</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>

            <EmptyState v-else title="Belum ada wali kelas aktif." icon="bi-person-badge" />

            <template v-if="waliKelas.links?.length" #footer>
                <Pagination :links="waliKelas.links" />
            </template>
        </Card>
    </AppShell>
</template>

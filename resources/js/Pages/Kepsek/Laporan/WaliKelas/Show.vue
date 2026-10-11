<script setup lang="ts">
import type { PropType } from 'vue';


import type { ReportOption, HomeroomAttendanceRow } from '../../../../types/reports';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import PageHeader from '../../../../Components/AppShell/PageHeader.vue';
import { SelectInput } from '../../../../Components/Form';
import AppShell from '../../../../Layouts/AppShell.vue';
import { Badge, Button, Card, EmptyState, TableWrapper } from '../../../../Components/UI';

const props = defineProps({
    waliKelas: { type: Object as PropType<{ id: number; title: string; kelas: string; guru: string }>, required: true },
    bulan: { type: String, required: true },
    bulanOptions: { type: Array as PropType<ReportOption[]>, default: () => [] },
    tanggalList: { type: Array as PropType<{ date: string; day: string }[]>, default: () => [] },
    siswaRows: { type: Array as PropType<HomeroomAttendanceRow[]>, default: () => [] },
    pertemuan: { type: Array as PropType<{ id: number; tanggal: string; topik: string; hasil: string | null }[]>, default: () => [] },
    penanganan: { type: Array as PropType<{ id: number; siswa: string; nis: string | null; kondisi: string; tindak_lanjut: string | null; status: string }[]>, default: () => [] },
    backUrl: { type: String, required: true },
    resetUrl: { type: String, required: true },
});

const filterForm = reactive({
    bulan: props.bulan,
});

function applyFilters() {
    router.get(props.resetUrl, { bulan: filterForm.bulan }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function statusClass(status: string | null) {
    return status ? `status-${status}` : '';
}

function penangananBadge(status: string) {
    if (status === 'selesai') return 'success';
    if (status === 'proses') return 'warning text-dark';
    return 'danger';
}

function statusLabel(status: string | null) {
    return status ? status.charAt(0).toUpperCase() + status.slice(1) : '-';
}
</script>

<template>
    <Head title="Detail Wali Kelas" />

    <AppShell title="Detail Wali Kelas">
        <PageHeader title="Detail Wali Kelas">
            <template #actions>
                <Button :href="backUrl" color="outline-secondary" icon="bi-arrow-left">Kembali</Button>
            </template>
        </PageHeader>

        <div class="row gy-4">
            <div class="col-12">
                <Card :title="waliKelas.title">
                    <form class="row g-3 align-items-end" @submit.prevent="applyFilters">
                        <div class="col-md-4">
                            <SelectInput
                                v-model="filterForm.bulan"
                                name="bulan"
                                label="Bulan Absensi"
                                wrapper-class="mb-0"
                                :options="bulanOptions"
                            />
                        </div>
                        <div class="col-md-3 d-grid">
                            <Button type="submit" color="primary" icon="bi-search">Tampilkan</Button>
                        </div>
                    </form>
                </Card>
            </div>

            <div class="col-12">
                <Card title="Rekap Absensi Bulanan" body-class="p-0">
                    <TableWrapper v-if="siswaRows.length" :min-width="700">
                        <table class="table table-bordered table-hover mb-0 wali-report-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="u-minw-90px">NIS</th>
                                    <th scope="col" class="u-minw-180px">Nama</th>
                                    <th scope="col" v-for="tanggal in tanggalList" :key="tanggal.date" class="text-center u-minw-48px">
                                        {{ tanggal.day }}
                                    </th>
                                    <th scope="col" class="text-center">H</th>
                                    <th scope="col" class="text-center">S</th>
                                    <th scope="col" class="text-center">I</th>
                                    <th scope="col" class="text-center">A</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="siswa in siswaRows" :key="siswa.id">
                                    <td class="tabular-nums" data-label="NIS">{{ siswa.nis }}</td>
                                    <td data-label="Nama" class="stack-title"><strong>{{ siswa.nama }}</strong></td>
                                    <td
                                        v-for="status in siswa.statuses"
                                        :key="`${siswa.id}-${status.date}`"
                                        class="text-center"
                                        :class="statusClass(status.status)"
                                    >
                                        {{ status.label }}
                                    </td>
                                    <td class="text-center text-success fw-bold tabular-nums" data-label="Hadir">{{ siswa.counts.hadir }}</td>
                                    <td class="text-center text-warning tabular-nums" data-label="Sakit">{{ siswa.counts.sakit }}</td>
                                    <td class="text-center text-info tabular-nums" data-label="Izin">{{ siswa.counts.izin }}</td>
                                    <td class="text-center text-danger fw-bold tabular-nums" data-label="Alpa">{{ siswa.counts.alpha }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>

                    <EmptyState v-else title="Tidak ada siswa aktif." icon="bi-people" />
                </Card>
            </div>

            <div class="col-lg-6">
                <Card title="Pertemuan Terbaru" body-class="p-0">
                    <TableWrapper v-if="pertemuan.length" :min-width="360">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Tanggal</th>
                                    <th scope="col">Topik</th>
                                    <th scope="col">Hasil</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in pertemuan" :key="item.id">
                                    <td class="tabular-nums" data-label="Tanggal">{{ item.tanggal }}</td>
                                    <td data-label="Topik" class="stack-title"><strong>{{ item.topik }}</strong></td>
                                    <td data-label="Hasil">{{ item.hasil || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada pertemuan." icon="bi-calendar-event" />
                </Card>
            </div>

            <div class="col-lg-6">
                <Card title="Penanganan Siswa" body-class="p-0">
                    <TableWrapper v-if="penanganan.length" :min-width="400">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Siswa</th>
                                    <th scope="col">Kondisi</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in penanganan" :key="item.id">
                                    <td data-label="Siswa" class="stack-title">
                                        <strong>{{ item.siswa }}</strong>
                                        <div class="small text-body-secondary tabular-nums">{{ item.nis }}</div>
                                    </td>
                                    <td data-label="Kondisi">
                                        {{ item.kondisi }}
                                        <div class="small text-body-secondary">{{ item.tindak_lanjut }}</div>
                                    </td>
                                    <td data-label="Status"><Badge :color="penangananBadge(item.status)">{{ statusLabel(item.status) }}</Badge></td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada penanganan siswa." icon="bi-heart-pulse" />
                </Card>
            </div>
        </div>
    </AppShell>
</template>

<style scoped>
.wali-report-table td.status-hadir {
    background: var(--status-success-bg);
    color: var(--status-success-text);
}

.wali-report-table td.status-sakit {
    background: var(--status-warning-bg);
    color: var(--status-warning-text);
}

.wali-report-table td.status-izin {
    background: var(--status-info-bg);
    color: var(--status-info-text);
}

.wali-report-table td.status-alpha {
    background: var(--status-danger-bg);
    color: var(--status-danger-text);
}
</style>

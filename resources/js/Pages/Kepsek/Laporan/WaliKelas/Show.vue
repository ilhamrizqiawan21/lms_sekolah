<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import PageHeader from '../../../../Components/AppShell/PageHeader.vue';
import { SelectInput } from '../../../../Components/Form';
import AppShell from '../../../../Layouts/AppShell.vue';
import { Badge, Button, Card, EmptyState, TableWrapper } from '../../../../Components/UI';

const props = defineProps({
    waliKelas: { type: Object, required: true },
    bulan: { type: String, required: true },
    bulanOptions: { type: Array, default: () => [] },
    tanggalList: { type: Array, default: () => [] },
    siswaRows: { type: Array, default: () => [] },
    pertemuan: { type: Array, default: () => [] },
    penanganan: { type: Array, default: () => [] },
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

function statusClass(status) {
    return status ? `status-${status}` : '';
}

function penangananBadge(status) {
    if (status === 'selesai') return 'success';
    if (status === 'proses') return 'warning text-dark';
    return 'danger';
}

function statusLabel(status) {
    return status ? status.charAt(0).toUpperCase() + status.slice(1) : '-';
}
</script>

<template>
    <Head title="Detail Wali Kelas" />

    <AppShell title="Detail Wali Kelas">
        <PageHeader title="Detail Wali Kelas" icon="bi-person-badge-fill">
            <template #actions>
                <Button :href="backUrl" color="outline-secondary" icon="bi-arrow-left">Kembali</Button>
            </template>
        </PageHeader>

        <div class="row gy-4">
            <div class="col-12">
                <Card :title="waliKelas.title" icon="bi-info-circle">
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
                <Card title="Rekap Absensi Bulanan" icon="bi-clipboard-data" body-class="p-0">
                    <TableWrapper v-if="siswaRows.length">
                        <table class="table table-bordered table-hover mb-0 wali-report-table">
                            <thead>
                                <tr>
                                    <th style="min-width:90px;">NIS</th>
                                    <th style="min-width:180px;">Nama</th>
                                    <th v-for="tanggal in tanggalList" :key="tanggal.date" class="text-center" style="min-width:48px;">
                                        {{ tanggal.day }}
                                    </th>
                                    <th class="text-center">H</th>
                                    <th class="text-center">S</th>
                                    <th class="text-center">I</th>
                                    <th class="text-center">A</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="siswa in siswaRows" :key="siswa.id">
                                    <td>{{ siswa.nis }}</td>
                                    <td><strong>{{ siswa.nama }}</strong></td>
                                    <td
                                        v-for="status in siswa.statuses"
                                        :key="`${siswa.id}-${status.date}`"
                                        class="text-center"
                                        :class="statusClass(status.status)"
                                    >
                                        {{ status.label }}
                                    </td>
                                    <td class="text-center text-success fw-bold">{{ siswa.counts.hadir }}</td>
                                    <td class="text-center text-warning">{{ siswa.counts.sakit }}</td>
                                    <td class="text-center text-info">{{ siswa.counts.izin }}</td>
                                    <td class="text-center text-danger fw-bold">{{ siswa.counts.alpha }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>

                    <EmptyState v-else title="Tidak ada siswa aktif." icon="bi-people" />
                </Card>
            </div>

            <div class="col-lg-6">
                <Card title="Pertemuan Terbaru" icon="bi-calendar-event" body-class="p-0">
                    <TableWrapper v-if="pertemuan.length">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Topik</th>
                                    <th>Hasil</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in pertemuan" :key="item.id">
                                    <td>{{ item.tanggal }}</td>
                                    <td>{{ item.topik }}</td>
                                    <td>{{ item.hasil }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada pertemuan." icon="bi-calendar-event" />
                </Card>
            </div>

            <div class="col-lg-6">
                <Card title="Penanganan Siswa" icon="bi-heart-pulse" body-class="p-0">
                    <TableWrapper v-if="penanganan.length">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th>Kondisi</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in penanganan" :key="item.id">
                                    <td>
                                        {{ item.siswa }}
                                        <div class="small text-muted">{{ item.nis }}</div>
                                    </td>
                                    <td>
                                        {{ item.kondisi }}
                                        <div class="small text-muted">{{ item.tindak_lanjut }}</div>
                                    </td>
                                    <td><Badge :color="penangananBadge(item.status)">{{ statusLabel(item.status) }}</Badge></td>
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

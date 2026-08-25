<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Card, EmptyState, MetricStrip, TableWrapper } from '../../../Components/UI';

const props = defineProps({
    kelasMapel: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    selected: { type: Object, default: null },
    rekap: { type: Array, default: () => [] },
    exportUrls: { type: Object, default: () => ({}) },
});

const kelasMapelId = ref(props.filters.kelas_mapel_id || '');
const mode = ref(props.filters.mode || 'bulanan');
const bulan = ref(props.filters.bulan || new Date().toISOString().slice(0, 7));

const metrics = computed(() => {
    const totals = props.rekap.reduce((carry, item) => ({
        total: carry.total + Number(item.total || 0),
        hadir: carry.hadir + Number(item.hadir || 0),
        sakit: carry.sakit + Number(item.sakit || 0),
        izin: carry.izin + Number(item.izin || 0),
        alpha: carry.alpha + Number(item.alpha || 0),
    }), { total: 0, hadir: 0, sakit: 0, izin: 0, alpha: 0 });

    return [
        { label: 'Total catatan', value: totals.total, icon: 'bi-clipboard-data-fill', tone: 'primary' },
        { label: 'Hadir', value: totals.hadir, icon: 'bi-check-circle-fill', tone: 'success' },
        { label: 'Sakit/Izin', value: totals.sakit + totals.izin, icon: 'bi-info-circle-fill', tone: 'info' },
        { label: 'Alpha', value: totals.alpha, icon: 'bi-exclamation-triangle-fill', tone: totals.alpha ? 'danger' : 'muted' },
        { label: 'Siswa', value: props.rekap.length, icon: 'bi-people-fill', tone: 'warning' },
    ];
});

function currentParams() {
    const params = {
        kelas_mapel_id: kelasMapelId.value || undefined,
        mode: mode.value || 'bulanan',
    };

    if (mode.value === 'bulanan') {
        params.bulan = bulan.value || undefined;
    }

    return params;
}

function reload() {
    router.get('/guru/rekap-absensi', currentParams(), {
        preserveState: true,
        replace: true,
    });
}

function exportUrl(format) {
    const base = props.exportUrls?.[format];
    const params = new URLSearchParams();

    Object.entries(currentParams()).forEach(([key, value]) => {
        if (value) {
            params.set(key, value);
        }
    });

    return base && params.toString() ? `${base}?${params}` : base;
}

function progressColor(value) {
    if (value >= 90) return 'bg-success';
    if (value >= 75) return 'bg-warning';
    return 'bg-danger';
}
</script>

<template>
    <Head title="Rekap Absensi" />

    <AppShell title="Rekap Absensi">
        <PageHeader
            title="Rekap Absensi"
            subtitle="Pantau ringkasan absensi per bulan atau keseluruhan untuk kelas yang Anda ampu."
            icon="bi-file-earmark-spreadsheet-fill"
        />

        <Card title="Filter Rekap" icon="bi-funnel-fill">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Kelas dan Mata Pelajaran</label>
                    <select v-model="kelasMapelId" class="form-select" @change="reload">
                        <option value="">Pilih kelas</option>
                        <option v-for="item in kelasMapel" :key="item.id" :value="String(item.id)">
                            {{ item.label }}
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Metode Rekap</label>
                    <select v-model="mode" class="form-select" @change="reload">
                        <option value="bulanan">Per bulan</option>
                        <option value="keseluruhan">Keseluruhan</option>
                    </select>
                </div>
                <div v-if="mode === 'bulanan'" class="col-md-2">
                    <label class="form-label">Bulan</label>
                    <input v-model="bulan" type="month" class="form-control" @change="reload">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <a
                        v-if="selected"
                        :href="exportUrl('excel')"
                        class="btn btn-outline-success flex-fill"
                    >
                        <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Excel
                    </a>
                    <a
                        v-if="selected"
                        :href="exportUrl('pdf')"
                        class="btn btn-outline-danger flex-fill"
                    >
                        <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>PDF
                    </a>
                </div>
            </div>
        </Card>

        <MetricStrip v-if="selected" :items="metrics" />

        <div v-if="selected" class="content-summary">
            <div>
                <div class="content-summary-title">{{ selected.kelas }} - {{ selected.mata_pelajaran }}</div>
                <div class="content-summary-text">
                    {{ mode === 'bulanan' ? `Rekap bulan ${bulan}` : 'Rekap seluruh data absensi yang tersedia' }}
                </div>
            </div>
            <Badge color="secondary">Semester {{ selected.semester }}</Badge>
        </div>

        <Card v-if="selected && rekap.length" title="Ringkasan Siswa" icon="bi-table" body-class="p-0">
            <TableWrapper>
                <table class="table table-hover mb-0 app-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th class="text-center">H</th>
                            <th class="text-center">S</th>
                            <th class="text-center">I</th>
                            <th class="text-center">A</th>
                            <th class="text-center">Total</th>
                            <th>Persen Hadir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in rekap" :key="item.nis">
                            <td>{{ item.no }}</td>
                            <td>{{ item.nis }}</td>
                            <td><strong>{{ item.nama }}</strong></td>
                            <td class="text-center">{{ item.hadir }}</td>
                            <td class="text-center">{{ item.sakit }}</td>
                            <td class="text-center">{{ item.izin }}</td>
                            <td class="text-center">{{ item.alpha }}</td>
                            <td class="text-center">{{ item.total }}</td>
                            <td style="min-width:160px">
                                <div class="progress rekap-progress">
                                    <div
                                        class="progress-bar"
                                        :class="progressColor(item.persen_hadir)"
                                        :style="{ width: `${item.persen_hadir}%` }"
                                    >
                                        {{ item.persen_hadir }}%
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>
        </Card>

        <EmptyState
            v-else
            :title="selected ? 'Belum ada data absensi untuk filter ini' : 'Pilih kelas untuk melihat rekap absensi'"
            icon="bi-clipboard-data"
        />
    </AppShell>
</template>

<style scoped>
.rekap-progress {
    height: 18px;
}

@media (max-width: 767.98px) {
    .btn {
        min-width: 0;
    }
}
</style>

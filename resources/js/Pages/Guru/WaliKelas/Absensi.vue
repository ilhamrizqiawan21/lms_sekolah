<script setup lang="ts">
import type { PropType } from 'vue';

import { Head, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { SelectInput } from '../../../Components/Form';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, EmptyState, TableWrapper } from '../../../Components/UI';

const props = defineProps({
    waliKelas: { type: Object as PropType<{ kelas: string; store_url: string; back_url: string }>, required: true },
    bulan: { type: String, required: true },
    bulanLabel: { type: String, default: '' },
    bulanOptions: { type: Object as PropType<Record<string, string>>, default: () => ({}) },
    tanggalList: { type: Array as PropType<{ key: string; day: string; label: string }[]>, default: () => [] },
    students: { type: Array as PropType<{ id: number; no: number; nis: string; nama: string; absensi: Record<string, string> }[]>, default: () => [] },
});

const filter = useForm({ bulan: props.bulan });
const form = useForm({
    bulan: props.bulan,
    absensi: buildAbsensi(),
});

watch(() => [props.students, props.bulan], () => {
    form.bulan = props.bulan;
    form.absensi = buildAbsensi();
    filter.bulan = props.bulan;
}, { deep: true });

function buildAbsensi() {
    return Object.fromEntries(props.students.map((student) => [
        String(student.id),
        Object.fromEntries(props.tanggalList.map((tanggal) => [
            tanggal.key,
            student.absensi?.[tanggal.key] ?? '',
        ])),
    ]));
}

function filterMonth() {
    router.get(window.location.pathname, { bulan: filter.bulan }, {
        preserveScroll: true,
        preserveState: false,
    });
}

function fillColumn(tanggalKey: string, event: Event) {
    if (!(event.target instanceof HTMLSelectElement)) return;
    const status = event.target.value;
    event.target.value = '';
    if (!status) return;

    props.students.forEach((student) => {
        form.absensi[String(student.id)][tanggalKey] = status;
    });
}

function statusClass(status: string) {
    return status ? `status-${status}` : '';
}

function submit() {
    if (form.processing) {
        return;
    }

    form.post(props.waliKelas.store_url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Absensi Wali Kelas" />

    <AppShell title="Absensi Wali Kelas">
        <PageHeader eyebrow="Wali Kelas" :title="`Absensi Harian · ${waliKelas.kelas}`" subtitle="Catat kehadiran harian siswa di kelas perwalian Anda." />

        <div class="row gy-4">
            <div class="col-12">
                <Card title="Filter Bulan" icon="bi-funnel-fill">
                    <form class="row g-3 align-items-end" @submit.prevent="filterMonth">
                        <div class="col-md-4">
                            <SelectInput
                                v-model="filter.bulan"
                                name="bulan"
                                label="Bulan"
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
                <form @submit.prevent="submit">
                    <input v-model="form.bulan" type="hidden" name="bulan">
                    <Card :title="`Absensi Harian ${bulanLabel}`" icon="bi-table" body-class="p-0">
                        <div class="p-3 d-flex flex-wrap gap-2">
                            <Badge color="success">H=Hadir</Badge>
                            <Badge color="warning text-dark">S=Sakit</Badge>
                            <Badge color="info text-dark">I=Izin</Badge>
                            <Badge color="danger">A=Alpha</Badge>
                        </div>

                        <TableWrapper :scroll-hint="false">
                            <table class="table table-bordered table-hover mb-0 wali-attendance-table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="grid-sticky-col u-minw-180px">Siswa</th>
                                        <th scope="col"
                                            v-for="tanggal in tanggalList"
                                            :key="tanggal.key"
                                            class="text-center u-minw-62px"
                                           
                                        >
                                            {{ tanggal.day }}<br><small class="text-body-secondary">{{ tanggal.label }}</small>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="grid-sticky-col identity-cell"><span class="identity-meta">Isi satu kolom</span></th>
                                        <td v-for="tanggal in tanggalList" :key="`fill-${tanggal.key}`" class="text-center p-1">
                                            <select
                                                class="form-select form-select-sm wali-attendance-select"
                                                :aria-label="`Isi semua siswa, ${tanggal.day} ${tanggal.label}`"
                                                @change="fillColumn(tanggal.key, $event)"
                                            >
                                                <option value="">-</option>
                                                <option value="hadir">H</option>
                                                <option value="sakit">S</option>
                                                <option value="izin">I</option>
                                                <option value="alpha">A</option>
                                            </select>
                                        </td>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="student in students" :key="student.id">
                                        <th scope="row" class="grid-sticky-col identity-cell">
                                            <span class="identity-name">{{ student.nama }}</span>
                                            <span class="identity-meta">{{ student.no }}. NIS {{ student.nis }}</span>
                                        </th>
                                        <td v-for="tanggal in tanggalList" :key="`${student.id}-${tanggal.key}`" class="p-1 text-center align-middle">
                                            <select
                                                v-model="form.absensi[String(student.id)][tanggal.key]"
                                                :aria-label="`${student.nama}, ${tanggal.day} ${tanggal.label}`"
                                                class="form-select form-select-sm wali-attendance-select"
                                                :class="statusClass(form.absensi[String(student.id)][tanggal.key])"
                                            >
                                                <option value="">-</option>
                                                <option value="hadir">H</option>
                                                <option value="sakit">S</option>
                                                <option value="izin">I</option>
                                                <option value="alpha">A</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr v-if="!students.length">
                                        <td :colspan="1 + tanggalList.length">
                                            <EmptyState title="Tidak ada siswa aktif di kelas ini." icon="bi-people" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </TableWrapper>

                        <div class="sticky-savebar">
                            <a :href="waliKelas.back_url" class="btn btn-outline-secondary" aria-label="Kembali">
                                <i class="bi bi-arrow-left" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">Kembali</span>
                            </a>
                            <span class="sticky-savebar-status" role="status" aria-live="polite">
                                <template v-if="form.isDirty"><i class="bi bi-circle-fill dirty-dot" aria-hidden="true"></i>Belum disimpan<span class="d-none d-sm-inline">: ada perubahan absensi</span></template>
                                <template v-else-if="form.recentlySuccessful"><i class="bi bi-check-circle-fill text-success me-1" aria-hidden="true"></i>Tersimpan</template>
                            </span>
                            <Button type="submit" color="primary" icon="bi-save" :loading="form.processing">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Absensi' }}
                            </Button>
                        </div>
                    </Card>
                </form>
            </div>
        </div>
    </AppShell>
</template>

<style scoped>
.wali-attendance-select { font-size:0.72rem; min-width:54px; padding:0.25rem 0.35rem; text-align:center; }
.wali-attendance-select.status-hadir { background:var(--status-success-bg); color:var(--status-success-text); }
.wali-attendance-select.status-sakit { background:var(--status-warning-bg); color:var(--status-warning-text); }
.wali-attendance-select.status-izin { background:var(--status-info-bg); color:var(--status-info-text); }
.wali-attendance-select.status-alpha { background:var(--status-danger-bg); color:var(--status-danger-text); }
.wali-attendance-table th { vertical-align:middle; }
</style>

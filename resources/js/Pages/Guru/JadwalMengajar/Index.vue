<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { SearchableSelect, SelectInput } from '../../../Components/Form';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, EmptyState, TableWrapper } from '../../../Components/UI';

const props = defineProps({
    days: { type: Array, default: () => [] },
    lessonSlots: { type: Array, default: () => [] },
    kelasMapel: { type: Array, default: () => [] },
    schedules: { type: Array, default: () => [] },
    storeUrl: { type: String, required: true },
});

const form = useForm({
    kelas_mapel_id: '',
    hari: '',
    pelajaran_ke: '',
});

function submit() {
    form.post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

async function destroySchedule(schedule) {
    const confirmed = await window.confirmDialog?.(`Hapus jadwal ${schedule.hari_label} pelajaran ke-${schedule.pelajaran_ke}?`, {
        title: 'Hapus Jadwal',
        confirmText: 'Ya, hapus',
        danger: true,
    });

    if (!confirmed) return;

    router.delete(schedule.delete_url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Jadwal Mengajar" />

    <AppShell title="Jadwal Mengajar">
        <PageHeader
            title="Jadwal Mengajar"
            subtitle="Atur slot Senin-Jumat pelajaran ke-1 sampai ke-5 untuk sinkronisasi absensi."
            icon="bi-calendar-week-fill"
        />

        <div class="row g-4">
            <div class="col-lg-5">
                <Card title="Tambah Jadwal" icon="bi-calendar-plus">
                    <form @submit.prevent="submit">
                        <SearchableSelect
                            v-model="form.kelas_mapel_id"
                            name="kelas_mapel_id"
                            label="Kelas dan Mata Pelajaran"
                            placeholder="Pilih kelas/mapel"
                            :options="kelasMapel.map((item) => ({ value: item.id, label: item.label }))"
                            required
                            :error="form.errors.kelas_mapel_id"
                        />
                        <SelectInput
                            v-model="form.hari"
                            name="hari"
                            label="Hari"
                            placeholder="Pilih hari"
                            :options="days"
                            required
                            :error="form.errors.hari"
                        />
                        <SelectInput
                            v-model="form.pelajaran_ke"
                            name="pelajaran_ke"
                            label="Jam Pelajaran"
                            placeholder="Pilih jam"
                            :options="lessonSlots"
                            required
                            :error="form.errors.pelajaran_ke"
                        />
                        <Button type="submit" color="success" icon="bi-save" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Jadwal' }}
                        </Button>
                    </form>
                </Card>
            </div>

            <div class="col-lg-7">
                <Card title="Daftar Jadwal" icon="bi-list-check" body-class="p-0">
                    <TableWrapper v-if="schedules.length">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Hari</th>
                                    <th>Jam</th>
                                    <th>Kelas/Mapel</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="schedule in schedules" :key="schedule.id">
                                    <td><Badge color="primary">{{ schedule.hari_label }}</Badge></td>
                                    <td>Pelajaran ke-{{ schedule.pelajaran_ke }}</td>
                                    <td><strong>{{ schedule.kelas_mapel }}</strong></td>
                                    <td class="text-end">
                                        <Button type="button" color="outline-danger" icon="bi-trash" @click="destroySchedule(schedule)">Hapus</Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada jadwal mengajar" message="Tambahkan jadwal agar absensi otomatis mengikuti tanggal yang benar." icon="bi-calendar-week" />
                </Card>
            </div>
        </div>
    </AppShell>
</template>

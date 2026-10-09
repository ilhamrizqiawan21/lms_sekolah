<script setup lang="ts">
import type { PropType } from 'vue';
import type { PaginationLink } from '../../../types/pagination';
interface Homeroom { id: number; kelas: string; guru: string; tahun_ajaran: string }
interface Teaching extends Homeroom { mapel: string; mapel_kode: string; semester: string; pertemuan_per_minggu: number }
interface Schedule { id: number; guru: string; hari: string; pelajaran_ke: number; kelas_mapel: string; delete_url: string }
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../../Components/AppShell/PageHeader.vue';
import { SearchableSelect, SelectInput, TextInput } from '../../../Components/Form';
import AppShell from '../../../Layouts/AppShell.vue';
import {
    Badge,
    Button,
    EmptyState,
    IconButton,
    Pagination,
    TableWrapper,
} from '../../../Components/UI';

const props = defineProps({
    kelasMapel: { type: Object as PropType<{ data: Teaching[]; links: PaginationLink[]; total?: number }>, default: () => ({ data: [], links: [] }) },
    waliKelas: { type: Object as PropType<{ data: Homeroom[]; links: PaginationLink[]; total?: number }>, default: () => ({ data: [], links: [] }) },
    jadwalMengajar: { type: Array as PropType<Schedule[]>, default: () => [] },
    kelasOptions: { type: Array as PropType<{ value: number; label: string }[]>, default: () => [] },
    mapelOptions: { type: Array as PropType<{ value: number; label: string }[]>, default: () => [] },
    guruOptions: { type: Array as PropType<{ value: number; label: string }[]>, default: () => [] },
    tahunAjaranOptions: { type: Array as PropType<{ value: number; label: string }[]>, default: () => [] },
});

const teachingForm = useForm({
    kelas_id: '',
    mapel_id: '',
    guru_id: '',
    tahun_ajaran_id: '',
    semester: '',
    pertemuan_per_minggu: 1,
});

const homeroomForm = useForm({
    kelas_id: '',
    guru_id: '',
    tahun_ajaran_id: '',
});

const semesterOptions = [
    { value: '1', label: 'Semester 1 (Ganjil)' },
    { value: '2', label: 'Semester 2 (Genap)' },
];

function submitTeaching() {
    if (teachingForm.processing) {
        return;
    }

    teachingForm.post('/admin/kelas-mapel', {
        preserveScroll: true,
        onSuccess: () => teachingForm.reset(),
    });
}

function submitHomeroom() {
    if (homeroomForm.processing) {
        return;
    }

    homeroomForm.post('/admin/wali-kelas', {
        preserveScroll: true,
        onSuccess: () => homeroomForm.reset(),
    });
}

async function destroyTeaching(item: Teaching) {
    const confirmed = await window.confirmDialog?.(`Hapus penugasan ${item.mapel} untuk ${item.kelas}?`, {
        title: 'Hapus Pengajaran',
        confirmText: 'Ya, hapus',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    router.delete(`/admin/kelas-mapel/${item.id}`, {
        preserveScroll: true,
    });
}

async function destroyHomeroom(item: Homeroom) {
    const confirmed = await window.confirmDialog?.(`Hapus wali kelas ${item.kelas}?`, {
        title: 'Hapus Wali Kelas',
        confirmText: 'Ya, hapus',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    router.delete(`/admin/wali-kelas/${item.id}`, {
        preserveScroll: true,
    });
}

async function destroySchedule(item: Schedule) {
    const confirmed = await window.confirmDialog?.(`Hapus jadwal ${item.guru} pada ${item.hari} pelajaran ke-${item.pelajaran_ke}?`, {
        title: 'Hapus Jadwal Mengajar',
        confirmText: 'Ya, hapus',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    router.delete(item.delete_url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Penugasan Guru" />

    <AppShell title="Penugasan Guru">
        <PageHeader
            eyebrow="Master Data"
            title="Penugasan Guru"
            subtitle="Atur guru pengampu mata pelajaran, beban pertemuan, dan wali kelas."
        />

        <div class="dashboard-grid assignment-grid">
            <section class="workspace-panel">
                <header class="workspace-panel-header">
                    <span class="workspace-panel-title">
                        <i class="bi bi-plus-circle" aria-hidden="true"></i>
                        Tambah Pengajaran
                    </span>
                    <Badge color="primary">Mapel</Badge>
                </header>
                <div class="workspace-panel-body">
                    <form @submit.prevent="submitTeaching">
                        <SearchableSelect
                            v-model="teachingForm.kelas_id"
                            name="kelas_id"
                            label="Kelas"
                            placeholder="Pilih kelas"
                            :options="kelasOptions"
                            required
                            :error="teachingForm.errors.kelas_id"
                        />
                        <SearchableSelect
                            v-model="teachingForm.mapel_id"
                            name="mapel_id"
                            label="Mata Pelajaran"
                            placeholder="Pilih mapel"
                            :options="mapelOptions"
                            required
                            :error="teachingForm.errors.mapel_id"
                        />
                        <SearchableSelect
                            v-model="teachingForm.guru_id"
                            name="guru_id"
                            label="Guru"
                            placeholder="Pilih guru"
                            :options="guruOptions"
                            required
                            :error="teachingForm.errors.guru_id"
                        />
                        <div class="row">
                            <div class="col-md-6">
                                <SelectInput
                                    v-model="teachingForm.tahun_ajaran_id"
                                    name="tahun_ajaran_id"
                                    label="Tahun Ajaran"
                                    placeholder="Pilih"
                                    :options="tahunAjaranOptions"
                                    required
                                    :error="teachingForm.errors.tahun_ajaran_id"
                                />
                            </div>
                            <div class="col-md-6">
                                <SelectInput
                                    v-model="teachingForm.semester"
                                    name="semester"
                                    label="Semester"
                                    placeholder="Pilih"
                                    :options="semesterOptions"
                                    required
                                    :error="teachingForm.errors.semester"
                                />
                            </div>
                        </div>
                        <TextInput
                            v-model="teachingForm.pertemuan_per_minggu"
                            name="pertemuan_per_minggu"
                            type="number"
                            label="Pertemuan per Minggu"
                            min="1"
                            max="6"
                            required
                            :error="teachingForm.errors.pertemuan_per_minggu"
                        />
                        <Button
                            type="submit"
                            color="success"
                            size=""
                            icon="bi-save"
                            class="w-100"
                            :disabled="teachingForm.processing"
                        >
                            {{ teachingForm.processing ? 'Menyimpan...' : 'Simpan Pengajaran' }}
                        </Button>
                    </form>
                </div>
            </section>

            <section class="workspace-panel">
                <header class="workspace-panel-header">
                    <span class="workspace-panel-title">
                        <i class="bi bi-person-badge-fill" aria-hidden="true"></i>
                        Tambah Wali Kelas
                    </span>
                    <Badge color="success">Wali</Badge>
                </header>
                <div class="workspace-panel-body">
                    <form @submit.prevent="submitHomeroom">
                        <SearchableSelect
                            v-model="homeroomForm.kelas_id"
                            name="wali_kelas_id"
                            label="Kelas"
                            placeholder="Pilih kelas"
                            :options="kelasOptions"
                            required
                            :error="homeroomForm.errors.kelas_id"
                        />
                        <SearchableSelect
                            v-model="homeroomForm.guru_id"
                            name="wali_guru_id"
                            label="Guru Wali Kelas"
                            placeholder="Pilih guru"
                            :options="guruOptions"
                            required
                            :error="homeroomForm.errors.guru_id"
                        />
                        <SearchableSelect
                            v-model="homeroomForm.tahun_ajaran_id"
                            name="wali_tahun_ajaran_id"
                            label="Tahun Ajaran"
                            placeholder="Pilih tahun ajaran"
                            :options="tahunAjaranOptions"
                            required
                            :error="homeroomForm.errors.tahun_ajaran_id"
                        />
                        <Button
                            type="submit"
                            color="success"
                            size=""
                            icon="bi-save"
                            class="w-100"
                            :disabled="homeroomForm.processing"
                        >
                            {{ homeroomForm.processing ? 'Menyimpan...' : 'Simpan Wali Kelas' }}
                        </Button>
                    </form>
                </div>
            </section>
        </div>

        <section class="workspace-panel">
            <header class="workspace-panel-header">
                <span class="workspace-panel-title">
                    <i class="bi bi-diagram-3-fill" aria-hidden="true"></i>
                    Daftar Pengajaran
                </span>
                <Badge color="primary">{{ kelasMapel.total ?? kelasMapel.data?.length ?? 0 }} penugasan</Badge>
            </header>
                    <TableWrapper stack v-if="kelasMapel.data?.length">
                        <table class="table table-hover app-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Kelas</th>
                                    <th scope="col">Mapel</th>
                                    <th scope="col">Guru</th>
                                    <th scope="col">Pertemuan</th>
                                    <th scope="col">Semester</th>
                                    <th scope="col">Tahun</th>
                                    <th scope="col" class="table-action-column">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in kelasMapel.data" :key="item.id">
                                    <td data-label="Kelas"><strong>{{ item.kelas }}</strong></td>
                                    <td class="stack-title">
                                        <Badge color="secondary">{{ item.mapel_kode }}</Badge>
                                        <span class="ms-2">{{ item.mapel }}</span>
                                    </td>
                                    <td data-label="Guru">{{ item.guru }}</td>
                                    <td data-label="Pertemuan">{{ item.pertemuan_per_minggu }}x/minggu</td>
                                    <td data-label="Semester"><Badge color="info">Semester {{ item.semester }}</Badge></td>
                                    <td data-label="Tahun">{{ item.tahun_ajaran }}</td>
                                    <td class="table-action-column stack-actions">
                                        <div class="d-flex justify-content-end gap-1">
                                            <IconButton
                                                icon="bi-trash"
                                                :label="`Hapus penugasan ${item.mapel}`"
                                                color="outline-danger"
                                                @click="destroyTeaching(item)"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada pengajaran" icon="bi-diagram-3" />

            <div v-if="kelasMapel.links?.length > 3" class="d-flex justify-content-end p-3 border-top">
                        <Pagination :links="kelasMapel.links" />
            </div>
        </section>

        <section class="workspace-panel">
            <header class="workspace-panel-header">
                <span class="workspace-panel-title">
                    <i class="bi bi-people-fill" aria-hidden="true"></i>
                    Daftar Wali Kelas
                </span>
                <Badge color="success">{{ waliKelas.total ?? waliKelas.data?.length ?? 0 }} wali kelas</Badge>
            </header>
                    <TableWrapper stack v-if="waliKelas.data?.length">
                        <table class="table table-hover app-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Kelas</th>
                                    <th scope="col">Wali Kelas</th>
                                    <th scope="col">Tahun Ajaran</th>
                                    <th scope="col" class="table-action-column">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in waliKelas.data" :key="item.id">
                                    <td data-label="Kelas"><strong>{{ item.kelas }}</strong></td>
                                    <td class="stack-title">{{ item.guru }}</td>
                                    <td data-label="Tahun Ajaran">{{ item.tahun_ajaran }}</td>
                                    <td class="table-action-column stack-actions">
                                        <div class="d-flex justify-content-end gap-1">
                                            <IconButton
                                                icon="bi-trash"
                                                :label="`Hapus wali kelas ${item.kelas}`"
                                                color="outline-danger"
                                                @click="destroyHomeroom(item)"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada penugasan wali kelas" icon="bi-person-badge" />

            <div v-if="waliKelas.links?.length > 3" class="d-flex justify-content-end p-3 border-top">
                        <Pagination :links="waliKelas.links" />
            </div>
        </section>

        <section class="workspace-panel">
            <header class="workspace-panel-header">
                <span class="workspace-panel-title">
                    <i class="bi bi-calendar-week-fill" aria-hidden="true"></i>
                    Jadwal Mengajar Guru
                </span>
                <Badge color="primary">{{ jadwalMengajar.length }} slot</Badge>
            </header>
            <TableWrapper stack v-if="jadwalMengajar.length">
                <table class="table table-hover app-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Guru</th>
                            <th scope="col">Hari</th>
                            <th scope="col">Jam</th>
                            <th scope="col">Kelas/Mapel</th>
                            <th scope="col" class="table-action-column">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in jadwalMengajar" :key="item.id">
                            <td class="stack-title"><strong>{{ item.guru }}</strong></td>
                            <td data-label="Hari"><Badge color="primary">{{ item.hari }}</Badge></td>
                            <td data-label="Jam">Pelajaran ke-{{ item.pelajaran_ke }}</td>
                            <td data-label="Kelas/Mapel">{{ item.kelas_mapel }}</td>
                            <td class="table-action-column stack-actions">
                                <IconButton
                                    icon="bi-trash"
                                    :label="`Hapus jadwal ${item.guru}`"
                                    color="outline-danger"
                                    @click="destroySchedule(item)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableWrapper>
            <EmptyState v-else title="Belum ada jadwal mengajar" icon="bi-calendar-week" />
        </section>
    </AppShell>
</template>

<style scoped>
.assignment-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

@media (max-width: 900px) {
    .assignment-grid {
        grid-template-columns: 1fr;
    }
}
</style>

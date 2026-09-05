<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { SelectInput, TextInput } from '../../../Components/Form';
import AppShell from '../../../Layouts/AppShell.vue';
import { Badge, Button, Card, DashboardHero, EmptyState, IconButton, MetricStrip, TableWrapper } from '../../../Components/UI';

const props = defineProps({
    kelas: { type: Array, default: () => [] },
    metrics: { type: Object, default: () => ({}) },
});

const metrics = computed(() => [
    { label: 'Kelas', value: props.metrics.total_kelas ?? 0, icon: 'bi-building', tone: 'primary' },
    { label: 'Siswa aktif', value: props.metrics.total_siswa ?? 0, icon: 'bi-people-fill', tone: 'success', href: '/admin/kelas-siswa' },
    { label: 'Penugasan mapel', value: props.metrics.total_penugasan ?? 0, icon: 'bi-diagram-3-fill', tone: 'info', href: '/admin/kelas-mapel' },
]);

const tingkatOptions = [
    { value: 'VII', label: 'VII' },
    { value: 'VIII', label: 'VIII' },
    { value: 'IX', label: 'IX' },
];

const editing = ref(null);
const createForm = useForm(blankForm());
const editForm = useForm(blankForm());
const formTitle = computed(() => editing.value ? 'Edit Kelas' : 'Tambah Kelas');
const formIcon = computed(() => editing.value ? 'bi-pencil-square' : 'bi-plus-circle');

function blankForm() {
    return {
        tingkat: '',
        nama_kelas: '',
    };
}

function startEdit(item) {
    editing.value = item;
    editForm.clearErrors();
    editForm.defaults({
        tingkat: item.tingkat ?? '',
        nama_kelas: item.nama_kelas ?? '',
    });
    editForm.reset();
}

function cancelEdit() {
    editing.value = null;
    editForm.clearErrors();
    editForm.reset();
}

function submitCreate() {
    if (createForm.processing) {
        return;
    }

    createForm.post('/admin/kelas', {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function submitEdit() {
    if (!editing.value || editForm.processing) {
        return;
    }

    editForm.put(`/admin/kelas/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: cancelEdit,
    });
}

async function destroy(item) {
    const confirmed = await window.confirmDialog?.(`Hapus kelas ${item.nama_kelas}?`, {
        title: 'Hapus Kelas',
        confirmText: 'Ya, hapus',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    router.delete(`/admin/kelas/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editing.value?.id === item.id) {
                cancelEdit();
            }
        },
    });
}
</script>

<template>
    <Head title="Data Kelas" />

    <AppShell title="Data Kelas">
        <DashboardHero
            eyebrow="Master Data"
            title="Data Kelas"
            subtitle="Kelola rombongan belajar, siswa aktif, dan kesiapan penugasan mengajar."
            icon="bi-building-fill"
            tone="admin"
        />

        <MetricStrip :items="metrics" />

        <div class="row">
            <div class="col-md-5 mb-4">
                <Card :title="formTitle" :icon="formIcon">
                    <form v-if="!editing" @submit.prevent="submitCreate">
                        <SelectInput
                            v-model="createForm.tingkat"
                            name="tingkat"
                            label="Tingkat"
                            placeholder="-- Pilih --"
                            required
                            :options="tingkatOptions"
                            :error="createForm.errors.tingkat"
                        />
                        <TextInput
                            v-model="createForm.nama_kelas"
                            name="nama_kelas"
                            label="Nama Kelas"
                            placeholder="Contoh: A"
                            help="Tingkat dipilih terpisah. Nama kelas dapat dipakai kembali pada tingkat lain."
                            maxlength="20"
                            required
                            :error="createForm.errors.nama_kelas"
                        />
                        <Button
                            type="submit"
                            color="success"
                            size=""
                            icon="bi-save"
                            class="w-100"
                            :disabled="createForm.processing"
                        >
                            {{ createForm.processing ? 'Menyimpan...' : 'Simpan' }}
                        </Button>
                    </form>

                    <form v-else @submit.prevent="submitEdit">
                        <SelectInput
                            v-model="editForm.tingkat"
                            name="tingkat"
                            label="Tingkat"
                            required
                            :options="tingkatOptions"
                            :error="editForm.errors.tingkat"
                        />
                        <TextInput
                            v-model="editForm.nama_kelas"
                            name="nama_kelas"
                            label="Nama Kelas"
                            maxlength="20"
                            required
                            :error="editForm.errors.nama_kelas"
                        />
                        <div class="d-flex gap-2">
                            <Button type="button" color="light" size="" class="flex-fill" @click="cancelEdit">
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                color="primary"
                                size=""
                                icon="bi-save"
                                class="flex-fill"
                                :disabled="editForm.processing"
                            >
                                {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>

            <div class="col-md-7 mb-4">
                <Card title="Daftar Kelas" icon="bi-building" body-class="p-0">
                    <TableWrapper v-if="kelas.length">
                        <table class="table table-hover app-table mb-0">
                            <thead>
                                <tr>
                                    <th>Tingkat</th>
                                    <th>Nama Kelas</th>
                                    <th>Siswa Aktif</th>
                                    <th>Penugasan</th>
                                    <th>Wali Kelas</th>
                                    <th class="table-action-column">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in kelas" :key="item.id">
                                    <td><Badge color="secondary">{{ item.tingkat }}</Badge></td>
                                    <td><strong>{{ item.nama_kelas }}</strong></td>
                                    <td>{{ item.siswa_count ?? 0 }} siswa</td>
                                    <td>{{ item.kelas_mapel_count ?? 0 }} mapel</td>
                                    <td>{{ item.wali_kelas_count ? 'Sudah ada' : 'Belum ada' }}</td>
                                    <td class="table-action-column">
                                        <div class="d-flex justify-content-end gap-1">
                                            <Button :href="item.siswa_url" color="outline-secondary" icon="bi-people" aria-label="Lihat siswa">Siswa</Button>
                                            <IconButton
                                                icon="bi-pencil"
                                                :label="`Edit kelas ${item.nama_kelas}`"
                                                color="outline-primary"
                                                @click="startEdit(item)"
                                            />
                                            <IconButton
                                                icon="bi-trash"
                                                :label="`Hapus kelas ${item.nama_kelas}`"
                                                color="outline-danger"
                                                @click="destroy(item)"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </TableWrapper>
                    <EmptyState v-else title="Belum ada kelas" icon="bi-building" />
                </Card>
            </div>
        </div>
    </AppShell>
</template>

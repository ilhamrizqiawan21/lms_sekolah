<script setup>
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/AppShell/PageHeader.vue';
import FileInput from '../../Components/Form/FileInput.vue';
import TextInput from '../../Components/Form/TextInput.vue';
import AppShell from '../../Layouts/AppShell.vue';
import { Badge, Button, Card } from '../../Components/UI';

const props = defineProps({
    profile: { type: Object, required: true },
    updateUrl: { type: String, required: true },
    avatarUpdateUrl: { type: String, required: true },
    avatarDeleteUrl: { type: String, required: true },
});

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const avatarForm = useForm({
    foto: null,
});

const accountRows = computed(() => [
    ['Username', props.profile.username || '-'],
    ['Nama Lengkap', props.profile.nama_lengkap || '-'],
    ['Email', props.profile.email || '-'],
    ['NIP / NIS', props.profile.nip_nis || '-'],
    ['Jenis Kelamin', props.profile.jenis_kelamin || '-'],
    ['Tanggal Dibuat', props.profile.created_at || '-'],
]);

const siswaRows = computed(() => {
    if (!props.profile.siswa) {
        return [];
    }

    return [
        ['NIS', props.profile.siswa.nis || '-'],
        ['Kelas', props.profile.siswa.kelas || '-'],
        ['Angkatan', props.profile.siswa.angkatan || '-'],
        ['Status Siswa', props.profile.siswa.status || '-'],
        ['Tinggal Kelas', props.profile.siswa.tinggal_kelas ? 'Ya' : 'Tidak'],
    ];
});

function submit() {
    form.put(props.updateUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function submitAvatar() {
    avatarForm.post(props.avatarUpdateUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => avatarForm.reset(),
    });
}

async function deleteAvatar() {
    const confirmed = await window.confirmDialog?.('Hapus foto profil saat ini?', {
        title: 'Hapus Foto',
        confirmText: 'Ya, hapus',
        danger: true,
    });

    if (!confirmed) {
        return;
    }

    router.delete(props.avatarDeleteUrl, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Pengaturan Akun" />

    <AppShell title="Pengaturan Akun">
        <PageHeader title="Pengaturan Akun" icon="bi-person-gear" />

        <div class="row">
            <div class="col-xl-7 mb-4">
                <Card title="Data Akun" icon="bi-info-circle">
                    <div class="account-summary">
                        <div class="account-identity">
                            <img
                                v-if="profile.foto_url"
                                :src="profile.foto_url"
                                :alt="`Foto ${profile.nama_lengkap}`"
                                class="account-avatar"
                            >
                            <div v-else class="account-avatar account-avatar-empty" aria-hidden="true">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div>
                                <div class="account-name">{{ profile.nama_lengkap || '-' }}</div>
                                <div class="account-username">@{{ profile.username || '-' }}</div>
                            </div>
                        </div>
                        <div class="account-badges">
                            <Badge :color="profile.role === 'admin' ? 'primary' : profile.role">
                                {{ profile.role_label || '-' }}
                            </Badge>
                            <Badge :color="profile.is_active ? 'success' : 'danger'">
                                {{ profile.is_active ? 'Aktif' : 'Nonaktif' }}
                            </Badge>
                            <Badge :color="profile.is_password_default ? 'warning' : 'secondary'">
                                {{ profile.is_password_default ? 'Password Default' : 'Password Pribadi' }}
                            </Badge>
                        </div>
                    </div>

                    <form class="avatar-form" @submit.prevent="submitAvatar">
                        <FileInput
                            v-model="avatarForm.foto"
                            name="foto"
                            label="Foto Profil"
                            accept=".jpg,.jpeg,.png,.webp"
                            accept-label=".jpg, .jpeg, .png, .webp"
                            max-size="2MB"
                            wrapper-class="mb-2"
                            :error="avatarForm.errors.foto"
                        />
                        <div class="d-flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                color="primary"
                                icon="bi-upload"
                                :disabled="avatarForm.processing || !avatarForm.foto"
                            >
                                {{ avatarForm.processing ? 'Mengunggah...' : 'Upload Foto' }}
                            </Button>
                            <Button
                                v-if="profile.foto_url"
                                type="button"
                                color="outline-danger"
                                icon="bi-trash"
                                @click="deleteAvatar"
                            >
                                Hapus Foto
                            </Button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 profile-table">
                            <tbody>
                                <tr v-for="[label, value] in accountRows" :key="label">
                                    <td>{{ label }}</td>
                                    <td>{{ value }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Card
                    v-if="siswaRows.length"
                    title="Data Siswa"
                    icon="bi-mortarboard-fill"
                    class="mt-4"
                >
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 profile-table">
                            <tbody>
                                <tr v-for="[label, value] in siswaRows" :key="label">
                                    <td>{{ label }}</td>
                                    <td>{{ value }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            <div class="col-xl-5 mb-4">
                <Card title="Ganti Password" icon="bi-key-fill">
                    <form @submit.prevent="submit">
                        <TextInput
                            v-model="form.current_password"
                            name="current_password"
                            label="Password Saat Ini"
                            type="password"
                            autocomplete="current-password"
                            required
                            :error="form.errors.current_password"
                        />

                        <TextInput
                            v-model="form.password"
                            name="password"
                            label="Password Baru"
                            type="password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            :error="form.errors.password"
                        />

                        <TextInput
                            v-model="form.password_confirmation"
                            name="password_confirmation"
                            label="Konfirmasi Password Baru"
                            type="password"
                            autocomplete="new-password"
                            required
                            :error="form.errors.password_confirmation"
                        />

                        <Button
                            type="submit"
                            color="success"
                            icon="bi-save"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Password' }}
                        </Button>
                    </form>
                </Card>
            </div>
        </div>
    </AppShell>
</template>

<style scoped>
.account-summary {
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.account-identity {
    align-items: center;
    display: flex;
    gap: 0.85rem;
}

.account-avatar {
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    height: 64px;
    object-fit: cover;
    width: 64px;
}

.account-avatar-empty {
    align-items: center;
    background: var(--gray-100);
    color: var(--gray-500);
    display: inline-flex;
    font-size: 1.8rem;
    justify-content: center;
}

.avatar-form {
    border-bottom: 1px solid var(--gray-100);
    margin-bottom: 1rem;
    padding-bottom: 1rem;
}

.account-name {
    color: var(--gray-900);
    font-size: 1.1rem;
    font-weight: 700;
}

.account-username {
    color: var(--gray-500);
    font-size: 0.9rem;
}

.account-badges {
    align-items: flex-start;
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    justify-content: flex-end;
}

.profile-table td:first-child {
    color: var(--gray-500);
    width: 160px;
}

@media (max-width: 575.98px) {
    .account-summary {
        flex-direction: column;
    }

    .account-badges {
        justify-content: flex-start;
    }

    .profile-table td:first-child {
        width: 130px;
    }
}
</style>

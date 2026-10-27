<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '../../Components/AppShell/PageHeader.vue';
import TextInput from '../../Components/Form/TextInput.vue';
import AppShell from '../../Layouts/AppShell.vue';
import { Badge, Button, Card } from '../../Components/UI';
interface GuruProfile { username: string; nama_lengkap: string; nip_nis?: string | null; role_label: string; is_active: boolean; }
interface Props { profile: GuruProfile; updateUrl: string; }
interface ProfileForm { username: string; nama_lengkap: string; nip_nis: string; current_password: string; password: string; password_confirmation: string; }

const props = defineProps<Props>();
const initials = computed(() => props.profile.nama_lengkap
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase());

const form = useForm<ProfileForm>({
    username: props.profile.username ?? '',
    nama_lengkap: props.profile.nama_lengkap ?? '',
    nip_nis: props.profile.nip_nis ?? '',
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit(section: 'profile' | 'password'): void {
    if (form.processing) {
        return;
    }

    form.transform((data) => section === 'profile'
        ? { username: data.username, nama_lengkap: data.nama_lengkap, nip_nis: data.nip_nis }
        : data);

    form.put(props.updateUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('current_password', 'password', 'password_confirmation');
        },
        onFinish: () => form.transform((data) => data),
    });
}
</script>

<template>
    <Head title="Profil" />

    <AppShell title="Profil">
        <PageHeader title="Pengaturan Akun" subtitle="Kelola data profil dan keamanan akun Anda." icon="bi-person-circle" />

        <div class="profile-settings-grid">
            <Card title="Data Akun" icon="bi-person-badge" class="profile-settings-card">
                <div class="profile-identity">
                    <span class="profile-avatar" aria-hidden="true">{{ initials }}</span>
                    <div class="profile-identity-copy">
                        <strong>{{ profile.nama_lengkap }}</strong>
                        <span>@{{ profile.username }}</span>
                        <div class="profile-statuses">
                            <Badge color="guru">{{ profile.role_label }}</Badge>
                            <Badge :color="profile.is_active ? 'success' : 'danger'">
                                {{ profile.is_active ? 'Aktif' : 'Nonaktif' }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <form class="profile-settings-form" @submit.prevent="submit('profile')">
                    <TextInput
                        v-model="form.username"
                        name="username"
                        label="Username"
                        required
                        :error="form.errors.username"
                    />

                    <TextInput
                        v-model="form.nama_lengkap"
                        name="nama_lengkap"
                        label="Nama Lengkap"
                        required
                        :error="form.errors.nama_lengkap"
                    />

                    <TextInput
                        v-model="form.nip_nis"
                        name="nip_nis"
                        label="NIP / NIK"
                        :error="form.errors.nip_nis"
                    />

                    <Button type="submit" color="success" icon="bi-save" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </Button>
                </form>
            </Card>

            <Card title="Ganti Password" icon="bi-shield-lock" class="profile-settings-card">
                <div class="profile-security-note">
                    <span class="profile-security-icon"><i class="bi bi-lock" aria-hidden="true"></i></span>
                    <div>
                        <strong>Perbarui kata sandi</strong>
                        <p>Isi bagian ini hanya jika ingin mengganti password.</p>
                    </div>
                </div>

                <form class="profile-settings-form" @submit.prevent="submit('password')">
                    <TextInput
                        v-model="form.current_password"
                        name="current_password"
                        label="Password Saat Ini"
                        type="password"
                        placeholder="Wajib bila mengganti password"
                        autocomplete="current-password"
                        :error="form.errors.current_password"
                    />

                    <TextInput
                        v-model="form.password"
                        name="password"
                        label="Password Baru"
                        type="password"
                        placeholder="Minimal 8 karakter"
                        minlength="8"
                        autocomplete="new-password"
                        :error="form.errors.password"
                    />

                    <TextInput
                        v-model="form.password_confirmation"
                        name="password_confirmation"
                        label="Konfirmasi Password Baru"
                        type="password"
                        placeholder="Ketik ulang password baru"
                        minlength="8"
                        autocomplete="new-password"
                        :error="form.errors.password_confirmation"
                    />

                    <Button type="submit" color="success" icon="bi-shield-check" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Password' }}
                    </Button>
                </form>
            </Card>
        </div>
    </AppShell>
</template>

<style scoped>
.profile-settings-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
    gap: 1rem;
}

.profile-settings-form {
    display: grid;
    gap: 0.15rem;
}

.profile-identity {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-soft);
}

.profile-avatar {
    display: grid;
    width: 56px;
    height: 56px;
    flex: 0 0 56px;
    place-items: center;
    border: 1px solid var(--border-soft);
    border-radius: 14px;
    background: var(--brand-soft-bg);
    color: var(--text-brand);
    font-size: 1.05rem;
    font-weight: 750;
}

.profile-identity-copy {
    display: grid;
    min-width: 0;
    gap: 0.15rem;
}

.profile-identity-copy > strong {
    color: var(--text-strong);
    font-size: 0.98rem;
}

.profile-identity-copy > span {
    overflow: hidden;
    color: var(--text-muted);
    font-size: 0.8rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.profile-statuses {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.25rem;
}

.profile-security-note {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin-bottom: 1rem;
    padding: 0.75rem;
    border-radius: var(--radius-md);
    background: var(--surface-subtle);
}

.profile-security-icon {
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    place-items: center;
    border-radius: 10px;
    background: var(--brand-soft-bg);
    color: var(--text-brand);
}

.profile-security-note strong {
    color: var(--text-strong);
    font-size: 0.85rem;
}

.profile-security-note p {
    margin: 0.1rem 0 0;
    color: var(--text-muted);
    font-size: 0.76rem;
}

@media (max-width: 767.98px) {
    .profile-settings-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>

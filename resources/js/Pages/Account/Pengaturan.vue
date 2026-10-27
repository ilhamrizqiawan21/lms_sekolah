<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/AppShell/PageHeader.vue';
import FileInput from '../../Components/Form/FileInput.vue';
import TextInput from '../../Components/Form/TextInput.vue';
import InputError from '../../Components/Form/InputError.vue';
import AppShell from '../../Layouts/AppShell.vue';
import { Badge, Button } from '../../Components/UI';
interface SiswaInfo { nis?: string; kelas?: string; angkatan?: string; status?: string; tinggal_kelas?: boolean; nomor_whatsapp?: string | null; whatsapp_opt_in?: boolean; phone_required?: boolean; }
interface AccountProfile { username?: string; nama_lengkap?: string; email?: string; nip_nis?: string; jenis_kelamin?: string; created_at?: string; foto_url?: string | null; role?: string; role_label?: string; is_active?: boolean; is_password_default?: boolean; siswa?: SiswaInfo | null; }
interface Props { profile: AccountProfile; updateUrl: string; avatarUpdateUrl: string; avatarDeleteUrl: string; phoneUpdateUrl?: string | null; }
interface PasswordForm { current_password: string; password: string; password_confirmation: string; }
interface AvatarForm { foto: File | File[] | null; }

const props = defineProps<Props>();
const initials = computed(() => (props.profile.nama_lengkap || props.profile.username || 'A')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase());
const passwordVisible = reactive({ current_password: false, password: false, password_confirmation: false });

const phoneForm = useForm({
    nomor_whatsapp: props.profile.siswa?.nomor_whatsapp ?? '',
    whatsapp_opt_in: props.profile.siswa?.whatsapp_opt_in ?? false,
});

function submitPhone(): void {
    if (!props.phoneUpdateUrl || phoneForm.processing) return;
    phoneForm.put(props.phoneUpdateUrl, {
        preserveScroll: true,
        onSuccess: () => {
            phoneForm.nomor_whatsapp = props.profile.siswa?.nomor_whatsapp ?? '';
            phoneForm.whatsapp_opt_in = props.profile.siswa?.whatsapp_opt_in ?? false;
        },
    });
}

const form = useForm<PasswordForm>({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const avatarForm = useForm<AvatarForm>({
    foto: null,
});
const avatarPreviewUrl = ref<string | null>(null);
watch(() => avatarForm.foto, (file) => {
    if (avatarPreviewUrl.value) URL.revokeObjectURL(avatarPreviewUrl.value);
    avatarPreviewUrl.value = file && !Array.isArray(file) ? URL.createObjectURL(file) : null;
});
onBeforeUnmount(() => {
    if (avatarPreviewUrl.value) URL.revokeObjectURL(avatarPreviewUrl.value);
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

function submit(): void {
    if (form.processing) {
        return;
    }

    form.put(props.updateUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function submitAvatar(): void {
    if (avatarForm.processing) {
        return;
    }

    avatarForm.post(props.avatarUpdateUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => avatarForm.reset(),
    });
}

async function deleteAvatar(): Promise<void> {
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
        <PageHeader eyebrow="Akun" title="Pengaturan Akun" subtitle="Kelola foto profil dan keamanan akun Anda." />

        <section v-if="profile.role === 'siswa'" class="account-card account-phone-card" aria-labelledby="phoneTitle">
            <header class="account-card-head account-phone-head">
                <div>
                    <h2 id="phoneTitle">Nomor Telepon Siswa</h2>
                    <p>Nomor untuk informasi tugas dan pengingat sekolah.</p>
                </div>
                <Badge v-if="profile.siswa?.phone_required" color="warning">Wajib dilengkapi</Badge>
            </header>
            <div class="account-card-body">
                <div v-if="!profile.siswa" class="alert alert-warning mb-0" role="alert">Data siswa belum tersedia. Hubungi administrator sekolah.</div>
                <form v-else @submit.prevent="submitPhone">
                    <TextInput
                        v-model="phoneForm.nomor_whatsapp"
                        name="nomor_whatsapp"
                        label="Nomor Telepon / WhatsApp"
                        type="tel"
                        autocomplete="tel"
                        inputmode="tel"
                        maxlength="32"
                        placeholder="08xxxxxxxxxx"
                        required
                        :error="phoneForm.errors.nomor_whatsapp"
                    />
                    <div class="form-check mb-3">
                        <input id="whatsapp_opt_in" v-model="phoneForm.whatsapp_opt_in" type="checkbox" class="form-check-input" required :aria-invalid="phoneForm.errors.whatsapp_opt_in ? 'true' : undefined">
                        <label for="whatsapp_opt_in" class="form-check-label">Saya setuju menerima informasi tugas dan pengingat sekolah melalui WhatsApp. <span class="text-danger">*</span></label>
                        <InputError :message="phoneForm.errors.whatsapp_opt_in" />
                    </div>
                    <Button type="submit" color="primary" icon="bi-check-lg" :disabled="phoneForm.processing || !phoneUpdateUrl">{{ phoneForm.processing ? 'Menyimpan...' : 'Simpan Nomor Telepon' }}</Button>
                </form>
            </div>
        </section>

        <div class="account-layout">
            <div class="account-column">
                <section class="account-card" aria-labelledby="account-data-title">
                    <header class="account-card-head">
                        <h2 id="account-data-title">Data Akun</h2>
                        <p>Informasi profil Anda di LMS Sekolah.</p>
                    </header>
                    <div class="account-card-body">
                    <div class="account-summary">
                        <div class="account-identity">
                            <img
                                v-if="avatarPreviewUrl || profile.foto_url"
                                :src="avatarPreviewUrl || profile.foto_url || ''"
                                :alt="avatarPreviewUrl ? 'Pratinjau foto profil' : `Foto ${profile.nama_lengkap}`"
                                class="account-avatar"
                            >
                            <div v-else class="account-avatar account-avatar-empty" aria-hidden="true">
                                {{ initials }}
                            </div>
                            <div class="account-identity-copy">
                                <div class="account-name">{{ profile.nama_lengkap || '-' }}</div>
                                <div class="account-username">@{{ profile.username || '-' }}</div>
                                <div class="account-badges">
                                    <span class="account-badge account-badge-role">{{ profile.role_label || '-' }}</span>
                                    <span class="account-badge" :class="profile.is_active ? 'account-badge-active' : 'account-badge-inactive'">
                                        <span class="account-status-dot" aria-hidden="true"></span>{{ profile.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                    <span class="account-badge" :class="profile.is_password_default ? 'account-badge-warning' : 'account-badge-neutral'">
                                        {{ profile.is_password_default ? 'Password default' : 'Password pribadi' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="account-divider"></div>
                    <form class="avatar-form" @submit.prevent="submitAvatar">
                        <h3>Foto profil</h3>
                        <p class="account-helper">Gunakan foto yang jelas agar mudah dikenali di kelas dan chat.</p>
                        <div class="account-upload-control">
                            <i class="bi bi-upload" aria-hidden="true"></i>
                            <FileInput
                                v-model="avatarForm.foto"
                                name="foto"
                                label="Pilih foto baru"
                                accept=".jpg,.jpeg,.png,.webp"
                                accept-label="JPG, PNG, atau WEBP"
                                max-size="2MB"
                                wrapper-class="account-file-field"
                                :error="avatarForm.errors.foto"
                            />
                        </div>
                        <div class="account-actions">
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

                    <div class="account-divider"></div>
                        <table class="account-table" aria-label="Informasi akun">
                            <tbody>
                                <tr v-for="[label, value] in accountRows" :key="label">
                                    <td>{{ label }}</td>
                                    <td>{{ value }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="account-readonly-note">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            <span>{{ profile.role === 'admin' ? 'Informasi identitas akun mengikuti data pengguna di sistem.' : 'Untuk mengubah nama, email, atau NIP/NIS, hubungi administrator sekolah.' }}</span>
                        </div>
                    </div>
                </section>

                <section v-if="siswaRows.length" class="account-card" aria-labelledby="student-data-title">
                    <header class="account-card-head">
                        <h2 id="student-data-title">Data Siswa</h2>
                        <p>Informasi kelas dan status Anda di sekolah.</p>
                    </header>
                    <div class="account-card-body">
                        <table class="account-table">
                            <tbody>
                                <tr v-for="[label, value] in siswaRows" :key="label">
                                    <td>{{ label }}</td>
                                    <td>{{ value }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="account-column">
                <section class="account-card" aria-labelledby="password-title">
                    <header class="account-card-head">
                        <h2 id="password-title">Ganti Password</h2>
                        <p>Perbarui kata sandi untuk menjaga keamanan akun.</p>
                    </header>
                    <form class="account-card-body" @submit.prevent="submit">
                        <div class="password-intro">
                            <span class="password-intro-icon"><i class="bi bi-lock" aria-hidden="true"></i></span>
                            <div><strong>Buat password yang kuat</strong><p>Gunakan kombinasi yang sulit ditebak.</p></div>
                        </div>
                        <div class="password-control">
                        <TextInput
                            v-model="form.current_password"
                            name="current_password"
                            label="Password Saat Ini"
                            :type="passwordVisible.current_password ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            :error="form.errors.current_password"
                        />
                            <button type="button" :aria-label="passwordVisible.current_password ? 'Sembunyikan password saat ini' : 'Tampilkan password saat ini'" @click="passwordVisible.current_password = !passwordVisible.current_password">
                                <i class="bi" :class="passwordVisible.current_password ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div class="password-control">
                        <TextInput
                            v-model="form.password"
                            name="password"
                            label="Password Baru"
                            :type="passwordVisible.password ? 'text' : 'password'"
                            autocomplete="new-password"
                            minlength="10"
                            required
                            :error="form.errors.password"
                        />
                            <button type="button" :aria-label="passwordVisible.password ? 'Sembunyikan password baru' : 'Tampilkan password baru'" @click="passwordVisible.password = !passwordVisible.password">
                                <i class="bi" :class="passwordVisible.password ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div class="password-control">
                        <TextInput
                            v-model="form.password_confirmation"
                            name="password_confirmation"
                            label="Konfirmasi Password Baru"
                            :type="passwordVisible.password_confirmation ? 'text' : 'password'"
                            autocomplete="new-password"
                            required
                            :error="form.errors.password_confirmation"
                        />
                            <button type="button" :aria-label="passwordVisible.password_confirmation ? 'Sembunyikan konfirmasi password' : 'Tampilkan konfirmasi password'" @click="passwordVisible.password_confirmation = !passwordVisible.password_confirmation">
                                <i class="bi" :class="passwordVisible.password_confirmation ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
                            </button>
                        </div>

                        <p class="password-hint"><strong>Password baru harus memenuhi:</strong> Minimal 10 karakter, berisi huruf besar dan kecil, angka, serta simbol.</p>
                        <div class="password-actions">
                            <Button type="submit" color="primary" icon="bi-check-lg" class="w-100" :disabled="form.processing">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Password' }}
                            </Button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </AppShell>
</template>

<style scoped>
.account-layout { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(300px, .85fr); align-items: start; gap: 1.1rem; }
.account-column { display: grid; min-width: 0; gap: 1.1rem; }
.account-card { min-width: 0; overflow: hidden; border: 1px solid var(--border-soft); border-radius: var(--panel-radius); background: var(--surface-card); box-shadow: var(--refresh-shadow-card); }
.account-phone-card { margin-bottom: 1.1rem; }
.account-card-head { padding: var(--space-4); border-bottom: 1px solid var(--border-soft); }
.account-phone-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .6rem; }
.account-card-head h2 { margin: 0; color: var(--text-strong); font-size: var(--card-heading-size); font-weight: var(--fw-bold); line-height: var(--lh-base); }
.account-card-head p { margin: var(--space-1) 0 0; color: var(--text-muted); font-size: var(--card-description-size); line-height: var(--lh-base); }
.account-card-body { padding: 1.1rem; }
.account-summary { min-width: 0; }
.account-identity { display: flex; align-items: center; gap: .9rem; min-width: 0; }
.account-avatar { display: grid; width: 72px; height: 72px; flex: 0 0 72px; place-items: center; overflow: hidden; border: 1px solid color-mix(in srgb, var(--app-primary) 18%, var(--border-soft)); border-radius: 14px; background: var(--brand-soft-bg); color: var(--text-brand); font-size: 1.35rem; font-weight: 800; object-fit: cover; }
.account-identity-copy { min-width: 0; }
.account-name { overflow-wrap: anywhere; color: var(--text-strong); font-size: 1.13rem; font-weight: 780; letter-spacing: -.03em; }
.account-username { margin-top: .1rem; color: var(--text-muted); font-size: .75rem; }
.account-badges { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .7rem; }
.account-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .27rem .55rem; border-radius: 999px; font-size: .65rem; font-weight: 700; white-space: nowrap; }
.account-badge-role { background: var(--brand-soft-bg); color: var(--text-brand); }
.account-badge-active { background: var(--status-success-bg); color: var(--status-success-text); }
.account-badge-inactive { background: var(--status-danger-bg); color: var(--status-danger-text); }
.account-badge-warning { background: var(--status-warning-bg); color: var(--status-warning-text); }
.account-badge-neutral { background: var(--surface-muted); color: var(--text-body); }
.account-status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.account-divider { height: 1px; margin: 1.1rem 0; background: var(--border-soft); }
.avatar-form h3 { margin: 0 0 .25rem; color: var(--text-strong); font-size: .82rem; font-weight: 750; }
.account-helper { margin: 0; color: var(--text-muted); font-size: .72rem; }
.account-upload-control { display: flex; align-items: flex-start; gap: .75rem; margin-top: .8rem; padding: .8rem; border: 1px dashed var(--border-soft); border-radius: 10px; background: var(--surface-subtle); }
.account-upload-control > .bi { margin-top: .2rem; color: var(--text-brand); }
.account-file-field { min-width: 0; flex: 1; }
.account-upload-control :deep(.form-label) { margin-bottom: .25rem; color: var(--text-strong); font-size: .76rem; font-weight: 750; }
.account-upload-control :deep(.form-file-meta) { margin-bottom: .4rem; color: var(--text-muted); font-size: .66rem; }
.account-upload-control :deep(.form-control) { max-width: 100%; font-size: .72rem; }
.account-upload-control :deep(.form-text) { font-size: .66rem; }
.account-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
.account-table { width: 100%; border-collapse: collapse; }
.account-table tr + tr { border-top: 1px solid var(--border-soft); }
.account-table td { padding: .62rem .2rem; font-size: .74rem; vertical-align: top; }
.account-table td:first-child { width: 36%; color: var(--text-muted); }
.account-table td:last-child { color: var(--text-body); font-weight: 650; overflow-wrap: anywhere; }
.account-readonly-note { display: flex; align-items: flex-start; gap: .5rem; margin-top: .9rem; padding: .65rem .7rem; border: 1px solid color-mix(in srgb, var(--app-primary) 13%, var(--border-soft)); border-radius: 9px; background: var(--brand-soft-bg); color: var(--text-body); font-size: .68rem; line-height: 1.5; }
.account-readonly-note .bi { color: var(--text-brand); }
.password-intro { display: flex; align-items: center; gap: .7rem; margin-bottom: 1rem; }
.password-intro-icon { display: grid; width: 38px; height: 38px; flex: 0 0 38px; place-items: center; border-radius: 10px; background: var(--brand-soft-bg); color: var(--text-brand); }
.password-intro strong { color: var(--text-strong); font-size: .8rem; }
.password-intro p { margin: .15rem 0 0; color: var(--text-muted); font-size: .7rem; }
.password-control { position: relative; }
.password-control > button { position: absolute; top: 1.9rem; right: .25rem; display: grid; width: 36px; height: 36px; place-items: center; border: 0; background: transparent; color: var(--text-muted); }
.password-control :deep(.form-control) { min-height: 40px; padding-right: 2.6rem; font-size: .78rem; }
.password-control :deep(.form-label) { font-size: .73rem; font-weight: 700; }
.password-hint { margin: 0; padding: .65rem .7rem; border-radius: 8px; background: var(--surface-subtle); color: var(--text-muted); font-size: .69rem; line-height: 1.5; }
.password-hint strong { display: block; color: var(--text-body); }
.password-actions { display: flex; width: 100%; margin-top: 1rem; }
.password-actions :deep(.btn) { width: 100%; }
@media (max-width: 720px) { .account-layout { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 380px) {
    .account-identity { gap: .6rem; }
    .account-avatar { width: 60px; height: 60px; flex-basis: 60px; }
    .account-badge { padding: .23rem .4rem; font-size: .6rem; }
    .account-table td { font-size: .69rem; }
}
</style>

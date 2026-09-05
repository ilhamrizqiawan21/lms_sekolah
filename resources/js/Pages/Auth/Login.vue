<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    branding: { type: Object, required: true },
    loginUrl: { type: String, required: true },
    publicAnnouncements: { type: Array, default: () => [] },
    year: { type: [String, Number], required: true },
});

const page = usePage();
const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const flash = computed(() => page.props.flash ?? {});
const title = computed(() => `Login - ${props.branding.school_short_name} ${props.branding.school_name}`);
const forgotPasswordUrl = 'https://wa.me/62895802329062?text=Assalamu%27alaikum%2C%20Bapa%20saya%20lupa%20password%20mohon%20bantu%20saya%20%20%3A%0ANama%20%3A%20........%0AKelas%20%3A%20.........%0ATerimakasih';

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
}

function formatFileSize(bytes) {
    if (!bytes) return '';
    const kb = bytes / 1024;
    return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.ceil(kb)} KB`;
}

function submit() {
    form.post(props.loginUrl, {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head :title="title" />

    <main class="login-page">
        <div class="login-wrapper">
            <section class="login-header" aria-labelledby="login-title">
                <div class="logo-circle">
                    <img
                        :src="branding.logo_url"
                        :alt="`Logo ${branding.school_name}`"
                        width="36"
                        height="36"
                        decoding="async"
                    >
                </div>
                <h1 id="login-title">{{ branding.school_name }}</h1>
                <div class="product-name">{{ branding.school_short_name }}</div>
                <div class="school-motto">{{ branding.school_motto }}</div>
                <div class="school-address">{{ branding.school_address }}</div>
            </section>

            <section class="login-card" aria-label="Form login">
                <div v-if="flash.error" class="alert alert-danger d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                    <span>{{ flash.error }}</span>
                </div>
                <div v-if="flash.success" class="alert alert-success d-flex align-items-center">
                    <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>
                    <span>{{ flash.success }}</span>
                </div>

                <form @submit.prevent="submit">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person-fill" aria-hidden="true"></i>
                            </span>
                            <input
                                id="username"
                                v-model="form.username"
                                type="text"
                                name="username"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.username }"
                                placeholder="Masukkan username"
                                required
                                autofocus
                                autocomplete="username"
                            >
                        </div>
                        <div v-if="form.errors.username" class="login-error">{{ form.errors.username }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                            </span>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                name="password"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.password }"
                                placeholder="Masukkan password"
                                required
                                autocomplete="current-password"
                            >
                        </div>
                        <div v-if="form.errors.password" class="login-error">{{ form.errors.password }}</div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input
                                id="remember"
                                v-model="form.remember"
                                type="checkbox"
                                name="remember"
                                class="form-check-input"
                            >
                            <label class="form-check-label" for="remember">Ingat saya</label>
                        </div>
                    </div>

                    <button type="submit" class="btn-login" :disabled="form.processing">
                        <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>
                        {{ form.processing ? 'Memproses...' : 'Masuk' }}
                    </button>
                    <div class="login-help" role="note">
                        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                        <span>
                            <a
                                class="login-help-title"
                                :href="forgotPasswordUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Lupa password?
                            </a>
                            Hubungi admin lewat WhatsApp untuk bantuan reset akun.
                        </span>
                    </div>
                </form>
            </section>

            <section v-if="publicAnnouncements.length" class="public-board" aria-label="Papan informasi">
                <div class="public-board-title">
                    <i class="bi bi-megaphone-fill" aria-hidden="true"></i>
                    <span>Papan Informasi</span>
                </div>
                <article
                    v-for="announcement in publicAnnouncements"
                    :key="announcement.id"
                    class="public-announcement"
                >
                    <div class="public-announcement-meta">
                        <span>{{ announcement.creator_name || 'Sekolah' }}</span>
                        <span v-if="formatDate(announcement.created_at)">{{ formatDate(announcement.created_at) }}</span>
                    </div>
                    <h2>{{ announcement.judul }}</h2>
                    <p>{{ announcement.isi }}</p>
                    <a
                        v-if="announcement.attachment"
                        class="public-attachment"
                        :href="announcement.attachment.url"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <i class="bi bi-paperclip" aria-hidden="true"></i>
                        <span>{{ announcement.attachment.name }}</span>
                        <small v-if="formatFileSize(announcement.attachment.size)">{{ formatFileSize(announcement.attachment.size) }}</small>
                    </a>
                </article>
            </section>

            <footer class="login-footer">
                &copy; {{ year }} {{ branding.school_name }}<br>
                <span>{{ branding.school_short_name }}</span>
            </footer>
        </div>
    </main>
</template>

<style scoped>
.login-page {
    position: relative;
    --login-primary: var(--app-primary, #198754);
    --login-primary-dark: var(--app-primary-dark, #166534);
    --login-primary-deep: var(--primary-800, #0f3f2a);
    --login-accent: var(--gold-500, #f59e0b);
    --login-surface: var(--surface-card, #ffffff);
    --login-surface-muted: var(--surface-muted, #f8fafc);
    --login-border: var(--gray-200, #e5e7eb);
    --login-text: var(--text-strong, #1f2937);
    --login-text-muted: var(--text-muted, #64748b);
    --login-danger-bg: var(--status-danger-bg, #fee2e2);
    --login-danger-text: var(--status-danger-text, #991b1b);
    --login-success-bg: var(--status-success-bg, #dcfce7);
    --login-success-text: var(--status-success-text, #166534);
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 24px 20px;
    overflow-x: hidden;
    background: linear-gradient(135deg, var(--login-primary-dark) 0%, var(--login-primary-deep) 58%, #0b2419 100%);
    font-family: var(--font-sans);
}

.login-page::before {
    content: '';
    position: fixed;
    inset: -50%;
    background:
        radial-gradient(circle at 30% 20%, color-mix(in srgb, var(--login-accent) 11%, transparent) 0%, transparent 46%),
        radial-gradient(circle at 70% 80%, color-mix(in srgb, var(--login-primary) 14%, transparent) 0%, transparent 50%);
    pointer-events: none;
}

.login-wrapper {
    position: relative;
    width: 100%;
    max-width: 520px;
    animation: fadeInUp 0.5s ease;
}

.login-header {
    text-align: center;
    color: white;
    margin-bottom: 20px;
}

.logo-circle {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    border: 3px solid color-mix(in srgb, var(--login-accent) 48%, transparent);
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    box-shadow: 0 0 30px color-mix(in srgb, var(--login-accent) 16%, transparent);
}

.logo-circle img {
    width: 36px;
    height: 36px;
    object-fit: contain;
    border-radius: 50%;
}

.login-header h1 {
    font-weight: 800;
    font-size: 1.4rem;
    margin-bottom: 4px;
}

.product-name {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 10px;
    margin-bottom: 8px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.9);
    font-weight: 700;
    font-size: 0.74rem;
}

.school-motto {
    max-width: 360px;
    margin: 0 auto 6px;
    color: rgba(255, 255, 255, 0.84);
    font-size: 0.84rem;
    line-height: 1.45;
}

.school-address {
    max-width: 360px;
    margin: 0 auto;
    color: rgba(255, 255, 255, 0.70);
    font-size: 0.74rem;
    line-height: 1.4;
}

.login-card {
    border-radius: 18px;
    background: var(--login-surface);
    padding: 35px 30px;
    box-shadow: 0 20px 60px rgba(4, 31, 20, 0.28);
}

.form-label {
    font-weight: 600;
    font-size: 0.84rem;
    color: var(--login-text);
    margin-bottom: 5px;
}

.input-group-text {
    border: 1px solid var(--login-border);
    border-right: 0;
    border-radius: 10px 0 0 10px;
    background: var(--login-surface-muted);
    color: var(--login-text-muted);
}

.form-control {
    border: 1px solid var(--login-border);
    border-left: 0;
    border-radius: 0 10px 10px 0;
    padding: 12px 16px;
    color: var(--login-text);
    font-size: 0.92rem;
}

.input-group:focus-within .input-group-text {
    border-color: var(--login-primary);
    color: var(--login-primary);
}

.form-control:focus {
    border-color: var(--login-primary);
    box-shadow: none;
}

.form-check-label {
    color: var(--login-text-muted);
    font-size: 0.85rem;
}

.form-check-input:checked {
    border-color: var(--login-primary);
    background-color: var(--login-primary);
}

.btn-login {
    width: 100%;
    border: 0;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--login-primary), var(--login-primary-dark));
    color: white;
    padding: 14px;
    font: inherit;
    font-weight: 700;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.btn-login:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(22, 101, 52, 0.32);
    background: linear-gradient(135deg, var(--login-primary-dark), var(--login-primary-deep));
}

.btn-login:disabled {
    cursor: wait;
    opacity: 0.75;
}

.login-help {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 14px;
    padding: 11px 12px;
    border: 1px solid var(--login-border);
    border-radius: 10px;
    background: var(--login-surface-muted);
    color: var(--login-text-muted);
    font-size: 0.82rem;
    line-height: 1.45;
}

.login-help i {
    flex-shrink: 0;
    margin-top: 2px;
    color: var(--login-primary);
}

.login-help-title {
    color: var(--login-primary-dark);
    font-weight: 700;
    text-decoration: none;
}

.login-help-title:hover {
    color: var(--login-primary);
    text-decoration: underline;
}

.alert {
    border: 0;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 20px;
    font-size: 0.85rem;
}

.alert-danger {
    background: var(--login-danger-bg);
    color: var(--login-danger-text);
}

.alert-success {
    background: var(--login-success-bg);
    color: var(--login-success-text);
}

.login-error {
    color: #dc2626;
    font-size: 0.75rem;
    margin-top: 4px;
}

.public-board {
    margin-top: 18px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.94);
    padding: 16px;
    box-shadow: 0 16px 44px rgba(4, 31, 20, 0.22);
}

.public-board-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: var(--login-primary-dark);
    font-size: 0.9rem;
    font-weight: 800;
}

.public-announcement {
    padding: 14px 0;
    border-top: 1px solid var(--login-border);
}

.public-announcement:first-of-type {
    border-top: 0;
    padding-top: 0;
}

.public-announcement:last-of-type {
    padding-bottom: 0;
}

.public-announcement-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 5px;
    color: var(--login-text-muted);
    font-size: 0.74rem;
}

.public-announcement h2 {
    margin: 0 0 7px;
    color: var(--login-text);
    font-size: 0.98rem;
    font-weight: 800;
    line-height: 1.3;
}

.public-announcement p {
    margin: 0;
    color: #475569;
    font-size: 0.85rem;
    line-height: 1.55;
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.public-attachment {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    max-width: 100%;
    margin-top: 10px;
    padding: 7px 10px;
    border: 1px solid color-mix(in srgb, var(--login-primary) 34%, white);
    border-radius: 10px;
    background: color-mix(in srgb, var(--login-primary) 7%, white);
    color: var(--login-primary-dark);
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
}

.public-attachment span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.public-attachment small {
    flex-shrink: 0;
    color: var(--login-text-muted);
    font-size: 0.72rem;
    font-weight: 600;
}

.login-footer {
    text-align: center;
    margin-top: 20px;
    color: rgba(255, 255, 255, 0.68);
    font-size: 0.78rem;
}

.login-footer span {
    color: color-mix(in srgb, var(--login-accent) 90%, white);
    font-weight: 700;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 480px) {
    .login-card {
        padding: 25px 20px;
    }
}
</style>

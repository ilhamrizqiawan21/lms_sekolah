<script setup lang="ts">
import type { PropType } from 'vue';
import type { AppPageProps } from '../../types/inertia';
import { computed, onBeforeMount, onBeforeUnmount, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { forceLightMode, releaseForcedMode } from '../../theme';

// Login selalu terang. Preferensi pengguna dipulihkan saat meninggalkan halaman ini.
onBeforeMount(forceLightMode);
onBeforeUnmount(releaseForcedMode);

const props = defineProps({
    branding: {
        type: Object as PropType<{
            school_short_name: string;
            school_name: string;
            school_motto: string;
            school_address: string;
            logo_url: string;
            support_contact: string | null;
        }>,
        required: true,
    },
    loginUrl: { type: String, required: true },
    publicAnnouncements: {
        type: Array as PropType<
            {
                id: number;
                judul: string;
                isi: string;
                creator_name: string | null;
                created_at: string | null;
                attachment: { url: string; name: string; size: number | null } | null;
            }[]
        >,
        default: () => [],
    },
    year: { type: [String, Number], required: true },
});

const page = usePage<AppPageProps>();
const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function togglePassword() {
    showPassword.value = !showPassword.value;
}

const flash = computed(() => page.props.flash ?? {});
const title = computed(() => 'Login');
const schoolName = computed(() => {
    const value = props.branding.school_name.trim();
    return value && value.toLocaleLowerCase('id-ID') !== 'nama sekolah' ? value : 'Portal Sekolah';
});
const schoolAddress = computed(() => {
    const value = props.branding.school_address.trim();
    return value && value.toLocaleLowerCase('id-ID') !== 'alamat sekolah belum diatur' ? value : '';
});

const forgotPasswordUrl = computed(() => {
    const rawContact = String(props.branding.support_contact ?? '').replace(/[^\d+]/g, '');

    if (!rawContact) {
        return null;
    }

    const digits = rawContact.replace(/\D/g, '');
    const whatsappNumber = digits.startsWith('0') ? `62${digits.slice(1)}` : digits;
    const message = encodeURIComponent('Assalamu\'alaikum, saya lupa password. Mohon bantu saya. Nama: ........ Kelas: .........');

    return `https://wa.me/${whatsappNumber}?text=${message}`;
});

const todayDate = computed(() => {
    return new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
}

function formatFileSize(bytes: number | null) {
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
        <!-- Panel Kiri: Identitas Sekolah & Motif Buku Tulis Bergaris -->
        <aside class="login-brand-panel" aria-label="Identitas Sekolah">
            <header class="brand-header">
                <div class="brand-top-row">
                    <div class="brand-logo-box">
                        <img
                            :src="branding.logo_url"
                            :alt="`Logo ${schoolName}`"
                            width="40"
                            height="40"
                            decoding="async"
                        >
                    </div>
                    <div class="brand-heading-titles">
                        <span class="brand-school-name">{{ schoolName }}</span>
                        <span v-if="schoolAddress" class="brand-school-sub">
                            {{ schoolAddress }}
                        </span>
                    </div>
                </div>

                <div class="brand-hero">
                    <h1 class="brand-headline">Ruang belajar yang tenang dan tertata.</h1>
                    <p v-if="branding.school_motto" class="brand-motto">
                        {{ branding.school_motto }}
                    </p>
                </div>
            </header>

            <div class="brand-lower">
            <!-- Papan Informasi / Pengumuman Resmi Sekolah -->
            <section
                class="brand-board"
                :class="{ 'has-announcements': publicAnnouncements.length > 0 }"
                aria-label="Papan Informasi Sekolah"
            >
                <div class="board-header">
                    <div class="board-header-left">
                        <i
                            :class="publicAnnouncements.length ? 'bi bi-megaphone-fill text-amber' : 'bi bi-info-circle-fill'"
                            class="board-icon"
                            aria-hidden="true"
                        ></i>
                        <span class="board-eyebrow">
                            {{ publicAnnouncements.length ? 'PENGUMUMAN SEKOLAH' : 'PAPAN INFORMASI' }}
                        </span>
                    </div>
                    <span class="board-count-pill">
                        {{ publicAnnouncements.length ? `${publicAnnouncements.length} pengumuman` : 'Info Portal' }}
                    </span>
                </div>

                <!-- Jika ada pengumuman publik yang disematkan -->
                <div v-if="publicAnnouncements.length" class="board-list">
                    <article
                        v-for="announcement in publicAnnouncements"
                        :key="announcement.id"
                        class="board-item"
                    >
                        <div class="board-item-header">
                            <h2 class="board-item-title">{{ announcement.judul }}</h2>
                            <span v-if="formatDate(announcement.created_at)" class="board-item-time">
                                <i class="bi bi-clock me-1" aria-hidden="true"></i>
                                {{ formatDate(announcement.created_at) }}
                            </span>
                        </div>
                        <div v-if="announcement.creator_name" class="board-item-author">
                            <i class="bi bi-person-fill me-1" aria-hidden="true"></i>
                            {{ announcement.creator_name }}
                        </div>
                        <p class="board-item-desc">{{ announcement.isi }}</p>
                        <a
                            v-if="announcement.attachment"
                            class="board-item-attachment"
                            :href="announcement.attachment.url"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i>
                            <span class="attachment-name">{{ announcement.attachment.name }}</span>
                            <small v-if="formatFileSize(announcement.attachment.size)" class="attachment-size">
                                ({{ formatFileSize(announcement.attachment.size) }})
                            </small>
                        </a>
                    </article>
                </div>

                <!-- Jika belum ada pengumuman publik (mengisi panel dengan panduan yang bermanfaat) -->
                <div v-else class="board-empty-state">
                    <p class="board-empty-intro">
                        Selamat datang di portal pembelajaran digital {{ schoolName }}. Papan ini memuat informasi dan pengumuman resmi yang ditujukan bagi seluruh civitas sekolah.
                    </p>
                    <div class="board-quick-guide">
                        <div class="guide-item">
                            <i class="bi bi-mortarboard-fill guide-icon" aria-hidden="true"></i>
                            <div class="guide-content">
                                <strong class="guide-title">Akses Pembelajaran Terpadu</strong>
                                <span class="guide-text">Masuk menggunakan kredensial resmi sekolah untuk mengakses materi, tugas, dan ujian daring.</span>
                            </div>
                        </div>
                        <div class="guide-item">
                            <i class="bi bi-shield-check guide-icon" aria-hidden="true"></i>
                            <div class="guide-content">
                                <strong class="guide-title">Jaga Keamanan Akun</strong>
                                <span class="guide-text">Jangan bagikan kata sandi kepada orang lain dan selalu keluar setelah menggunakan perangkat bersama.</span>
                            </div>
                        </div>
                        <div class="guide-item">
                            <i class="bi bi-question-circle-fill guide-icon" aria-hidden="true"></i>
                            <div class="guide-content">
                                <strong class="guide-title">Bantuan &amp; Kendala Akses</strong>
                                <span class="guide-text">Jika lupa kata sandi atau akun bermasalah, silakan hubungi wali kelas atau admin sekolah.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Footer Panel Kiri: Tanggal & Hak Cipta -->
            <footer class="brand-footer">
                <div class="brand-footer-row">
                    <span class="brand-date-text">
                        <i class="bi bi-calendar3 me-1.5" aria-hidden="true"></i>
                        {{ todayDate }}
                    </span>
                    <span class="brand-dot" aria-hidden="true">•</span>
                    <span class="brand-copyright-text">&copy; {{ year }} {{ branding.school_short_name }}</span>
                </div>
            </footer>
            </div>
        </aside>

        <!-- Panel Kanan: Form Akses LMS -->
        <section class="login-form-panel" aria-label="Form login">
            <div class="login-form-wrapper">
                <header class="form-header">
                    <span class="login-eyebrow">Akses LMS</span>
                    <h2 class="login-title">Selamat datang kembali</h2>
                    <p class="login-subtitle">Masuk menggunakan akun sekolah Anda.</p>
                </header>

                <!-- Alert Notifikasi Flash -->
                <div v-if="flash.error" class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content">{{ flash.error }}</span>
                </div>
                <div v-if="flash.success" class="alert alert-success" role="status">
                    <i class="bi bi-check-circle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content">{{ flash.success }}</span>
                </div>

                <!-- Form Login -->
                <form class="login-form" @submit.prevent="submit">
                    <div class="form-field-group">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-shell" :class="{ 'has-error': form.errors.username }">
                            <i class="bi bi-person field-icon" aria-hidden="true"></i>
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
                                :aria-describedby="form.errors.username ? 'username-error' : undefined"
                            >
                        </div>
                        <div v-if="form.errors.username" id="username-error" class="field-error-text">
                            {{ form.errors.username }}
                        </div>
                    </div>

                    <div class="form-field-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-shell" :class="{ 'has-error': form.errors.password }">
                            <i class="bi bi-lock field-icon" aria-hidden="true"></i>
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.password }"
                                placeholder="Masukkan password"
                                required
                                autocomplete="current-password"
                                :aria-describedby="form.errors.password ? 'password-error' : undefined"
                            >
                            <button
                                type="button"
                                class="btn-toggle-password"
                                :aria-pressed="showPassword"
                                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                @click="togglePassword"
                            >
                                <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div v-if="form.errors.password" id="password-error" class="field-error-text">
                            {{ form.errors.password }}
                        </div>
                    </div>

                    <div class="form-options-row">
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

                        <a
                            v-if="forgotPasswordUrl"
                            class="forgot-link"
                            :href="forgotPasswordUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Lupa password?
                        </a>
                    </div>

                    <button type="submit" class="btn-login" :disabled="form.processing">
                        <span v-if="!form.processing">Masuk ke LMS</span>
                        <span v-else>Memproses...</span>
                        <i v-if="!form.processing" class="bi bi-arrow-right" aria-hidden="true"></i>
                        <span v-else class="login-spinner" aria-hidden="true"></span>
                    </button>

                    <div class="login-role-guide">
                        <span class="role-guide-eyebrow">Akses civitas sekolah:</span>
                        <div class="role-chips">
                            <span class="role-chip">Siswa</span>
                            <span class="role-chip">Guru</span>
                            <span class="role-chip">Admin</span>
                            <span class="role-chip">Kepala Sekolah</span>
                        </div>
                        <p class="role-guide-help">
                            Gunakan akun resmi sekolah. Jika mengalami kendala akses atau lupa kredensial, hubungi admin sekolah melalui kontak bantuan resmi.
                        </p>
                    </div>
                </form>
            </div>
        </section>
    </main>
</template>

<style scoped>
/*
 * Sengaja terisolasi dari token bersama (hex dan beberapa !important di sini disengaja):
 * - Halaman login dikunci ke tema terang dan tidak boleh berubah mengikuti mode gelap, jadi paletnya
 *   (variabel --login-...) berdiri sendiri, bukan token --surface/--text yang berganti saat data-bs-theme=dark.
 * - Panel kiri memakai hijau tua identitas sekolah yang tidak mengikuti tema pilihan admin.
 * - !important pada input dan color-scheme melawan gaya bawaan browser/autofill dan override mode gelap.
 */
.login-page {
    color-scheme: light !important;
    --login-bg: var(--app-bg, #f8f8f5);
    --login-surface: var(--surface-card, #ffffff);
    --login-border: var(--bs-border-color, #e5e7eb);
    --login-text: var(--text-strong, #1f2937);
    --login-text-muted: var(--text-muted, #64748b);
    --login-primary: var(--app-primary, #2d5d63);
    --login-primary-dark: var(--app-primary-dark, #1f4247);
    --login-input-bg: var(--surface-card, #ffffff);
    --login-input-border: #d1d5db;
    --login-brand-bg: color-mix(in srgb, var(--app-primary, #2d5d63) 78%, #0f272a);
    --login-glass-bg: rgba(255, 255, 255, 0.08);
    --login-glass-border: rgba(255, 255, 255, 0.16);
    --login-chip-bg: #f1f5f9;
    --login-chip-text: #475569;
    --login-danger-bg: #fef2f2;
    --login-danger-border: #fecaca;
    --login-danger-text: #991b1b;
    --login-success-bg: #f0fdf4;
    --login-success-border: #bbf7d0;
    --login-success-text: #166534;

    min-height: 100vh;
    width: 100%;
    display: grid;
    grid-template-columns: minmax(420px, 46%) minmax(460px, 54%);
    background: var(--login-bg);
    color: var(--login-text);
    font-family: var(--font-sans);
}

/* =========================================
   PANEL KIRI: Identitas Sekolah
   ========================================= */
.login-brand-panel {
    position: relative;
    background-color: var(--login-brand-bg);
    padding: clamp(36px, 5vw, 56px) clamp(32px, 4vw, 52px) clamp(28px, 4vw, 44px);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 36px;
    color: #ffffff;
}

.brand-top-row {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 32px;
}

.brand-logo-box {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.22);
}

.brand-logo-box img {
    width: 38px;
    height: 38px;
    object-fit: contain;
}

.brand-heading-titles {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.brand-school-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.3;
}

.brand-school-sub {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.78);
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.brand-hero {
    margin-top: 10px;
}

.brand-headline {
    margin: 0 0 10px;
    font-size: clamp(1.6rem, 3vw, 2.15rem);
    font-weight: 700;
    line-height: 1.25;
    color: #ffffff;
    letter-spacing: -0.01em;
}

.brand-motto {
    margin: 0;
    font-size: 0.92rem;
    font-style: italic;
    color: rgba(255, 255, 255, 0.84);
    line-height: 1.5;
}

/* Papan Informasi / Pengumuman Resmi */
.brand-board {
    border-radius: 14px;
    padding: clamp(16px, 2.5vw, 22px);
    background: var(--login-glass-bg);
    border: 1px solid var(--login-glass-border);
    color: #ffffff;
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}

.board-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.14);
}

.board-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.board-icon {
    font-size: 0.95rem;
    flex-shrink: 0;
}

.board-icon.text-amber {
    color: #fbbf24;
}

.board-eyebrow {
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.92);
}

.board-count-pill {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 9px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.14);
    color: #ffffff;
    white-space: nowrap;
}

.board-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-height: clamp(260px, 45vh, 480px);
    overflow-y: auto;
    padding-right: 6px;
    flex: 1;
    min-height: 0;
}

.board-list::-webkit-scrollbar {
    width: 4px;
}

.board-list::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.25);
    border-radius: 4px;
}

.board-item {
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.board-item:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.board-item-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 2px;
}

.board-item-title {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 700;
    line-height: 1.35;
    color: #ffffff;
}

.board-item-time {
    flex-shrink: 0;
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.75);
    display: inline-flex;
    align-items: center;
}

.board-item-author {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.75);
    margin-bottom: 6px;
    display: inline-flex;
    align-items: center;
}

.board-item-desc {
    margin: 0;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.88);
    line-height: 1.5;
    white-space: pre-line;
    word-break: break-word;
}

.board-item-attachment {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    padding: 5px 11px;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    color: #ffffff;
    font-size: 0.74rem;
    font-weight: 600;
    text-decoration: none;
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.board-item-attachment:hover {
    background: rgba(255, 255, 255, 0.22);
    border-color: rgba(255, 255, 255, 0.35);
    color: #ffffff;
}

/* Empty State / Panduan Informasi Sekolah */
.board-empty-state {
    display: flex;
    flex-direction: column;
    gap: 14px;
    flex: 1;
    justify-content: center;
}

.board-empty-intro {
    margin: 0;
    font-size: 0.82rem;
    color: rgba(255, 255, 255, 0.84);
    line-height: 1.5;
}

.board-quick-guide {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.guide-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.guide-icon {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.85);
    flex-shrink: 0;
    margin-top: 1px;
}

.guide-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.guide-title {
    font-size: 0.79rem;
    font-weight: 600;
    color: #ffffff;
}

.guide-text {
    font-size: 0.73rem;
    color: rgba(255, 255, 255, 0.74);
    line-height: 1.35;
}

/* Footer Panel Kiri */
.brand-footer {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 0.76rem;
    color: rgba(255, 255, 255, 0.8);
}

.brand-footer-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.brand-dot {
    opacity: 0.5;
}

/* =========================================
   PANEL KANAN: Form Akses LMS
   ========================================= */
.login-form-panel {
    background: var(--login-surface);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(36px, 5vw, 64px) clamp(24px, 5vw, 60px);
}

.login-form-wrapper {
    width: 100%;
    max-width: 420px;
    margin: 0 auto;
}

.form-header {
    margin-bottom: 24px;
}

.login-eyebrow {
    display: inline-block;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--login-primary);
    margin-bottom: 6px;
}

.login-title {
    margin: 0 0 6px;
    font-size: clamp(1.35rem, 2.5vw, 1.65rem);
    font-weight: 700;
    color: var(--login-text);
    line-height: 1.25;
}

.login-subtitle {
    margin: 0;
    font-size: 0.875rem;
    color: var(--login-text-muted);
    line-height: 1.45;
}

/* Alerts */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 11px 13px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 0.825rem;
    line-height: 1.45;
}

.alert-danger {
    background: var(--login-danger-bg);
    border: 1px solid var(--login-danger-border);
    color: var(--login-danger-text);
}

.alert-success {
    background: var(--login-success-bg);
    border: 1px solid var(--login-success-border);
    color: var(--login-success-text);
}

.alert-icon {
    font-size: 0.95rem;
    flex-shrink: 0;
    margin-top: 1px;
}

.alert-content {
    flex: 1;
}

/* Form fields */
.form-field-group {
    margin-bottom: 18px;
}

.form-label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.825rem;
    font-weight: 600;
    color: var(--login-text);
}

.input-shell {
    display: flex;
    align-items: center;
    gap: 10px;
    height: 44px;
    min-height: 44px;
    padding: 0 14px;
    border: 1px solid var(--login-input-border);
    border-radius: 10px;
    background: var(--login-input-bg);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.input-shell:focus-within {
    border-color: var(--login-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--login-primary) 18%, transparent);
}

.input-shell.has-error {
    border-color: #ef4444;
}

.input-shell.has-error:focus-within {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.18);
}

.field-icon {
    flex-shrink: 0;
    font-size: 1rem;
    color: var(--login-text-muted);
    transition: color 0.15s ease;
}

.input-shell:focus-within .field-icon {
    color: var(--login-primary);
}

.input-shell.has-error .field-icon {
    color: #ef4444;
}

.form-control {
    flex: 1;
    min-width: 0;
    height: 100%;
    padding: 0;
    border: 0 !important;
    background: transparent !important;
    color: var(--login-text) !important;
    font-size: 0.9rem;
    box-shadow: none !important;
}

.form-control::placeholder {
    color: var(--login-text-muted);
    opacity: 0.85;
}

.btn-toggle-password {
    background: transparent;
    border: 0;
    padding: 4px;
    color: var(--login-text-muted);
    font-size: 1.05rem;
    cursor: pointer;
    display: grid;
    place-items: center;
    border-radius: 6px;
    transition: color 0.15s ease;
}

.btn-toggle-password:hover {
    color: var(--login-text);
}

.btn-toggle-password:focus-visible {
    outline: 2px solid var(--login-primary);
    outline-offset: 2px;
}

.field-error-text {
    margin-top: 5px;
    font-size: 0.75rem;
    font-weight: 500;
    color: #dc2626;
}

/* Options */
.form-options-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 4px 0 22px;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    padding: 0;
    min-height: auto;
}

.form-check-input {
    width: 17px;
    height: 17px;
    margin: 0;
    cursor: pointer;
    border: 1px solid var(--login-input-border);
    border-radius: 4px;
    background-color: var(--login-input-bg);
}

.form-check-input:checked {
    background-color: var(--login-primary);
    border-color: var(--login-primary);
}

.form-check-input:focus {
    border-color: var(--login-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--login-primary) 18%, transparent);
}

.form-check-label {
    font-size: 0.825rem;
    color: var(--login-text-muted);
    cursor: pointer;
    user-select: none;
}

.forgot-link {
    font-size: 0.825rem;
    font-weight: 600;
    color: var(--login-primary);
    text-decoration: none;
    transition: color 0.15s ease;
}

.forgot-link:hover {
    color: var(--login-primary-dark);
    text-decoration: underline;
    text-underline-offset: 3px;
}

.forgot-link:focus-visible {
    outline: 2px solid var(--login-primary);
    outline-offset: 2px;
    border-radius: 4px;
}

/* Submit Button (48px) */
.btn-login {
    width: 100%;
    height: 48px;
    min-height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 0 20px;
    border: 1px solid var(--login-primary);
    border-radius: 10px;
    background: var(--login-primary);
    color: #ffffff;
    font-size: 0.925rem;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.btn-login:hover:not(:disabled) {
    background: var(--login-primary-dark);
    border-color: var(--login-primary-dark);
    box-shadow: 0 4px 14px color-mix(in srgb, var(--login-primary) 28%, transparent);
}

.btn-login:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--login-primary) 28%, transparent);
}

.btn-login:disabled {
    opacity: 0.7;
    cursor: wait;
}

.login-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: login-spin 0.7s linear infinite;
}

@keyframes login-spin {
    to {
        transform: rotate(360deg);
    }
}

/* Role guide */
.login-role-guide {
    margin-top: 26px;
    padding-top: 20px;
    border-top: 1px solid var(--login-border);
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.role-guide-eyebrow {
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--login-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.role-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.role-chip {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    font-size: 0.725rem;
    font-weight: 600;
    border-radius: 6px;
    background: var(--login-chip-bg);
    color: var(--login-chip-text);
    border: 1px solid var(--login-border);
    cursor: default;
    user-select: none;
}

.role-guide-help {
    margin: 4px 0 0;
    font-size: 0.76rem;
    color: var(--login-text-muted);
    line-height: 1.45;
}

.brand-lower {
    display: contents;
}

/* Responsive Layout */
@media (max-width: 960px) {
    .login-page {
        grid-template-columns: 1fr;
        min-height: auto;
    }

    /* Mobile: identitas -> form login -> papan informasi */
    .login-brand-panel {
        display: contents;
    }

    .brand-header {
        order: 1;
        background-color: var(--login-brand-bg);
        color: #ffffff;
        padding: 32px 24px 28px;
    }

    .login-form-panel {
        order: 2;
    }

    .brand-lower {
        display: flex;
        flex-direction: column;
        gap: 24px;
        order: 3;
        background-color: var(--login-brand-bg);
        color: #ffffff;
        padding: 32px 24px 28px;
    }

    .brand-top-row {
        margin-bottom: 20px;
    }

    .brand-headline {
        font-size: 1.45rem;
    }

    .board-list {
        max-height: 280px;
    }

    .login-form-panel {
        padding: 36px 20px 48px;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .btn-login,
    .input-shell,
    .login-spinner,
    .btn-toggle-password,
    .board-item-attachment {
        transition: none !important;
        animation: none !important;
    }
}


/* Visual refresh: a clear school identity panel paired with a focused sign-in card. */
.login-page {
    --login-bg: #f1f5fb;
    --login-surface: #ffffff;
    --login-border: #e5eaf1;
    --login-text: #14243a;
    --login-text-muted: #65758b;
    --login-primary: var(--app-primary, #2563eb);
    --login-primary-dark: var(--app-primary-dark, #173b63);
    --login-input-border: #d7e0eb;
    --login-brand-bg: #102944;
    --login-glass-bg: rgba(255, 255, 255, 0.075);
    --login-glass-border: rgba(255, 255, 255, 0.16);

    grid-template-columns: minmax(0, 1.08fr) minmax(460px, 0.92fr);
    background:
        radial-gradient(ellipse at 82% 8%, rgba(31, 111, 235, 0.08), transparent 32rem),
        var(--login-bg);
}

.login-brand-panel {
    isolation: isolate;
    overflow: hidden;
    padding: clamp(36px, 5vw, 72px) clamp(32px, 6vw, 88px);
    background:
        radial-gradient(circle at 88% 18%, rgba(50, 178, 204, 0.28), transparent 17rem),
        radial-gradient(circle at 8% 92%, rgba(29, 78, 216, 0.34), transparent 22rem),
        linear-gradient(145deg, #102944 0%, #123b53 56%, #123454 100%);
}

.login-brand-panel::after {
    position: absolute;
    z-index: -1;
    top: 14%;
    right: -210px;
    width: 470px;
    height: 470px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 50%;
    box-shadow: 0 0 0 36px rgba(255, 255, 255, 0.035), 0 0 0 86px rgba(255, 255, 255, 0.025);
    content: '';
    pointer-events: none;
}

.brand-header,
.brand-lower,
.brand-footer {
    position: relative;
    z-index: 1;
}

.brand-top-row { gap: 16px; margin-bottom: clamp(36px, 7vh, 76px); }
.brand-logo-box { width: 60px; height: 60px; border-radius: 18px; background: rgba(255, 255, 255, 0.12); }
.brand-logo-box img { width: 44px; height: 44px; }
.brand-school-name { font-size: 1.1rem; letter-spacing: -0.01em; }
.brand-school-sub { margin-top: 3px; }
.brand-headline {
    max-width: 620px;
    font-size: clamp(2.2rem, 4.2vw, 3.65rem);
    font-weight: 750;
    letter-spacing: -0.055em;
    line-height: 1.06;
}
.brand-motto { margin-top: 16px; font-size: 1rem; }
.brand-board {
    min-height: 270px;
    padding: clamp(18px, 2.4vw, 28px);
    border-radius: 22px;
    background: rgba(8, 27, 46, 0.3);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(10px);
}
.board-header { margin-bottom: 18px; padding-bottom: 14px; }
.board-quick-guide { gap: 10px; }
.guide-item { padding: 12px 14px; border-radius: 13px; background: rgba(255, 255, 255, 0.07); }
.guide-title { font-size: 0.82rem; }
.guide-text { font-size: 0.76rem; line-height: 1.45; }
.brand-footer { font-size: 0.78rem; }

.login-form-panel {
    padding: clamp(28px, 5vw, 72px);
    background: transparent;
}

.login-form-wrapper {
    position: relative;
    max-width: 500px;
    padding: clamp(28px, 4vw, 48px);
    border: 1px solid rgba(214, 224, 237, 0.85);
    border-radius: 28px;
    background: var(--login-surface);
    box-shadow: 0 28px 80px -42px rgba(19, 43, 73, 0.35), 0 3px 12px rgba(19, 43, 73, 0.04);
}

.login-form-wrapper::before {
    position: absolute;
    top: 0;
    right: 34px;
    left: 34px;
    height: 4px;
    border-radius: 0 0 999px 999px;
    background: linear-gradient(90deg, var(--login-primary), #2dd4bf);
    content: '';
}

.form-header { margin-bottom: 30px; }
.login-eyebrow { margin-bottom: 10px; font-size: 0.72rem; letter-spacing: 0.13em; }
.login-title { font-size: clamp(1.8rem, 3vw, 2.25rem); font-weight: 750; letter-spacing: -0.045em; }
.login-subtitle { font-size: 0.94rem; }
.form-field-group { margin-bottom: 20px; }
.form-label { margin-bottom: 8px; font-size: 0.83rem; font-weight: 700; }
.input-shell { height: 52px; min-height: 52px; border-radius: 14px; background: #fbfcfe; }
.input-shell:focus-within { background: #ffffff; }
.btn-login {
    height: 54px;
    min-height: 54px;
    border: 0;
    border-radius: 14px;
    background: linear-gradient(105deg, var(--login-primary), color-mix(in srgb, var(--login-primary) 68%, #16b8a6));
    box-shadow: 0 10px 22px color-mix(in srgb, var(--login-primary) 24%, transparent);
    font-weight: 700;
}
.btn-login:hover:not(:disabled) { box-shadow: 0 14px 26px color-mix(in srgb, var(--login-primary) 30%, transparent); }
.login-role-guide { margin-top: 28px; padding-top: 22px; }
.role-guide-eyebrow { font-weight: 700; letter-spacing: 0.06em; }
.role-chips { gap: 7px; }
.role-chip { padding: 5px 11px; border-radius: 999px; background: #f4f7fb; }
.role-guide-help { margin-top: 5px; line-height: 1.55; }

@media (max-width: 960px) {
    .login-page { grid-template-columns: minmax(0, 1fr); }
    .brand-header { padding: 32px 24px 30px; }
    .brand-lower { padding: 0 24px 28px; }
    .brand-top-row { margin-bottom: 32px; }
    .brand-headline { max-width: 660px; font-size: clamp(2rem, 6vw, 3rem); }
    .login-form-panel { padding: 28px 20px 42px; }
    .login-form-wrapper { max-width: 560px; }
}

@media (max-width: 575.98px) {
    .brand-header { padding: 26px 20px 24px; }
    .brand-lower { padding: 0 20px 24px; }
    .brand-top-row { margin-bottom: 28px; }
    .brand-logo-box { width: 52px; height: 52px; border-radius: 16px; }
    .brand-logo-box img { width: 38px; height: 38px; }
    .brand-headline { font-size: 2.05rem; }
    .brand-board { min-height: 0; padding: 17px; border-radius: 18px; }
    .login-form-panel { padding: 22px 14px 32px; }
    .login-form-wrapper { padding: 27px 21px; border-radius: 22px; }
    .login-form-wrapper::before { right: 24px; left: 24px; }
}
</style>

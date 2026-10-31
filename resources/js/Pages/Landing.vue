<script setup lang="ts">
import type { PropType } from 'vue';
import { computed, onBeforeMount, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { forceLightMode, releaseForcedMode } from '../theme';

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

const visibleAnnouncements = computed(() => props.publicAnnouncements.slice(0, 3));

const supportUrl = computed(() => {
    const rawContact = String(props.branding.support_contact ?? '').replace(/[^\d+]/g, '');

    if (!rawContact) {
        return null;
    }

    const digits = rawContact.replace(/\D/g, '');
    const whatsappNumber = digits.startsWith('0') ? `62${digits.slice(1)}` : digits;
    const message = encodeURIComponent('Assalamu\'alaikum, saya membutuhkan bantuan LMS. Nama: ........ Kelas: .........');

    return `https://wa.me/${whatsappNumber}?text=${message}`;
});

function formatDate(value: string | null) {
    return value
        ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
        : '';
}
</script>

<template>
    <Head title="Beranda" />

    <main class="landing-page">
        <header class="landing-header">
            <div class="landing-container landing-nav">
                <Link href="/" class="landing-brand" aria-label="Beranda LMS">
                    <span class="landing-logo-box">
                        <img
                            :src="branding.logo_url"
                            :alt="`Logo ${branding.school_name}`"
                            width="38"
                            height="38"
                            decoding="async"
                        >
                    </span>
                    <span class="landing-brand-copy">
                        <strong>{{ branding.school_name }}</strong>
                        <small>Portal akademik sekolah</small>
                    </span>
                </Link>

                <nav class="landing-nav-links" aria-label="Navigasi utama">
                    <a href="#fitur">Fitur</a>
                    <a href="#pengumuman">Pengumuman</a>
                    <Link :href="loginUrl" class="landing-nav-login">
                        Masuk <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                    </Link>
                </nav>
            </div>
        </header>

        <section class="landing-hero" aria-labelledby="landing-title">
            <div class="landing-container landing-hero-grid">
                <div class="landing-hero-copy">
                    <span class="landing-eyebrow">
                        <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                        Ruang belajar warga sekolah
                    </span>
                    <h1 id="landing-title">
                        Belajar lebih terarah,<br>
                        <em>dari satu tempat.</em>
                    </h1>
                    <p class="landing-hero-description">
                        {{ branding.school_motto || `Materi, tugas, ujian, dan informasi ${branding.school_name} tersedia dalam satu portal yang mudah digunakan.` }}
                    </p>

                    <div class="landing-hero-actions">
                        <Link :href="loginUrl" class="landing-button landing-button-primary">
                            Masuk ke LMS
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </Link>
                        <a href="#pengumuman" class="landing-button landing-button-secondary">
                            Lihat pengumuman
                        </a>
                    </div>

                    <div class="landing-trust-line">
                        <span class="landing-trust-mark"><i class="bi bi-check2" aria-hidden="true"></i></span>
                        <span>
                            <strong>Untuk seluruh warga {{ branding.school_short_name }}</strong>
                            <small>{{ branding.school_address }}</small>
                        </span>
                    </div>
                </div>

                <div class="landing-hero-visual" aria-label="Ringkasan ruang belajar LMS">
                    <div class="landing-visual-topline">
                        <span class="landing-live-dot" aria-hidden="true"></span>
                        <span>Ruang belajar</span>
                        <span class="landing-visual-status">TERHUBUNG</span>
                    </div>
                    <div class="landing-visual-heading">
                        <span class="landing-visual-kicker">Hari ini di LMS</span>
                        <strong>Semua yang penting,<br>lebih mudah ditemukan.</strong>
                    </div>
                    <div class="landing-visual-list">
                        <div class="landing-visual-row">
                            <span class="landing-visual-icon landing-visual-icon-blue"><i class="bi bi-book-half" aria-hidden="true"></i></span>
                            <span><strong>Materi pembelajaran</strong><small>Akses materi kapan saja</small></span>
                            <i class="bi bi-arrow-up-right landing-visual-arrow" aria-hidden="true"></i>
                        </div>
                        <div class="landing-visual-row">
                            <span class="landing-visual-icon landing-visual-icon-amber"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
                            <span><strong>Tugas dan ujian</strong><small>Kerjakan dengan teratur</small></span>
                            <i class="bi bi-arrow-up-right landing-visual-arrow" aria-hidden="true"></i>
                        </div>
                        <div class="landing-visual-row">
                            <span class="landing-visual-icon landing-visual-icon-green"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
                            <span><strong>Progress belajar</strong><small>Pantau perkembanganmu</small></span>
                            <i class="bi bi-arrow-up-right landing-visual-arrow" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="landing-visual-footer">
                        <span><i class="bi bi-shield-check" aria-hidden="true"></i> Aman untuk warga sekolah</span>
                        <span>{{ branding.school_short_name }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="fitur" class="landing-features" aria-labelledby="features-title">
            <div class="landing-container">
                <div class="landing-section-heading">
                    <div>
                        <span class="landing-section-kicker">Satu portal, banyak kemudahan</span>
                        <h2 id="features-title">Dirancang untuk kegiatan belajar sehari-hari.</h2>
                    </div>
                    <p>Gunakan akun sekolah untuk mengakses ruang belajar yang sesuai dengan peran Anda.</p>
                </div>

                <div class="landing-feature-list">
                    <article class="landing-feature-item">
                        <span class="landing-feature-number">01</span>
                        <i class="bi bi-book-half landing-feature-icon" aria-hidden="true"></i>
                        <h3>Materi terpusat</h3>
                        <p>Temukan materi dari setiap kelas dan mata pelajaran dalam satu tempat.</p>
                    </article>
                    <article class="landing-feature-item">
                        <span class="landing-feature-number">02</span>
                        <i class="bi bi-journal-check landing-feature-icon" aria-hidden="true"></i>
                        <h3>Tugas lebih teratur</h3>
                        <p>Lihat tugas, ujian, tenggat waktu, dan hasil belajar dengan lebih jelas.</p>
                    </article>
                    <article class="landing-feature-item">
                        <span class="landing-feature-number">03</span>
                        <i class="bi bi-megaphone landing-feature-icon" aria-hidden="true"></i>
                        <h3>Informasi terkini</h3>
                        <p>Ikuti pengumuman sekolah dan kabar penting tanpa melewatkan informasi.</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="pengumuman" class="landing-announcements" aria-labelledby="announcements-title">
            <div class="landing-container">
                <div class="landing-section-heading landing-announcements-heading">
                    <div>
                        <span class="landing-section-kicker">Informasi sekolah</span>
                        <h2 id="announcements-title">Pengumuman terbaru.</h2>
                    </div>
                    <span class="landing-announcement-count">
                        {{ publicAnnouncements.length ? `${publicAnnouncements.length} tersedia` : 'Belum ada pengumuman' }}
                    </span>
                </div>

                <div v-if="visibleAnnouncements.length" class="landing-announcement-list">
                    <article v-for="announcement in visibleAnnouncements" :key="announcement.id" class="landing-announcement-item">
                        <div class="landing-announcement-date">
                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                            <span>{{ formatDate(announcement.created_at) || 'Informasi sekolah' }}</span>
                        </div>
                        <div class="landing-announcement-content">
                            <h3>{{ announcement.judul }}</h3>
                            <p>{{ announcement.isi }}</p>
                            <a
                                v-if="announcement.attachment"
                                :href="announcement.attachment.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="landing-announcement-attachment"
                            >
                                <i class="bi bi-paperclip" aria-hidden="true"></i>
                                {{ announcement.attachment.name }}
                            </a>
                        </div>
                        <span v-if="announcement.creator_name" class="landing-announcement-author">
                            {{ announcement.creator_name }}
                        </span>
                    </article>
                </div>

                <div v-else class="landing-announcement-empty">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <div>
                        <strong>Belum ada pengumuman untuk ditampilkan.</strong>
                        <p>Informasi sekolah terbaru akan muncul di halaman ini.</p>
                    </div>
                </div>
            </div>
        </section>

        <footer class="landing-footer">
            <div class="landing-container landing-footer-inner">
                <div class="landing-footer-brand">
                    <span class="landing-footer-logo"><img :src="branding.logo_url" :alt="`Logo ${branding.school_name}`" width="30" height="30"></span>
                    <span><strong>{{ branding.school_short_name }}</strong><small>Portal akademik sekolah</small></span>
                </div>
                <div class="landing-footer-meta">
                    <span>&copy; {{ year }} {{ branding.school_name }}</span>
                    <a v-if="supportUrl" :href="supportUrl" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-chat-dots" aria-hidden="true"></i> Butuh bantuan?
                    </a>
                    <Link :href="loginUrl">Masuk ke LMS <i class="bi bi-arrow-right" aria-hidden="true"></i></Link>
                </div>
            </div>
        </footer>
    </main>
</template>

<style scoped>
.landing-page {
    --landing-ink: #172033;
    --landing-body: #475569;
    --landing-muted: #64748b;
    --landing-border: #e2e8f0;
    --landing-surface: #ffffff;
    --landing-subtle: #f8fafc;
    --landing-primary: var(--app-primary, #198754);
    --landing-primary-dark: var(--app-primary-dark, #166534);
    --landing-visual-start: color-mix(in srgb, var(--landing-primary) 86%, #0f172a);
    --landing-visual-end: color-mix(in srgb, var(--landing-primary-dark) 92%, #0f172a);
    --landing-gold: #d97706;

    min-height: 100vh;
    overflow: hidden;
    background: var(--landing-surface);
    color: var(--landing-ink);
    font-family: var(--font-sans);
}

.landing-container {
    width: min(100% - 3rem, 1180px);
    margin-inline: auto;
}

.landing-header {
    position: relative;
    z-index: 2;
    border-bottom: 1px solid rgba(226, 232, 240, 0.82);
    background: rgba(255, 255, 255, 0.94);
}

.landing-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 76px;
    gap: 1rem;
}

.landing-brand,
.landing-nav-links,
.landing-footer-brand,
.landing-footer-meta {
    display: flex;
    align-items: center;
}

.landing-brand {
    min-width: 0;
    gap: 0.75rem;
    color: var(--landing-ink);
    text-decoration: none;
}

.landing-logo-box,
.landing-footer-logo {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--landing-border);
    background: var(--landing-subtle);
}

.landing-logo-box {
    width: 42px;
    height: 42px;
    border-radius: 12px;
}

.landing-logo-box img,
.landing-footer-logo img {
    width: 78%;
    height: 78%;
    object-fit: contain;
}

.landing-brand-copy,
.landing-footer-brand > span:last-child {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

.landing-brand-copy strong {
    overflow: hidden;
    color: var(--landing-ink);
    font-size: 0.92rem;
    font-weight: 750;
    line-height: 1.25;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.landing-brand-copy small,
.landing-footer-brand small {
    margin-top: 0.13rem;
    color: var(--landing-muted);
    font-size: 0.68rem;
    font-weight: 600;
}

.landing-nav-links {
    gap: clamp(1rem, 2.5vw, 2rem);
}

.landing-nav-links a {
    color: var(--landing-muted);
    font-size: 0.8rem;
    font-weight: 650;
    text-decoration: none;
    transition: color 150ms ease;
}

.landing-nav-links a:hover,
.landing-nav-links a:focus-visible {
    color: var(--landing-primary-dark);
}

.landing-nav-login {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-height: 38px;
    padding: 0.55rem 0.85rem;
    border: 1px solid color-mix(in srgb, var(--landing-primary) 25%, var(--landing-border));
    border-radius: 8px;
    background: color-mix(in srgb, var(--landing-primary) 7%, white);
    color: var(--landing-primary-dark) !important;
}

.landing-hero {
    position: relative;
    background:
        linear-gradient(90deg, transparent 0, transparent calc(50% - 590px), rgba(22, 101, 52, 0.04) calc(50% - 590px), rgba(22, 101, 52, 0.04) calc(50% - 589px), transparent calc(50% - 589px)),
        var(--landing-surface);
}

.landing-hero::after {
    position: absolute;
    top: -210px;
    right: -100px;
    width: 430px;
    height: 430px;
    border: 1px solid rgba(22, 101, 52, 0.09);
    border-radius: 50%;
    content: '';
    pointer-events: none;
}

.landing-hero-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(360px, 0.95fr);
    align-items: center;
    gap: clamp(3rem, 8vw, 8rem);
    min-height: 580px;
    padding-block: clamp(4rem, 8vw, 7.5rem);
}

.landing-hero-copy {
    max-width: 620px;
}

.landing-eyebrow,
.landing-section-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--landing-primary-dark);
    font-size: 0.72rem;
    font-weight: 750;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.landing-eyebrow i {
    color: var(--landing-gold);
    font-size: 0.9rem;
}

.landing-hero h1 {
    max-width: 650px;
    margin: 1rem 0 1.2rem;
    color: var(--landing-ink);
    font-size: clamp(2.5rem, 5vw, 4.7rem);
    font-weight: 780;
    letter-spacing: -0.065em;
    line-height: 1.03;
}

.landing-hero h1 em {
    color: var(--landing-primary-dark);
    font-style: normal;
}

.landing-hero-description {
    max-width: 550px;
    margin: 0;
    color: var(--landing-body);
    font-size: clamp(0.95rem, 1.5vw, 1.08rem);
    line-height: 1.7;
}

.landing-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.7rem;
    margin-top: 2rem;
}

.landing-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.7rem;
    min-height: 46px;
    padding: 0.7rem 1rem;
    border-radius: 8px;
    font-size: 0.84rem;
    font-weight: 700;
    text-decoration: none;
    transition: transform 150ms ease, background-color 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
}

.landing-button:hover {
    transform: translateY(-1px);
}

.landing-button-primary {
    border: 1px solid var(--landing-primary);
    background: var(--landing-primary);
    color: #fff;
    box-shadow: 0 8px 18px color-mix(in srgb, var(--landing-primary) 20%, transparent);
}

.landing-button-primary:hover,
.landing-button-primary:focus-visible {
    border-color: var(--landing-primary-dark);
    background: var(--landing-primary-dark);
    color: #fff;
}

.landing-button-secondary {
    border: 1px solid var(--landing-border);
    background: var(--landing-surface);
    color: var(--landing-body);
}

.landing-button-secondary:hover,
.landing-button-secondary:focus-visible {
    border-color: var(--landing-primary);
    color: var(--landing-primary-dark);
}

.landing-trust-line {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin-top: 2.6rem;
    color: var(--landing-muted);
}

.landing-trust-mark {
    display: grid;
    width: 25px;
    height: 25px;
    place-items: center;
    border-radius: 50%;
    background: #dcfce7;
    color: #15803d;
    font-size: 0.75rem;
}

.landing-trust-line span:last-child {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
}

.landing-trust-line strong {
    color: var(--landing-body);
    font-size: 0.76rem;
    font-weight: 700;
}

.landing-trust-line small {
    overflow: hidden;
    max-width: 420px;
    font-size: 0.72rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.landing-hero-visual {
    position: relative;
    padding: clamp(1.35rem, 3vw, 2rem);
    border: 1px solid color-mix(in srgb, var(--landing-primary) 38%, #d8e5dc);
    border-radius: 18px;
    background: linear-gradient(145deg, var(--landing-visual-start) 0%, var(--landing-visual-end) 100%);
    color: #fff;
    box-shadow: 0 24px 48px color-mix(in srgb, var(--landing-primary-dark) 22%, transparent);
}

.landing-hero-visual::before {
    position: absolute;
    right: 20px;
    bottom: 18px;
    width: 130px;
    height: 130px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 50%;
    content: '';
    pointer-events: none;
}

.landing-visual-topline,
.landing-visual-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    color: rgba(255, 255, 255, 0.68);
    font-size: 0.68rem;
    font-weight: 650;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.landing-visual-topline > span:first-of-type {
    margin-right: -0.35rem;
}

.landing-live-dot {
    width: 7px;
    height: 7px;
    flex: 0 0 auto;
    border-radius: 50%;
    background: #fbbf24;
    box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.12);
}

.landing-visual-status {
    margin-left: auto;
    color: #86efac;
    font-size: 0.6rem;
}

.landing-visual-heading {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin: 4rem 0 2rem;
}

.landing-visual-kicker {
    color: #fbbf24;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.landing-visual-heading strong {
    font-size: clamp(1.4rem, 2.5vw, 2rem);
    font-weight: 700;
    letter-spacing: -0.04em;
    line-height: 1.13;
}

.landing-visual-list {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.landing-visual-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.11);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.065);
}

.landing-visual-icon {
    display: grid;
    width: 32px;
    height: 32px;
    place-items: center;
    border-radius: 8px;
    font-size: 0.85rem;
}

.landing-visual-icon-blue { background: rgba(125, 211, 252, 0.16); color: #bae6fd; }
.landing-visual-icon-amber { background: rgba(251, 191, 36, 0.16); color: #fde68a; }
.landing-visual-icon-green { background: rgba(134, 239, 172, 0.16); color: #bbf7d0; }

.landing-visual-row > span:nth-child(2) {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.15rem;
}

.landing-visual-row strong {
    overflow: hidden;
    color: rgba(255, 255, 255, 0.94);
    font-size: 0.76rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.landing-visual-row small {
    color: rgba(255, 255, 255, 0.56);
    font-size: 0.67rem;
}

.landing-visual-arrow {
    color: rgba(255, 255, 255, 0.42);
    font-size: 0.75rem;
}

.landing-visual-footer {
    position: relative;
    z-index: 1;
    margin-top: 1.5rem;
    padding-top: 0.9rem;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    font-size: 0.62rem;
    letter-spacing: 0;
    text-transform: none;
}

.landing-visual-footer i { color: #86efac; margin-right: 0.25rem; }

.landing-features,
.landing-announcements {
    border-top: 1px solid var(--landing-border);
}

.landing-features {
    padding-block: clamp(3.75rem, 7vw, 6rem);
    background: var(--landing-subtle);
}

.landing-section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 2rem;
    margin-bottom: 2rem;
}

.landing-section-heading h2 {
    max-width: 620px;
    margin: 0.45rem 0 0;
    color: var(--landing-ink);
    font-size: clamp(1.45rem, 3vw, 2.25rem);
    font-weight: 750;
    letter-spacing: -0.045em;
    line-height: 1.12;
}

.landing-section-heading > p {
    max-width: 330px;
    margin: 0;
    color: var(--landing-muted);
    font-size: 0.82rem;
    line-height: 1.6;
}

.landing-feature-list {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    border-top: 1px solid var(--landing-border);
    border-bottom: 1px solid var(--landing-border);
}

.landing-feature-item {
    position: relative;
    min-height: 220px;
    padding: 1.35rem 1.4rem 1.5rem;
}

.landing-feature-item + .landing-feature-item {
    border-left: 1px solid var(--landing-border);
}

.landing-feature-number {
    display: block;
    margin-bottom: 2.1rem;
    color: var(--landing-muted);
    font-size: 0.68rem;
    font-weight: 750;
    letter-spacing: 0.08em;
}

.landing-feature-icon {
    display: block;
    margin-bottom: 1.2rem;
    color: var(--landing-primary);
    font-size: 1.2rem;
}

.landing-feature-item h3 {
    margin: 0 0 0.45rem;
    color: var(--landing-ink);
    font-size: 0.98rem;
    font-weight: 750;
}

.landing-feature-item p {
    max-width: 28ch;
    margin: 0;
    color: var(--landing-muted);
    font-size: 0.78rem;
    line-height: 1.6;
}

.landing-announcements {
    padding-block: clamp(3.75rem, 7vw, 6rem);
    background: var(--landing-surface);
}

.landing-announcements-heading {
    align-items: center;
}

.landing-announcement-count {
    padding: 0.35rem 0.7rem;
    border: 1px solid #bbf7d0;
    border-radius: 999px;
    background: #f0fdf4;
    color: #166534;
    font-size: 0.7rem;
    font-weight: 700;
}

.landing-announcement-list {
    border-top: 1px solid var(--landing-border);
}

.landing-announcement-item {
    display: grid;
    grid-template-columns: 155px minmax(0, 1fr) 150px;
    gap: 1.5rem;
    align-items: start;
    padding: 1.35rem 0;
    border-bottom: 1px solid var(--landing-border);
}

.landing-announcement-date,
.landing-announcement-author {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--landing-muted);
    font-size: 0.72rem;
}

.landing-announcement-date i { color: var(--landing-primary); }

.landing-announcement-content h3 {
    margin: 0 0 0.35rem;
    color: var(--landing-ink);
    font-size: 0.96rem;
    font-weight: 750;
}

.landing-announcement-content p {
    max-width: 70ch;
    margin: 0;
    color: var(--landing-muted);
    font-size: 0.8rem;
    line-height: 1.6;
}

.landing-announcement-attachment {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.65rem;
    color: var(--landing-primary-dark);
    font-size: 0.74rem;
    font-weight: 700;
    text-decoration: none;
}

.landing-announcement-attachment:hover { text-decoration: underline; text-underline-offset: 3px; }

.landing-announcement-author {
    justify-content: flex-end;
    text-align: right;
}

.landing-announcement-empty {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 1.2rem;
    border: 1px solid var(--landing-border);
    background: var(--landing-subtle);
    color: var(--landing-muted);
}

.landing-announcement-empty > i {
    color: var(--landing-primary);
    font-size: 1.1rem;
}

.landing-announcement-empty strong {
    color: var(--landing-body);
    font-size: 0.8rem;
}

.landing-announcement-empty p {
    margin: 0.2rem 0 0;
    font-size: 0.76rem;
}

.landing-footer {
    border-top: 1px solid var(--landing-border);
    background: var(--landing-subtle);
}

.landing-footer-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    min-height: 86px;
}

.landing-footer-brand { gap: 0.6rem; }

.landing-footer-logo {
    width: 32px;
    height: 32px;
    border-radius: 9px;
}

.landing-footer-brand strong {
    color: var(--landing-ink);
    font-size: 0.8rem;
}

.landing-footer-meta {
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 1rem;
    color: var(--landing-muted);
    font-size: 0.7rem;
}

.landing-footer-meta a {
    color: var(--landing-primary-dark);
    font-weight: 700;
    text-decoration: none;
}

.landing-footer-meta a:hover { text-decoration: underline; text-underline-offset: 3px; }

.landing-page :is(a, button):focus-visible {
    outline: 3px solid color-mix(in srgb, var(--landing-primary) 34%, transparent);
    outline-offset: 3px;
}

@media (max-width: 900px) {
    .landing-hero-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 3rem;
        min-height: auto;
    }

    .landing-hero-copy { max-width: 700px; }
    .landing-hero-visual { max-width: 620px; }
    .landing-announcement-item { grid-template-columns: 135px minmax(0, 1fr); }
    .landing-announcement-author { grid-column: 2; justify-content: flex-start; text-align: left; }
}

@media (max-width: 680px) {
    .landing-container { width: min(100% - 2rem, 1180px); }
    .landing-nav { min-height: 68px; }
    .landing-brand-copy small { display: none; }
    .landing-nav-links > a:not(.landing-nav-login) { display: none; }
    .landing-hero-grid { padding-block: 3.25rem 4rem; }
    .landing-hero h1 { font-size: clamp(2.35rem, 13vw, 3.5rem); }
    .landing-hero-description { font-size: 0.9rem; }
    .landing-hero-actions { flex-direction: column; align-items: stretch; }
    .landing-button { width: 100%; }
    .landing-trust-line { margin-top: 2rem; }
    .landing-hero-visual { padding: 1.15rem; border-radius: 14px; }
    .landing-visual-heading { margin-top: 3rem; }
    .landing-section-heading { align-items: flex-start; flex-direction: column; gap: 0.8rem; }
    .landing-feature-list { grid-template-columns: 1fr; }
    .landing-feature-item { min-height: 0; padding: 1.2rem 0; }
    .landing-feature-item + .landing-feature-item { border-top: 1px solid var(--landing-border); border-left: 0; }
    .landing-feature-number { margin-bottom: 1.15rem; }
    .landing-announcement-item { grid-template-columns: 1fr; gap: 0.65rem; }
    .landing-announcement-author { grid-column: auto; }
    .landing-announcements-heading { align-items: flex-start; }
    .landing-footer-inner { align-items: flex-start; flex-direction: column; padding-block: 1.2rem; }
    .landing-footer-meta { justify-content: flex-start; gap: 0.65rem 1rem; }
}

@media (prefers-reduced-motion: reduce) {
    .landing-button,
    .landing-nav-links a { transition: none; }
}
</style>

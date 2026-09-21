# LMS Sekolah

LMS Sekolah adalah Learning Management System single-school untuk mengelola pengguna, kelas, siswa, guru, mata pelajaran, materi, tugas, pengumpulan, nilai, absensi, komunikasi, kalender, laporan, dan branding sekolah.

> Project ini ditujukan untuk satu sekolah per instalasi. Multi-tenant SaaS, billing, dan subscription belum termasuk scope.

[![CI](https://github.com/ilhamrizqiawan21/lms_sekolah/actions/workflows/ci.yml/badge.svg)](https://github.com/ilhamrizqiawan21/lms_sekolah/actions/workflows/ci.yml)

## Fitur utama

- Role-based access untuk Admin, Kepala Sekolah, Guru, dan Siswa.
- Master akademik: tahun ajaran, semester, kelas, siswa, mata pelajaran, guru-mapel, kelas-mapel, wali kelas, dan jadwal.
- Pembelajaran: materi, tugas, tenggat, submission teks/multi-file, penilaian, dan catatan perbaikan.
- Akademik: absensi, kelas daring, nilai akhir, sikap spiritual/sosial, progress, rekap, dan export.
- Komunikasi: chat kelas, pengumuman, notifikasi, kalender, dan log persiapan pesan WhatsApp.
- Branding sekolah: identitas, logo, favicon, warna tema, visi, misi, kontak, dan data legal.
- Security foundation: authorization server-side, middleware keamanan, rate limiting, blocked IP, security headers, dan audit logging.

## Tech stack

| Layer | Teknologi |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | Vue 3, Inertia.js, Blade |
| UI/build | Bootstrap 5, Bootstrap Icons, Vite |
| Database | MySQL 8+ atau MariaDB 10.6+ |
| Export | DomPDF, OpenSpout |
| Testing | PHPUnit/Laravel Test Suite, Playwright |
| Local environment | Lerd; konfigurasi project memakai PHP 8.5 dan Node 22 |

## Quick start dengan Lerd

    git clone https://github.com/ilhamrizqiawan21/lms_sekolah.git
    cd lms_sekolah
    lerd site:list
    lerd env:setup
    lerd setup

Untuk development, gunakan worker Vite Lerd atau jalankan npm install dan npm run dev. Domain utama checkout ini saat ini adalah https://lms_sekolah.test; selalu verifikasi dengan lerd site:list.

## Instalasi manual

    composer install
    npm install
    cp .env.example .env
    php artisan key:generate

Konfigurasikan MySQL/MariaDB di .env, lalu pilih salah satu:

    # Demo development/testing
    php artisan migrate --seed

    # Instalasi kosong dengan admin dari DEFAULT_ADMIN_*
    php artisan migrate --seed --seeder=EmptyProductSeeder

Lanjutkan dengan:

    php artisan storage:link
    npm run build
    php artisan serve

Panduan lengkap ada di docs/INSTALLATION.md.

## Akun demo

Seeder demo memakai password password untuk development/testing saja:

| Role | Email |
|---|---|
| Admin | admin@demo.test |
| Guru | guru@demo.test |
| Siswa | siswa@demo.test |
| Kepala Sekolah | kepsek@demo.test |

Jangan deploy akun atau password demo ke production. Untuk instalasi kosong, isi DEFAULT_ADMIN_USERNAME, DEFAULT_ADMIN_EMAIL, DEFAULT_ADMIN_PASSWORD (minimal 12 karakter), dan DEFAULT_ADMIN_NAME di .env.

## Development dan testing

    composer lint
    composer test
    npm run typecheck
    npm run build

Test Laravel memakai SQLite :memory: yang diatur di phpunit.xml, jadi tidak boleh menyentuh database development. Untuk UI, gunakan Playwright pada domain Lerd yang benar dan credential yang memang tersedia.

## Dokumentasi

### Produk dan data

- docs/PRD.md — tujuan, persona, scope, modul, aturan domain, dan acceptance baseline.
- docs/ERD.md — ERD Mermaid, katalog tabel, relasi, dan constraint penting.
- AI_RULES.md — aturan kerja AI agent di repository.

### Setup dan arsitektur

- docs/INSTALLATION.md — instalasi Lerd/manual, seeder, asset, dan production checklist.
- docs/ARCHITECTURE.md — penempatan kode dan pola arsitektur.
- docs/IMPORT_SISWA.md — import siswa melalui Excel.
- docs/CUSTOM_BRANDING.md — konfigurasi identitas sekolah.

### Engineering dan quality

- docs/PHASE-10-SECURITY.md — security hardening.
- docs/SECURITY_CHECK_RESULT.md — hasil security verification.
- docs/MANUAL_TEST_RESULT.md — hasil pengujian manual.
- docs/COMMERCIAL_READY_CHECKLIST.md — checklist kesiapan produk.
- docs/FRONTEND_CONTRAST_CHECKLIST.md — checklist contrast/accessibility.

Dokumen phase, audit, roadmap, dan TODO lainnya tersedia di folder docs/.

## Prinsip kontribusi

- Schema diubah melalui migration.
- Business logic reusable ditempatkan di service.
- Authorization ditegakkan di server, bukan hanya di UI.
- Secret dan data pribadi tidak masuk repository.
- Perubahan feature/schema/role/setup harus memperbarui dokumentasi terkait.

## License

MIT. Lihat LICENSE.

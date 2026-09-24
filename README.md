# LMS Sekolah

Learning Management System **single-school** untuk mengelola pengguna, kelas, siswa, guru, mata pelajaran, materi, tugas, pengumpulan, nilai, absensi, komunikasi, kalender, laporan, dan branding sekolah dalam satu aplikasi.

[![CI](https://github.com/ilhamrizqiawan21/lms_sekolah/actions/workflows/ci.yml/badge.svg)](https://github.com/ilhamrizqiawan21/lms_sekolah/actions/workflows/ci.yml)
![License](https://img.shields.io/badge/license-proprietary-red)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb4)
![Laravel](https://img.shields.io/badge/Laravel-13-ff2d20)

> **Scope:** satu instalasi mewakili satu sekolah. Multi-tenant SaaS, billing, dan subscription belum termasuk scope saat ini — lihat [docs/PRD.md](docs/PRD.md) untuk detail scope dan roadmap.

> **Lisensi:** proprietary — hak cipta penuh ada pada pemilik. Software ini **tidak** open-source; penggunaan, penyalinan, atau distribusi memerlukan izin tertulis. Lihat [LICENSE](LICENSE).

---

## Daftar isi

- [Fitur utama](#fitur-utama)
- [Tech stack](#tech-stack)
- [Quick start](#quick-start)
- [Akun demo](#akun-demo)
- [Development dan testing](#development-dan-testing)
- [Dokumentasi](#dokumentasi)
- [Prinsip kontribusi](#prinsip-kontribusi)
- [Lisensi](#lisensi)

## Fitur utama

| Modul | Cakupan |
|---|---|
| **Akses & keamanan** | Role-based access (Admin, Kepala Sekolah, Guru, Siswa), authorization server-side, rate limiting, blocked IP, security headers, dan audit logging. |
| **Master akademik** | Tahun ajaran, semester, kelas, siswa, mata pelajaran, guru-mapel, kelas-mapel, wali kelas, dan jadwal mengajar. |
| **Pembelajaran** | Materi, tugas dengan tenggat, submission teks/multi-file, penilaian, dan catatan perbaikan. |
| **Evaluasi** | Absensi (tatap muka & daring), nilai akhir, sikap spiritual/sosial, progress siswa, rekap, dan export. |
| **Komunikasi** | Chat per kelas-mapel, pengumuman bertarget, notifikasi, kalender akademik, dan log persiapan pesan WhatsApp. |
| **Branding** | Identitas sekolah, logo, favicon, warna tema, visi/misi, kontak, dan data legal — dapat dikonfigurasi tanpa mengubah kode. |
| **Pelaporan** | Export PDF dan Excel untuk absensi, nilai, tugas, sikap, wali kelas, dan performa guru. |

Detail fungsional lengkap per role ada di [docs/PRD.md](docs/PRD.md).

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

## Quick start

### Dengan Lerd (direkomendasikan)

    git clone https://github.com/ilhamrizqiawan21/lms_sekolah.git
    cd lms_sekolah
    lerd site:list
    lerd env:setup
    lerd setup

Untuk development, gunakan worker Vite Lerd atau jalankan `npm install` dan `npm run dev`. Domain checkout ini saat ini adalah `https://lms_sekolah.test`; selalu verifikasi dengan `lerd site:list`.

### Instalasi manual (ringkas)

    composer install
    npm install
    cp .env.example .env
    php artisan key:generate

Konfigurasikan MySQL/MariaDB di `.env`, lalu pilih salah satu jalur seed:

    # Demo development/testing
    php artisan migrate --seed

    # Instalasi kosong dengan admin dari DEFAULT_ADMIN_*
    php artisan migrate --seed --seeder=EmptyProductSeeder

Lanjutkan dengan:

    php artisan storage:link
    npm run build
    php artisan serve

Panduan lengkap termasuk checklist production ada di **[docs/INSTALLATION.md](docs/INSTALLATION.md)**.

## Akun demo

Seeder demo memakai password `password` untuk development/testing **saja**:

| Role | Email |
|---|---|
| Admin | admin@demo.test |
| Guru | guru@demo.test |
| Siswa | siswa@demo.test |
| Kepala Sekolah | kepsek@demo.test |

Jangan deploy akun atau password demo ke production. Untuk instalasi kosong, isi `DEFAULT_ADMIN_USERNAME`, `DEFAULT_ADMIN_EMAIL`, `DEFAULT_ADMIN_PASSWORD` (minimal 12 karakter), dan `DEFAULT_ADMIN_NAME` di `.env`.

## Development dan testing

    composer lint
    composer test
    npm run typecheck
    npm run build

Test Laravel memakai SQLite `:memory:` yang diatur di `phpunit.xml`, jadi tidak menyentuh database development. Untuk UI, gunakan Playwright pada domain Lerd yang benar dan credential yang memang tersedia — jangan seed/reset database aplikasi untuk kebutuhan pengecekan browser.

## Dokumentasi

### Produk dan data

- [docs/PRD.md](docs/PRD.md) — tujuan, persona, scope, modul, aturan domain, dan acceptance baseline.
- [docs/ERD.md](docs/ERD.md) — ERD Mermaid, katalog tabel, relasi, dan constraint penting.
- [AI_RULES.md](AI_RULES.md) — aturan kerja AI agent di repository.

### Setup dan arsitektur

- [docs/INSTALLATION.md](docs/INSTALLATION.md) — instalasi Lerd/manual, seeder, asset, dan production checklist.
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — penempatan kode dan pola arsitektur.
- [docs/IMPORT_SISWA.md](docs/IMPORT_SISWA.md) — import siswa melalui Excel.
- [docs/CUSTOM_BRANDING.md](docs/CUSTOM_BRANDING.md) — konfigurasi identitas sekolah.

### Engineering dan quality

- [docs/PHASE-10-SECURITY.md](docs/PHASE-10-SECURITY.md) — security hardening.
- [docs/SECURITY_CHECK_RESULT.md](docs/SECURITY_CHECK_RESULT.md) — hasil security verification.
- [docs/MANUAL_TEST_RESULT.md](docs/MANUAL_TEST_RESULT.md) — hasil pengujian manual.
- [docs/COMMERCIAL_READY_CHECKLIST.md](docs/COMMERCIAL_READY_CHECKLIST.md) — checklist kesiapan produk.
- [docs/FRONTEND_CONTRAST_CHECKLIST.md](docs/FRONTEND_CONTRAST_CHECKLIST.md) — checklist contrast/accessibility.

Dokumen phase, audit, roadmap, dan TODO lainnya tersedia di folder `docs/`.

## Prinsip kontribusi

- Schema diubah melalui migration — tidak ada perubahan skema manual di luar migration.
- Business logic yang reusable ditempatkan di service (`app/Services/`), bukan di controller.
- Authorization ditegakkan di server (policy/gate/middleware), bukan hanya disembunyikan di UI.
- Secret dan data pribadi tidak boleh masuk repository.
- Perubahan feature/schema/role/setup wajib memperbarui dokumentasi terkait pada PR yang sama.

## Lisensi

Proprietary — All Rights Reserved. Software ini **bukan** open-source. Tidak ada hak yang diberikan untuk menyalin, memodifikasi, mendistribusikan, atau menggunakan software ini di luar izin tertulis dari pemilik hak cipta. Lihat [LICENSE](LICENSE) untuk teks lengkap, atau hubungi pemilik untuk pertanyaan lisensi/komersial.

# Instalasi LMS Sekolah

Panduan ini mencakup instalasi lokal dengan Lerd, instalasi manual, pilihan data awal (demo/kosong), konfigurasi awal setelah login, dan checklist production.

> Software ini berlisensi **proprietary** (lihat [LICENSE](../LICENSE)). Panduan ini ditujukan untuk pemilik lisensi yang memasang aplikasi di server/lingkungan sendiri, bukan untuk redistribusi kode ke pihak lain.

## Daftar isi

1. [Prasyarat](#1-prasyarat)
2. [Cara yang direkomendasikan: Lerd](#2-cara-yang-direkomendasikan-lerd)
3. [Instalasi manual](#3-instalasi-manual)
4. [Pilihan data awal](#4-pilihan-data-awal)
5. [Asset, storage, dan menjalankan aplikasi](#5-asset-storage-dan-menjalankan-aplikasi)
6. [Konfigurasi awal setelah login](#6-konfigurasi-awal-setelah-login)
7. [Verifikasi lokal](#7-verifikasi-lokal)
8. [Checklist production](#8-checklist-production)
9. [Troubleshooting singkat](#9-troubleshooting-singkat)

## 1. Prasyarat

| Komponen | Versi |
|---|---|
| PHP | 8.3+; konfigurasi repository Lerd saat ini memakai 8.5 |
| Laravel | 13 |
| Composer | 2+ |
| Node.js | 20 LTS atau 22 LTS; repository saat ini mematok 22 |
| Package manager | npm (`package-lock.json`) |
| Database | MySQL 8+ atau MariaDB 10.6+ |
| PHP extensions | bcmath, ctype, curl, dom, fileinfo, filter, gd, hash, json, mbstring, openssl, pcre, pdo, pdo_mysql, session, tokenizer, xml, zip |

## 2. Cara yang direkomendasikan: Lerd

Project memiliki `.lerd.yaml` untuk Laravel 13, PHP 8.5, Node 22, MySQL, Redis, dan Mailpit.

    git clone <repository-url> lms_sekolah
    cd lms_sekolah
    lerd site:list
    lerd env:setup
    lerd setup

Jika checkout baru masih menggunakan SQLite atau database belum dipilih, pilih database project terlebih dahulu melalui Lerd, lalu ulangi setup environment dan framework. Gunakan `lerd site:list` untuk memastikan domain; checkout utama saat ini adalah `https://lms_sekolah.test`.

Perintah pengembangan yang umum digunakan:

    lerd shell
    composer install
    npm install
    php artisan migrate --seed
    npm run build

Lerd juga menyediakan worker Vite. Gunakan worker yang terdaftar untuk HMR, atau jalankan `npm run dev` sesuai kebutuhan project.

## 3. Instalasi manual

### 3.1 Clone dan dependency

    git clone <repository-url> lms_sekolah
    cd lms_sekolah
    composer install
    npm install

Untuk production:

    composer install --no-dev --optimize-autoloader

### 3.2 Environment

    cp .env.example .env
    php artisan key:generate

Atur minimal nilai berikut:

    APP_NAME="LMS Sekolah"
    APP_ENV=local
    APP_DEBUG=true
    APP_URL=http://127.0.0.1:8000
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=lms_school
    DB_USERNAME=lms_app
    DB_PASSWORD=<password-kuat>

Jangan commit `.env`. Untuk production, gunakan `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, dan credential database dengan privilege minimal.

### 3.3 Database

Buat database dan user aplikasi, misalnya:

    CREATE DATABASE lms_school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER 'lms_app'@'localhost' IDENTIFIED BY '<password-kuat>';
    GRANT ALL PRIVILEGES ON lms_school.* TO 'lms_app'@'localhost';
    FLUSH PRIVILEGES;

## 4. Pilihan data awal

Pilih **salah satu** jalur berikut, sesuai tujuan instalasi.

### 4.1 Demo untuk development/testing

    php artisan migrate --seed

`DatabaseSeeder` membuat role, branding demo, akun demo, data akademik (kelas, mapel, jadwal mengajar), dan data LMS (materi, tugas, absensi, nilai, wali kelas, dll). Password semua akun demo adalah `password`; **jangan** gunakan data ini pada production.

| Role | Email |
|---|---|
| Admin | admin@demo.test |
| Guru | guru@demo.test |
| Siswa | siswa@demo.test |
| Kepala Sekolah | kepsek@demo.test |

### 4.2 Instalasi kosong untuk sekolah (production)

Isi nilai rahasia terlebih dahulu di `.env`:

    DEFAULT_ADMIN_USERNAME=admin
    DEFAULT_ADMIN_EMAIL=admin@sekolah.example
    DEFAULT_ADMIN_PASSWORD=<minimal-12-karakter>
    DEFAULT_ADMIN_NAME="Administrator"

Lalu jalankan:

    php artisan migrate --seed --seeder=EmptyProductSeeder

Seeder ini membuat role, branding awal, admin, tahun ajaran aktif, semester aktif, dan konfigurasi akademik dasar **tanpa** data demo. Jangan memakai `migrate:fresh` pada database yang berisi data penting.

## 5. Asset, storage, dan menjalankan aplikasi

    php artisan storage:link
    npm run build
    php artisan serve

Buka `http://127.0.0.1:8000` atau `APP_URL`. Untuk hot reload saat development:

    npm run dev

Pastikan `storage/` dan `bootstrap/cache/` writable oleh proses PHP. Hasil build production harus menghasilkan `public/build/manifest.json`.

## 6. Konfigurasi awal setelah login

1. Login menggunakan akun admin.
2. Buka **Pengaturan** dan isi identitas sekolah, tahun ajaran, semester, kontak, logo, dan favicon.
3. Buat atau import data kelas, siswa, mata pelajaran, guru, dan kelas-mapel (lihat [docs/IMPORT_SISWA.md](IMPORT_SISWA.md) untuk import Excel).
4. Verifikasi dashboard, permission tiap role, export PDF/Excel, serta link file upload.
5. Ganti password awal dan hapus/nonaktifkan akun demo bila pernah digunakan.

## 7. Verifikasi lokal

    composer validate
    composer lint
    composer test
    npm run typecheck
    npm run build

Test Laravel dikonfigurasi memakai SQLite `:memory:` melalui `phpunit.xml`, sehingga tidak menggunakan database aplikasi lokal. Jangan mengganti konfigurasi test menjadi database development.

## 8. Checklist production

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL` HTTPS.
- [ ] `APP_KEY` baru dan seluruh secret tidak berada di repository.
- [ ] User database khusus dengan privilege minimal; jangan memakai `root`.
- [ ] `composer install --no-dev --optimize-autoloader` selesai.
- [ ] `npm install` dan `npm run build` selesai; manifest ter-deploy.
- [ ] `storage:link` sudah dibuat dan directory terkait writable.
- [ ] Admin dibuat lewat `EmptyProductSeeder` dengan password minimal 12 karakter.
- [ ] Backup database, rotasi log, HTTPS, dan monitoring disiapkan.
- [ ] Queue/scheduler diaktifkan sesuai kebutuhan deployment.
- [ ] `php artisan optimize` dijalankan setelah konfigurasi final.
- [ ] Lisensi/hak pakai instalasi ini sudah sesuai perjanjian dengan pemilik software (lihat [LICENSE](../LICENSE)).

## 9. Troubleshooting singkat

| Gejala | Yang perlu dicek |
|---|---|
| Asset tidak muncul | Jalankan `npm run build` dan cek `public/build/manifest.json`. |
| Database gagal tersambung | Cek host, port, database, user, password, dan service MySQL/MariaDB berjalan. |
| Upload gagal | Cek `php artisan storage:link` serta permission `storage/`. |
| Login instalasi kosong gagal | Pastikan `DEFAULT_ADMIN_PASSWORD` terisi dan minimal 12 karakter. |
| Domain Lerd tidak terbuka | Jalankan `lerd site:list` dan `lerd diag:status`; gunakan domain yang benar. |

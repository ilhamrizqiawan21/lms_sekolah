# AI Rules — LMS Sekolah

Panduan ini berlaku untuk AI agent yang membaca atau mengubah repository ini.

## 1. Konteks project

- Project: single-school LMS berbasis Laravel 13, Vue 3, Inertia.js, Blade, Vite, dan MySQL/MariaDB.
- Runtime lokal standar project: Lerd, PHP 8.5, Node 22, domain `https://lms_sekolah.test`.
- Sumber kebenaran schema: migration. Sumber kebenaran perilaku: route, request, controller/service, model, middleware/policy, dan test.

## 2. Aturan sebelum mengubah kode

1. Baca `AGENTS.md` dan dokumen yang relevan.
2. Periksa `git status`; jangan menimpa perubahan pengguna yang sudah ada.
3. Gunakan Serena untuk memahami symbol, reference, controller, service, model, route, dan frontend dependency sebelum mengedit application code.
4. Temukan site dengan Lerd (`site list`) dan gunakan path repository secara eksplisit untuk command PHP/Composer/Artisan.
5. Untuk pertanyaan API/framework/library yang bisa berubah, gunakan Context7; jangan mengandalkan ingatan model.
6. Untuk perubahan lintas modul atau schema, buat rencana singkat dan tentukan risiko authorization, data integrity, upload, dan backward compatibility.

## 3. Aturan implementasi

- Perubahan database wajib berupa migration yang reversible bila memungkinkan; jangan mengedit database manual untuk menyelesaikan feature.
- Controller mengorkestrasi request/response. Pindahkan business logic yang panjang, reusable, atau lintas model ke `app/Services`.
- Validasi input melalui Form Request yang sesuai role/fitur.
- Authorization wajib ditegakkan di server melalui middleware, gate/policy, atau ownership check; menyembunyikan tombol UI tidak cukup.
- Pertahankan scope single-school. Jangan memperkenalkan tenant ID, billing, atau SaaS abstraction tanpa permintaan eksplisit.
- Pertahankan kompatibilitas dengan role `admin`, `guru`, `siswa`, dan `kepala_sekolah`.
- Upload harus memvalidasi tipe/ukuran, memakai storage yang benar, dan tidak mengekspos path internal.
- Jangan commit `.env`, secret, token, dump database, password production, atau data siswa nyata.
- Jangan memasukkan credential demo ke production flow. Seeder demo hanya untuk development/testing.
- Ikuti pola Vue/Inertia dan komponen yang sudah ada; jangan menambahkan framework frontend baru tanpa keputusan arsitektur.
- Update dokumentasi (PRD/ERD/README/INSTALLATION) jika perubahan mengubah feature, schema, setup, role, atau command.

## 4. Testing dan verifikasi

- Sebelum test database-changing, periksa `phpunit.xml`; test suite saat ini memakai SQLite `:memory:` dan tidak boleh menyentuh database development.
- Temukan binary Composer dengan Lerd `exec vendor_bins`, lalu jalankan tool melalui `exec vendor_run` bila tersedia.
- Minimal untuk perubahan PHP: lint yang relevan dan test yang relevan.
- Untuk perubahan frontend: `npm run typecheck` dan `npm run build` bila dependency/build tersedia.
- Untuk alur UI: gunakan Playwright terhadap domain Lerd aktual; jangan membuat atau menebak credential baru.
- Jangan menjalankan `migrate:fresh`, seed, atau reset pada database development tanpa kebutuhan eksplisit dan konfirmasi scope.
- Laporkan command yang benar-benar dijalankan, hasilnya, dan keterbatasan yang tersisa.

## 5. Gaya perubahan

- Perubahan harus scoped, mudah direview, dan tidak melakukan refactor tak terkait.
- Pertahankan istilah domain yang sudah dipakai kode: `kelas_mapel`, `tahun_ajaran`, `pengumpulan_tugas`, `wali_kelas`, dan `kepala_sekolah`.
- Jangan menghapus atau mengganti file pengguna secara destruktif.
- Gunakan `apply_patch` untuk edit file; gunakan command read-only untuk inspeksi.
- Jika requirement ambigu dan keputusan akan mengubah scope produk atau data, berhenti dan minta keputusan pengguna.

## 6. Checklist handoff

- [ ] Perubahan dan file yang disentuh disebutkan.
- [ ] Test/lint/build yang dijalankan dicantumkan dengan hasil aktual.
- [ ] Migration, seeder, route, policy, dan dokumentasi telah diselaraskan bila relevan.
- [ ] Tidak ada secret atau data pribadi yang ikut berubah.
- [ ] Risiko, asumsi, dan pekerjaan lanjutan disebutkan secara singkat.

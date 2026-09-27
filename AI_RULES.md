# AI Rules — LMS Sekolah

Panduan ini berlaku untuk AI agent yang membaca atau mengubah repository ini. Baca `AGENTS.md` terlebih dahulu — file itu adalah sumber tunggal untuk environment, kebijakan dependency, testing/coverage, dan static analysis. File ini hanya menambahkan aturan yang belum dibahas di `AGENTS.md`.

## 1. Konteks project

- Project: single-school LMS berbasis Laravel 13, Vue 3, Inertia.js, Blade, Vite, dan MySQL/MariaDB.
- Runtime lokal: PHP 8.3+, Node (lihat `.node-version`), langsung via Composer/Artisan/npm — tidak ada wrapper environment tambahan.
- Sumber kebenaran schema: migration. Sumber kebenaran perilaku: route, request, controller/service, model, middleware/policy, dan test.

## 2. Aturan sebelum mengubah kode

1. Baca `AGENTS.md` dan dokumen yang relevan.
2. Periksa `git status`; jangan menimpa perubahan pengguna yang sudah ada.
3. Gunakan Serena untuk memahami symbol, reference, controller, service, model, route, dan frontend dependency sebelum mengedit application code.
4. Untuk pertanyaan API/framework/library yang bisa berubah, gunakan Context7; jangan mengandalkan ingatan model.
5. Untuk perubahan lintas modul atau schema, buat rencana singkat dan tentukan risiko authorization, data integrity, upload, dan backward compatibility.
6. Sebelum menambah dependency baru, ikuti kebijakan dependency di `AGENTS.md` (tidak boleh package deprecated/abandoned tanpa justifikasi tertulis).

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

## 4. Testing, coverage, dan static analysis

Lihat `AGENTS.md` untuk command testing, requirement coverage untuk logic baru/berubah, dan status static analysis (belum ada PHPStan/Larastan terpasang). Tambahan khusus repo ini:

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
- [ ] Test/lint/build yang dijalankan dicantumkan dengan hasil aktual, termasuk cakupan test untuk logic baru/berubah.
- [ ] Tidak ada dependency baru yang deprecated/abandoned tanpa justifikasi.
- [ ] Migration, seeder, route, policy, dan dokumentasi telah diselaraskan bila relevan.
- [ ] Tidak ada secret atau data pribadi yang ikut berubah.
- [ ] Risiko, asumsi, dan pekerjaan lanjutan disebutkan secara singkat.

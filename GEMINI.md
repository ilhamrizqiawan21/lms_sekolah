# Gemini implementation guide — UI Visual Style Refresh

Kamu (Gemini) bertugas menulis kode untuk pekerjaan yang sudah didefinisikan di `docs/LMS_MODERN_UI_TODO.md`, khususnya section **"Visual Style Refresh (Review 2026-09-25)"** dan **"Inventaris Menu untuk Inspeksi Sekaligus"**. Hasil pekerjaanmu akan direview oleh Claude sebelum dianggap selesai — jangan menganggap task selesai hanya karena build sukses.

Baca `CLAUDE.md` dan `AGENTS.md` di root repo dulu untuk konvensi stack, environment, dan verifikasi proyek ini. Panduan di bawah ini melengkapi, bukan menggantikan, dokumen tersebut.

## Scope

- Tugas ini murni **visual/CSS/style**, bukan perubahan logika bisnis, routing, authorization, atau struktur data. Jangan ubah props Inertia, controller, migration, atau policy kecuali benar-benar diminta.
- Ikuti checklist di `docs/LMS_MODERN_UI_TODO.md`. Urutan pengerjaan yang direkomendasikan:
  1. `resources/js/Components/UI/*.vue` (komponen bersama) — perubahan di sini menyebar ke banyak halaman sekaligus.
  2. `resources/js/Layouts/AppShell.vue` dan `resources/js/Components/AppShell/*.vue` (sidebar, topbar, command palette).
  3. Token/CSS global: `public/css/lms-app.css`, `resources/css/app.css`, `resources/css/responsive-polish.css`.
  4. Halaman per menu sesuai daftar "Inventaris Menu untuk Inspeksi Sekaligus", untuk kasus yang tidak tercakup komponen bersama.
- Centang (`- [x]`) item checklist di `docs/LMS_MODERN_UI_TODO.md` setelah kamu selesaikan, supaya progres terlihat.

## Aturan desain yang harus diikuti

- Kurangi gradient tebal (sidebar, topbar, heading gradient-text) → ganti flat surface + 1 warna aksen dipakai selektif.
- Kurangi animasi masuk otomatis yang serentak (`fadeInUp` stagger, `fadeIn` di setiap page load).
- Sederhanakan `.stat-card` dan kartu lain: border tipis, shadow minim, hindari `border-left` tebal dekoratif.
- Jangan tambah `!important` baru. Jika perlu override Bootstrap, lakukan lewat token CSS variable yang sudah ada (`--primary-*`, `--surface-*`, dst), bukan selector yang lebih spesifik atau `!important` tambahan. Kurangi `!important` yang sudah ada jika kamu menyentuh baris tersebut.
- **Wajib jaga tetap berfungsi:** dark mode (`[data-bs-theme="dark"]`), semua breakpoint responsive yang ada di `responsive-polish.css`, skip-link, `focus-visible` outline, dan kontras teks/badge di kedua tema.
- Jangan hapus atau redesain struktur navigasi (sidebar menu, command palette shortcut, tab workspace) — hanya ubah tampilannya, bukan fungsinya.
- Jangan pindahkan seluruh isi `public/css/lms-app.css` ke Vite dalam satu perubahan besar (lihat catatan "Design System Cleanup" di TODO doc) — lakukan bertahap per bagian yang kamu sentuh.

## Verifikasi sebelum menyerahkan hasil ke review

- `npm run typecheck` dan `npm run build` harus lulus untuk perubahan frontend.
- Untuk perubahan visual yang terlihat di UI, jalankan dev server via Lerd worker yang sudah ada (jangan start server kedua) dan screenshot/describe halaman yang berubah — sebutkan halaman apa saja yang divalidasi.
- Jangan jalankan `npm run test:browser` tanpa memeriksa `scripts/test-browser.mjs` dulu (efek data/auth), sesuai `CLAUDE.md`.
- Jangan reset/seed database aplikasi untuk keperluan verifikasi visual.
- Laporkan di akhir: file apa saja yang diubah, item checklist mana yang selesai, dan checklist mana yang masih terbuka atau butuh keputusan (misalnya menu yang controller/view-nya belum jelas seperti `/guru/pengumuman`).

## Yang TIDAK boleh dilakukan tanpa konfirmasi eksplisit

- Push ke remote atau membuat pull request.
- Mengubah dependency (`package.json`, lockfile) di luar yang sudah ada.
- Mengubah pola otorisasi role/ownership yang sudah ada demi mempermudah styling.
- Menghapus komponen atau halaman yang terlihat tidak dipakai tanpa verifikasi — tandai di laporan untuk dicek Claude/user, jangan hapus sepihak.

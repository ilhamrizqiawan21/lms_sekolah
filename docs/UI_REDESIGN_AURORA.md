# UI Eksperimen "Aurora" (STATUS: DIGANTIKAN)

> [!NOTE]
> **Status: DIGANTIKAN (2026-10-09)** oleh arah desain **"Ruang Kelas Digital"** (lihat `docs/REDESIGN_RUANG_KELAS_TODO.md`).
> Berkas `resources/css/modern.css` telah dihapus dan seluruh override Aurora di Login/Error page telah dibersihkan. Dokumen ini dipertahankan sebagai catatan riwayat eksperimen.

Dokumen ini mencatat eksperimen tampilan "Aurora" yang sempat dicoba di atas redesain Calm Academic sebelum akhirnya digantikan oleh arah Ruang Kelas Digital demi performa, keterbacaan, dan kesederhanaan.

## 1. Konsep

| Aspek | Keputusan |
|---|---|
| Latar | Mesh-gradien lembut (tiga radial) yang mengikuti warna tema pilihan admin, di terang dan gelap. |
| Shell | Sidebar dan topbar **melayang** (jarak 14 px dari tepi, sudut 1,4-1,75 rem) berefek kaca (`backdrop-filter: blur`). Item aktif berupa pil gradien dengan cahaya. Navigasi bawah mobile berupa kapsul kaca. |
| Hero | Blok berwarna penuh dengan gradien per peran (admin condong ke violet, siswa ke biru, guru ke cyan), dengan ikon di dalam kotak kaca dan tombol aksi kaca. |
| Kartu | Sudut 1,25 rem, bayangan berlapis, tanpa bayangan keras. Kartu metrik: angka besar 1,85 rem, chip ikon membulat. |
| Kontrol | Tombol utama gradien dengan cahaya dan efek naik 1 px saat hover. Input berlatar terisi lembut, ring fokus berpendar 4 px, tinggi 44 px. |
| Tabel | Kepala kolom huruf kapital kecil dengan jarak huruf, hover bertint tema, kepala kolom kategori bertint tipis. |
| Overlay | Dropdown, modal, dan command palette berkaca. |
| Gerak | Animasi masuk halaman 0,45 detik (bertahap per blok), dimatikan oleh `prefers-reduced-motion`. |
| Login | Dua panel: kartu gradien penuh di kiri (cincin dekoratif, Papan Informasi kaca), kartu form kaca di kanan. Tetap dikunci ke tema terang. |
| Halaman error | Satu template (`errors/status.blade.php`) dengan mesh-gradien, kartu kaca, angka kode bergradien, mode gelap mengikuti `lms.color-mode` atau preferensi sistem. |

## 2. Peta berkas

| Berkas | Isi |
|---|---|
| `resources/css/modern.css` (baru, ±510 baris) | Seluruh lapisan tampilan. Dimuat paling akhir lewat `import '../css/modern.css'` di `resources/js/app.ts` dan `resources/js/inertia.ts`. Menimpa token (`:root` dan `[data-bs-theme="dark"]`) lalu aturan komponen. Bagian akhir berisi polesan mobile, tablet, dan tombol outline. |
| `resources/js/Pages/Auth/Login.vue` | Blok `AURORA` di `<style scoped>` (desktop) dan `Aurora, mobile` (di bawah 900 px). Props, perilaku, dan aksesibilitas tidak berubah. |
| `resources/views/errors/status.blade.php` | CSS inline baru; `errors/403.blade.php` kini memakai template ini (sebelumnya halaman terpisah dengan tombol tak terlihat). |
| `resources/js/Components/AppShell/Topbar.vue` | Menghapus teks nama pengguna yang duplikat dengan tombol akun. |
| `resources/js/Pages/Guru/Dashboard.vue` | Grafik Tren Kehadiran dan Tren Pengumpulan Tugas menjadi grafik batang; ikon judul disesuaikan. |
| `resources/js/Pages/Siswa/Ujian/Kerjakan.vue` | Perbaikan timer (lihat bagian 4) dan kelas penanda `cbt-shell`. |

Token baru yang dipakai (di `modern.css`): `--glass-bg`, `--glass-border`, `--aurora-a/b/c`, `--brand-gradient`, `--brand-glow`, `--float-gap`. Token Calm Academic yang ditimpa: `--surface-*`, `--border-soft`, `--card-radius`, `--radius-control`, `--control-height`, `--shadow-card`, `--shadow-float`, `--sidebar-width`.

## 3. Cara mencabut atau mengubah

- **Kembali ke Calm Academic penuh:** hapus dua baris `import '../css/modern.css'` di `app.ts` dan `inertia.ts` (atau pindah ke branch `redesign_calm_academic`). Perubahan lain di bawah (bagian 4 dan 5) tidak bergantung pada `modern.css` dan tetap berguna.
- **Mengubah intensitas:** mayoritas keputusan ada di blok token paling atas `modern.css`: `--card-radius`, `--glass-bg`, `--float-gap`, `--aurora-*`, dan `--brand-gradient`.
- **Mematikan satu komponen saja:** setiap bagian `modern.css` diberi judul komentar (`── Kartu ──`, `── Hero berwarna penuh ──`, dan seterusnya), jadi bisa dihapus per bagian.

## 4. Perubahan di luar CSS

Ditemukan dan diperbaiki selama eksperimen. Bagian ini tidak bergantung pada tampilan Aurora.

1. **Bug timer CBT** (`Siswa/Ujian/Kerjakan.vue`): `sisa_detik` dari server berupa pecahan sehingga timer menampilkan "59:55.35775899999999" dan melebarkan halaman di mobile. Sekarang dibulatkan ke bawah. Ada di commit `799a967`; bisa di-cherry-pick sendiri ke `main` terlepas dari arah desain.
2. **Halaman 403:** tombol "Kembali" tidak terlihat karena halaman hanya memuat `app.css` tanpa variabel warna tema. Kini memakai template `errors/status`.
3. **Berkas Blade lama dihapus** (`2df8242`): `guru/rekap-nilai.blade.php`, `guru/rekap-sikap.blade.php`, `layouts/app.blade.php`, `layouts/sidebar.blade.php`. Penyebab aman dihapus: `AppServiceProvider` mengikat `NilaiController` dan `SikapController` ke `NilaiRekapController` dan `SikapRekapController` (versi Inertia), sehingga view itu tidak pernah dirender. Method `rekap()` di kedua controller induk (yang merujuk view tersebut) ikut dihapus bersama import yang menjadi tak terpakai.
4. **Sidebar mobile tertutup** menyisakan strip 8 px di tepi kiri (efek `left: 8px` pada versi melayang); kini digeser penuh.
5. **Navigasi bawah mobile** disembunyikan di layar ujian agar tidak menutupi tombol Sebelumnya/Selanjutnya.

Polesan akhir (CSS di `modern.css`, bagian "Polesan akhir"):

- Kepala kolom kategori di tabel nilai (`bg-soft-*` pada `<th>`) memakai tint tipis, bukan pastel penuh.
- Tombol outline status (Edit, Hapus, Excel, PDF) lembut dan konsisten; sebelumnya garis kuning/merah tajam.
- Kartu statistik Kepsek dua kolom di mobile.
- Kolom aksi tabel selebar tombolnya dengan lebar minimum, sehingga tidak terpotong di tabel pengguna.

## 5. Catatan teknis penting

- Bootstrap 5.3 mewarnai `<a>` lewat `--bs-link-color-rgb`, bukan `--bs-link-color`. Tautan karenanya tetap biru bawaan sampai `components.css` menambah `a { color: var(--bs-link-color) }`. Itu temuan dari fase Calm Academic, bukan Aurora, tetapi baru terlihat saat memeriksa browser.
- Elemen yang memakai `display: contents` (panel merek login di mobile) tetap menggambar `::before/::after` relatif ke viewport. Lingkaran dekoratif karenanya disembunyikan di mobile supaya halaman tidak melebar.
- Menaikkan spesifisitas `.card-header` pernah menimpa padding responsif di `app.css` (kartu jadi 5 px lebih tinggi di mobile). Aturan Aurora memakai selektor yang sama atau lebih rendah dari aturan dasar, dan blok mobile ditaruh di akhir berkas.
- Aturan tombol outline status (`btn-outline-warning/danger/success/info`) memakai variabel `--bs-btn-*` dan `--tone-text`, bukan `!important`.
- Tabel di dalam kartu tanpa padding (`.card-body.p-0`) kehilangan bingkai sendiri supaya tidak berlapis dua.

## 6. Verifikasi

Diperiksa langsung di browser (Chromium via Playwright, server PHP dan SQLite terisolasi dari `tests/Browser/seed.php`, bukan database pengembangan):

- Terang dan gelap, desktop 1366-1440 px dan mobile 390 px; tablet 820 px untuk dashboard dan tabel.
- 58 rute (semua rute di `tests/Browser/migration.spec.ts`) dibuka di 390 px dan diukur overflow horizontal: tidak ada halaman yang melebar. Hanya tab workspace yang memang bisa digeser.
- Halaman yang ditinjau visual: login, dashboard admin/guru/siswa/kepsek, pengguna, nilai, kalender, chat, ujian (daftar dan pengerjaan CBT, dialog konfirmasi, mobile), progress, statistik, tugas, kelas-mapel, absensi, halaman error.
- Data ujian untuk meninjau layar CBT dan data multi-bulan untuk grafik ditambahkan sementara ke seed uji lalu dikembalikan; tidak ada perubahan permanen pada `seed.php`.
- `php artisan test`: 138 lolos. `npm run typecheck` dan `npm run build` hijau. `npm run test:browser`: 23 lolos, 2 gagal (lihat bagian 7).

Cara menjalankan Playwright di WSL tanpa sudo: unduh `libnspr4`, `libnss3`, `libasound2t64` lewat `apt-get download`, ekstrak dengan `dpkg-deb -x`, lalu set `LD_LIBRARY_PATH` ke folder `usr/lib/x86_64-linux-gnu` hasil ekstraksi sebelum `npm run test:browser`.

## 7. Sisa dan keputusan terbuka

- **Dua tes browser gagal** sejak sebelum Fase 3 dan tidak disebabkan eksperimen ini: `grade paste and attendance save` dan `searchable select opens upward at the last option` (keduanya mengharapkan `kelas_id` bernilai 1 tetapi mendapat 2).
- **Validasi filter rekap guru:** versi Inertia memakai `Request` biasa; `RekapNilaiRequest` dan `RekapSikapRequest` kini tidak dipakai. Putuskan: pasang validasinya ke versi Inertia (dengan tes) atau hapus kelasnya.
- **Navigasi bawah mobile** masih menutupi sedikit bagian bawah konten saat di-scroll (konten sudah punya padding bawah sehingga tetap bisa dibaca dengan scroll). Opsi: sembunyikan saat scroll turun.
- **Tema pilihan admin** selain hijau (biru-azure, biru-aqua, indigo, marun) diuji manual oleh pemilik proyek, bukan oleh pengujian otomatis. Tema aqua tetap menyisakan catatan kontras dari fase Calm Academic (`#0891b2` hanya 3,68:1 dengan teks putih); gradien tombol dan hero memakai warna yang sama.
- **Sisa Fase 4 Calm Academic** yang sengaja ditunda (input padat di tabel, kartu CBT, badge dinamis) tidak ikut berubah di eksperimen ini.
- **Keputusan arah: Aurora dipilih (2026-10-04).** Langkah berikutnya: push dan merge `experimental/ui-modern` (yang sudah memuat seluruh pekerjaan Calm Academic fase 0-5). Belum ada branch yang di-push.

# UI Redesign: "Calm Academic"

Dokumen ini berisi hasil audit style UI, spesifikasi desain tema **Calm Academic**, serta TODO dan checklist pengerjaannya. Status (2026-10-04): Fase 0 (baseline), Fase 1 (fondasi token), dan Fase 2 (komponen bersama) selesai; login sudah dikerjakan sebagian oleh Gemini (belum memakai token bersama); Fase 3 (shell) selesai; Fase 4 selesai (sisa yang sengaja dibiarkan tercatat di bagian Fase 4); Fase 5 (pembersihan CSS) selesai; Fase 6 belum dimulai.

Dokumen terkait: `UI_REDESIGN_AURORA.md` (eksperimen tampilan lebih berani di branch `experimental/ui-modern`), `LMS_MODERN_UI_TODO.md` (rombakan layout/workspace sebelumnya), `FRONTEND_CONTRAST_CHECKLIST.md` (kontras warna per tema).

## 1. Baseline Audit

Diukur ulang 2026-10-04 (setelah login dikerjakan Gemini, sebelum fase implementasi). Metode: `bash scripts/ui-audit.sh` (hanya membaca file). Angka adalah jumlah **baris** yang cocok, bukan jumlah kemunculan; pakai skrip yang sama untuk audit ulang di Fase 6 agar sebanding.

Stack: Bootstrap 5.3 + CSS kustom, Vue 3 + Inertia, 88 halaman, 17 komponen UI bersama. CSS ada di `public/css/lms-app.css` (1797 baris), `resources/css/app.css` (702 baris, meng-import file `public/`), `resources/css/responsive-polish.css` (530 baris), `public/css/login-isolation.css` (kini ±20 baris), serta blok `<style>` di dalam beberapa `.vue`.

| Temuan | Baseline | Target |
|---|---|---|
| Hex di template/script `.vue` | 54 | 0 (kecuali palet pilihan tema di `Admin/Pengaturan`) |
| Hex di `<style>` dalam `.vue` | 74 (Login.vue 32, CalendarWorkspace.vue 22) | 0 |
| Hex di file CSS (di luar `tokens.css`) | 115 (`lms-app.css` 73, `app.css` 37, `responsive-polish.css` 5) | 0 |
| `!important` | 101 di CSS (`lms-app.css` 66, `app.css` 30) + 18 di `<style>` `.vue` | < 10 total (hanya override utilitas yang sah) |
| `style="…"` inline | 117 (+19 `:style`) | < 20 (hanya nilai dinamis) |
| `text-muted` (deprecated BS 5.3) | 223 (`text-body-secondary`: 0) | 0 |
| Selector override dark mode manual | 136 di CSS + 26 di `<style>` `.vue` | turun drastis, dark dihasilkan dari token |
| Card mentah vs `<Card>` | 58 vs 138 | 0 mentah |
| Badge mentah vs `<Badge>` | 46 vs 107 | 0 mentah |
| `<table>` vs `<TableWrapper>` | 63 vs 58 | semua lewat wrapper |
| `form-control`/`form-select` mentah vs komponen form | 56 vs 147 | 0 mentah |
| `button.btn` mentah vs `<Button>` | 20 vs 124 | 0 mentah |
| Teks kosong manual vs `<EmptyState>` | 88 vs 89 | seluruhnya `EmptyState` |
| `btn-primary` vs `btn-success` | 8 vs 9 | satu gaya tombol utama |
| `<th>` dengan `scope` | 31 dari 311 | semua |
| Modal tanpa Esc/fokus | 2 (`CalendarWorkspace.vue`, `Guru/Tugas/Pengumpulan.vue`) | 0 |
| `window.confirm` (pemakaian nyata) | 1 (`Admin/Pengumuman/Index.vue:98`); skrip menghitung 3 karena ikut `ConfirmDialog.vue` | 0 |
| Variasi media query berbeda | 20 (campuran `991`/`991.98`, `767`/`767.98`/`768`, `575.98`/`576`, `900`/`899.98`, `640`, `480`, `420`, `399.98`, penulisan `@media(` tanpa spasi) | satu set breakpoint |

Setelah Fase 1: hex di file CSS (di luar `tokens.css`) turun 115 menjadi 76 (`app.css` 5, `lms-app.css` 66, `responsive-polish.css` 5); variasi media query 20 menjadi 12. Metrik lain tidak berubah sampai fase berikutnya.

Koreksi angka audit awal: `<th` sebelumnya tertulis 377 karena regex ikut menghitung `<thead`; angka benar 311. Hex di `.vue` (130) kini dipecah menjadi 54 di template/script dan 74 di `<style>`. Selisih lain kecil (`text-muted` 221 menjadi 223, card mentah 60 menjadi 58) berasal dari perubahan kode dan metode hitung baris.

Temuan baru dari pengukuran ulang:
- Perubahan login Gemini menambah 32 hex dan beberapa `!important` di `<style>` `Login.vue`. Login kini punya blok CSS sendiri yang belum memakai token bersama (ditangani di Fase 3).
- `CalendarWorkspace.vue` punya 22 hex di `<style>`, tidak terlihat pada audit awal karena hanya template yang dihitung.
- Login selalu terang (disengaja). Guard tema ada di `app.blade.php`, `theme.ts`, dan `Login.vue`; jangan dihapus saat refactor token.

Sudah baik dan dipertahankan: token CSS dasar, dark mode lewat `data-bs-theme`, `prefers-reduced-motion`, `alt` pada gambar, `aria-label` pada tombol ikon, tanpa `user-scalable=no`.

## 2. Spesifikasi Desain

Prinsip: bersih, lapang, fokus pada konten. Brand hijau sekolah dipertahankan. Rombakan dilakukan **token-first di atas Bootstrap**, bukan mengganti framework.

| Aspek | Keputusan |
|---|---|
| Warna | Brand `#198754` menjadi `--brand-600` dengan skala 50-900. Netral slate dingin. Status (sukses, peringatan, bahaya, info) berupa token berpasangan latar/teks yang lolos WCAG AA. |
| Tipografi | Plus Jakarta Sans atau Inter. Skala 12/14/16/20/24/32. `tabular-nums` untuk angka tabel nilai. |
| Bentuk | Radius 8 px kontrol, 12-16 px kartu. Border tipis, bayangan hanya untuk elemen melayang (dropdown, modal). |
| Kepadatan | Dua mode token: nyaman (siswa) dan padat (guru/admin). |
| Layout | Sidebar ringan netral, item aktif berupa pil hijau lembut. Topbar bersih. Lebar konten maksimum konsisten. |
| Komponen | Satu gaya tombol, badge (varian `*-subtle` saja), kartu statistik, tabel, dan form (fokus ring hijau jelas). |
| Dark mode | Dihasilkan dari token yang sama, bukan override per kelas. |
| Gerak | Transisi 150-200 ms, hormati `prefers-reduced-motion`. |

### Desain halaman login (diperbarui 2026-10-04)

Mockup interaktif: https://claude.ai/artifact/FCaK9QL22x37bmEZCfK7zs (tab Login, Dashboard siswa, Tabel & form; toggle terang/gelap). Link ini privat milik pemilik akun.

Login dirombak dari kartu di atas latar polos menjadi **layout dua panel**:

- **Panel kiri (identitas sekolah)**: latar hijau tua flat/tenang (tanpa garis motif), bersih dan fokus. Berisi logo + nama sekolah, judul singkat dan motto, **Papan Informasi** (pengumuman publik tampil sebagai kartu kaca tipis), serta alamat dan tanggal hari ini di footer.
- **Panel kanan (form)**: eyebrow "Akses LMS", judul "Selamat datang kembali", field Username dan Password setinggi 44 px dengan ikon, tombol **tampilkan/sembunyikan password**, "Ingat saya" (checkbox kustom) dan "Lupa password?", tombol "Masuk ke LMS" 48 px, lalu catatan bantuan dengan label peran (Siswa, Guru, Admin, Kepala sekolah).
- **State**: normal, error (alert di atas form + pesan di bawah field password + border merah), dan memproses (tombol nonaktif dengan spinner). Fokus input memakai ring hijau.
- **Mobile (< 900 px)**: satu kolom dengan urutan header hijau (logo + judul), form, Papan Informasi, footer. Papan Informasi tidak dihilangkan.
- **Tema**: khusus halaman login dikunci murni tema light; seluruh aplikasi default ke light.

Catatan implementasi (Fase 3):

- Props dan perilaku `Login.vue` tidak berubah (`branding`, `loginUrl`, `publicAnnouncements`, `year`, `useForm` dengan `username`/`password`/`remember`, tautan WhatsApp "Lupa password" tetap bersyarat pada `support_contact`).
- Tombol tampilkan password butuh `aria-pressed` dan `aria-label` yang berganti; pesan error tetap terhubung lewat `aria-describedby`.
- Papan Informasi tetap dirender hanya jika `publicAnnouncements.length > 0`; saat kosong, panel kiri tetap seimbang tanpa blok tersebut.
- Gunakan `branding.logo_url` sebagai logo; ikon topi toga di mockup hanya pengganti.
- `login-isolation.css` (142 baris) dan blok style 500 baris lebih di `Login.vue` perlu dipangkas ke token bersama; dokumentasikan alasan jika ada CSS login yang sengaja tetap terisolasi.
- Teks di mockup (nama sekolah, motto, pengumuman, nama siswa) adalah contoh, bukan data nyata.

Mockup juga memuat arah dashboard siswa (sidebar ringan, metric strip satu baris, daftar tugas dengan badge status, jadwal hari ini, kartu mapel dengan progress) serta tabel nilai (header ringan, toggle kepadatan nyaman/padat, pagination), form dengan state error, badge status, dan empty state. Pakai sebagai acuan visual Fase 2 dan 4.

Keputusan (diambil di Fase 1, 2026-10-04; ubah bila tidak sesuai):

- [x] Font: **Plus Jakarta Sans** (sudah dipakai aplikasi), kini self-host lewat `@fontsource-variable/plus-jakarta-sans` (Vite), bukan CDN.
- [x] Tema pilihan admin **dipertahankan** (hijau, biru-azure, biru-aqua, indigo, marun). Token yang bergantung tema tetap di `app.blade.php`; sisanya di `tokens.css`.
- [x] Kepadatan default tabel: **nyaman**. Mode padat tersedia lewat `data-density="compact"` pada ancestor tabel (belum dipasang di halaman mana pun; Fase 2/4).

## 3. TODO dan Checklist

Aturan umum: satu commit per fase, jalankan `npm run typecheck` dan `npm run build` untuk setiap perubahan di `resources/js`. Verifikasi alur yang terlihat dengan Playwright (lihat `scripts/test-browser.mjs` dan `AGENTS.md`), memakai data terisolasi.

### Fase 0: Persiapan

- [ ] Ambil tangkapan layar baseline (terang dan gelap, mobile 390 px dan desktop 1366 px) untuk: Login, Dashboard (Admin/Guru/Siswa/Kepsek), satu tabel daftar, satu form, satu halaman Ujian.
- [ ] Putuskan keputusan terbuka di bagian 2.
- [x] Pastikan `npm run typecheck` dan `npm run build` hijau sebelum mulai (hijau 2026-10-04).
- [x] Baseline audit terukur dan tersimpan di bagian 1; skrip audit: `scripts/ui-audit.sh`.

### Fase 1: Fondasi token (selesai 2026-10-04)

- [x] Buat `resources/css/tokens.css`: netral, permukaan/teks, status berpasangan, layout, spasi, radius, bayangan, gerak, tipografi, ukuran komponen, pemetaan Bootstrap, plus tambahan baru (skala tipografi `--fs-*`, `--lh-*`, `--fw-*`, `--dur-*`, `--ease-standard`, mode kepadatan, skala brand `--primary-200/400/900`).
- [x] Petakan token ke variabel Bootstrap (`--bs-primary`, `--bs-link-color`, dan lainnya) di `tokens.css`. Catatan: `--bs-body-bg`, `--bs-border-color`, dan sejenisnya hanya didefinisikan untuk mode gelap; mode terang memakai bawaan Bootstrap (belum dipetakan, ditinjau di Fase 2).
- [x] Definisikan token dark mode di `[data-bs-theme="dark"]` sekali (dipindah dari `app.css`, 56 token identik). Override per-komponen dark (±136 selector) dibersihkan di Fase 5.
- [x] Pindahkan `public/css/lms-app.css` ke `resources/css/lms-app.css` (`git mv`); `app.css` meng-import `./tokens.css` lalu `./lms-app.css`. Blok `:root` "Modern LMS Workspace V1" di dalamnya dihapus (nilainya pindah ke `tokens.css`).
- [x] Seragamkan media query yang ekuivalen: `991px`, `767px`, `768px`, `576px`, dan penulisan `@media(` diubah ke `991.98px`/`767.98px`/`575.98px`/`@media (`. Variasi berbeda turun dari 20 menjadi 12. Sisa pengecualian yang sengaja tidak diubah (spesifik komponen, terutama login): `900px`, `899.98px`, `640px`, `480px`, `420px`, `399.98px`. Efek samping: aturan yang dulu `max-width: 768px` (satu di `lms-app.css`) dan `576px` (`AcademicTimeline.vue`, `CalendarWorkspace.vue`) tidak lagi berlaku pada lebar persis 768/576 px.
- [x] Self-host font, `font-display: swap` (bawaan paket). Link Google Fonts dihapus dari `app.blade.php` dan `layouts/app.blade.php`; tidak ada lagi permintaan ke `fonts.googleapis.com`.
- [x] Cek kontras token; hasil dan perbaikan dicatat di `FRONTEND_CONTRAST_CHECKLIST.md` (bagian "Hasil audit Fase 1").

Hasil verifikasi Fase 1:

- Ekuivalensi token: nilai efektif light (93) dan dark (56) dibandingkan sebelum/sesudah. Identik, kecuali dua perubahan disengaja (`--font-sans` memakai font self-host, `--text-muted` `#64748b` menjadi `#5b6b82` agar lolos AA) dan 27 token baru. Nilai ganda di Blade dan `lms-app.css` (mis. `--app-bg`) dipindahkan sebagai nilai efektif (yang di `lms-app.css` menang).
- `npm run typecheck` dan `npm run build` hijau. Login (`/login`) dirender ulang di Chrome: tampilan identik dengan sebelumnya.
- Hex di CSS di luar `tokens.css`: 115 menjadi 76. Hex di `tokens.css`: 66 (sumber token, sah).
- Belum diverifikasi visual: halaman setelah login (butuh akun uji). Karena nilai token dipertahankan, perubahan visual yang diharapkan hanya di tabel di bawah.

Perubahan visual yang diharapkan dari Fase 1 (periksa saat uji manual):

| Perubahan | Dampak |
|---|---|
| `--text-muted` sedikit lebih gelap | Teks sekunder di mana pun sedikit lebih kontras |
| `--surface-hover` kini terdefinisi di mode terang | 4 tempat yang sebelumnya tanpa gaya (tombol topbar `lms-app.css`, dropdown topbar, hover akun topbar, `.attendance-row-highlighted` di `Guru/Absensi/Index.vue`) kini punya latar `#f1f5f9` |
| `.text-primary`/`.text-success` memakai `--text-brand` | Mode terang tetap sama; mode gelap lebih terang dan terbaca |
| Link (`--bs-link-color`) mode gelap memakai `--text-brand` | Link lebih terang dan terbaca di mode gelap |

Temuan dan tindak lanjut dari Fase 1:

- Tombol utama tema **biru-aqua** (`#0891b2`) hanya 3,68:1 dengan teks putih (batas AA 4,5:1). Belum diubah karena menyangkut warna brand; usul: gelapkan primary aqua ke `#0b7f9b` (4,64:1). Keputusan ada di pemilik produk.
- `app.blade.php` dan `layouts/app.blade.php` (layout lama untuk 2 halaman rekap guru) sama-sama punya blok `:root` token tema yang terduplikasi, dan layout lama juga memuat Bootstrap dari CDN sebagai cadangan. Rapikan di Fase 5.
- Warna teks memakai `--primary-600` di ±31 tempat (komponen) masih gagal AA di mode gelap; ganti ke `--text-brand` saat migrasi halaman (Fase 4).
- `--bs-primary-text-emphasis` mode gelap diset `#bbf7d0` (hijau tetap) sehingga tidak mengikuti tema non-hijau; perbaiki di Fase 2.
- `--success-600` dipakai `ChatRoom.vue` dengan fallback hex dan belum didefinisikan; `--course-accent`/`--queue-accent` diset per komponen lewat `:style` (sah).

### Fase 2: Komponen bersama (selesai 2026-10-04)

Pendekatan: komponen yang ada sudah rapi dan sebagian besar bertoken, jadi API dipertahankan dan perubahan visual dibuat lewat token dan lapisan `resources/css/components.css` (dimuat setelah `lms-app.css`). Satu komponen baru: `Modal`.

- [x] `Button`: varian `soft` dan `ghost` ditambahkan (CSS), `outline-secondary` memakai token netral, tinggi minimum 38 px (kecil 32 px), prop baru `loading` (spinner, `disabled`, `aria-busy`). Warna `danger`/`info`/`warning` kini dari token (`--danger-600`, `--info-600`, `--warning-300`, dan lainnya), bukan hex. Penyatuan `btn-primary`/`btn-success` di halaman dikerjakan di Fase 4 (visualnya sudah identik: `--bs-success` = warna tema).
- [x] `Badge`: varian `primary` kini lembut (`--brand-soft-bg`/`--brand-soft-text`, AA di terang dan gelap untuk kelima tema); `Badge` bawaan komponen mendapat penanda titik (tanpa ikon). Kelas lama `bg-soft-*`, `bg-light text-dark`, `text-bg-*` di halaman dibereskan di Fase 4.
- [x] `Card` dan `StatCard`: bergaris, bayangan dihapus lewat token `--shadow-card: none` (satu token untuk dikembalikan).
- [x] `TableWrapper`/tabel: header ringan (`--surface-muted`, teks `--text-muted`, bobot 600). Mode padat tersedia lewat `data-density="compact"`. Atribut `scope` pada `<th>` dikerjakan per halaman di Fase 4.
- [x] Komponen form (`TextInput`, `SelectInput`, `TextareaInput`, `FileInput`, `SearchableSelect`): tinggi kontrol seragam (`--control-height`), radius `--radius-control`; ring fokus dan state error sudah ada dan diverifikasi.
- [x] `EmptyState`, `ErrorState`, `LoadingState`: diverifikasi di terang/gelap; sudah bertoken sehingga tidak diubah.
- [x] `Modal` bersama (`Components/UI/Modal.vue`, diekspor dari `UI/index.ts`): `v-model`, ukuran `sm`/`md`/`lg`, mode `bare`, `align="top"`, Esc, klik latar (tidak menutup saat drag dari dalam), perangkap fokus, fokus kembali ke pemicu, kunci scroll body, `role="dialog"`, `aria-modal`, `aria-labelledby`, tumpukan modal (hanya yang teratas menangani Esc/Tab), `<Teleport>` ke `body`.
- [x] `ConfirmDialog` dan `CommandPalette` dialihkan ke `Modal` (API `window.confirmAction`/`window.confirmDialog` tidak berubah). Tombol konfirmasi non-bahaya kini `btn-primary` (sebelumnya `btn-success`).
- [ ] Modal `CalendarWorkspace.vue` dan `Guru/Tugas/Pengumpulan.vue` masih memakai `.confirm-overlay`; dialihkan ke `Modal` di Fase 4 (CSS `.confirm-overlay`/`.confirm-dialog` sengaja dibiarkan sampai itu).

Verifikasi Fase 2:

- `npm run typecheck` dan `npm run build` hijau.
- Uji perilaku otomatis (halaman uji sementara, sudah dihapus) di Chrome: 20 dari 20 lolos. Mencakup `Modal` (ARIA, fokus awal, Tab dan Shift+Tab, Esc, fokus kembali, kunci scroll, klik latar vs klik dalam), `ConfirmDialog` (tampil danger, hasil `true` dan `false` via Esc), `CommandPalette` (fokus input, memakai backdrop bersama, Esc), dan `Button loading`.
- Tinjauan visual (tangkapan layar): semua komponen di terang tema hijau; gelap tema marun; modal gelap tema hijau; konfirmasi terang tema aqua; palet gelap tema azure. Sebagian kombinasi tema dan mode lain tidak ditangkap.
- Belum diverifikasi: tampilan pada halaman aplikasi sebenarnya (butuh akun uji). Uji perilaku di atas tidak disimpan sebagai tes permanen karena Playwright bawaan belum bisa jalan di WSL ini (butuh `libnspr4` dan lainnya, perlu `sudo`); disarankan dijadikan tes Playwright begitu lingkungan siap.

Perubahan visual yang diharapkan dari Fase 2 (periksa saat uji manual):

| Perubahan | Dampak |
|---|---|
| Tinggi tombol dan input 38 px (kecil 32 px) | Tombol dan input terasa sedikit lebih tinggi; tabel padat dengan `btn-sm` bertambah ±3 px per baris |
| Tombol kini `inline-flex` | Teks dan ikon sejajar vertikal; ikon `me-1` tetap jaraknya |
| Kartu tanpa bayangan | Hanya garis tipis (kembalikan lewat `--shadow-card`) |
| Header tabel lebih ringan | Latar abu-abu netral, teks sekunder, bukan tint hijau |
| Badge `primary` lembut | Sebelumnya solid dengan teks putih |
| Dialog konfirmasi | Padding dan lebar dari `Modal` (sedikit lebih ramping), tombol utama `btn-primary` |
| Palet perintah | Kolom cari kini transparan di mode gelap (sebelumnya kotak abu-abu), ada garis fokus di bawah, tombol `Esc` mengikuti tema |

Temuan dan tindak lanjut dari Fase 2:

- CSS lama punya aturan global `footer { margin-top: 2rem; border-top; text-align: center; color; font-size }`. Elemen `<footer>` di komponen baru ikut terkena (di `Modal` sudah dihindari dengan `div`). Ganti dengan kelas khusus (mis. `.app-footer`) di Fase 5 agar tidak ada jebakan lagi.
- Fokus awal `Modal` jatuh ke elemen fokus pertama (tombol tutup bila ada judul). Untuk form, beri atribut `data-autofocus` pada kolom pertama saat migrasi di Fase 4.
- `Modal` mengunci scroll lewat kelas `modal-open` di `body` (sama seperti sebelumnya), bukan lewat style inline.

### Fase 3: Shell (selesai 2026-10-04)

- [x] Sidebar: gaya netral ringan, tanpa bayangan, hover netral, item aktif pil hijau lembut (`--brand-soft-bg`/`--brand-soft-text`), fokus keyboard terlihat; drawer mobile memakai `--shadow-float`.
- [x] Topbar (tanpa bayangan, kolom cari dan tombol memakai `--radius-control`, ring fokus) dan bottom navigation mobile (pil aktif hijau lembut, tinggi sentuh `--control-height`). Gaya ada di bagian "Shell (Fase 3)" `components.css`; override dark sidebar/bottom-nav di `app.css` yang bentrok dihapus.
- [ ] Halaman Login (`Pages/Auth/Login.vue` dan `login-isolation.css`) sesuai desain dua panel di bagian 2:
  - [x] Layout dua panel; panel kiri latar flat hijau tenang + Papan Informasi, panel kanan form.
  - [x] Tombol tampilkan/sembunyikan password (`aria-pressed`, `aria-label`).
  - [x] State error (alert + pesan field + `aria-describedby`) dan state memproses (spinner, tombol nonaktif).
  - [x] Mobile < 900 px: header, form, Papan Informasi, footer dalam satu kolom.
  - [x] Kasus tanpa pengumuman publik dan tanpa `support_contact` tetap rapi.
  - [x] Halaman login dikunci ke tema light; kontras teks panel kiri dan kanan lolos AA.
  - [x] Uji Playwright `tests/Browser/login.spec.ts`: login berhasil, login gagal, tombol tampilkan password, mobile 390 px tanpa overflow (data seed terisolasi).
- [x] Indikator fokus pengganti untuk `.command-palette-input input { outline: 0 }`: garis bawah inset di `:focus-within` (sudah ada sejak Fase 2).

Verifikasi Fase 3: `npm run typecheck` dan `npm run build` hijau; tangkapan layar dashboard siswa terang/gelap, 1440 dan 390 px ditinjau. `npm run test:browser`: 19 lolos, 2 gagal (`grade paste and attendance save`, `searchable select opens upward`) dan keduanya juga gagal tanpa perubahan Fase 3 (nilai `kelas_id` hidden = 2, bukan 1), jadi sudah ada sebelumnya dan perlu ditinjau terpisah. Playwright di WSL ini jalan dengan `LD_LIBRARY_PATH` berisi `libnspr4`/`libnss3`/`libasound2` hasil `apt-get download` + `dpkg-deb -x` (tanpa sudo).

### Fase 4: Migrasi halaman per modul (selesai 2026-10-04)

Dikerjakan lintas modul (bukan per modul) karena sebagian besar perubahan mekanis. Hasil ukur ulang `scripts/ui-audit.sh`:

| Temuan | Baseline | Sekarang |
|---|---|---|
| Hex di template/script `.vue` | 54 | 5 (palet tema `Admin/Pengaturan`, sah) |
| `style="…"` inline statis | 117 | 15 statis + `:style` dinamis (sisanya nilai satu-kali: `top`, `grid-template-columns`, `cursor`) |
| `text-muted` (kelas) | 223 | 0 (33 baris tersisa adalah `var(--text-muted)` di `<style>`, sah) |
| `window.confirm` | 1 | 0 |
| Modal tanpa Esc/fokus | 2 | 0 (plus 2 modal manual `modal fade show d-block` di `Kerjakan.vue` dan `SoalBank/Index.vue` ikut dimigrasi) |
| `<th>` dengan `scope` | 31 dari 311 | 316 dari 318 |
| `button.btn` mentah | 20 | 1 (tombol `btn-login` di Login, sengaja) |
| Badge mentah | 46 | 16 (sebagian besar kelas `*-badge` khusus jadwal, bukan `.badge`) |
| `<table>` vs `<TableWrapper>` | 63 vs 58 | 63 vs 63 |
| Card mentah | 58 | 41 (sebagian besar cocok `course-card`/`schedule-card`, bukan `.card`; 4 halaman Pengumuman dan `Admin/Rekap` dimigrasi) |

Yang dikerjakan:

- `text-muted` menjadi `text-body-secondary` di seluruh `resources/js`.
- Tombol utama: `btn-success` menjadi `btn-primary` (tombol konfirmasi fallback, Pengumuman, Materi, Kelas Daring). `btn-outline-success` (ekspor Excel) dan `btn-success` di tombol WhatsApp dan navigasi soal ujian dipertahankan karena bermakna semantik.
- Warna: token aksen kategori `--accent-*` di `tokens.css`; notifikasi, dashboard, kartu mapel, dan grafik memakai token. Helper `resources/js/utils/cssColor.ts` (`cssVar`, `withAlpha`) dipakai grafik Chart.js (butuh nilai terselesaikan, bukan `var()`).
- `Badge` kini satu gaya lembut (`bg-soft-*` dari token status; `secondary`/`light` menjadi netral). `.bg-soft-primary` memakai `--brand-soft-bg`/`--brand-soft-text` sehingga mengikuti tema pilihan admin; override dark hex-nya dihapus.
- Style inline statis menjadi kelas utilitas `u-*` (dibangkitkan di akhir `components.css`).
- Modal: `CalendarWorkspace`, `Guru/Tugas/Pengumpulan`, `Siswa/Ujian/Kerjakan` (konfirmasi selesai), `Guru/SoalBank` (form soal) kini memakai `Modal` (submit di footer lewat atribut `form="…"`). Tes baru `tests/Browser/modals.spec.ts` (SoalBank: Esc dan simpan lewat footer; detail pengumpulan: dialog berlabel dan Esc).
- `ConfirmDialog` menggantikan `window.confirm` di `Admin/Pengumuman`.

Lanjutan (putaran kedua, 2026-10-04):

- Form non-tabel dialihkan ke komponen form (`TextInput`/`SelectInput`/`TextareaInput`): filter dan form `SoalBank`, form `Admin/Pengumuman`, `Guru/Rekap/Absensi` dan `Rekap/Nilai`, pencarian di `Guru/Tugas/{List,Index,Pengumpulan}` dan `Guru/Ujian/{Index,List}`, serta `Guru/Ujian/Builder` (kategori, jadwal, filter bank soal). Form mentah turun 56 menjadi 44.
- Badge dinamis (`Guru/Rekap/{Sikap,Nilai}`, `Guru/Ujian/{AttemptDetail,Hasil}`, `Siswa/Ujian/Hasil`) memakai `<Badge :color>`; `Siswa/Materi/Index` memakai `Card`. Badge mentah 16 menjadi 10, card mentah 41 menjadi 39.
- Tes baru di `tests/Browser/modals.spec.ts`: form Pengumuman (judul, target, isi) tersimpan, dan filter/pencarian pengumpulan tugas.

Sisa yang sengaja tidak diubah:

- Input padat di dalam tabel (`score-input`, `attendance-select`, `attitude-select`, `wali-attendance-select`, bobot poin di Builder): dipakai handler keyboard/paste dan tes; membungkusnya dengan komponen form (yang menambah `div` pembungkus dan label) berisiko merusak navigasi Enter/paste.
- `<select>` dengan nilai numerik/null yang dibandingkan secara ketat (`Admin/Rekap`, `Guru/Nilai/Index`, `Guru/Sikap/Index`, `Guru/Rekap/Sikap`) dan `select multiple` di `Admin/Pengumuman`: `SelectInput` selalu memancarkan string.
- `Durasi` di `Guru/Ujian/Builder` (`v-model.number` pada komponen akan menempelkan `modelModifiers` ke elemen `input`).
- Antarmuka pengerjaan ujian (`Siswa/Ujian/Kerjakan.vue`): bilah atas `bg-primary text-white`, kartu soal, navigator, dan legenda nomor adalah chrome CBT khusus; kartu dan badge legenda dibiarkan.
- Kartu bersarang di `Guru/Ujian/Index` dan `AttemptDetail` (kartu `border shadow-none` di dalam kartu).
- Badge `*-badge` khusus di `Guru/JadwalMengajar` (kelas komponen sendiri, bukan `.badge` Bootstrap).
- Pemindahan `<style>` di `.vue` ke token (74 hex): Fase 5.
- Tinjauan visual semua 88 halaman per peran: ditunda ke Fase 6 (verifikasi akhir); saat ini hanya dashboard siswa, Pengumuman admin, Rekap admin, dan modal SoalBank yang ditinjau lewat tangkapan layar.

Verifikasi: `npm run typecheck` dan `npm run build` hijau; `npm run test:browser` 23 lolos, 2 gagal yang sudah gagal sebelum Fase 3 (`grade paste and attendance save`, `searchable select opens upward`).

### Fase 5: Pembersihan CSS (selesai 2026-10-04)

Dibandingkan per piksel dengan tangkapan baseline (4 peran x terang/gelap x 1366/390 px, 3 sampai 7 halaman per peran, dijalankan lewat `toHaveScreenshot`; berkas pembanding sementara sudah dihapus). Perbedaan yang tersisa semuanya dijelaskan: halaman Log Login (memuat waktu), kanvas grafik Chart.js (animasi), kalender (sengaja dipindah ke token), dan kolom "Rata-rata" di `Siswa/Nilai` mode gelap (kini hijau seperti mode terang, sebelumnya tertimpa override gelap).

| Temuan | Baseline | Sekarang |
|---|---|---|
| Hex di file CSS (di luar `tokens.css`) | 115 | 0 |
| Hex di `<style>` .vue | 74 | 32 (semuanya `Login.vue`, terisolasi sengaja; alasan dicatat di komentar file) |
| `!important` di CSS | 101 | 18 baris (16 `lms-app.css`, 1 `app.css`, 1 `login-isolation.css`), sebagian komentar |
| `!important` di `<style>` .vue | 18 | 10 (7 Login, 1 Topbar, sisanya komentar/sah) |
| Selector `[data-bs-theme="dark"]` di CSS dan `<style>` .vue | 136 + 26 | 0 (gelap sepenuhnya dari token) |
| Variasi media query | 20 | 12 (sisanya spesifik komponen/login) |

Yang dikerjakan:

- Override gelap per komponen dihapus seluruhnya. Mode gelap kini murni dari token: varian `-rgb` Bootstrap (`--bs-light-rgb`, `--bs-white-rgb`, `--bs-dark-rgb`, `--bs-secondary-rgb`, `--bs-tertiary-bg-rgb`, `--bs-light-bg-subtle`, `--bs-code-color`) dipetakan di blok gelap `tokens.css`. Nilai `-rgb` adalah salinan `--surface-muted`, `--text-body`, `--text-muted` (dicatat di komentar; ubah bersamaan).
- Utilitas yang melawan Bootstrap (`.bg-soft-*`, `.bg-primary/success/warning/danger/info`, `.text-primary/success/warning/danger/info`, `.border-primary/success`) dipusatkan: tiap kelas hanya menetapkan variabel nada (`--soft-bg/--soft-fg`, `--solid-bg/--solid-fg`, `--text-tone`), lalu satu aturan `!important` per jenis. `!important` di blok itu sah karena utilitas Bootstrap sendiri `!important` (ada komentar di file).
- Tombol topbar tidak lagi memakai `!important`; gayanya lewat variabel `--bs-btn-*`. `btn-light` kini dari token (`components.css`), bukan override gelap.
- `!important` lain dihapus dengan spesifisitas atau variabel: radius kartu, pagination, overlay sidebar, `main-content`, ukuran kolom (`w-score` dan sejenisnya), `quick-action` (sebelumnya mematikan ring fokus), badge `.badge.bg-*`/`text-bg-*` (digantikan komponen `Badge`).
- Yang sengaja tetap `!important` (dengan komentar): utilitas warna di atas, ring fokus tombol/form, `.text-xs`, `margin-bottom:0` pada `.form-section` (melawan `.mb-3`), `gap` kolom aksi tabel (melawan `.gap-*`), `prefers-reduced-motion`, penyembunyian nprogress (gaya inline), `margin:0` ikon akun topbar (melawan `.me-1`), dan Login.
- Fallback hex pada `var(--x, #hex)` dibuang; hex lepas dipetakan ke token (`--on-brand` baru untuk teks di atas warna tema; ikon metrik dan orbit memakai token status/brand-soft; toast memakai `--danger-600`, `--gold-600`, `--info-600`).
- Kelas yang tidak dirujuk di mana pun dibuang (23 selector, mis. `badge-admin`, `form-section-header`, `login-help`, `sticky-student`). Kelas yang dibentuk dinamis (`app-modal--*`, `bg-soft-*`, `btn-soft`, `dashboard-hero-*`, `metric-*`) sengaja dipertahankan.
- `responsive-polish.css` digabung ke akhir `app.css` (urutan kaskade sama) dan impornya di `app.ts`/`inertia.ts` dihapus.
- `CalendarWorkspace`, `AcademicTimeline`, `ChatRoom`: blok gelap `<style>` diganti token.

Temuan dari pemeriksaan browser langsung (setelah Fase 5): pemetaan `--bs-link-color` di Fase 1 tidak pernah berlaku di mode terang karena Bootstrap 5.3 mewarnai `<a>` lewat `--bs-link-color-rgb`; tautan tetap biru bawaan. Diperbaiki di `components.css` (`a { color: var(--bs-link-color) }`). `btn-info` (dipakai 16 tombol "Detail" dan sejenisnya) kini varian lembut dari token status, bukan biru solid, dan `code` memakai `--text-brand` (sebelumnya magenta Bootstrap).

Bug yang ditemukan dan diperbaiki saat pembandingan: menaikkan spesifisitas `.card-header` membuat padding responsif tidak berlaku di mobile (kartu +5 px); `btn-light` menjadi putih di mode gelap setelah override gelap dihapus.

Catatan: audit `!important` mencatat baris, termasuk baris komentar. Target "< 10" tidak tercapai secara harfiah; sisanya adalah daftar sah di atas.

### Fase 6: Verifikasi akhir

- [ ] `npm run typecheck` dan `npm run build` hijau.
- [ ] `npm run test:browser` hijau (periksa `scripts/test-browser.mjs` terlebih dulu untuk efek data dan autentikasi).
- [ ] Tangkapan layar sesudah vs baseline Fase 0, terang dan gelap, mobile dan desktop.
- [ ] Uji manual per peran (Admin, Guru, Siswa, Kepsek): data kosong, banyak data, state error.
- [ ] Uji keyboard: Tab, Esc pada semua modal, indikator fokus terlihat.
- [ ] Audit ulang dengan `bash scripts/ui-audit.sh`, bandingkan dengan tabel bagian 1, dan catat hasilnya di dokumen ini.

## 4. Risiko

- Perubahan menyeluruh dapat merusak tampilan halaman yang jarang dibuka; mitigasi: fase bertahap, satu commit per fase, tangkapan layar pembanding.
- Menghapus `!important` dan override dark mode dapat mengubah kaskade; mitigasi: lakukan di Fase 5 setelah semua halaman memakai token.
- Tema pilihan admin bergantung pada variabel CSS yang ada; mitigasi: putuskan nasib tema di Fase 0 dan uji ketiganya di Fase 6.

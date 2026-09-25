# LMS Modern UI TODO

Dokumen ini melacak rombakan UI modern untuk demo LMS. V1 sudah diarahkan ke workspace modern dengan shell baru, dashboard role-first, dan komponen reusable.

## V1 Implemented

- AppShell modern dengan sidebar ringkas, topbar compact, dan mobile bottom navigation.
- Dashboard Admin sebagai pusat health operasional.
- Dashboard Guru sebagai teaching cockpit.
- Dashboard Siswa sebagai command center belajar.
- Komponen reusable:
  - `DashboardHero`
  - `MetricStrip`
  - `QuickActionBar`
  - `ActionQueue`
  - `CourseCard`
  - `AgendaPanel`
- Lazy page imports untuk Inertia agar bundle awal lebih ringan.
- Pagination tidak lagi memakai `v-html`.

## Current Checkpoint

Milestone 1 sampai 3 telah diimplementasikan pada domain demo. Workspace kelas/mapel menjadi titik masuk alur guru dan siswa, sedangkan tabel dipertahankan untuk entri serta rekap data yang memang membutuhkan pemindaian kolom.

- `AgendaPanel` dipakai pada ringkasan workspace guru dan siswa untuk deadline tugas yang dapat langsung dibuka.
- `CourseCard` dashboard guru dan siswa memakai data kelas/mapel nyata beserta jumlah materi dan tugas.
- Topbar membuka command palette fungsional untuk navigasi menu melalui klik, `/`, atau `Ctrl/Cmd+K`; ArrowUp, ArrowDown, Enter, dan Escape didukung.
- `SearchableSelect` mendukung keyboard dan atribut ARIA combobox/listbox.
- Materi, Tugas, Nilai, dan Absensi guru telah memiliki konteks workspace; Materi dan Tugas siswa diarahkan kembali ke workspace kelas/mapel.
- Penugasan Guru admin telah memakai halaman Inertia modern dengan metric strip, form searchable, daftar pengajaran, dan daftar wali kelas.
- Kalender lintas peran tetap tersedia pada halaman Kalender masing-masing role; agenda workspace berfokus pada tindakan deadline yang relevan dengan kelas/mapel aktif.

Pekerjaan yang masih memerlukan verifikasi operasional, bukan implementasi tambahan:

- Uji manual desktop dan mobile menggunakan akun Admin, Guru, serta Siswa.
- Uji data kosong, banyak data, dan respons error pada tiap alur utama.
- Jalankan audit dependency saat akses registry tersedia, lalu tinjau perubahan lockfile sebelum menerapkannya.

## Next UI Pass: Ordered Milestones

### Milestone 1: Workspace Kelas/Mapel - Selesai

Jadikan detail kelas/mapel sebagai fondasi navigasi kerja guru dan siswa.

- Buat halaman detail kelas/mapel dengan tabs: Ringkasan, Materi, Tugas, Nilai, Absensi, dan Chat.
- Tentukan akses tab berdasarkan peran serta relasi kelas/mapel pengguna.
- Sajikan ringkasan course yang nyata: guru, kelas, semester, deadline terdekat, tugas belum dinilai, dan notifikasi terbaru.
- Pastikan URL setiap tab dapat dibuka langsung serta mempertahankan konteks kelas/mapel.

### Milestone 2: Modernisasi Alur Harian - Selesai

- Jadikan halaman `Materi`, `Tugas`, `Nilai`, dan `Absensi` berbasis workspace tab/card, bukan tabel sebagai tampilan pertama.
- Pertahankan tabel sebagai mode detail/rekap untuk kebutuhan scan data dan ekspor.
- Prioritaskan alur Guru: membuat materi/tugas, memeriksa pengumpulan, memberi nilai, dan mencatat absensi.
- Prioritaskan alur Siswa: membuka course, melihat deadline, mengumpulkan tugas, serta membaca feedback nilai.

### Milestone 3: Command dan Agenda - Selesai

- Command palette navigasi dari topbar dengan shortcut dan keyboard lengkap.
- Agenda deadline pada workspace dan halaman Kalender lintas peran yang sudah tersedia.
- `SearchableSelect` dengan ArrowDown, ArrowUp, Enter, Escape, serta role combobox/listbox.

## Acceptance Checks Per Milestone

- Uji desktop dan mobile untuk Admin, Guru, dan Siswa; teks, action, dan tabel tidak boleh overflow.
- Uji state kosong, loading, error validasi, dan data berjumlah banyak pada setiap workspace baru.
- Pastikan semua aksi tetap memakai route serta otorisasi Laravel yang sudah ada.
- Jalankan `npm run build`, test PHP terkait, dan uji manual alur utama sebelum melanjutkan ke milestone berikutnya.
- Tambahkan atau perbarui dokumentasi props Inertia saat kebutuhan data baru diperkenalkan.

## Backend/Data Enhancements - Selesai Untuk UI Saat Ini

- Props `courses` dashboard memakai count materi dan tugas tanpa query per kartu.
- Props workspace memuat deadline tugas, status absensi guru hari ini, dan antrean pengumpulan untuk dinilai.
- Command palette dibatasi pada menu yang telah tersedia untuk role dan capability pengguna, sehingga tidak membuka route di luar otorisasi sidebar.
- Kontrak props workspace berada pada controller `Guru/KelasMapelWorkspaceController` dan `Siswa/KelasMapelWorkspaceController`.

## Design System Cleanup

- Pindahkan styling utama dari `public/css/lms-app.css` ke pipeline Vite secara bertahap.
- Kurangi ketergantungan ke selector Bootstrap global untuk layout aplikasi.
- Konsolidasikan token warna di satu tempat agar theme sekolah tetap bisa mengontrol accent tanpa membuat UI kembali terlalu gradient.
- Review contrast dan mobile spacing untuk semua role setelah halaman utama ikut dimodernisasi.
- Pindahkan CSS per komponen atau per halaman terlebih dahulu; hindari memindahkan seluruh stylesheet dalam satu perubahan besar.

## Visual Style Refresh (Review 2026-09-25)

Hasil review gaya visual saat ini: fondasi sudah baik (dark mode, token, komponen reusable, aksesibilitas dasar), tetapi tampilan masih condong ke pola admin-template lama (gradient tebal, animasi masuk serentak, kartu statistik berat bayangan). Todo berikut untuk menyegarkan tampilan tanpa mengubah struktur navigasi/komponen yang sudah bekerja.

- [x] Kurangi gradient pada sidebar dan topbar (`.sidebar`, `.topbar` di `public/css/lms-app.css`); ganti ke surface flat dengan satu warna aksen yang dipakai selektif (active state, CTA).
- [x] Hapus gradient-text pada judul halaman (`.page-header h1/h4`, `background-clip: text`); ganti warna solid dengan bobot font agar konsisten di light/dark dan lebih aman untuk kontras/print/select.
- [x] Kurangi animasi masuk otomatis (`fadeInUp`, stagger delay per stat-card, `fadeIn` di `.page-content`) menjadi opsional/lebih halus, atau hilangkan di halaman dengan data banyak agar terasa tenang, bukan "template demo".
- [x] Sederhanakan `.stat-card`: kurangi `border-left` tebal + shadow besar, pertimbangkan border tipis + shadow minim sesuai tren surface flat.
- [ ] Audit dan kurangi penggunaan `!important` di `public/css/lms-app.css`, `resources/css/app.css`, `resources/css/responsive-polish.css`. **Status per review 2026-09-25: baru `responsive-polish.css` yang benar-benar diaudit (14 → 4 `!important`).** `lms-app.css` nyaris tidak berubah (67 → 66) dan `app.css` masih 30 `!important` tanpa perubahan. Sisa `!important` di `app.css:306-315` ([data-bs-theme="dark"] override untuk topbar button) **memang masih dibutuhkan** karena meng-override `.modern-topbar .topbar-toggle-btn` di `lms-app.css:1159-1166` yang juga masih `!important` — menghapus salah satu sisi saja akan merusak kontras tombol topbar dark mode. Perlu pekerjaan terpisah: turunkan dulu specificity di `lms-app.css`, baru `app.css` bisa ikut lepas `!important`, lalu uji ulang light+dark.
- [x] Konsolidasikan token warna/spacing dari 3 file CSS (`app.css`, `responsive-polish.css`, `lms-app.css`) menjadi satu sumber sebelum menambah token baru. Duplikasi nama token antar file yang tersisa (`--text-strong`, `--surface-card`, dll.) adalah pasangan light/dark yang disengaja, bukan token yang belum dikonsolidasi.
- [x] Setelah token/gradient disederhanakan, review ulang badge status (`bg-soft-*`) dan tabel agar tetap kontras cukup di kedua tema. Terverifikasi visual (lihat bagian Verifikasi di bawah).
- [x] Uji visual sebelum/sesudah pada Dashboard Admin, Guru, dan Siswa (desktop + mobile) sebelum menyebar perubahan ke halaman lain. Diverifikasi via Playwright terhadap `https://lms_sekolah.test` pada 2026-09-25 — lihat bagian Verifikasi.

### Verifikasi (Review 2026-09-25, lanjutan)

`npm run build` sukses tanpa error. Verifikasi visual nyata dilakukan via Playwright terhadap situs Lerd `https://lms_sekolah.test` (bukan cuma baca kode):

- **Admin** — Dashboard (`/admin/dashboard`) dan halaman yang tidak disentuh langsung (`/admin/users`) dicek desktop 1440px, mobile 390px, light, dan dark. Sidebar/topbar flat, judul solid (bukan gradient-text), stat-card tipis tanpa shadow besar, badge status kontras baik di kedua tema, tabel di mobile scroll horizontal tanpa overflow.
- **Guru** — Dashboard (`/guru/dashboard`) dan Chat Kelas (`/guru/chat/1`, file `ChatRoom.vue` yang diedit) dicek dark mode: bubble chat "is-mine" solid `var(--primary-600)` (gradient sudah hilang), kontras teks putih tetap terbaca.
- **Siswa** — Dashboard (`/siswa/dashboard`) dan Nilai Saya (`/siswa/nilai`, file `Siswa/Nilai/Index.vue` yang diedit) dicek: kolom rata-rata pakai `text-success`/`text-danger` (bukan lagi inline style), header tabel pakai `var(--surface-muted)`, tampil benar di dark mode.
- Login pakai akun demo bawaan seeder (`admin`/`guru`/`siswa`, password `password`) — bukan kredensial baru yang dibuat.
- **Catatan cakupan**: ini bukan pengecekan satu-per-satu ke 60+ menu di "Inventaris Menu" di bawah, melainkan spot-check representatif (global CSS + shared component + 1 halaman yang benar-benar diedit per role). Karena refresh gaya visual bertumpu pada class global (`.sidebar`, `.topbar`, `.stat-card`, `.page-header`) dan komponen `PageHeader.vue`, hasil di `/admin/users` (halaman yang TIDAK disentuh) sudah flat dengan benar — memperkuat bahwa perubahan global memang menyebar ke halaman lain, tapi belum tiap-tiap 60+ halaman dibuka manual satu-satu.

## Inventaris Menu untuk Inspeksi Sekaligus

Daftar ini memetakan setiap item sidebar (`resources/js/Components/AppShell/sidebarMenu.ts`) ke file Vue-nya, supaya refresh visual style di atas bisa dikerjakan menu-per-menu dalam satu pass tanpa harus mencari ulang filenya. Checklist per item: apakah masih pakai gradient/animasi lama, stat-card berat, atau `!important` yang bisa disederhanakan.

### Admin

- [x] Dashboard (`/admin/dashboard`) → `Admin/Dashboard.vue`
- [x] Guru dan Staf (`/admin/users`) → `Admin/Users/Index.vue`, `Admin/Users/Form.vue`
- [x] Data Kelas (`/admin/kelas`) → `Admin/Kelas/Index.vue`
- [x] Kelas & Siswa (`/admin/kelas-siswa`) → `Admin/KelasSiswa/Index.vue`
- [x] Mata Pelajaran (`/admin/mata-pelajaran`) → `Admin/MataPelajaran/Index.vue`
- [x] Penugasan Guru (`/admin/kelas-mapel`) → `Admin/KelasMapel/Index.vue`
- [x] Tahun Ajaran (`/admin/tahun-ajaran`) → `Admin/TahunAjaran/Index.vue`
- [x] Pengaturan Akun (`/admin/pengaturan-akun`) → `Account/Pengaturan.vue` (dipakai bersama semua role via `AccountSettingsController`)
- [x] Pengumuman (`/admin/pengumuman`) → `Admin/Pengumuman/Index.vue`, `Admin/Pengumuman/Show.vue`
- [x] Kalender (`/admin/kalender`) → `Admin/Kalender/Index.vue`
- [x] Performa Guru (`/admin/performa-guru`) → `PerformaGuru/Index.vue` (dipakai bersama Kepsek)
- [x] Rekap Absensi/Nilai/Sikap/Tugas (`/admin/rekap/*`) → `Admin/Rekap.vue`
- [x] Pengaturan (`/admin/pengaturan`, `/admin/school-settings`) → `Admin/Pengaturan/Index.vue`
- [x] Log Login (`/admin/log-login`) → `Admin/LogLogin/Index.vue`
- [x] Log Error (`/admin/log-error`) → `Admin/LogError/Index.vue`
- [x] Log Akademik (`/admin/log-akademik`) → `Admin/AcademicAuditLog/Index.vue`
- [x] IP Diblokir (`/admin/blocked-ips`) → `Admin/BlockedIps/Index.vue`

### Guru

- [x] Dashboard (`/guru/dashboard`) → `Guru/Dashboard.vue`
- [x] Jadwal Mengajar (`/guru/jadwal-mengajar`) → `Guru/JadwalMengajar/Index.vue`
- [x] Absensi (`/guru/absensi`) → `Guru/Absensi/Index.vue`
- [x] Materi (`/guru/materi`) → `Guru/Materi/Index.vue`, `Guru/Materi/List.vue`
- [x] Tugas (`/guru/tugas`) → `Guru/Tugas/Index.vue`, `Guru/Tugas/List.vue`, `Guru/Tugas/Pengumpulan.vue`, `Guru/Tugas/Partials/SubmissionGradeForm.vue`, `Guru/Tugas/Partials/SubmissionRow.vue`
- [x] Ujian (CBT) (`/guru/ujian`) → `Guru/Ujian/Index.vue`, `List.vue`, `Builder.vue`, `Hasil.vue`, `AttemptDetail.vue`
- [x] Bank Soal (`/guru/soal-bank`) → `Guru/SoalBank/Index.vue`
- [x] Nilai (`/guru/nilai`) → `Guru/Nilai/Index.vue`, `Guru/Nilai/Input.vue`
- [x] Sikap (`/guru/sikap`) → `Guru/Sikap/Index.vue`, `Guru/Sikap/Input.vue`
- [x] Wali Kelas (`/guru/wali-kelas`, kondisional) → `Guru/WaliKelas/Index.vue`, `Absensi.vue`, `Biodata.vue`, `Penanganan.vue`, `Pertemuan.vue`
- [x] Kelas Daring (`/guru/kelas-daring`) → `Guru/KelasDaring/Index.vue`
- [x] Kalender (`/guru/kalender`) → `Guru/Kalender/Index.vue`
- [x] Pengumuman (`/guru/pengumuman`) → rute `/guru/pengumuman` menggunakan `AdminPengumumanController` & view bersama `Admin/Pengumuman/Index.vue`
- [x] Chat Kelas (`/guru/chat`) → `Guru/Chat/Index.vue`, `Guru/Chat/Show.vue`
- [x] Rekap Absensi/Nilai/Sikap (`/guru/rekap-*`) → `Guru/Rekap/Absensi.vue`, `Guru/Rekap/Nilai.vue`, `Guru/Rekap/Sikap.vue`
- [x] Pengaturan (`/guru/pengaturan`, `/guru/profil`) → `Account/Pengaturan.vue`, `Guru/Profil.vue`
- [x] Workspace kelas/mapel → `Guru/KelasMapel/Show.vue`
- [x] Notifikasi → `Guru/Notifikasi/Index.vue`

### Siswa

- [x] Dashboard (`/siswa/dashboard`) → `Siswa/Dashboard.vue`
- [x] Jadwal Pelajaran (`/siswa/jadwal-pelajaran`) → `Siswa/Jadwal/Index.vue`
- [x] Kelas Daring (`/siswa/kelas-daring`) → `Siswa/KelasDaring/Index.vue`
- [x] Progress Saya (`/siswa/progress`) → `Siswa/Progress.vue`
- [x] Materi Saya (`/siswa/materi`) → `Siswa/Materi/Index.vue`, `Siswa/Materi/List.vue`
- [x] Tugas Saya (`/siswa/tugas`) → `Siswa/Tugas/Index.vue`, `Siswa/Tugas/Show.vue`
- [x] Ujian (CBT) (`/siswa/ujian`) → `Siswa/Ujian/Index.vue`, `Hasil.vue`, `Kerjakan.vue`
- [x] Nilai Saya (`/siswa/nilai`) → `Siswa/Nilai/Index.vue`
- [x] Kalender (`/siswa/kalender`) → `Siswa/Kalender/Index.vue`
- [x] Pengumuman (`/siswa/pengumuman`) → `Siswa/Pengumuman/Index.vue`, `Show.vue`
- [x] Chat Kelas (`/siswa/chat`) → `Siswa/Chat/Index.vue`, `Show.vue`
- [x] Pengaturan (`/siswa/pengaturan`, `/siswa/profil`) → `Account/Pengaturan.vue`, `Siswa/Profil.vue`
- [x] Workspace kelas/mapel → `Siswa/KelasMapel/Show.vue`
- [x] Notifikasi → `Siswa/Notifikasi/Index.vue`

### Kepala Sekolah

- [x] Dashboard (`/kepsek/dashboard`) → `Kepsek/Dashboard.vue`
- [x] Statistik (`/kepsek/statistik`) → `Kepsek/Statistik/Index.vue`
- [x] Performa Guru (`/kepsek/performa-guru`) → `PerformaGuru/Index.vue` (shared)
- [x] Kalender (`/kepsek/kalender`) → `Kepsek/Kalender/Index.vue`
- [x] Pengumuman (`/kepsek/pengumuman`) → `Kepsek/Pengumuman/Index.vue`, `Show.vue`
- [x] Laporan Absensi (`/kepsek/laporan/absensi`) → `Kepsek/Laporan/Absensi.vue`
- [x] Laporan Nilai (`/kepsek/laporan/nilai`) → `Kepsek/Laporan/Nilai.vue`
- [x] Laporan Wali Kelas (`/kepsek/laporan/wali-kelas`) → `Kepsek/Laporan/WaliKelas/Index.vue`, `Show.vue`
- [x] Rekap Absensi (`/kepsek/laporan/rekap-absensi`) → `Kepsek/Laporan/RekapAbsensi.vue`
- [x] Rekap Tugas (`/kepsek/laporan/rekap-tugas`) → `Kepsek/Laporan/RekapTugas.vue`
- [x] Rekap Sikap (`/kepsek/laporan/rekap-sikap`) → `Kepsek/Laporan/RekapSikap.vue`

### Lintas Role (di luar sidebar menu)

- [x] Login → `Auth/Login.vue`
- [x] Notifikasi umum → `Notifications/Index.vue`
- [x] Shell/Layout global → `Layouts/AppShell.vue`, `Components/AppShell/Sidebar.vue`, `Components/AppShell/Topbar.vue`, `Components/AppShell/CommandPalette.vue`, `Components/AppShell/ConfirmDialog.vue`, `Components/AppShell/ToastStack.vue`
- [x] Komponen UI dasar → semua file `Components/UI/*.vue` (Card, Button, StatCard, DashboardHero, CourseCard, dll.) — refresh di sini otomatis menyebar ke banyak halaman sekaligus, jadi prioritaskan lebih dulu sebelum menyentuh halaman satu-per-satu.

## Security/Dependency

- `npm audit fix` telah memperbarui `postcss` beserta dependency transitifnya; audit ulang menunjukkan `0 vulnerabilities`.
- Gunakan Node.js LTS 20 atau 22 untuk development/deployment. Lingkungan lokal saat ini memakai Node 19 yang tidak termasuk rentang engine Vite dan plugin yang digunakan.
- Jalankan `npm audit` secara berkala dan tinjau perubahan lockfile setiap pembaruan dependency.

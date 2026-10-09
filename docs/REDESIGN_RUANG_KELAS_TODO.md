# Redesain "Ruang Kelas Digital" — TODO dan Progres

Dokumen kerja untuk mengganti tampilan Aurora dengan arah **Ruang Kelas Digital**. Dokumen ini adalah sumber kebenaran progres: dicentang saat sebuah butir benar-benar selesai **dan** terverifikasi, bukan saat mulai dikerjakan.

- **Pemilik implementasi:** Claude (diusulkan dan dikerjakan oleh Claude atas persetujuan pemilik proyek, 2026-10-09).
- **Branch:** `ruang_kelas` (dibuat dari `main` @ `4bc9ca2`). Aurora tetap utuh di `main` sebagai titik kembali.
- **Kontrol pemilik proyek:** setiap fase diakhiri titik review. Fase berikutnya tidak dimulai sebelum pemilik proyek menyetujui hasil fase sebelumnya.
- **Keterangan status:** `[ ]` belum, `[~]` sedang dikerjakan, `[x]` selesai dan terverifikasi, `[-]` dibatalkan (alasan ditulis).

## 1. Mengapa Aurora diganti

Hasil evaluasi UI 2026-10-09 (38 halaman, 4 peran, desktop 1440 px dan mobile 390 px, mode gelap, axe-core WCAG 2 AA):

1. Gaya halaman pemasaran SaaS (gradien mesh, panel kaca, tombol bercahaya, hero penuh warna) untuk aplikasi yang dipakai harian sebagai alat kerja.
2. Semua elemen bersaing menonjol, sehingga hierarki di layar padat data (input nilai, absensi) hilang.
3. Generik dan tanpa identitas sekolah; gradien per peran bertabrakan dengan warna tema pilihan sekolah.
4. `backdrop-filter: blur` di shell dan overlay berat untuk HP siswa kelas bawah, termasuk saat ujian CBT.
5. Kepadatan rendah: hero sekitar 260 px di 25 halaman, tabel nilai terpotong, Pengaturan mobile setinggi 5.700 px.

## 2. Prinsip desain (spesifikasi)

| Aspek | Keputusan |
|---|---|
| Karakter | Tenang, padat, fungsional. Karakter datang dari konteks sekolah (logo, warna sekolah, tahun ajaran/semester), bukan dari efek. |
| Permukaan | Datar dan solid. Latar netral hangat ("kertas"), kartu putih berbingkai tipis **tanpa bayangan**. Bayangan hanya untuk lapisan yang melayang (dropdown, modal, toast). Tanpa kaca, tanpa gradien latar, tanpa blur. |
| Warna | Warna tema sekolah = **aksen**: aksi utama, item navigasi aktif, tautan, fokus. Warna status (hijau/kuning/merah/biru) hanya untuk makna. Tidak ada warna per peran. |
| Tipografi | Plus Jakarta Sans (sudah terpasang, tanpa dependensi baru) dengan letter-spacing normal. Hierarki lewat ukuran dan ketebalan. Angka di tabel dan metrik memakai `tabular-nums`. |
| Bentuk | Radius 8 px (kartu), 6 px (kontrol), penuh hanya untuk badge/pil. |
| Kepadatan | Kontrol 38 px, baris tabel sekitar 44 px. Padding kartu 16–20 px. |
| Header halaman | `PageHeader` ringkas: breadcrumb/konteks, judul, satu baris ringkasan, aksi di kanan. Hero hanya di dashboard sebagai sapaan singkat, tanpa ikon dekoratif besar. Topbar tidak mengulang judul halaman. |
| Tabel | Warga kelas satu: header lengket, kolom identitas tetap saat digeser, angka rata kanan, aksi berlabel/bertooltip, dan **daftar kartu di bawah 768 px** untuk tabel daftar. |
| Mobile | Navigasi bawah berisi maksimal 4 tujuan terpenting per peran + "Menu". Label tidak terpotong. |
| Gerak | Transisi 150–200 ms untuk hover/fokus saja. Tanpa animasi masuk halaman. Hormati `prefers-reduced-motion`. |
| Bahasa | Seluruh label UI berbahasa Indonesia (tidak ada "HEALTH OPERASIONAL", "TEACHER DASHBOARD", "INSIGHT", "Choose File"). |
| Aksesibilitas | Kontras teks minimal AA (4,5:1; 3:1 untuk teks besar dan komponen UI). axe-core tanpa pelanggaran serius/kritis. |
| Mode gelap | Netral gelap (bukan navy), mengikuti token yang sama. |

## 3. Keputusan teknis

- **D1. Strategi lapisan.** `modern.css` (Aurora) diganti `ruang-kelas.css` yang dimuat paling akhir. Lima berkas CSS lama (`app`, `tokens`, `lms-app`, `components`, `modern`) **tidak** digabung di Fase 0 karena 41 halaman masih memakai kelas lama di scoped style. Penggabungan menjadi satu sistem dilakukan di Fase 5, setelah semua halaman pindah.
- **D2. Tanpa dependensi baru.** Tetap Bootstrap 5.3, Bootstrap Icons, Plus Jakarta Sans, Chart.js.
- **D3. Warna tema sekolah** tetap disuntikkan dari `app.blade.php` (`--app-primary`, skala `--primary-*`). Tidak ada perubahan backend untuk tema.
- **D4. Grafik** membaca warna dari token CSS (`utils/cssColor.ts`), tidak memakai warna bawaan Chart.js.
- **D5. Konteks akademik di topbar.** `HandleInertiaRequests` membagikan prop `academic` (`tahun`, `semester`; `null` bila tidak ada tahun ajaran aktif) untuk menggantikan judul halaman yang dobel di topbar. Dites di `tests/Feature/AcademicPeriodShareTest.php`.
- **D7. Grid input bersama.** Kelas global `.grid-sticky-col`, `.identity-cell` (`.identity-name`/`.identity-meta`), `.sticky-savebar` (+ `-standalone`, `-status`, `.dirty-dot`) di `ruang-kelas.css`, dipakai Nilai, Sikap, Absensi, dan Absensi Wali Kelas.
- **D8. Tabel daftar menjadi kartu di HP.** `<TableWrapper stack>` + `data-label` pada `<td>`; `.stack-title` = judul kartu, `.stack-actions` = baris tombol, `.stack-hide` = kolom nomor. Tanpa menduplikasi markup.
- **D9. `PageHeader` punya prop `eyebrow`** untuk konteks (mis. "Kelas & Mapel"); halaman non-dashboard tidak lagi memakai `DashboardHero`.
- **D6. Halaman contoh sebagai acuan pola.** Pola daftar (tabel desktop + kartu HP) dari `Siswa/Tugas/Index.vue` dan pola grid input (kolom identitas tetap + bilah simpan menempel) dari `Guru/Nilai/Input.vue` dipakai ulang di fase berikutnya.

## 4. Fase dan butir pekerjaan

### Fase 0 — Fondasi (shell, token, komponen inti)

- [x] Token baru `ruang-kelas.css`: permukaan, teks, border, status, radius, bayangan overlay, kepadatan, terang + gelap.
- [x] Ganti import `modern.css` → `ruang-kelas.css` di `app.ts` dan `inertia.ts`; Aurora tidak dimuat lagi (`modern.css` masih ada di repo, dihapus di Fase 5).
- [x] AppShell: sidebar solid (aksen tema pada item aktif), hapus kartu identitas duplikat dan kartu "LMS Sekolah/Tahun" di kaki sidebar, kontras label seksi sidebar lolos AA. Nama sekolah kini judul sidebar.
- [x] Topbar ringkas: tidak mengulang judul halaman (diganti tahun ajaran + semester aktif), nama sekolah tanpa awalan "LMS" di mobile, tombol tema pindah ke menu akun di layar < 576 px.
- [x] Navigasi bawah mobile: 4 tujuan prioritas per peran (`mobileNav()` di `sidebarMenu.ts`) + tombol "Menu" pembuka sidebar, label pendek dan utuh.
- [x] `PageHeader` ringkas (judul kini `<h1>`); `DashboardHero` jadi sapaan datar tanpa panel, gradien, atau ikon dekoratif. Prop `icon`/`tone` dibiarkan untuk kompatibilitas.
- [x] Komponen inti lewat CSS: Card, Button, Badge, StatCard/metrik (2 kolom di HP), tabel, form control, Modal, dropdown, palet perintah, EmptyState, panel antrean. Label role lewat `utils/roles.ts` dengan badge netral. Toast belum dicek visual (dicek di Fase 1).
- [x] Gutter halaman simetris; tidak ada konten terpotong di tepi kanan.
- [x] 3 halaman contoh untuk review: Dashboard Guru, Input Nilai (kolom siswa tetap, satu bilah simpan menempel dengan indikator belum tersimpan), Tugas Saya (kartu di HP + perbaikan hitungan `belum`).
- [x] Verifikasi fase (lihat bagian 6 dan log progres).
- [x] **Titik review pemilik proyek.** Disetujui 2026-10-09; di-commit `5df5f7f` di branch `ruang_kelas`.

### Fase 1 — Guru

- [x] Dashboard Guru: hero ringkas, panel antrean ringkas, kedua grafik memakai warna tema (`--app-primary`) dan grid `--border-soft`, label "Tren" ganda dibuang.
- [x] Input Nilai: kolom NIS/nama tetap saat digeser, satu tombol simpan dengan indikator "ada perubahan belum disimpan", tidak ada kolom terpotong. (Selesai di Fase 0 sebagai halaman contoh.)
- [x] Input Sikap dan indeks Sikap: kolom siswa tetap, satu bilah simpan menempel dengan indikator, select ringkas di sel tabel, label aksesibel.
- [x] Absensi: header ringkas, kolom siswa + baris "isi satu kolom" tetap, bilah simpan menempel (label "Simpan Absensi" dipertahankan untuk tes), ekspor diseragamkan. Rekap absensi lolos pemeriksaan tanpa perubahan struktur.
- [x] Tugas: judul "Tugas" (bukan "Penugasan Guru"), aksi "Buka kelas pertama" dibuang, ikon cari tidak lagi menimpa placeholder (bug lama `:deep`), daftar & pengumpulan memakai header ringkas, input nilai berlabel.
- [x] Materi: header ringkas, tabel menjadi kartu di HP.
- [x] Ujian CBT (indeks, daftar, builder, hasil, detail jawaban) dan Bank Soal: header ringkas, tabel menjadi kartu di HP, tab pil mengikuti warna sekolah, ekspor netral, input berlabel.
- [x] Jadwal Mengajar, Kelas Daring, Wali Kelas (absensi dengan pola grid, pertemuan & penanganan sebagai kartu di HP, biodata).
- [x] Chat, Notifikasi, Kalender ("Linimasa Akademik"), Pengumuman (header `PageHeader`, tanggal kosong tidak lagi "1/1/1970", label target manusiawi; halaman ini dipakai juga oleh admin & kepsek).
- [x] Verifikasi fase (lihat log progres).
- [ ] **Titik review pemilik proyek.**

### Fase 2 — Siswa

- [ ] Dashboard Siswa.
- [ ] Tugas Saya: daftar kartu di mobile; perbaiki hitungan "belum dikumpulkan" (`Siswa/Tugas/Index.vue`, `openTasks` tidak menghitung status `belum`).
- [ ] Detail tugas dan pengumpulan.
- [ ] Ujian CBT: daftar, layar pengerjaan (ringan di HP murah, tanpa blur), hasil.
- [ ] Nilai, Progress, Jadwal, Kelas Daring, Materi, Kalender, Pengumuman, Chat, Notifikasi.
- [ ] Verifikasi fase.
- [ ] **Titik review pemilik proyek.**

### Fase 3 — Admin

- [ ] Dashboard Admin.
- [ ] Kelas & Siswa: daftar siswa sebagai konten utama; import, tambah siswa, dan kelulusan dipindah ke aksi/panel; `<select id="status">` diberi label.
- [ ] Guru & Staf, Data Kelas, Mata Pelajaran, Penugasan Guru, Tahun Ajaran.
- [ ] Pengaturan Sistem dan Pengaturan Sekolah: dibagi tab/seksi dengan navigasi.
- [ ] Rekap (absensi, nilai, sikap, tugas). Judul tab `/admin/rekap/*` sudah diisi di Fase 1 (bersama perbaikan judul Rekap Nilai/Sikap guru).
- [ ] Log login, log error, log akademik, IP terblokir.
- [ ] Verifikasi fase.
- [ ] **Titik review pemilik proyek.**

### Fase 4 — Kepala Sekolah, Login, halaman error

- [ ] Dashboard Kepsek: grafik absensi skala bilangan bulat dan warna token; tabel login tidak terpotong.
- [ ] Statistik, Laporan (absensi, nilai, wali kelas, rekap), Performa Guru.
- [ ] Login: tata letak baru sesuai prinsip, letter-spacing normal, chip peran tidak tampak bisa diklik, judul tab tidak dobel.
- [ ] Halaman error (`errors/status.blade.php`) tanpa mesh-gradien/kaca.
- [ ] Pengaturan Akun (semua peran).
- [ ] Verifikasi fase.
- [ ] **Titik review pemilik proyek.**

### Fase 5 — Penutup

- [ ] Gabungkan CSS lama menjadi satu sistem; hapus aturan yang tak lagi dipakai, termasuk `modern.css` dan `public/css/login-isolation.css` bila tidak diperlukan.
- [ ] Ganti warna hex hardcoded di komponen Vue dengan token.
- [ ] Audit axe-core di semua rute: tanpa pelanggaran serius/kritis.
- [ ] Semua rute di `tests/Browser/migration.spec.ts` dicek di 390 px: tanpa overflow horizontal.
- [ ] Cek kelima tema sekolah (hijau, biru-azure, biru-aqua, indigo, marun) terang dan gelap.
- [ ] Perbarui `docs/UI_REDESIGN_AURORA.md` (status: digantikan) dan dokumentasi terkait.
- [ ] **Review akhir pemilik proyek, lalu PR.**

## 5. Temuan untuk fase berikutnya

- `Admin/Pengumuman/Show.vue` dan `Kepsek/Pengumuman/Show.vue` punya dua `<h1>` (judul header + judul konten); `Siswa/Ujian/Hasil.vue` perlu dicek. `Siswa/Nilai` tidak punya `<h1>`.
- Dashboard Guru: empty state antrean sudah ringkas; susunan final (urutan seksi, grafik dari token) tetap di Fase 1.
- Tes browser `grade paste and attendance save` gagal **sejak sebelum redesain**: commit `3c81887` (2026-09-30, "Remove Decimal di Menu Nilai") membulatkan tampilan nilai, sedangkan tes masih mengharapkan `81.5`. Perlu keputusan pemilik proyek: tes diselaraskan dengan pembulatan, atau pembulatan ditinjau ulang. Bukan bagian redesain.
- Tes browser `searchable select opens upward at the last option` gagal sejak sebelum redesain (`kelas_id` 2 vs 1), sudah tercatat di `docs/UI_REDESIGN_AURORA.md`.

- Lingkungan evaluasi: server uji dengan `APP_ENV=testing` di HTTP memicu CSP `upgrade-insecure-requests`, sehingga redirect setelah simpan di Chrome naik ke HTTPS dan gagal. Bukan bug aplikasi (lokal `APP_ENV=local` dan produksi HTTPS tidak terdampak); verifikasi alur simpan memakai server `APP_ENV=local` dengan SQLite terisolasi yang sama.
- `Guru/Tugas/Pengumpulan`: di HP tombol Excel/PDF masih bertumpuk penuh (fungsional; dirapikan bila ada waktu di Fase 5).
- Rekap Nilai/Sikap guru dan Rekap admin sebelumnya tanpa `<Head>` (judul tab hanya "LMS Sekolah"): sudah diperbaiki.
- Ikon `bi-journal-fill` tidak ada di Bootstrap Icons (kartu metrik kosong) di workspace guru dan siswa: diganti `bi-journal-check`. Pemindaian seluruh nama ikon: tidak ada lagi yang hilang.

## 6. Definisi selesai per fase

Sebuah fase baru boleh dicentang selesai bila semua ini sudah dijalankan dan hasilnya dicatat di log progres:

- `npm run typecheck` dan `npm run build` hijau.
- `php artisan test` hijau (untuk fase yang menyentuh backend).
- `npm run test:browser` dijalankan; kegagalan yang sudah ada sebelumnya dicatat, bukan disembunyikan.
- Cek visual Playwright di 1440 px dan 390 px, terang dan gelap, untuk halaman fase tersebut; tanpa overflow horizontal.
- axe-core di halaman fase tersebut tanpa pelanggaran serius/kritis baru.
- Verifikasi memakai server dan SQLite terisolasi (`tests/Browser/seed.php`), bukan database pengembangan.

## 7. Log progres

| Tanggal | Fase | Catatan |
|---|---|---|
| 2026-10-09 | — | Evaluasi UI selesai; arah Ruang Kelas Digital disetujui; branch kerja dibuat (kini `ruang_kelas`); dokumen ini ditulis. |
| 2026-10-09 | 0 | Fondasi selesai, disetujui dan di-commit `5df5f7f`. Verifikasi: `npm run typecheck` hijau; `npm run build` hijau; `php artisan test` (phpunit) 148/148 lolos; `npm run test:browser` 23 lolos, 2 gagal (keduanya kegagalan lama, lihat bagian 5); 58 rute `migration.spec.ts` di 390 px tanpa overflow dan tanpa error JS; axe-core (WCAG 2 A/AA) 0 pelanggaran di Dashboard Guru, Input Nilai (1440 dan 390), Dashboard Siswa, Tugas Saya (terang dan gelap); Pint lolos untuk berkas PHP yang diubah. Belum di-commit. |
| 2026-10-09 | 1 | Semua halaman guru selesai, menunggu review. Verifikasi: `npm run typecheck` hijau; `npm run build` hijau; phpunit 148/148 lolos; `npm run test:browser` 23 lolos, 2 gagal (kegagalan lama yang sama; \"grade paste\" tetap lolos tahap tempel→simpan→toast, gagal hanya pada pembulatan 81,5); 35 rute guru di 1440 & 390 px: 200, tanpa overflow, satu `<h1>`, hero hanya di dashboard, berjudul, tanpa error JS; axe-core (WCAG 2 A/AA) 0 pelanggaran serius/kritis di 35 rute × terang/gelap (sebelumnya 13 jenis, mayoritas sudah ada sebelum redesain: kontrol grid tanpa label, progressbar tanpa nama, tombol ikon tanpa nama, 3 masalah kontras); toast terlihat di terang/gelap dan di atas nav bawah HP; Pint lolos. Data contoh hanya ditambahkan ke SQLite sementara server uji. Belum di-commit. |

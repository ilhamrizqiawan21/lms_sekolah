# LMS Sekolah — Handoff Implementasi UI

**Arah visual:** Modern Educational SaaS  
**Ruang lingkup:** UI-only, dashboard-first, light/dark mode menyeluruh  
**Repo acuan:** [ilhamrizqiawan21/lms_sekolah](https://github.com/ilhamrizqiawan21/lms_sekolah)  
**Snapshot repo:** `main` pada commit [`aafa20b`](https://github.com/ilhamrizqiawan21/lms_sekolah/commit/aafa20b546107850229402661e74cc53a0e923df), 28 Oktober 2026  
**Status bundel:** siap dipakai Codex untuk implementasi bertahap

## 1. Konteks dan keputusan desain

Bundel ini melanjutkan percakapan **“Rekomendasi Mockup Gratis”**. Keputusan yang dibawa ke implementasi:

- Setiap halaman harus memiliki pasangan light dan dark.
- Pengguna mengganti tema melalui satu switch global; bukan toggle terpisah per halaman.
- Implementasi dimulai dari empat dashboard: Admin, Guru, Siswa, dan Kepala Sekolah.
- Mockup adalah referensi komposisi dan hirarki, bukan sumber data atau logika.
- Tidak mengubah route, authorization, controller, model, migration, data, seeder produksi, atau deployment.

Kompatibilitas dengan repo: gunakan fondasi **Ruang Kelas Digital** yang sudah ada—surface solid, token terpusat, tanpa glass/mesh/blur, tanpa gradient per role, tabel responsif, dan aksesibilitas. Permintaan baru yang diterapkan di atas fondasi itu adalah sidebar navy dan framing Modern Educational SaaS yang lebih konsisten.

## 2. Snapshot repo yang harus dihormati

Repo adalah Laravel 13 + Vue 3 + Inertia + Bootstrap 5.3 + Vite. Struktur UI penting yang sudah tersedia:

- `resources/js/theme.ts` menangani `data-bs-theme`, `localStorage` key `lms.color-mode`, preferensi sistem, `aria-pressed`, dan halaman login yang dipaksa light.
- `resources/js/app.ts` dan `resources/js/inertia.ts` sudah memuat `resources/css/ruang-kelas.css`.
- `resources/js/Components/AppShell/sidebarMenu.ts` adalah sumber menu empat role dan mobile navigation.
- `resources/js/Layouts/AppShell.vue`, `Components/AppShell/Sidebar.vue`, `Topbar.vue`, `PageHeader.vue` adalah shell bersama.
- `resources/js/Components/UI/` sudah memiliki `Card`, `Button`, `Badge`, `MetricStrip`, `DashboardHero`, `ActionQueue`, `CourseCard`, `TableWrapper`, `EmptyState`, `Modal`, dan komponen terkait.
- `TableWrapper` mendukung mode tabel desktop dan `stack` untuk kartu pada layar kecil.
- Role yang wajib dipertahankan: `admin`, `guru`, `siswa`, `kepala_sekolah`.
- Tidak perlu dependency baru. Plus Jakarta Sans, Bootstrap Icons, Chart.js, dan komponen yang ada sudah cukup.

Dokumen repo yang menjadi acuan teknis:

- [AGENTS.md](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/AGENTS.md)
- [AI_RULES.md](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/AI_RULES.md)
- [REDESIGN_RUANG_KELAS_TODO.md](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/REDESIGN_RUANG_KELAS_TODO.md)
- [LMS_MODERN_UI_TODO.md](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/LMS_MODERN_UI_TODO.md)
- [FRONTEND_CONTRAST_CHECKLIST.md](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/FRONTEND_CONTRAST_CHECKLIST.md)

## 3. Design system — Modern Educational SaaS

### 3.1 Karakter

Modern, tenang, dan enterprise-ready untuk pekerjaan sekolah sehari-hari. Identitas datang dari logo, nama sekolah, tahun ajaran, aksen brand sekolah, dan data akademik—bukan dari dekorasi berlebihan.

Aturan karakter:

- Navy dipakai untuk shell/sidebar, bukan sebagai warna semua komponen.
- Aksen sekolah dipakai untuk active state, CTA utama, tautan, fokus, dan grafik.
- Status hijau/kuning/merah/biru hanya menyampaikan makna.
- Surface datar, border tipis, radius terkontrol, shadow hanya untuk overlay.
- Hindari hero tinggi, gradient text, glassmorphism, blur, dan animasi masuk serentak.

### 3.2 Token visual yang disarankan

Nama token menyesuaikan token yang sudah ada; bila token ekuivalen sudah tersedia, gunakan token repo tersebut dan jangan membuat duplikat.

| Kelompok | Light | Dark | Catatan |
|---|---|---|---|
| Sidebar navy | `#102A43` | `#0B1F33` | Solid, kontras tinggi, tetap ringan secara visual |
| Sidebar teks | `#D9E7F5` | `#D9E7F5` | Teks utama dan sekunder dibedakan dengan opacity/token |
| Canvas | `#F6F8FB` | `#0B1220` | Latar kerja, bukan putih murni |
| Surface | `#FFFFFF` | `#111C2E` | Kartu, panel, dropdown |
| Surface muted | `#F1F5F9` | `#1B2A3D` | Header tabel, row hover, filter |
| Text strong | `#172033` | `#F8FAFC` | Judul dan angka utama |
| Text body | `#334155` | `#D9E3EF` | Isi utama |
| Text muted | `#5B6B82` | `#9FB0C5` | Keterangan; tetap AA |
| Border | `#E2E8F0` | `rgba(148,163,184,.22)` | Garis tipis |
| Focus | Aksen sekolah | Aksen sekolah terang | Ring minimum 3 px terlihat |
| Primary | `--app-primary` | `--text-brand`/pasangan gelap | Ikuti tema sekolah yang sudah ada |

Token bentuk dan ukuran:

- Font: `Plus Jakarta Sans Variable`, fallback sistem.
- Body: 14–16 px; label: 12–13 px; judul halaman: 24–32 px; angka metrik: 24–30 px.
- Sidebar: 248 px desktop; drawer pada `< 992 px`.
- Topbar: 60–64 px.
- Kontrol: 38 px; tombol utama maksimal 44 px bila konteks penting.
- Radius kartu: 10–12 px; kontrol: 6–8 px; badge: full.
- Spacing dasar: 4 / 8 / 12 / 16 / 20 / 24 / 32 px.
- Angka nilai, jumlah, tanggal, dan tabel: `font-variant-numeric: tabular-nums`.
- Motion: 150–200 ms untuk hover/focus dan perubahan state; hormati `prefers-reduced-motion`.

### 3.3 Shell dan layout

```text
┌──────────────────────┬────────────────────────────────────────────────┐
│ Logo sekolah         │ Topbar: konteks · tahun ajaran · search · bell │
│                      ├────────────────────────────────────────────────┤
│ Master Data          │ PageHeader / Dashboard greeting                │
│ Dashboard            │                                                │
│ Kelas & Siswa        │ Metric strip                                   │
│ ...                  │                                                │
│                      │ Main workspace: 2 kolom desktop                │
│ Komunikasi           │ - antrean / tabel / grafik                      │
│ Kalender             │ - agenda / quick action                         │
│                      │                                                │
│ Profil + akun        │ Footer                                          │
└──────────────────────┴────────────────────────────────────────────────┘
```

Alignment: konten utama left-aligned, angka tabel right-aligned, action area tetap di kanan pada desktop dan turun penuh pada mobile. Dashboard boleh memakai grid, halaman CRUD dan laporan mengutamakan scanability tabel.

### 3.4 Dark mode menyeluruh

Kontrak implementasi:

- Pertahankan `theme.ts` sebagai sumber perilaku tema.
- Switch di topbar desktop dan menu akun mobile memakai selector `[data-theme-toggle]` yang sudah ada.
- Toggle memperbarui icon, label, `title`, `aria-pressed`, `meta[name="theme-color"]`, dan `data-bs-theme`.
- Preferensi tersimpan di `localStorage` key `lms.color-mode`.
- Semua warna komponen berasal dari token; tidak boleh ada `background: white`, `#fff`, atau warna status hardcoded yang membuat dark mode silau.
- Login publik tetap light sesuai kontrak repo; aplikasi setelah login mengikuti preferensi pengguna.
- Uji switch di dashboard, tabel, modal, dropdown, toast, empty state, form, grafik, mobile drawer, dan bottom nav.

### 3.5 Komponen inti

1. **Sidebar:** navy solid, logo sekolah, section label, item aktif memakai aksen sekolah, collapse/drawer mobile, menu role-aware.
2. **Topbar:** breadcrumb konteks, tahun ajaran/semester, quick search/command palette, notifikasi, theme switch, account menu.
3. **PageHeader:** eyebrow konteks, satu `<h1>`, ringkasan satu baris, action kanan.
4. **DashboardHero:** sapaan singkat dan konteks hari ini; tidak lebih tinggi dari kebutuhan konten.
5. **MetricStrip:** 4 metrik utama; card sederhana, angka kuat, label jelas, icon tidak dekoratif berlebihan.
6. **Table:** header kontras, sticky header bila relevan, identitas/sticky column untuk grid nilai/absensi, angka tabular, action berlabel atau tooltip.
7. **Mobile table:** gunakan `TableWrapper stack` + `data-label`; jangan menduplikasi markup desktop.
8. **State:** loading, empty, error, disabled, validation, success, dan destructive confirmation harus terlihat di light/dark.

## 4. Peta delapan kelompok UI/mockup

Satu kelompok adalah satu pola visual yang dapat dipakai ulang pada beberapa menu. Nama menu dan route tetap mengikuti `sidebarMenu.ts` serta route Laravel yang sudah ada.

| # | Kelompok | Menu/halaman yang dicakup | Pola reusable | Referensi mockup |
|---:|---|---|---|---|
| 1 | Shell, navigasi, tema | AppShell, Sidebar, Topbar, PageHeader, akun, mobile nav | Navy shell, breadcrumb, theme switch, command palette, role-aware nav | Semua mockup; terutama [Dashboard Guru](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-dashboard-guru.html) |
| 2 | Dashboard empat role | `/admin/dashboard`, `/guru/dashboard`, `/siswa/dashboard`, `/kepsek/dashboard` | Greeting, metric strip, action queue, chart/table, quick actions | [Dashboard Guru](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-dashboard-guru.html) |
| 3 | Master data & CRUD enterprise | Users, Data Kelas, Kelas & Siswa, Mata Pelajaran, Kelas Mapel, Tahun Ajaran | Filter bar, table/card responsive, import/form panel, pagination, export | [CRUD Kelas & Siswa](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-crud-kelas-siswa.html), [Penugasan Guru](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-penugasan-guru.html) |
| 4 | Learning workspace | Kelas Mapel, Materi, Tugas, Kelas Daring | Context header, tab/panel, deadline queue, course card, feedback | [Materi Guru](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-materi-guru.html), [Tugas Guru](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-tugas-guru.html) |
| 5 | Assessment & grading | Ujian CBT, Bank Soal, Nilai, Sikap, hasil ujian | Dense input grid, sticky identity column, save bar, status tabs, result table | [Tugas Guru](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-tugas-guru.html) sebagai pola antrean dan status |
| 6 | Attendance & reporting | Absensi, Rekap Absensi/Nilai/Sikap/Tugas, Laporan, Performa Guru | Chart + table, date/filter controls, export actions, tabular numbers | [Dashboard Guru](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-dashboard-guru.html) |
| 7 | Communication & calendar | Pengumuman, Chat Kelas, Kalender, Notifikasi | Timeline/list, unread state, announcement card, message workspace | [Chat Guru](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-chat-guru.html), [Kalender](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-kalender.html), [Pengumuman](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-pengumuman.html) |
| 8 | Account, system & security | Pengaturan Akun, Pengaturan Sistem, Log Login, Log Error, Log Akademik, IP Diblokir | Settings sections, save state, audit table, severity/status badges | [Pengaturan Guru](https://github.com/ilhamrizqiawan21/lms_sekolah/blob/main/docs/mockups/2026-ui/mockup-pengaturan-guru.html) |

Mockup yang tersedia di repo berstatus referensi visual dan sebagian HTML-nya masih menyatakan `color-scheme: light`. Saat implementasi, setiap pola di atas wajib diberi dark state dari token yang sama; teks contoh, nama siswa, angka, dan tanggal tidak boleh masuk sebagai data aplikasi.

Catatan sumber: preview percakapan referensi hanya membawa keputusan dan content reference, bukan lampiran mockup yang dapat diekspor ulang. Karena itu, bundel ini memakai mockup yang tersedia di repo pada `docs/mockups/2026-ui` sebagai set referensi yang dapat diverifikasi dan langsung dibuka Codex.

## 5. Tahapan implementasi — mulai dashboard empat role

### Fase 0 — Fondasi visual dan shell

Tujuan: memastikan dashboard dapat dikerjakan tanpa membuat variasi layout baru.

- Review token yang sudah ada di `tokens.css`/`ruang-kelas.css`; konsolidasikan token navy sidebar, surface, border, text, status, focus, dan density.
- Pastikan theme switch global tetap berjalan di light/dark tanpa mengubah kontrak `theme.ts`.
- Selaraskan `Sidebar`, `Topbar`, `PageHeader`, `DashboardHero`, `MetricStrip`, `Card`, `Badge`, `TableWrapper`, `EmptyState`, dan `ActionQueue`.
- Pastikan sidebar tetap role-aware dan capability-aware; jangan menampilkan menu yang tidak diberi server.

### Fase 1 — Dashboard Admin

Target file utama: `resources/js/Pages/Admin/Dashboard.vue`.

- Fokus visual: health operasional sekolah, metric strip, aktivitas terbaru, pengumuman, dan quick actions.
- Pertahankan props, route, query, authorization, dan data login terbaru yang sudah diberikan backend.
- Tabel login terbaru memakai pola enterprise table dan kartu di mobile.
- Empty/loading/error state harus mencerminkan data aktual, bukan placeholder permanen.

### Fase 2 — Dashboard Guru

Target file utama: `resources/js/Pages/Guru/Dashboard.vue`.

- Fokus visual: teaching cockpit—jadwal mengajar, antrean tugas/pengumpulan, kelas/mapel, tren atau grafik yang sudah tersedia.
- Pertahankan capability wali kelas dan semua link ke workspace guru.
- Aksi utama yang terlihat: buka kelas, cek pengumpulan, isi absensi/nilai; jangan membuat alur baru.

### Fase 3 — Dashboard Siswa

Target file utama: `resources/js/Pages/Siswa/Dashboard.vue`.

- Fokus visual: tugas terdekat, kelas daring, notifikasi, course cards, progress belajar.
- Status tugas (`Selesai`, `Belum`, deadline) harus tetap berasal dari props yang ada.
- Mobile harus memprioritaskan Tugas, Ujian, Nilai sesuai `mobileNav()`.

### Fase 4 — Dashboard Kepala Sekolah

Target file utama: `resources/js/Pages/Kepsek/Dashboard.vue`.

- Fokus visual: ringkasan sekolah, statistik absensi, rata-rata nilai, pengumuman, dan login terbaru.
- Grafik membaca warna dari token CSS, bukan warna baru di komponen.
- Tabel tetap dapat dipindai di desktop dan berubah menjadi kartu/scroll yang aman di mobile.

### Fase 5 — Reuse ke delapan kelompok UI

Setelah empat dashboard disetujui, migrasikan pola yang sudah terbukti ke kelompok 3–8 secara bertahap. Urutan yang disarankan:

1. Master data & CRUD.
2. Learning workspace.
3. Assessment & grading.
4. Attendance & reporting.
5. Communication & calendar.
6. Account, system & security.

Setiap kelompok harus selesai sebagai satu pola reusable sebelum halaman berikutnya dipoles satu per satu.

## 6. Peta file implementasi Codex

### File yang kemungkinan disentuh

- `resources/css/tokens.css` atau token yang berlaku di `resources/css/ruang-kelas.css`.
- `resources/css/ruang-kelas.css` untuk shell, komponen bersama, responsive, dan dark state.
- `resources/js/Components/AppShell/Sidebar.vue`.
- `resources/js/Components/AppShell/Topbar.vue`.
- `resources/js/Components/AppShell/PageHeader.vue`.
- `resources/js/Components/UI/*` hanya bila API visual/state yang ada belum cukup.
- `resources/js/Pages/Admin/Dashboard.vue`.
- `resources/js/Pages/Guru/Dashboard.vue`.
- `resources/js/Pages/Siswa/Dashboard.vue`.
- `resources/js/Pages/Kepsek/Dashboard.vue`.
- `resources/js/theme.ts` hanya bila ada gap perilaku yang terverifikasi; jangan mengganti key, kontrak login light, atau mekanisme persistensi tanpa alasan.

### File yang tidak boleh disentuh untuk pekerjaan UI ini

- `app/Models/**`, `app/Services/**`, `database/migrations/**`, dan database produksi.
- Controller, Form Request, Policy, middleware, route, seeder produksi, dan konfigurasi deployment.
- Props, nama route, URL, authorization, dan query backend—kecuali ditemukan bug UI yang memerlukan laporan terpisah.
- Dependency manifest dan lockfile; tidak ada dependency baru yang dibutuhkan.

## 7. Checklist implementasi

### Kontrak dan scope

- [ ] Hanya UI/style/component yang berubah.
- [ ] Tidak ada perubahan business logic, data, migration, route, policy, atau produksi.
- [ ] Props Inertia dan URL route yang sudah ada tetap dipakai.
- [ ] Role `admin`, `guru`, `siswa`, `kepala_sekolah` tetap kompatibel.
- [ ] Capability `has_wali_kelas` tetap dihormati.
- [ ] Tidak ada dependency baru.

### Design system

- [ ] Plus Jakarta Sans dipakai konsisten.
- [ ] Sidebar navy solid dengan active state aksen sekolah.
- [ ] Light/dark memakai token yang sama; tidak ada white/hex hardcoded yang merusak dark mode.
- [ ] Theme switch global memiliki label, `aria-pressed`, icon, dan persistensi yang benar.
- [ ] Tabel enterprise punya header, density, angka tabular, pagination/filter, dan state yang jelas.
- [ ] Mobile menggunakan drawer/bottom nav dan tabel stack/scroll yang sudah tersedia.
- [ ] Fokus keyboard terlihat; reduced motion dihormati.

### Dashboard role

- [ ] Admin dashboard selesai dan teruji.
- [ ] Guru dashboard selesai dan teruji.
- [ ] Siswa dashboard selesai dan teruji.
- [ ] Kepala Sekolah dashboard selesai dan teruji.
- [ ] Keempat dashboard memiliki light + dark + mobile.
- [ ] Empty, loading, error, disabled, dan populated states ditinjau.
- [ ] Tidak ada horizontal overflow pada 390 px, 768 px, dan 1440 px.

### Verifikasi

- [ ] `npm run typecheck`.
- [ ] `npm run build`.
- [ ] `npm run test:browser` untuk alur UI yang disentuh, dengan data terisolasi.
- [ ] `php artisan test` hanya bila perubahan ternyata menyentuh kontrak backend; UI-only tidak mengklaim test backend.
- [ ] Pemeriksaan keyboard: Tab, Shift+Tab, Enter, Escape, drawer, dropdown, modal, theme switch.
- [ ] Pemeriksaan kontras WCAG AA untuk teks dan komponen pada kedua tema.
- [ ] Tinjau diff dan pastikan tidak ada file/data produksi yang ikut berubah.

## 8. Acceptance criteria

### Global

1. Pengguna dapat berpindah light/dark dari dashboard mana pun; semua shell, kartu, tabel, form, modal, dropdown, toast, grafik, dan empty state berubah tanpa reload.
2. Setelah reload atau navigasi Inertia, tema yang dipilih tetap tersimpan dan kontrol tema menyampaikan state yang benar lewat label dan `aria-pressed`.
3. Sidebar navy konsisten di empat role, tetapi menu, active state, dan capability tetap berasal dari konfigurasi role yang sudah ada.
4. UI tidak memperkenalkan route, data, kredensial, atau perilaku server baru.

### Dashboard empat role

1. Admin melihat ringkasan operasional dan aktivitas terbaru menggunakan data/props yang ada.
2. Guru melihat alur kerja mengajar, kelas/mapel, jadwal, dan antrean penilaian/pengumpulan yang sudah tersedia.
3. Siswa melihat tugas, kelas daring, notifikasi, materi/course, dan progress dari data yang sudah tersedia.
4. Kepala Sekolah melihat statistik sekolah, absensi, nilai, pengumuman, dan login terbaru dari data yang sudah tersedia.
5. Setiap aksi yang terlihat membawa pengguna ke route yang sudah ada dan tetap menghormati authorization server-side.

### Responsive dan aksesibilitas

1. Pada 1440 px, layout memiliki sidebar + workspace yang seimbang dan tabel dapat dipindai tanpa kartu berlebihan.
2. Pada 768 px, layout berubah ke tablet tanpa memotong header, filter, action, atau angka.
3. Pada 390 px, drawer/bottom nav tidak menutup konten, tabel memakai stack atau scroll yang diberi petunjuk, dan tidak ada overflow horizontal.
4. Semua control icon-only memiliki accessible name; setiap input/filter memiliki label atau `aria-label` yang bermakna.
5. Kontras teks biasa minimal 4.5:1 dan komponen/fokus minimal 3:1 pada light dan dark.
6. Motion berhenti atau diperkecil saat `prefers-reduced-motion: reduce` aktif.

## 9. Instruksi siap-tempel untuk Codex

> Implementasikan UI-only untuk LMS Sekolah berdasarkan `outputs/LMS_SEKOLAH_UI_HANDOFF.md`.
>
> Mulai dari fondasi shell/theme, lalu kerjakan berurutan: Dashboard Admin, Dashboard Guru, Dashboard Siswa, Dashboard Kepala Sekolah. Gunakan Plus Jakarta Sans, sidebar navy solid, enterprise table, responsive mobile, dan global light/dark switch. Pertahankan pola Ruang Kelas Digital: surface solid, token-first, tanpa glass/mesh/blur/gradient per role.
>
> Jangan mengubah business logic, data, route, controller, model, migration, authorization, dependency, atau production. Pertahankan props dan link yang sudah ada. Gunakan komponen bersama dan `TableWrapper` yang tersedia. Setelah tiap fase, jalankan check yang relevan dari `AGENTS.md`, periksa light/dark pada 1440/768/390 px, dan laporkan file yang berubah serta limitation aktual.

## 10. Definisi selesai bundel

Bundel ini selesai ketika Codex dapat mengimplementasikan empat dashboard dengan satu sumber token, satu perilaku theme switch, satu shell role-aware, dan satu pola responsive table tanpa memerlukan keputusan produk tambahan. Pekerjaan implementasi belum dilakukan oleh bundel ini dan tidak ada perubahan ke repo produksi yang dibuat.

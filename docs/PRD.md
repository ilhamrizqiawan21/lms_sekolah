# Product Requirements Document — LMS Sekolah

| | |
|---|---|
| **Dokumen** | Product Requirements Document (PRD) |
| **Produk** | LMS Sekolah — single-school Learning Management System |
| **Status** | Current product baseline |
| **Versi dokumen** | 2.0 |
| **Pemilik produk** | Ilham Rizqiawan |
| **Klasifikasi** | Confidential — proprietary, lihat [LICENSE](../LICENSE) |
| **Terakhir ditinjau** | 2026-09-24 |
| **Sumber kebenaran** | Kode di repository ini (route, controller/service, model, migration, policy, test). Jika dokumen ini berbeda dengan kode, kode yang menang. |

## Riwayat versi

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | 2026-09-21 | Baseline awal: ringkasan produk, scope fungsional, aturan domain. |
| 2.0 | 2026-09-24 | Menambahkan document control, executive summary, persona naratif, success metrics, risiko, dan glossary. Tidak mengubah scope fungsional dari v1.0. |

---

## 1. Executive summary

LMS Sekolah adalah aplikasi web berbasis Laravel + Inertia/Vue yang menyatukan pengelolaan akademik satu sekolah dalam satu sistem: identitas pengguna, struktur kelas/mapel, pembelajaran (materi, tugas, submission), evaluasi (absensi, nilai, sikap), komunikasi (chat, pengumuman, notifikasi, kalender), serta pelaporan (export PDF/Excel). Produk dirancang **per instalasi per sekolah** (single-tenant), dengan branding yang dapat dikonfigurasi tanpa mengubah kode sehingga satu basis kode dapat dipasang ulang untuk sekolah yang berbeda.

Empat role first-class — **Admin**, **Kepala Sekolah**, **Guru**, **Siswa** — masing-masing memiliki dashboard dan alur kerja terpisah, ditegakkan melalui authorization server-side (middleware, policy/gate, ownership check), bukan hanya disembunyikan di UI.

## 2. Masalah yang diselesaikan

Sekolah menengah di Indonesia umumnya mengelola aktivitas akademik (materi, tugas, nilai, absensi, sikap) secara manual atau tersebar di beberapa aplikasi/spreadsheet terpisah — menyulitkan guru mengelola banyak kelas, kepala sekolah memantau performa sekolah, dan siswa mengakses progres belajarnya sendiri. LMS Sekolah menyatukan proses ini ke satu sumber data yang konsisten, dengan alur kerja yang mengikuti struktur penilaian Kurikulum Merdeka (nilai sumatif, sikap spiritual/sosial) dan struktur pendampingan wali kelas yang umum di sekolah Indonesia.

## 3. Tujuan produk

- Menyediakan satu sumber data akademik yang konsisten untuk sekolah.
- Mengurangi pekerjaan administratif guru dan operator melalui workflow digital.
- Memberi siswa akses terpusat ke materi, tugas, nilai, jadwal, dan komunikasi.
- Menyediakan laporan akademik yang dapat diekspor ke PDF dan Excel.
- Menjaga kontrol akses berbasis role, integritas data, dan jejak audit.

## 4. Success metrics (indikatif)

Karena produk ini dipasang per instalasi (bukan SaaS terpusat dengan analytics bawaan), metrik berikut diukur secara kualitatif/manual per instalasi hingga instrumentasi produk tersedia:

| Metrik | Target arah | Cara ukur saat ini |
|---|---|---|
| Adopsi guru | Mayoritas guru aktif mengisi absensi & nilai tiap bulan berjalan | Cek rutin tabel `absensi`/`nilai_akhir` vs jumlah `kelas_mapel` aktif |
| Ketepatan waktu pengumpulan tugas | Meningkatnya rasio status `sudah`/`dinilai` dibanding `belum`/`terlambat` | Rekap `pengumpulan_tugas` per kelas-mapel |
| Stabilitas sistem | Nol insiden downtime tak terjadwal per bulan | Log server, `system_errors` |
| Kepercayaan data | Nol laporan ketidaksesuaian nilai/absensi dari kepala sekolah/wali kelas | Umpan balik manual, audit log akademik |
| Kualitas rilis | Test suite hijau di setiap PR sebelum merge | CI (`.github/workflows/ci.yml`) |

## 5. Persona

### 5.1 Admin — Operator sekolah
Bertanggung jawab menyiapkan data master (tahun ajaran, kelas, mapel, penugasan guru), mengelola akun pengguna, mengatur branding/identitas sekolah, dan menjalankan export data lintas kelas. Biasanya staf tata usaha atau operator sekolah, tidak selalu berlatar belakang teknis — sehingga alur admin harus dapat dilakukan tanpa menyentuh kode/database langsung.

### 5.2 Kepala Sekolah — Pemantau
Memerlukan gambaran cepat kondisi sekolah: statistik kehadiran, nilai, performa guru, dan pengumuman — tanpa perlu mengelola data mentah. Fokusnya monitoring dan pengambilan keputusan, bukan input data harian.

### 5.3 Guru — Pengguna paling sering
Mengelola kelas-mapel yang diampu: mempublikasikan materi, membuat tugas, menilai submission, mengisi absensi tiap pertemuan, menilai sikap, dan (bila menjadi wali kelas) memantau serta menindaklanjuti kondisi siswa. Guru adalah persona dengan frekuensi penggunaan tertinggi dan paling sensitif terhadap friksi UI.

### 5.4 Siswa — Konsumen akhir
Mengakses materi, mengumpulkan tugas (teks/multi-file), melihat nilai dan progres belajar, memeriksa jadwal dan kalender, serta berkomunikasi lewat chat kelas dan menerima pengumuman/notifikasi.

## 6. Kebutuhan utama per role

| Role | Kebutuhan utama |
|---|---|
| Admin | Mengelola pengguna, master akademik, penugasan guru, pengaturan sekolah, sistem, dan export. |
| Kepala Sekolah | Melihat dashboard, statistik, laporan, performa guru, kalender, dan pengumuman. |
| Guru | Mengelola kelas-mapel, materi, tugas, penilaian, absensi, sikap, wali kelas, chat, dan notifikasi. |
| Siswa | Mengakses materi, mengumpulkan tugas, melihat progress/nilai, jadwal, kalender, chat, dan notifikasi. |

## 7. Ruang lingkup fungsional

### 7.1 Identitas dan akses

- Login berbasis username/email dan password.
- Empat role aplikasi: `admin`, `guru`, `siswa`, dan `kepala_sekolah`.
- Aktivasi/nonaktifkan pengguna, foto profil, dan pengaturan akun.
- Pembatasan endpoint melalui middleware, policy/gate, dan ownership check.
- Pengamanan tambahan: rate limit, blocked IP, security headers, forced password change, dan login/error audit.

### 7.2 Administrasi akademik

- Tahun ajaran dan semester aktif.
- Kelas, siswa, mata pelajaran, guru-mapel, dan kelas-mapel.
- Wali kelas beserta absensi, pertemuan, dan penanganan siswa.
- Jadwal mengajar dan kelas daring.
- Import siswa menggunakan template Excel.

### 7.3 Pembelajaran dan evaluasi

- Materi per kelas-mapel, termasuk file materi.
- Tugas dengan tenggat, kategori nilai, jawaban teks, dan upload multi-file.
- Status pengumpulan: `belum`, `sudah`, `terlambat`, `dinilai`, dan `perlu_perbaikan`.
- Penilaian tugas, nilai akhir (gaya Kurikulum Merdeka: sumatif 1-4, nilai harian, STS, SAS, SAT), rekap nilai, sikap spiritual, dan sikap sosial.
- Absensi per siswa dan kelas-mapel, termasuk presensi kelas daring.
- Progress siswa dan laporan akademik.

### 7.4 Komunikasi dan operasional

- Chat per kelas-mapel.
- Pengumuman global, role-based, atau kelas-mapel.
- Notifikasi dan status sudah dibaca.
- Kalender personal dan kegiatan sekolah.
- Persiapan pesan WhatsApp dan pencatatan status pengiriman; pengiriman aktual tetap bergantung pada integrasi/provider yang dikonfigurasi (belum terhubung ke API WhatsApp pada baseline ini).

### 7.5 Branding dan pelaporan

- Nama, logo, favicon, kontak, kepala sekolah, warna tema, visi, misi, dan data legal sekolah.
- Export absensi, nilai, tugas, sikap, wali kelas, dan performa guru ke PDF/Excel.
- Identitas sekolah diterapkan pada tampilan dan dokumen export.

## 8. Persyaratan nonfungsional

- **Runtime:** PHP 8.3+, Laravel 13, Node.js 20+/22+, npm, dan MySQL 8 atau MariaDB 10.6+.
- **Maintainability:** perubahan schema wajib melalui migration; business logic lintas controller berada di service (`app/Services/`).
- **Security:** secret berada di environment, validasi request terpusat, authorization ditegakkan di server, dan upload dibatasi/ditinjau. Lihat `docs/PHASE-10-SECURITY.md`.
- **Reliability:** operasi import bersifat all-or-nothing; constraint database mencegah duplikasi akademik penting.
- **Usability:** antarmuka responsif dan role-first untuk alur Admin, Guru, Siswa, dan Kepala Sekolah.
- **Operability:** asset production dibangun dengan Vite; backup database dan writable directory wajib disiapkan oleh deployment.
- **Testability:** perubahan behavior harus disertai/diverifikasi test (`tests/Feature`, `tests/Unit`); CI menjalankan `composer validate`, `pint`, `artisan test`, dan `npm run build` pada setiap perubahan.

## 9. Aturan domain penting

- Satu instalasi hanya mewakili satu sekolah.
- `kelas_mapel` adalah konteks pengajaran utama: kelas, mata pelajaran, guru, tahun ajaran, dan semester.
- Satu siswa hanya memiliki satu submission untuk satu tugas; submission dapat memiliki banyak file.
- Nilai akhir dibatasi unik berdasarkan siswa, kelas-mapel, tahun ajaran, dan semester.
- Wali kelas dibatasi satu per kelas dan tahun ajaran.
- Tanggal pertemuan riil (untuk absensi & ekspor) bersumber tunggal dari `AttendanceScheduleService`, dihitung dari `jadwal_mengajar` dan hari libur (`calendar_events.is_holiday`) — bukan dari tanggal bebas yang diinput manual.
- Akses guru terhadap data akademik harus dibatasi pada kelas-mapel yang diampu atau wali kelas yang menjadi tanggung jawabnya.
- Data demo hanya untuk development/testing dan tidak boleh dipakai pada production.

## 10. Di luar scope saat ini

- Multi-tenant dalam satu database atau satu deployment.
- Billing, subscription, dan payment gateway (termasuk SPP/pembayaran sekolah).
- Portal orang tua/wali murid dengan akun terpisah.
- Mobile native application.
- Video conference provider built-in.
- Pengiriman WhatsApp production tanpa provider/API yang dikonfigurasi.
- SSO enterprise dan sinkronisasi otomatis dengan sistem sekolah eksternal (Dapodik, dsb).

## 11. Asumsi dan dependensi

- Sekolah pengguna memiliki minimal satu operator (admin) yang bersedia mengisi data master di awal instalasi.
- Hosting/infrastruktur (server, database, HTTPS, backup) disediakan/dikelola oleh pihak yang memasang instalasi, bukan oleh produk ini.
- Kurikulum yang diasumsikan adalah Kurikulum Merdeka (struktur nilai sumatif + sikap spiritual/sosial); sekolah dengan kurikulum lain perlu penyesuaian struktur nilai.
- Pengiriman WhatsApp aktual bergantung pada provider pihak ketiga yang belum termasuk dalam baseline ini.

## 12. Risiko

| Risiko | Dampak | Mitigasi saat ini / yang disarankan |
|---|---|---|
| Instalasi single-tenant menyulitkan skala ke banyak sekolah sekaligus | Operasional lebih berat saat jumlah sekolah bertambah | Tetap by design untuk baseline ini; konversi multi-tenant adalah item roadmap terpisah |
| Ketergantungan pada satu maintainer/developer | Bus factor rendah untuk dukungan jangka panjang | Dokumentasi arsitektur (`docs/ARCHITECTURE.md`), test suite, dan PRD/ERD ini disiapkan agar developer lain dapat onboarding |
| Data akademik keliru akibat kesalahan input manual | Nilai/absensi tidak akurat, kepercayaan pengguna turun | Constraint database (unique key), audit log akademik, dan validasi request terpusat |
| Fitur WhatsApp disangka "otomatis kirim" padahal baru pencatatan | Ekspektasi pengguna tidak terpenuhi | Didokumentasikan eksplisit di scope (§10) dan PRD ini |

## 13. Indikator penerimaan baseline

- Setiap role dapat login dan hanya melihat workflow yang diizinkan.
- Admin dapat menyiapkan tahun ajaran, kelas, siswa, mapel, guru, dan kelas-mapel.
- Guru dapat mempublikasikan materi/tugas, menerima submission, mengisi absensi/nilai, dan menghasilkan rekap.
- Siswa dapat mengakses materi, mengumpulkan tugas, dan melihat hasilnya.
- Kepala Sekolah dapat membaca dashboard/laporan dan melakukan export.
- Migration dapat dijalankan pada database baru dan test suite memakai database terisolasi.

## 14. Glossary

| Istilah | Arti |
|---|---|
| Kelas-mapel | Konteks pengajaran: kombinasi kelas + mata pelajaran + guru + tahun ajaran + semester. |
| Wali kelas | Guru yang ditugaskan mendampingi satu rombongan belajar (kelas) untuk satu tahun ajaran; berbeda dari guru mata pelajaran biasa. |
| Sikap spiritual (KI-1) / sikap sosial (KI-2) | Komponen penilaian non-akademik pada Kurikulum Merdeka, dinilai per siswa per kelas-mapel per semester. |
| STS / SAS / SAT | Sumatif Tengah Semester / Sumatif Akhir Semester / Sumatif Akhir Tahun — komponen nilai akhir. |
| NIS | Nomor Induk Siswa — identitas unik siswa di sekolah. |
| Kelas daring | Sesi pembelajaran daring yang tercatat terhubung ke kelas-mapel dan (opsional) ke absensi hari itu. |
| Rombongan belajar (rombel) | Istilah umum untuk satu kelas sebagai satu kelompok siswa tetap. |

## 15. Sumber kebenaran

Dokumen ini menjelaskan baseline produk. Implementasi aktual ditentukan oleh route, controller/service, model, migration, policy/middleware, dan test. Jika dokumen berbeda dengan kode, verifikasi kode dan buat perubahan dokumentasi dalam PR yang sama. Untuk skema data detail, lihat [docs/ERD.md](ERD.md).

# Product Requirements Document — LMS Sekolah

**Status:** Current product baseline  
**Scope:** Single-school deployment  
**Last reviewed:** 2026-09-21

## 1. Ringkasan produk

LMS Sekolah adalah aplikasi web untuk mengelola kegiatan akademik dan pembelajaran digital dalam satu sekolah. Aplikasi menyatukan data pengguna, kelas, mata pelajaran, materi, tugas, pengumpulan, nilai, absensi, komunikasi, kalender, laporan, dan identitas sekolah.

Produk ini ditujukan untuk instalasi mandiri per sekolah. Multi-tenant SaaS, billing, subscription, dan integrasi identitas lintas sekolah bukan bagian dari scope saat ini.

## 2. Tujuan

- Menyediakan satu sumber data akademik yang konsisten untuk sekolah.
- Mengurangi pekerjaan administratif guru dan operator melalui workflow digital.
- Memberi siswa akses terpusat ke materi, tugas, nilai, jadwal, dan komunikasi.
- Menyediakan laporan akademik yang dapat diekspor ke PDF dan Excel.
- Menjaga kontrol akses berbasis role, integritas data, dan jejak audit.

## 3. Pengguna dan kebutuhan utama

| Role | Kebutuhan utama |
|---|---|
| Admin | Mengelola pengguna, master akademik, penugasan guru, pengaturan sekolah, sistem, dan export. |
| Kepala Sekolah | Melihat dashboard, statistik, laporan, performa guru, kalender, dan pengumuman. |
| Guru | Mengelola kelas-mapel, materi, tugas, penilaian, absensi, sikap, wali kelas, chat, dan notifikasi. |
| Siswa | Mengakses materi, mengumpulkan tugas, melihat progress/nilai, jadwal, kalender, chat, dan notifikasi. |

## 4. Ruang lingkup fungsional

### 4.1 Identitas dan akses

- Login berbasis username/email dan password.
- Empat role aplikasi: `admin`, `guru`, `siswa`, dan `kepala_sekolah`.
- Aktivasi/nonaktifkan pengguna, foto profil, dan pengaturan akun.
- Pembatasan endpoint melalui middleware, policy/gate, dan ownership check.
- Pengamanan tambahan: rate limit, blocked IP, security headers, forced password change, dan login/error audit.

### 4.2 Administrasi akademik

- Tahun ajaran dan semester aktif.
- Kelas, siswa, mata pelajaran, guru-mapel, dan kelas-mapel.
- Wali kelas beserta absensi, pertemuan, dan penanganan siswa.
- Jadwal mengajar dan kelas daring.
- Import siswa menggunakan template Excel.

### 4.3 Pembelajaran dan evaluasi

- Materi per kelas-mapel, termasuk file materi.
- Tugas dengan tenggat, kategori nilai, jawaban teks, dan upload multi-file.
- Status pengumpulan: belum, sudah, terlambat, dinilai, dan perlu perbaikan.
- Penilaian tugas, nilai akhir, rekap nilai, sikap spiritual, dan sikap sosial.
- Absensi per siswa dan kelas-mapel, termasuk presensi kelas daring.
- Progress siswa dan laporan akademik.

### 4.4 Komunikasi dan operasional

- Chat per kelas-mapel.
- Pengumuman global, role-based, atau kelas-mapel.
- Notifikasi dan status sudah dibaca.
- Kalender personal dan kegiatan sekolah.
- Persiapan pesan WhatsApp dan pencatatan status pengiriman; pengiriman aktual tetap bergantung pada integrasi/provider yang dikonfigurasi.

### 4.5 Branding dan pelaporan

- Nama, logo, favicon, kontak, kepala sekolah, warna tema, visi, misi, dan data legal sekolah.
- Export absensi, nilai, tugas, sikap, wali kelas, dan performa guru ke PDF/Excel.
- Identitas sekolah diterapkan pada tampilan dan dokumen export.

## 5. Persyaratan nonfungsional

- **Runtime:** PHP 8.3+, Laravel 13, Node.js 20+/22+, npm, dan MySQL 8 atau MariaDB 10.6+.
- **Maintainability:** perubahan schema wajib melalui migration; business logic lintas controller berada di service.
- **Security:** secret berada di environment, validasi request terpusat, authorization ditegakkan di server, dan upload dibatasi/ditinjau.
- **Reliability:** operasi import bersifat all-or-nothing; constraint database mencegah duplikasi akademik penting.
- **Usability:** antarmuka responsif dan role-first untuk alur Admin, Guru, Siswa, dan Kepala Sekolah.
- **Operability:** asset production dibangun dengan Vite; backup database dan writable directory wajib disiapkan oleh deployment.

## 6. Aturan domain penting

- Satu instalasi hanya mewakili satu sekolah.
- `kelas_mapel` adalah konteks pengajaran utama: kelas, mata pelajaran, guru, tahun ajaran, dan semester.
- Satu siswa hanya memiliki satu submission untuk satu tugas; submission dapat memiliki banyak file.
- Nilai akhir dibatasi unik berdasarkan siswa, kelas-mapel, tahun ajaran, dan semester.
- Wali kelas dibatasi satu per kelas dan tahun ajaran.
- Akses guru terhadap data akademik harus dibatasi pada kelas-mapel yang diampu atau wali kelas yang menjadi tanggung jawabnya.
- Data demo hanya untuk development/testing dan tidak boleh dipakai pada production.

## 7. Di luar scope saat ini

- Multi-tenant dalam satu database atau satu deployment.
- Billing, subscription, dan payment gateway.
- Mobile native application.
- Video conference provider built-in.
- Pengiriman WhatsApp production tanpa provider/API yang dikonfigurasi.
- SSO enterprise dan sinkronisasi otomatis dengan sistem sekolah eksternal.

## 8. Indikator penerimaan baseline

- Setiap role dapat login dan hanya melihat workflow yang diizinkan.
- Admin dapat menyiapkan tahun ajaran, kelas, siswa, mapel, guru, dan kelas-mapel.
- Guru dapat mempublikasikan materi/tugas, menerima submission, mengisi absensi/nilai, dan menghasilkan rekap.
- Siswa dapat mengakses materi, mengumpulkan tugas, dan melihat hasilnya.
- Kepala Sekolah dapat membaca dashboard/laporan dan melakukan export.
- Migration dapat dijalankan pada database baru dan test suite memakai database terisolasi.

## 9. Sumber kebenaran

Dokumen ini menjelaskan baseline produk. Implementasi aktual ditentukan oleh route, controller/service, model, migration, policy/middleware, dan test. Jika dokumen berbeda dengan kode, verifikasi kode dan buat perubahan dokumentasi dalam PR yang sama.

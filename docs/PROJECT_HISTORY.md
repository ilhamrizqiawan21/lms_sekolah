# Project History & Handoff

Dokumen ini menyimpan keputusan penting dan konteks handoff yang perlu diketahui pada sesi Codex berikutnya. Ini bukan pengganti kode, test, roadmap, atau issue tracker; klaim teknis harus tetap diverifikasi terhadap repository.

## Cara melanjutkan

Jika sesi CLI sebelumnya sudah berakhir, jalankan dari root repository:

```bash
codex resume
```

Jika sesi tidak muncul karena filter folder proyek:

```bash
codex resume --all
```

Pada thread baru, baca dokumen ini sebelum melanjutkan pekerjaan. Jangan menyimpan password, token, isi `.env`, atau data pribadi siswa di dokumen ini.

## Handoff terakhir — 2026-10-31

- Fokus berikutnya yang disepakati adalah mempelajari dan menyempurnakan e-Rapor.
- Paket Administrasi Guru sudah memiliki menu Kurikulum/ATP, Alokasi Waktu, Prota, Promes, KKTP/KKM, Modul Ajar, Jurnal Harian, dan Analisis Butir Soal.
- Data dummy Administrasi Guru tersedia melalui `DemoAdministrasiGuruSeeder`. Seeder hanya boleh dijalankan dengan `DEMO_MODE=true` dan diblokir di production.
- Migrasi dokumen kurikulum dan pengelompokan TP berdasarkan bab sudah tersedia pada migrasi `2026_10_31_000008` dan `2026_10_31_000009`.
- Ekspor Paket Administrasi Kurikulum sudah diperbaiki secara teknis, tetapi format hasilnya dinilai belum sesuai dan untuk sementara tidak menjadi fokus. Jangan melanjutkan penyempurnaan format ekspor sebelum ada rancangan yang disepakati.

## Rancangan e-Rapor yang sudah ada

- Wali kelas memilih semester lalu membuat draft rapor untuk siswa aktif.
- Draft mengambil snapshot nilai akhir, predikat, deskripsi awal, dan rekap sakit/izin/alpha.
- Wali kelas dapat menyunting deskripsi dan catatan wali kelas.
- Status `final` mengunci rapor dari perubahan.
- Siswa hanya dapat melihat rapor berstatus `final`.
- Cetak rapor saat ini menggunakan halaman cetak browser.
- Rujukan kode utama: `app/Http/Controllers/Guru/RaporController.php`, `resources/js/Pages/Guru/WaliKelas/Rapor.vue`, `resources/js/Pages/Guru/WaliKelas/RaporCetak.vue`, dan `resources/js/Pages/Siswa/Rapor.vue`.

## Kekurangan e-Rapor yang perlu dibahas sebelum implementasi

- Belum ada snapshot payload lengkap dengan checksum/integritas.
- Belum ada alur verifikasi atau persetujuan Kepala Sekolah.
- Belum ada tanggal terbit, penerbit, dan audit publikasi.
- Belum ada ledger nilai satu kelas.
- Belum ada komponen sikap, prestasi, ekstrakurikuler, dan catatan pendukung dalam snapshot rapor.
- Skema/bobot penilaian dan versi formula belum menjadi bagian rapor.
- Export PDF server-side dan format cetak resmi belum dikunci.

## Keputusan yang sudah dikonfirmasi

| Tanggal | Keputusan | Dampak |
|---|---|---|
| 2026-10-31 | Fokus pekerjaan berikutnya beralih ke e-Rapor. | Jangan memulai pekerjaan lanjutan pada format ekspor Paket Administrasi. |
| 2026-10-31 | Format ekspor Paket Administrasi sebelumnya belum diterima. | Rancangan format harus disepakati ulang sebelum implementasi. |
| 2026-10-31 | Data dummy hanya untuk database lokal. | Jangan memasukkan dump database atau data dummy hasil seeding ke repository. |

## Aturan pemeliharaan

- Tambahkan entri hanya untuk keputusan, penggantian pendekatan, insiden material, atau handoff lintas sesi.
- Perubahan kode rutin tetap dilacak oleh Git dan test, bukan dicatat satu per satu di sini.
- Jika keputusan berubah, tambahkan koreksi bertanggal; jangan menghapus sejarah lama tanpa alasan.
- Jangan menyalin rahasia, kredensial, token, atau data pribadi.

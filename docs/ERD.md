# Entity Relationship Diagram — LMS Sekolah

| | |
|---|---|
| **Dokumen** | Entity Relationship Diagram (ERD) |
| **Sumber** | Migration di `database/migrations/` |
| **Status** | Baseline schema |
| **Versi dokumen** | 2.0 |
| **Klasifikasi** | Confidential — proprietary, lihat [LICENSE](../LICENSE) |
| **Terakhir ditinjau** | 2026-09-24 |

## Riwayat versi

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | 2026-09-21 | Baseline diagram domain akademik inti + katalog tabel. |
| 2.0 | 2026-09-24 | Menambahkan `biodata_siswa`, `jadwal_mengajar`, `kelas_daring` ke diagram; menambah legend notasi, document control, dan katalog index. |

## Legend notasi

Diagram memakai notasi crow's foot standar Mermaid `erDiagram`:

| Notasi | Arti |
|---|---|
| `\|\|--o{` | Satu ke banyak, sisi "banyak" opsional (contoh: satu `kelas_mapel` bisa punya nol atau banyak `tugas`). |
| `\|\|--o\|` | Satu ke nol-atau-satu (contoh: satu `siswa` punya nol atau satu `biodata_siswa`). |
| `PK` | Primary key. `FK` | Foreign key. `UK` | Unique key. |

Tabel framework Laravel (`sessions`, `cache`, `jobs`, dst.) dan sebagian tabel observability/keamanan tetap ada di database, tetapi dipisahkan dari diagram agar tetap terbaca — lihat katalog tabel di §2 untuk daftar lengkap termasuk kelompok tersebut.

## 1. Diagram domain akademik

```mermaid
erDiagram
    roles ||--o{ users : memiliki
    users ||--o| siswa : profil_siswa
    siswa ||--o| biodata_siswa : biodata
    kelas ||--o{ siswa : menampung
    tahun_ajaran ||--o{ kelas_mapel : periode
    kelas ||--o{ kelas_mapel : membuka
    mata_pelajaran ||--o{ kelas_mapel : diajarkan
    users ||--o{ kelas_mapel : mengampu
    users ||--o{ guru_mapel : menguasai
    mata_pelajaran ||--o{ guru_mapel : dipetakan

    kelas_mapel ||--o{ materi : memiliki
    kelas_mapel ||--o{ tugas : memiliki
    tugas ||--o{ pengumpulan_tugas : menerima
    siswa ||--o{ pengumpulan_tugas : mengumpulkan
    pengumpulan_tugas ||--o{ pengumpulan_files : melampirkan
    siswa ||--o{ absensi : memiliki
    kelas_mapel ||--o{ absensi : mencatat
    siswa ||--o{ nilai_akhir : menerima
    kelas_mapel ||--o{ nilai_akhir : menilai
    tahun_ajaran ||--o{ nilai_akhir : periode
    siswa ||--o{ sikap_spiritual : dinilai
    siswa ||--o{ sikap_sosial : dinilai
    kelas_mapel ||--o{ sikap_spiritual : konteks
    kelas_mapel ||--o{ sikap_sosial : konteks
    tahun_ajaran ||--o{ sikap_spiritual : periode
    tahun_ajaran ||--o{ sikap_sosial : periode

    kelas ||--o{ wali_kelas : ditangani
    users ||--o{ wali_kelas : menjadi_wali
    tahun_ajaran ||--o{ wali_kelas : periode
    wali_kelas ||--o{ absensi_wali_kelas : mencatat
    wali_kelas ||--o{ pertemuan_wali_kelas : melakukan
    wali_kelas ||--o{ penanganan_siswa : menangani
    siswa ||--o{ absensi_wali_kelas : hadir
    siswa ||--o{ penanganan_siswa : ditangani

    kelas ||--o{ jadwal_mengajar : slot_kelas
    users ||--o{ jadwal_mengajar : mengajar
    kelas_mapel ||--o{ jadwal_mengajar : sumber_pertemuan
    kelas_mapel ||--o{ kelas_daring : sesi_daring
    users ||--o{ kelas_daring : host
    kelas_daring ||--o{ absensi : opsional_konteks

    users ||--o{ chat_messages : mengirim
    kelas_mapel ||--o{ chat_messages : kanal
    users ||--o{ pengumuman : membuat
    kelas_mapel ||--o{ pengumuman : target_opsional
    users ||--o{ notifikasi : menerima
    users ||--o{ dashboard_widgets : mengatur
    users ||--o{ calendar_events : memiliki
    users ||--o{ academic_audit_logs : melakukan
    users ||--o{ whatsapp_message_logs : mengirimkan
    siswa ||--o{ whatsapp_message_logs : penerima

    users {
      bigint id PK
      tinyint role_id FK
      string username UK
      string email UK
      string nama_lengkap
      boolean is_active
    }
    siswa {
      bigint id PK
      bigint user_id FK UK
      bigint kelas_id FK
      string nis UK
      enum status
    }
    kelas_mapel {
      bigint id PK
      bigint kelas_id FK
      bigint mapel_id FK
      bigint guru_id FK
      bigint tahun_ajaran_id FK
      enum semester
      tinyint pertemuan_per_minggu
    }
    jadwal_mengajar {
      bigint id PK
      bigint guru_id FK
      bigint kelas_id FK
      bigint kelas_mapel_id FK
      tinyint hari
      tinyint pelajaran_ke
    }
    tugas {
      bigint id PK
      bigint kelas_mapel_id FK
      string judul
      datetime batas_waktu
      enum kategori_nilai
    }
    pengumpulan_tugas {
      bigint id PK
      bigint tugas_id FK
      bigint siswa_id FK
      enum status
      decimal nilai
    }
    nilai_akhir {
      bigint id PK
      bigint siswa_id FK
      bigint kelas_mapel_id FK
      bigint tahun_ajaran_id FK
      enum semester
      decimal rata_akhir GENERATED
    }
    wali_kelas {
      bigint id PK
      bigint kelas_id FK
      bigint guru_id FK
      bigint tahun_ajaran_id FK
    }
    biodata_siswa {
      bigint id PK
      bigint siswa_id FK UK
      string nama_panggilan
      date tanggal_lahir
      string nama_ayah
      string nama_ibu
    }
```

## 2. Katalog tabel

| Kelompok | Tabel | Keterangan |
|---|---|---|
| Akses | `roles`, `users`, `siswa`, `biodata_siswa` | Identitas, role, akun siswa, dan biodata 1:1. |
| Master | `tahun_ajaran`, `kelas`, `mata_pelajaran`, `guru_mapel`, `kelas_mapel` | Struktur akademik dan penugasan guru. |
| Pembelajaran | `materi`, `tugas`, `pengumpulan_tugas`, `pengumpulan_files` | Materi, tugas, submission, dan lampiran. |
| Penilaian | `absensi`, `nilai_akhir`, `sikap_spiritual`, `sikap_sosial` | Kehadiran, nilai akhir, dan penilaian sikap. |
| Wali kelas | `wali_kelas`, `absensi_wali_kelas`, `pertemuan_wali_kelas`, `penanganan_siswa` | Pendampingan dan monitoring siswa oleh wali kelas — terpisah dari absensi mata pelajaran. |
| Jadwal & daring | `jadwal_mengajar`, `kelas_daring` | Slot pelajaran mingguan (sumber tunggal tanggal pertemuan via `AttendanceScheduleService`) dan sesi daring. `absensi.kelas_daring_id` bersifat opsional. |
| Komunikasi | `chat_messages`, `pengumuman`, `notifikasi`, `whatsapp_message_logs` | Kanal kelas, pengumuman, notifikasi, dan log persiapan pesan WhatsApp. |
| Operasional | `school_settings`, `pengaturan`, `calendar_events`, `dashboard_widgets` | Branding sekolah (singleton), konfigurasi key-value, kalender, dan preferensi dashboard. |
| Security/audit | `academic_audit_logs`, `log_login`, `blocked_ips`, `login_attempts`, `system_errors` | Jejak perubahan akademik, login, rate limit, dan error. |

## 3. Constraint penting

- `siswa.user_id` unik; satu akun hanya memiliki satu profil siswa.
- `biodata_siswa.siswa_id` unik; relasi 1:1 dengan `siswa`.
- `guru_mapel` unik pada pasangan guru dan mapel.
- `pengumpulan_tugas` unik pada pasangan tugas dan siswa.
- `absensi` unik pada siswa, kelas-mapel, dan tanggal.
- `wali_kelas` unik pada kelas dan tahun ajaran.
- `absensi_wali_kelas` unik pada wali kelas, siswa, dan tanggal.
- `nilai_akhir` unik pada siswa, kelas-mapel, tahun ajaran, dan semester.
- `jadwal_mengajar` unik pada (guru, hari, pelajaran_ke) **dan** (kelas, hari, pelajaran_ke) — satu guru/kelas tidak bisa berada di dua tempat pada slot jam yang sama.
- Foreign key akademik umumnya `cascadeOnDelete`; relasi opsional seperti kelas siswa dan target pengumuman menggunakan `nullOnDelete`.
- `nilai_akhir.rata_akhir` adalah generated column pada MySQL; jangan diisi manual dari aplikasi.

## 4. Index performa

Index tambahan untuk query yang sering diakses (di luar primary/foreign/unique key bawaan):

| Tabel | Index | Tujuan |
|---|---|---|
| `kelas_mapel` | `(guru_id, semester, tahun_ajaran_id)` | Query "kelas-mapel aktif milik guru ini" (dashboard guru, absensi, export). |
| `absensi` | `(tanggal)` | Filter/rekap absensi per rentang tanggal. |
| `notifikasi` | `(user_id, is_read, created_at)` | Inbox notifikasi per user, urut terbaru, filter belum dibaca. |
| `chat_messages` | `(kelas_mapel_id, created_at)` | Riwayat chat per kelas-mapel, urut waktu. |
| `pengumuman` | `(created_at)` | Feed pengumuman terbaru. |
| `log_login` | `(login_time)` | Laporan/monitoring login. |

> Didefinisikan pada migration `2026_09_23_000001_add_runtime_query_indexes.php`. Migration ini **additive only** (tidak mengubah data) — jalankan `php artisan migrate` sebelum deploy ke production jika belum diterapkan di environment target.

## 5. Catatan pemeliharaan

ERD ini adalah ringkasan, bukan pengganti migration. Setiap perubahan tabel, constraint, enum, atau generated column harus dibuat melalui migration lalu diikuti pembaruan dokumen ini (diagram §1, katalog §2, dan constraint §3) pada PR yang sama. Untuk kebutuhan produk yang melatarbelakangi skema ini, lihat [docs/PRD.md](PRD.md).

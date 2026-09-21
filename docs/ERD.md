# Entity Relationship Diagram — LMS Sekolah

**Sumber:** migration di `database/migrations/`  
**Status:** baseline schema  
**Last reviewed:** 2026-09-21

Diagram berikut merangkum tabel domain utama. Tabel framework Laravel dan tabel observability/keamanan tetap ada di database, tetapi dipisahkan dari alur akademik inti agar diagram terbaca.

```mermaid
erDiagram
    roles ||--o{ users : memiliki
    users ||--o| siswa : profil_siswa
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
```

## Katalog tabel domain

| Kelompok | Tabel | Keterangan |
|---|---|---|
| Akses | `roles`, `users`, `siswa`, `biodata_siswa` | Identitas, role, akun siswa, dan biodata 1:1. |
| Master | `tahun_ajaran`, `kelas`, `mata_pelajaran`, `guru_mapel`, `kelas_mapel` | Struktur akademik dan penugasan guru. |
| Pembelajaran | `materi`, `tugas`, `pengumpulan_tugas`, `pengumpulan_files` | Materi, tugas, submission, dan lampiran. |
| Penilaian | `absensi`, `nilai_akhir`, `sikap_spiritual`, `sikap_sosial` | Kehadiran, nilai akhir, dan penilaian sikap. |
| Wali kelas | `wali_kelas`, `absensi_wali_kelas`, `pertemuan_wali_kelas`, `penanganan_siswa` | Pendampingan dan monitoring siswa. |
| Jadwal daring | `jadwal_mengajar`, `kelas_daring` | Slot pelajaran dan sesi daring. `absensi.kelas_daring_id` bersifat opsional. |
| Komunikasi | `chat_messages`, `pengumuman`, `notifikasi`, `whatsapp_message_logs` | Kanal kelas, pengumuman, notifikasi, dan log pesan. |
| Operasional | `school_settings`, `pengaturan`, `calendar_events`, `dashboard_widgets` | Branding sekolah, konfigurasi, kalender, dan preferensi dashboard. |
| Security/audit | `academic_audit_logs`, `log_login`, `blocked_ips`, `login_attempts`, `system_errors` | Jejak perubahan, login, rate limit, dan error. |

## Constraint penting

- `siswa.user_id` unik; satu akun hanya memiliki satu profil siswa.
- `guru_mapel` unik pada pasangan guru dan mapel.
- `pengumpulan_tugas` unik pada pasangan tugas dan siswa.
- `absensi` unik pada siswa, kelas-mapel, dan tanggal.
- `wali_kelas` unik pada kelas dan tahun ajaran.
- `nilai_akhir` unik pada siswa, kelas-mapel, tahun ajaran, dan semester.
- Foreign key akademik umumnya `cascadeOnDelete`; relasi opsional seperti kelas siswa dan target pengumuman menggunakan `nullOnDelete`.
- `nilai_akhir.rata_akhir` adalah generated column pada MySQL; jangan diisi manual dari aplikasi.

## Catatan pemeliharaan

ERD ini adalah ringkasan, bukan pengganti migration. Setiap perubahan tabel, constraint, enum, atau generated column harus dibuat melalui migration lalu diikuti pembaruan dokumen ini.

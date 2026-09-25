# Rencana Fitur CBT (Ujian Online) dan Bank Soal

Status: rencana awal, belum diimplementasikan. Dokumen ini adalah spesifikasi teknis
untuk agen yang mengerjakan implementasi, ditulis agar dapat dikerjakan tanpa
konteks percakapan sebelumnya. Ikuti pola proyek yang sudah ada (fitur `Tugas`)
sedapat mungkin; setiap penyimpangan dari pola tersebut dijelaskan alasannya.

## 1. Tujuan & Ruang Lingkup

### Tujuan

Memungkinkan guru membuat bank soal pilihan ganda yang dapat dipakai ulang,
menyusun ujian (CBT) dari soal-soal di bank tersebut untuk `kelas_mapel` yang ia
ajar, dan memungkinkan siswa mengerjakan ujian tersebut secara daring dengan
batas waktu, urutan soal/opsi acak, dan deteksi pindah tab — dengan nilai hasil
ujian otomatis masuk ke `nilai_akhir` sesuai `kategori_nilai` ujian.

### Termasuk (fase ini)

- Bank soal pilihan ganda milik guru (per guru, tidak dibagi ke guru lain).
- Tagging opsional pada soal: topik, tingkat kesulitan, kategori nilai default.
- Pembuatan ujian dari kelas_mapel: judul, durasi (menit), kategori nilai
  (NH/STS/SAS/SAT), jendela waktu buka ujian (`waktu_mulai`/`waktu_selesai`),
  dan pemilihan soal dari bank beserta bobot poin per soal.
- Satu kali attempt per siswa per ujian (tanpa fitur retake di fase ini —
  lihat §13).
- Pengacakan urutan soal dan urutan opsi jawaban, deterministik per attempt
  (di-seed dari id attempt), bukan acak ulang setiap reload halaman.
- Timer sisi klien + pencatatan waktu mulai di server, auto-submit saat waktu
  habis.
- Autosave setiap jawaban (POST per pilihan, bukan submit sekali di akhir)
  agar refresh/putus koneksi tidak menghilangkan progres.
- Deteksi visibility change (ganti tab/minimize) selama attempt aktif —
  sinyal lunak, dicatat sebagai log, ditampilkan ke guru, tidak memblokir
  siswa.
- Auto-grading penuh (pilihan ganda saja) tanpa penilaian manual guru.
- Auto-write skor ke `nilai_akhir` kolom yang sesuai `kategori_nilai` ujian.
- Rekap/export hasil ujian per kelas_mapel (Excel & PDF), mengikuti pola
  `GuruReportService`.

### Tidak termasuk (fase ini) — lihat detail di §14

- Tipe soal esai, benar-salah, menjodohkan.
- Retake/attempt ulang.
- Proctoring penuh (webcam, screen recording).
- Berbagi bank soal antar guru.
- Import soal dari Excel/Word.
- Hardening keamanan acak-urutan tingkat lanjut (mis. mencegah siswa teknis
  merekonstruksi urutan asli dari respons jaringan).

## 2. Terminologi

Mengikuti konvensi istilah proyek (`tugas`, bukan `assignment`; `absensi`,
bukan `attendance`), fitur ini memakai istilah **`ujian`** untuk ujian/CBT dan
**`soal_bank`** untuk bank soal. Nama tabel dan model final:

- `soal_bank` — bank soal milik guru.
- `soal_bank_opsi` — opsi jawaban pilihan ganda milik satu soal bank.
- `ujian` — ujian/CBT, terikat ke satu `kelas_mapel`.
- `ujian_soal` — tabel pivot yang menghubungkan `ujian` ke `soal_bank` yang
  dipilih, dengan bobot poin per soal.
- `ujian_attempt` — satu pengerjaan ujian oleh satu siswa.
- `ujian_attempt_jawaban` — jawaban siswa untuk tiap soal dalam satu attempt.

## 3. Domain Model / Skema Database

Keputusan desain kunci (baca sebelum membuat migrasi):

1. **Opsi jawaban sebagai tabel anak (`soal_bank_opsi`), bukan kolom
   `opsi_a..opsi_e`.** Proyek ini sudah memakai pola tabel anak untuk data
   yang jumlahnya bervariasi/berulang (`pengumpulan_files` vs kolom tunggal
   `file_upload` di `pengumpulan_tugas`). Opsi jawaban pilihan ganda memiliki
   sifat serupa: jumlah opsi bisa 4 atau 5, dan struktur anak memudahkan
   pengacakan urutan opsi (di-shuffle dari daftar row, bukan dari nama kolom)
   serta ekstensi ke tipe soal lain nanti. Tandai opsi benar dengan boolean
   `is_benar` pada baris opsi itu sendiri (bukan kolom terpisah `jawaban_benar`
   di `soal_bank` yang menyimpan huruf), supaya urutan opsi yang berbeda-beda
   per soal tetap valid tanpa remapping huruf.
2. **Urutan soal & opsi yang ditampilkan ke siswa disimpan eksplisit di
   `ujian_attempt` dan `ujian_attempt_jawaban`** (bukan dihitung ulang dari
   seed setiap kali diakses) — ini dibutuhkan agar guru bisa mereview persis
   apa yang dilihat siswa, dan agar auto-submit/expiry tidak bergantung pada
   kemampuan menghitung ulang shuffle yang identik dari kode yang mungkin
   berubah di masa depan. Seed tetap disimpan sebagai jejak audit, tapi
   urutan final yang disajikan disimpan sebagai data, bukan hanya diturunkan
   dari seed setiap render.
3. **Log tab-switch disimpan sebagai kolom JSON pada `ujian_attempt`**
   (`tab_switch_log`), bukan tabel anak terpisah. Volumenya kecil per
   attempt, tidak perlu di-query lintas attempt/siswa secara relasional, dan
   proyek ini sudah punya preseden data terstruktur ringan disimpan sebagai
   kolom (bandingkan `nilai_akhir.rata_akhir` sebagai generated column,
   bukan tabel breakdown terpisah). Jika kelak butuh analitik lintas siswa,
   ini bisa dimigrasikan ke tabel anak tanpa mengubah kontrak API luar.
4. **Satu attempt per siswa per ujian**, unique constraint
   `(ujian_id, siswa_id)` pada `ujian_attempt`, mengikuti pola unique
   `(tugas_id, siswa_id)` pada `pengumpulan_tugas`.

### 3.1 Migrasi baru

Buat file migrasi baru (nama file mengikuti pola timestamp
`YYYY_MM_DD_HHMMSS_deskripsi.php`, contoh nama:
`2026_09_25_000001_create_cbt_tables.php`), dengan urutan `Schema::create`
sebagai berikut (satu file migrasi berisi seluruh tabel baru, mengikuti pola
`0001_01_01_000020_create_akademik_tables.php` yang mengelompokkan beberapa
tabel domain terkait dalam satu file):

```php
Schema::create('soal_bank', function (Blueprint $table) {
    $table->id();
    $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('mapel_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
    $table->text('pertanyaan');
    $table->string('topik', 100)->nullable();
    $table->enum('kesulitan', ['mudah', 'sedang', 'sulit'])->default('sedang');
    $table->enum('kategori_nilai', ['NH', 'STS', 'SAS', 'SAT'])->nullable();
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
    $table->index(['guru_id', 'mapel_id']);
});

Schema::create('soal_bank_opsi', function (Blueprint $table) {
    $table->id();
    $table->foreignId('soal_bank_id')->constrained('soal_bank')->cascadeOnDelete();
    $table->text('teks_opsi');
    $table->boolean('is_benar')->default(false);
    $table->unsignedTinyInteger('urutan')->default(0);
});

Schema::create('ujian', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kelas_mapel_id')->constrained('kelas_mapel')->cascadeOnDelete();
    $table->string('judul', 200);
    $table->text('deskripsi')->nullable();
    $table->unsignedSmallInteger('durasi_menit');
    $table->enum('kategori_nilai', ['NH', 'STS', 'SAS', 'SAT'])->default('NH');
    $table->dateTime('waktu_mulai')->nullable();
    $table->dateTime('waktu_selesai')->nullable();
    $table->boolean('acak_soal')->default(true);
    $table->boolean('acak_opsi')->default(true);
    $table->dateTime('created_at')->nullable();
    $table->dateTime('updated_at')->nullable();
});

Schema::create('ujian_soal', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ujian_id')->constrained('ujian')->cascadeOnDelete();
    $table->foreignId('soal_bank_id')->constrained('soal_bank')->cascadeOnDelete();
    $table->decimal('poin', 5, 2)->default(1);
    $table->unsignedSmallInteger('urutan')->default(0);
    $table->unique(['ujian_id', 'soal_bank_id']);
});

Schema::create('ujian_attempt', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ujian_id')->constrained('ujian')->cascadeOnDelete();
    $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
    $table->enum('status', ['belum_mulai', 'sedang_mengerjakan', 'selesai', 'waktu_habis'])
        ->default('belum_mulai');
    $table->unsignedInteger('seed');
    $table->json('urutan_soal_ids'); // array of ujian_soal.id dalam urutan tampil
    $table->dateTime('waktu_mulai')->nullable();
    $table->dateTime('batas_waktu')->nullable(); // waktu_mulai + durasi_menit, dihitung sekali saat mulai
    $table->dateTime('waktu_submit')->nullable();
    $table->decimal('skor_total', 6, 2)->nullable();
    $table->decimal('skor_maksimal', 6, 2)->nullable();
    $table->json('tab_switch_log')->nullable(); // array of ISO8601 timestamps
    $table->unsignedSmallInteger('tab_switch_count')->default(0);
    $table->unique(['ujian_id', 'siswa_id']);
});

Schema::create('ujian_attempt_jawaban', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ujian_attempt_id')->constrained('ujian_attempt')->cascadeOnDelete();
    $table->foreignId('ujian_soal_id')->constrained('ujian_soal')->cascadeOnDelete();
    $table->foreignId('soal_bank_opsi_id')->nullable()->constrained('soal_bank_opsi')->nullOnDelete();
    $table->json('urutan_opsi_ids'); // array of soal_bank_opsi.id dalam urutan tampil untuk soal ini
    $table->boolean('is_benar')->nullable(); // null = belum dijawab
    $table->decimal('poin_didapat', 5, 2)->nullable();
    $table->dateTime('dijawab_pada')->nullable();
    $table->unique(['ujian_attempt_id', 'ujian_soal_id']);
});
```

Catatan implementasi migrasi:

- Nama tabel mata pelajaran di project ini **sudah dikonfirmasi**: `mata_pelajaran`
  (lihat `app/Models/MataPelajaran.php:10` — `protected $table = 'mata_pelajaran';`,
  dan migrasi `0001_01_01_000010_create_master_tables.php`). FK `soal_bank.mapel_id`
  di atas sudah benar mengacu ke `mata_pelajaran` — jangan diganti ke nama lain.
- Semua FK memakai `cascadeOnDelete()` mengikuti pola dominan di proyek ini
  (lihat `tugas`, `pengumpulan_tugas`, `nilai_akhir` di migrasi
  `0001_01_01_000020_create_akademik_tables.php`), kecuali
  `soal_bank_opsi_id` pada `ujian_attempt_jawaban` yang memakai
  `nullOnDelete()` karena opsi soal bisa saja dihapus dari bank sementara
  histori jawaban attempt lama tetap harus tersimpan sebagai jejak (jawaban
  jadi "opsi terhapus" bukan seluruh baris jawaban ikut terhapus).
- `urutan_soal_ids` dan `urutan_opsi_ids` disimpan `json` agar Eloquent cast
  `array` otomatis menangani encode/decode.

## 4. Model Class Baru

Semua model baru masuk `app/Models/`, ikuti pola `$timestamps = false` dengan
kolom manual `created_at`/`updated_at` di tempat yang memakainya (`soal_bank`,
`ujian`), dan `$timestamps = false` tanpa kolom sama sekali untuk tabel yang
tidak butuh jejak waktu penuh (`soal_bank_opsi`, `ujian_soal`,
`ujian_attempt_jawaban`), mengikuti pola `PengumpulanTugas`/`PengumpulanFile`.

### `App\Models\SoalBank`

- `$fillable`: `guru_id, mapel_id, pertanyaan, topik, kesulitan, kategori_nilai`.
- `$casts`: tidak perlu cast khusus.
- Relasi: `guru(): belongsTo(User::class, 'guru_id')`,
  `mapel(): belongsTo(MataPelajaran::class, 'mapel_id')`,
  `opsi(): hasMany(SoalBankOpsi::class, 'soal_bank_id')`.
- Konstanta: `KESULITAN_MUDAH='mudah'`, `KESULITAN_SEDANG='sedang'`,
  `KESULITAN_SULIT='sulit'`.

### `App\Models\SoalBankOpsi`

- `$timestamps = false`.
- `$fillable`: `soal_bank_id, teks_opsi, is_benar, urutan`.
- `$casts`: `['is_benar' => 'boolean']`.
- Relasi: `soalBank(): belongsTo(SoalBank::class, 'soal_bank_id')`.

### `App\Models\Ujian`

- `$fillable`: `kelas_mapel_id, judul, deskripsi, durasi_menit, kategori_nilai, waktu_mulai, waktu_selesai, acak_soal, acak_opsi`.
- `$casts`: `['waktu_mulai' => 'datetime', 'waktu_selesai' => 'datetime', 'acak_soal' => 'boolean', 'acak_opsi' => 'boolean']`.
- Relasi: `kelasMapel(): belongsTo(KelasMapel::class, 'kelas_mapel_id')`,
  `ujianSoal(): hasMany(UjianSoal::class, 'ujian_id')`,
  `attempts(): hasMany(UjianAttempt::class, 'ujian_id')`.
- Method util: `isBuka(): bool` — true jika `now()` berada dalam
  `[waktu_mulai, waktu_selesai]` (null di salah satu berarti tanpa batas di
  sisi itu), dipakai baik di controller siswa maupun policy.

### `App\Models\UjianSoal`

- `$timestamps = false`.
- `$fillable`: `ujian_id, soal_bank_id, poin, urutan`.
- Relasi: `ujian(): belongsTo(Ujian::class, 'ujian_id')`,
  `soalBank(): belongsTo(SoalBank::class, 'soal_bank_id')`.

### `App\Models\UjianAttempt`

- `$fillable`: `ujian_id, siswa_id, status, seed, urutan_soal_ids, waktu_mulai, batas_waktu, waktu_submit, skor_total, skor_maksimal, tab_switch_log, tab_switch_count`.
- `$casts`: `['urutan_soal_ids' => 'array', 'tab_switch_log' => 'array', 'waktu_mulai' => 'datetime', 'batas_waktu' => 'datetime', 'waktu_submit' => 'datetime']`.
- Konstanta status: `STATUS_BELUM_MULAI='belum_mulai'`,
  `STATUS_SEDANG_MENGERJAKAN='sedang_mengerjakan'`, `STATUS_SELESAI='selesai'`,
  `STATUS_WAKTU_HABIS='waktu_habis'`;
  `STATUS_TERKUNCI = [STATUS_SELESAI, STATUS_WAKTU_HABIS]` (grup status yang
  tidak boleh lagi menerima jawaban baru — mengikuti pola grup
  `PengumpulanTugas::STATUS_SUBMITTED`).
- Relasi: `ujian(): belongsTo(Ujian::class, 'ujian_id')`,
  `siswa(): belongsTo(Siswa::class, 'siswa_id')`,
  `jawaban(): hasMany(UjianAttemptJawaban::class, 'ujian_attempt_id')`.

### `App\Models\UjianAttemptJawaban`

- `$timestamps = false`.
- `$fillable`: `ujian_attempt_id, ujian_soal_id, soal_bank_opsi_id, urutan_opsi_ids, is_benar, poin_didapat, dijawab_pada`.
- `$casts`: `['urutan_opsi_ids' => 'array', 'is_benar' => 'boolean', 'dijawab_pada' => 'datetime']`.
- Relasi: `attempt(): belongsTo(UjianAttempt::class, 'ujian_attempt_id')`,
  `ujianSoal(): belongsTo(UjianSoal::class, 'ujian_soal_id')`,
  `opsiTerpilih(): belongsTo(SoalBankOpsi::class, 'soal_bank_opsi_id')`.

## 5. Otorisasi

### `App\Policies\UjianPolicy`

Method `mengajar(User $user, Ujian $ujian): bool`, isi persis mengikuti pola
`TugasPolicy::mengajar()`:

```php
public function mengajar(User $user, Ujian $ujian): bool
{
    $kelasMapel = $ujian->kelasMapel;

    return $user->isGuru()
        && $kelasMapel !== null
        && (int) $kelasMapel->guru_id === (int) $user->id
        && $kelasMapel->isAktif();
}
```

### `App\Policies\SoalBankPolicy`

Method `kelola(User $user, SoalBank $soalBank): bool` — true jika
`$user->isGuru() && (int) $soalBank->guru_id === (int) $user->id`. Tidak perlu
cek `isAktif()` di sini karena bank soal tidak terikat ke satu `kelas_mapel`
aktif tertentu (bisa dipakai lintas semester); kepemilikan saja sudah cukup.

### Registrasi Gate

Di `app/Providers/AppServiceProvider.php`, tambahkan (di dekat baris 28–30
yang sudah ada):

```php
Gate::define('mengajar-ujian', [UjianPolicy::class, 'mengajar']);
Gate::define('kelola-soal-bank', [SoalBankPolicy::class, 'kelola']);
```

Route yang bertipe `/ujian/{kelasMapel}/...` tetap memakai middleware
`can:mengajar,kelasMapel` (Gate `mengajar` yang sudah ada, dari
`KelasMapelPolicy`) karena parameter route-nya `KelasMapel`, sama seperti pola
`tugas.list`/`tugas.store`. Gate `mengajar-ujian` dipakai khusus untuk route
yang parameter route-nya langsung `Ujian` (analog dengan
`mengajar-tugas` dipakai di route `tugas.destroy`). Gate `kelola-soal-bank`
dipakai untuk route yang parameter route-nya `SoalBank`.

### Akses sisi siswa

Tidak ada policy Laravel terpisah untuk siswa (proyek ini tidak memakai
policy di sisi siswa — akses dibatasi lewat query scoping di controller,
sama seperti `Siswa/TugasController::index()` yang men-scope lewat
`whereHas('kelasMapel', fn($q) => $q->where('kelas_id', $siswa->kelas_id))`).
Terapkan pola yang sama plus tambahan IDOR-guard eksplisit:

- Siswa hanya bisa melihat/mengerjakan `ujian` yang `kelas_mapel_id`-nya
  match `kelas_mapel` milik kelas siswa tsb DAN `kelasMapel->isAktif()`.
- Siswa hanya bisa mengakses `ujian_attempt` miliknya sendiri — setiap
  controller method siswa yang menerima `UjianAttempt $attempt` via route
  model binding wajib memanggil helper privat
  `ensureAttemptBelongsToSiswa($attempt, $siswa)` yang
  `abort_unless((int) $attempt->siswa_id === (int) $siswa->id, 403)`,
  mengikuti pola `ensureTugasBelongsToKelasMapel` di
  `app/Http/Controllers/Guru/TugasController.php`.
- Ujian hanya bisa dimulai jika `$ujian->isBuka()` true saat request
  `mulai()` diterima.

## 6. Backend Controller

### `App\Http\Controllers\Guru\SoalBankController`

- `index()` — daftar bank soal guru, dengan filter `mapel_id`, `topik`,
  `kesulitan`, `kategori_nilai` via query string. Render `Guru/SoalBank/Index`.
- `store(StoreSoalBankRequest $request)` — buat satu soal + opsi-opsinya
  dalam satu transaksi (`DB::transaction`), `guru_id` diambil dari
  `Auth::id()`, bukan dari input.
- `update(UpdateSoalBankRequest $request, SoalBank $soalBank)` — otorisasi
  `$this->authorize('kelola', $soalBank)`; replace seluruh opsi (hapus opsi
  lama, insert opsi baru) di dalam transaksi supaya konsisten dengan urutan
  baru dari form.
- `destroy(SoalBank $soalBank)` — otorisasi `kelola`; tolak (kembalikan
  error, bukan hapus paksa) jika `$soalBank->id` masih dipakai di
  `ujian_soal` manapun (cek `UjianSoal::where('soal_bank_id', $soalBank->id)->exists()`),
  meniru pola guard di `TugasController::destroy()` yang menolak hapus tugas
  yang sudah punya pengumpulan.

### `App\Http\Controllers\Guru\UjianController`

- `index()` — daftar ujian lintas kelas_mapel milik guru (mirip
  `TugasController::index()`), render `Guru/Ujian/Index`.
- `list(KelasMapel $kelasMapel)` — daftar ujian untuk satu kelas_mapel,
  middleware `can:mengajar,kelasMapel`. Render `Guru/Ujian/List`.
- `create(KelasMapel $kelasMapel)` — form builder ujian: tampilkan bank soal
  guru (dengan filter) untuk dipilih. Render `Guru/Ujian/Builder`.
- `store(StoreUjianRequest $request, KelasMapel $kelasMapel)` — buat `ujian`
  + baris `ujian_soal` (dari daftar `soal_bank_id` + `poin` terpilih) dalam
  satu transaksi. Validasi: minimal 1 soal dipilih, semua `soal_bank_id`
  yang dikirim harus milik guru yang sama (`SoalBank::where('guru_id', Auth::id())->whereIn('id', $ids)->count() === count($ids)`,
  IDOR-guard eksplisit terhadap bank soal guru lain).
- `edit(KelasMapel $kelasMapel, Ujian $ujian)` — form edit, hanya diizinkan
  selama belum ada attempt yang `status != belum_mulai` (jika sudah ada yang
  mulai mengerjakan, tolak edit soal/poin agar tidak mengubah ujian yang
  sedang berjalan — kembalikan error, biarkan guru hanya mengubah
  judul/deskripsi/jendela waktu dalam kondisi ini).
- `update(UpdateUjianRequest $request, KelasMapel $kelasMapel, Ujian $ujian)`.
- `destroy(Ujian $ujian)` — otorisasi via Gate `mengajar-ujian`; tolak hapus
  jika `$ujian->attempts()->exists()`.
- `hasil(KelasMapel $kelasMapel, Ujian $ujian)` — rekap hasil semua siswa:
  status attempt, skor, jumlah tab-switch. Render `Guru/Ujian/Hasil`.
- `detailJawaban(KelasMapel $kelasMapel, Ujian $ujian, UjianAttempt $attempt)`
  — review per-soal satu attempt siswa tertentu (untuk guru memeriksa jawaban
  & log tab-switch). IDOR-guard: pastikan `$attempt->ujian_id === $ujian->id`.

Setiap method guru di atas yang menerima `Ujian $ujian` bersamaan dengan
`KelasMapel $kelasMapel` di parameter wajib memanggil helper privat
`ensureUjianBelongsToKelasMapel($ujian, $kelasMapel)` (pola sama seperti
`ensureTugasBelongsToKelasMapel`), demikian juga
`ensureAttemptBelongsToUjian($attempt, $ujian)`.

### `App\Http\Controllers\Siswa\UjianController`

- `index()` — daftar ujian yang tersedia untuk siswa (dari kelas_mapel aktif
  kelas siswa), tampilkan status attempt siswa untuk tiap ujian
  (`belum_mulai`/`sedang_mengerjakan`/`selesai`/`waktu_habis`) dan apakah
  ujian sedang buka (`isBuka()`). Render `Siswa/Ujian/Index`.
- `mulai(Ujian $ujian)` — cek `isBuka()`; cek belum ada attempt existing
  untuk `(ujian_id, siswa_id)` — jika sudah ada dan statusnya bukan
  `belum_mulai`, redirect ke halaman kerjakan (idempotent, bukan error, agar
  refresh tombol "mulai" tidak dobel-create). Jika belum ada: dalam
  `DB::transaction`, hitung `seed` (mis. `random_int()` atau id yang baru
  saja dibuat dipakai sebagai seed setelah insert pertama — insert dulu baris
  tanpa `urutan_soal_ids` final, lalu update `seed = $attempt->id` dan hitung
  urutan dari seed itu, supaya seed selalu unik dan reproducible dari id
  attempt itu sendiri, konsisten dengan keputusan produk "seeded by attempt
  id"), set `urutan_soal_ids` (shuffle jika `$ujian->acak_soal`, else urutan
  `ujian_soal.urutan`), set `waktu_mulai = now()`,
  `batas_waktu = now()->addMinutes($ujian->durasi_menit)`,
  `status = sedang_mengerjakan`. Buat baris `ujian_attempt_jawaban` kosong
  untuk tiap `ujian_soal` sekaligus (`urutan_opsi_ids` dihitung & disimpan di
  sini juga, per soal, dari seed yang sama + id soal supaya deterministik dan
  berbeda antar soal). Redirect ke `kerjakan`.
- `kerjakan(UjianAttempt $attempt)` — halaman pengerjaan. Guard:
  `ensureAttemptBelongsToSiswa`; jika `now() > $attempt->batas_waktu` dan
  status masih `sedang_mengerjakan`, panggil service auto-submit (lihat §6.1)
  sebelum render, lalu redirect ke halaman hasil siswa. Kirim ke Vue: daftar
  soal terurut sesuai `urutan_soal_ids` (teks soal + opsi terurut sesuai
  `urutan_opsi_ids` tiap jawaban, TANPA `is_benar` — jangan pernah kirim
  informasi opsi mana yang benar ke payload siswa), jawaban yang sudah
  tersimpan (untuk render ulang saat refresh), dan `batas_waktu` (epoch ms)
  untuk hitung mundur klien.
- `jawab(SimpanJawabanUjianRequest $request, UjianAttempt $attempt, UjianSoal $ujianSoal)`
  — endpoint autosave, dipanggil setiap siswa memilih opsi. Guard kepemilikan
  attempt + guard `$ujianSoal` termasuk dalam `$attempt->ujian_id`. Tolak
  (`422`/`403`) jika `$attempt->status` sudah masuk `STATUS_TERKUNCI` atau
  `now() > $attempt->batas_waktu`. `updateOrCreate` pada
  `ujian_attempt_jawaban` berdasarkan `(ujian_attempt_id, ujian_soal_id)`,
  set `soal_bank_opsi_id`, `dijawab_pada = now()`. Response JSON ringan
  (bukan redirect Inertia) — dipanggil lewat `axios`/`fetch` dari Vue, bukan
  `router.post` Inertia, karena ini dipanggil sangat sering dan tidak perlu
  reload halaman.
- `submit(UjianAttempt $attempt)` — submit manual oleh siswa (tombol
  "Selesai"). Guard kepemilikan + status. Panggil `CbtScoringService::submit()`
  (lihat §7). Redirect ke `hasil`.
- `logTabSwitch(UjianAttempt $attempt)` — endpoint dipanggil dari event
  Page Visibility API klien. Guard kepemilikan + status masih
  `sedang_mengerjakan`. Append timestamp ke `tab_switch_log` (JSON array),
  increment `tab_switch_count`. Response JSON kosong/`204`.
- `hasil(UjianAttempt $attempt)` — halaman hasil siswa setelah selesai:
  skor, jumlah benar/salah, tidak menampilkan kunci jawaban jika guru belum
  mengizinkan (fase ini: default TIDAK menampilkan kunci jawaban ke siswa,
  hanya skor total — kunci jawaban per soal adalah kandidat fase depan,
  bukan wajib fase ini).

#### 6.1 Auto-submit saat waktu habis

Tidak ada job terjadwal (scheduler) untuk auto-submit di fase ini — auto
grading berbasis polling ringan cukup untuk skala single-tenant ini:

- Klien menghitung mundur dari `batas_waktu` dan saat mencapai nol memanggil
  endpoint `submit()` yang sama seperti submit manual (deteksi client-side).
- Sebagai fallback jika klien mati/menutup tab tanpa sempat memanggil submit,
  method `kerjakan()` dan `hasil()` (dan idealnya setiap endpoint yang
  menerima `UjianAttempt`) melakukan **lazy auto-submit**: setiap kali
  attempt dengan status `sedang_mengerjakan` diakses dan
  `now() > $attempt->batas_waktu`, panggil
  `CbtScoringService::autoSubmitKarenaWaktuHabis($attempt)` sebelum
  melanjutkan — status hasil menjadi `waktu_habis`, skor dihitung dari
  jawaban yang sudah tersimpan sejauh itu (soal yang tak sempat dijawab
  dianggap salah/0 poin). Ini menghindari kebutuhan cron job di fase MVP,
  konsisten dengan pola proyek ini yang belum memakai scheduler untuk fitur
  serupa.

## 7. Service Grading/Scoring: `App\Services\CbtScoringService`

Buat file baru `app/Services/CbtScoringService.php`. Sebelum menulis kode,
baca `app/Services/NilaiService.php` (sudah dikonfirmasi isinya) —
method `simpanNilai(array $data): NilaiAkhir` menerima array dengan key
`siswa_id, kelas_mapel_id, tahun_ajaran_id, semester, sum1..sum4,
nilai_harian, sts, sas, sat` dan melakukan `updateOrCreate` berdasarkan
`(siswa_id, kelas_mapel_id, tahun_ajaran_id, semester)`; ia TIDAK menghitung
rata-rata sendiri — pemanggil wajib mengirim nilai final tiap kolom
(komponen yang tidak diubah harus dikirim dengan nilai existing-nya, seperti
pola `syncNilaiHarian()` di `TugasController` yang membaca `$existing` lebih
dulu). `CbtScoringService` harus memakai pola yang sama persis.

Method:

- `hitungSkor(UjianAttempt $attempt): array` — iterasi
  `$attempt->jawaban` dengan eager-load `ujianSoal.soalBank.opsi`; untuk tiap
  jawaban, cek apakah `soal_bank_opsi_id` yang dipilih siswa memiliki
  `is_benar = true`; set `is_benar` dan `poin_didapat` (= `ujianSoal->poin`
  jika benar, `0` jika salah/tidak dijawab) pada baris jawaban itu sendiri
  (persist). Kembalikan `['skor_total' => ..., 'skor_maksimal' => ...]`
  (jumlah poin didapat vs jumlah total poin seluruh `ujian_soal` di ujian
  itu — dinormalisasi ke skala 0–100 untuk ditulis ke `nilai_akhir`, karena
  kolom `nilai_akhir.*` bertipe `decimal(5,2)` dan dipakai sebagai skala
  0–100 di seluruh proyek, lihat `nilai` di `pengumpulan_tugas` yang juga
  0–100).
- `submit(UjianAttempt $attempt): UjianAttempt` — dalam `DB::transaction`:
  set `status = selesai`, `waktu_submit = now()`, panggil `hitungSkor()`,
  simpan `skor_total`/`skor_maksimal` pada attempt (skor_total di sini
  disimpan dalam skala poin asli, bukan yang sudah dinormalisasi — normalisasi
  0-100 dihitung sesaat sebelum dikirim ke `syncNilaiUjian()`), lalu panggil
  `syncNilaiUjian($attempt)`. Return attempt yang sudah di-refresh.
- `autoSubmitKarenaWaktuHabis(UjianAttempt $attempt): UjianAttempt` — sama
  seperti `submit()` tapi set `status = waktu_habis` bukan `selesai`.
- `syncNilaiUjian(UjianAttempt $attempt): void` (private) — method ini yang
  menjawab keputusan produk soal agregasi di bawah. Hitung skor 0–100:
  `$skor100 = $attempt->skor_maksimal > 0 ? round($attempt->skor_total / $attempt->skor_maksimal * 100, 2) : 0`.
  Lalu, untuk field `nilai_akhir` yang sesuai `kategori_nilai` milik
  `$attempt->ujian`: hitung **rata-rata skor 0–100 dari SEMUA attempt siswa
  ini yang `status` masuk `STATUS_TERKUNCI` (selesai atau waktu_habis), untuk
  SEMUA ujian dengan `kategori_nilai` yang sama, di `kelas_mapel` yang sama**
  — pola query persis meniru `syncNilaiHarian()` di
  `TugasController::syncNilaiHarian()` (yang `avg('nilai')` dari
  `pengumpulan_tugas` yang `kategori_nilai = NH` untuk kelas_mapel itu),
  hanya bedanya sumber datanya `ujian_attempt` join `ujian` alih-alih
  `pengumpulan_tugas` join `tugas`. Field target ditentukan oleh
  `kategori_nilai`: `NH → nilai_harian`, `STS → sts`, `SAS → sas`,
  `SAT → sat`. Panggil `NilaiService::simpanNilai()` dengan field lain
  (`sum1..sum4` dan 3 field kategori lainnya) dibaca dari record
  `NilaiAkhir` existing terlebih dulu (query `NilaiAkhir::where([...])->first()`),
  sama seperti pola di `syncNilaiHarian()`.

### Keputusan agregasi & overwrite (wajib didokumentasikan di kode, bukan hanya di sini)

**Keputusan: rata-rata (average), SELALU overwrite, mengikuti persis pola NH
yang sudah ada untuk Tugas.** Justifikasi:

- **Konsistensi vs kejutan (least surprise):** Guru yang sudah familiar
  dengan perilaku NH (Tugas) — di mana nilai akhir NH selalu dihitung ulang
  otomatis sebagai rata-rata setiap kali ada nilai baru masuk, dan guru tidak
  pernah mengisi NH secara manual — akan mengharapkan STS/SAS/SAT dari CBT
  berperilaku sama persis begitu mereka memakai fitur ini. Perilaku
  "hanya isi jika kosong" justru lebih mengejutkan karena tidak konsisten
  dengan satu-satunya preseden auto-write yang sudah ada di produk ini.
- **Namun ini TETAP merupakan perubahan perilaku yang harus di-highlight ke
  pengguna**, karena sebelum fitur ini, STS/SAS/SAT 100% manual — seorang
  guru yang sudah mengisi `sts` manual untuk seorang siswa lalu membuat &
  menjalankan ujian CBT berkategori STS untuk kelas_mapel yang sama akan
  melihat angka `sts` manualnya tertimpa otomatis begitu siswa menyelesaikan
  attempt pertama. **Mitigasi**: tampilkan info non-blocking "kelola ujian
  CBT ini juga mempengaruhi nilai" di halaman `Guru/Ujian/Hasil`, dan flash
  message eksplisit setiap kali attempt disubmit/otomatis-submit yang
  men-trigger sync, mis. di halaman hasil siswa/guru: "Skor ujian ini telah
  diperbarui ke nilai [kategori] pada Nilai Akhir." Skema `nilai_akhir`
  TIDAK diubah (tidak menambah kolom sumber) — dijaga sederhana, pelacakan
  sumber per-kolom nilai_akhir di luar scope fase ini.
- **Beberapa ujian CBT kategori sama dalam satu kelas_mapel/semester**:
  dirata-ratakan bersama. Karena STS/SAS/SAT saat ini 100% manual dan tidak
  ada catatan atomik "STS asal manual vs asal ujian" tersimpan terpisah,
  begitu SATU ujian CBT kategori STS pertama kali disubmit untuk kelas_mapel
  itu, nilai `sts` manual sebelumnya akan tertimpa oleh rata-rata attempt CBT
  saja (bukan rata-rata gabungan manual+CBT) — sama seperti NH: begitu ada
  satu Tugas NH pertama dinilai, nilai_harian manual sebelumnya (jika ada)
  langsung tertimpa oleh rata-rata Tugas. Ini didokumentasikan eksplisit
  sebagai keputusan yang meniru pola NH, bukan bug.

## 8. Routes

Tambahkan di `routes/web.php`. Import controller: `App\Http\Controllers\Guru\SoalBankController`,
`App\Http\Controllers\Guru\UjianController as GuruUjianController`,
`App\Http\Controllers\Siswa\UjianController as SiswaUjianController`.

### Guru (di dalam grup `Route::middleware(['auth','role:guru'])->prefix('guru')->name('guru.')`, tempatkan setelah blok `tugas.*` yang sudah ada, sebelum blok `nilai.*`)

```php
// Bank soal
Route::get('/soal-bank', [SoalBankController::class, 'index'])->name('soal-bank.index');
Route::post('/soal-bank', [SoalBankController::class, 'store'])->name('soal-bank.store');
Route::put('/soal-bank/{soalBank}', [SoalBankController::class, 'update'])->name('soal-bank.update')->middleware('can:kelola-soal-bank,soalBank');
Route::delete('/soal-bank/{soalBank}', [SoalBankController::class, 'destroy'])->name('soal-bank.destroy')->middleware('can:kelola-soal-bank,soalBank');

// Ujian
Route::get('/ujian', [GuruUjianController::class, 'index'])->name('ujian.index');
Route::get('/ujian/{kelasMapel}/list', [GuruUjianController::class, 'list'])->name('ujian.list')->middleware('can:mengajar,kelasMapel');
Route::get('/ujian/{kelasMapel}/create', [GuruUjianController::class, 'create'])->name('ujian.create')->middleware('can:mengajar,kelasMapel');
Route::post('/ujian/{kelasMapel}/store', [GuruUjianController::class, 'store'])->name('ujian.store')->middleware('can:mengajar,kelasMapel');
Route::get('/ujian/{kelasMapel}/{ujian}/edit', [GuruUjianController::class, 'edit'])->name('ujian.edit')->middleware('can:mengajar,kelasMapel');
Route::put('/ujian/{kelasMapel}/{ujian}', [GuruUjianController::class, 'update'])->name('ujian.update')->middleware('can:mengajar,kelasMapel');
Route::get('/ujian/{kelasMapel}/{ujian}/hasil', [GuruUjianController::class, 'hasil'])->name('ujian.hasil')->middleware('can:mengajar,kelasMapel');
Route::get('/ujian/{kelasMapel}/{ujian}/hasil/export/excel', [ExportController::class, 'guruUjianHasilExcel'])->name('ujian.hasil.export.excel')->middleware('can:mengajar,kelasMapel');
Route::get('/ujian/{kelasMapel}/{ujian}/hasil/export/pdf', [ExportController::class, 'guruUjianHasilPdf'])->name('ujian.hasil.export.pdf')->middleware('can:mengajar,kelasMapel');
Route::get('/ujian/{kelasMapel}/{ujian}/attempt/{attempt}', [GuruUjianController::class, 'detailJawaban'])->name('ujian.attempt.show')->middleware('can:mengajar,kelasMapel');
Route::delete('/ujian/{ujian}', [GuruUjianController::class, 'destroy'])->name('ujian.destroy')->middleware('can:mengajar-ujian,ujian');
```

### Siswa (di dalam grup `Route::middleware(['auth','role:siswa', RequireStudentPhone::class])->prefix('siswa')->name('siswa.')`, tempatkan setelah blok `tugas.*`)

```php
Route::get('/ujian', [SiswaUjianController::class, 'index'])->name('ujian.index');
Route::post('/ujian/{ujian}/mulai', [SiswaUjianController::class, 'mulai'])->name('ujian.mulai');
Route::get('/ujian/attempt/{attempt}', [SiswaUjianController::class, 'kerjakan'])->name('ujian.kerjakan');
Route::post('/ujian/attempt/{attempt}/soal/{ujianSoal}/jawab', [SiswaUjianController::class, 'jawab'])->name('ujian.jawab');
Route::post('/ujian/attempt/{attempt}/submit', [SiswaUjianController::class, 'submit'])->name('ujian.submit');
Route::post('/ujian/attempt/{attempt}/tab-switch', [SiswaUjianController::class, 'logTabSwitch'])->name('ujian.tab-switch');
Route::get('/ujian/attempt/{attempt}/hasil', [SiswaUjianController::class, 'hasil'])->name('ujian.hasil');
```

Catatan: route siswa TIDAK memakai `can:` middleware (proyek ini tidak
memasang Gate untuk sisi siswa, lihat §5) — semua guard IDOR ada di dalam
body controller lewat helper privat, sama seperti pola
`Siswa\TugasController`.

## 9. Frontend — Halaman Vue Baru

Ikuti pola import/komposisi `resources/js/Pages/Guru/Tugas/*` dan
`resources/js/Pages/Siswa/Tugas/*`: `PageHeader` dari
`resources/js/Components/AppShell/PageHeader.vue`; komponen UI dari
`resources/js/Components/UI` (`Badge`, `IconButton`, `Button`, `Card`,
`DashboardHero`, `EmptyState`, `MetricStrip`, `QuickActionBar`,
`TableWrapper`); form input dari `resources/js/Components/Form`
(`TextInput`, `TextareaInput`).

### Guru

- `resources/js/Pages/Guru/SoalBank/Index.vue` — tabel bank soal dengan
  filter (mapel, topik, kesulitan, kategori_nilai), tombol tambah/edit/hapus
  soal (form soal + N opsi dinamis, radio button untuk tandai opsi benar,
  di dalam modal/drawer — bukan halaman terpisah, mengikuti pola `Tugas`
  yang memakai form inline/modal daripada halaman create terpisah di daftar
  yang sama).
- `resources/js/Pages/Guru/Ujian/Index.vue` — daftar semua ujian guru lintas
  kelas_mapel (mirror `Guru/Tugas/Index.vue`), `MetricStrip` menampilkan
  ringkasan (jumlah ujian aktif, jumlah attempt selesai, dsb).
- `resources/js/Pages/Guru/Ujian/List.vue` — daftar ujian per kelas_mapel
  (mirror `Guru/Tugas/List.vue`), tombol "Buat Ujian" mengarah ke Builder,
  tombol "Lihat Hasil".
- `resources/js/Pages/Guru/Ujian/Builder.vue` — form buat/edit ujian: field
  judul, deskripsi, durasi_menit, kategori_nilai, waktu_mulai/waktu_selesai,
  toggle acak_soal/acak_opsi, dan panel pemilihan soal dari bank (checklist
  dengan filter topik/kesulitan, input poin per soal terpilih, total poin
  ditampilkan real-time).
- `resources/js/Pages/Guru/Ujian/Hasil.vue` — tabel hasil per siswa: nama,
  status attempt, skor, waktu mulai/submit, badge jumlah tab-switch (mis.
  badge kuning "3x pindah tab" jika `tab_switch_count > 0`, klik untuk buka
  detail jawaban), tombol export Excel/PDF memakai `export_excel_url`/
  `export_pdf_url` dari props (pola sama seperti `Guru/Tugas/List.vue`).
- `resources/js/Pages/Guru/Ujian/AttemptDetail.vue` — review jawaban satu
  siswa: tiap soal, jawaban dipilih, kunci jawaban benar, timestamp log
  tab-switch mentah.

### Siswa

- `resources/js/Pages/Siswa/Ujian/Index.vue` — daftar ujian tersedia
  (mirror `Siswa/Tugas/Index.vue`): status (`Belum dikerjakan`/`Sedang
  berlangsung`/`Selesai, skor: X`/`Waktu habis`), tombol "Mulai Ujian"
  (disabled jika `!isBuka()`, dengan keterangan jendela waktu).
- `resources/js/Pages/Siswa/Ujian/Kerjakan.vue` — halaman pengerjaan.
  Keputusan tata letak: **satu soal per layar dengan navigasi
  nomor-soal di sidebar** (bukan semua soal dalam satu halaman panjang),
  karena ini memudahkan render timer yang selalu terlihat, mengurangi risiko
  siswa scroll-cheat melihat semua soal sekaligus untuk direkam/screenshot
  masif, dan lebih mudah dioperasikan di layar kecil/tablet sekolah. Sidebar
  navigasi nomor soal menampilkan indikator sudah-dijawab/belum per nomor
  (warna berbeda), siswa bebas lompat antar nomor (tidak dipaksa linear).
  Detail interaksi:
  - Timer countdown di header, dihitung dari `batas_waktu` (epoch ms) yang
    dikirim server, dengan `setInterval` klien; saat mencapai nol, panggil
    `submit()` otomatis dan tampilkan pesan "Waktu habis, jawaban
    dikumpulkan otomatis."
  - Memilih opsi jawaban langsung memicu `POST` (fetch/axios, bukan Inertia
    `router.post`, agar tidak reload state timer) ke
    `siswa.ujian.jawab`, dengan optimistic UI (highlight opsi terpilih
    segera) dan indikator kecil "tersimpan" setelah response sukses.
  - Saat komponen mount, langsung load jawaban existing dari props (dikirim
    server dari data `ujian_attempt_jawaban` yang sudah ada) untuk mengisi
    ulang pilihan — ini yang membuat refresh mid-exam tidak kehilangan
    progres.
  - Page Visibility API: `document.addEventListener('visibilitychange', ...)`
    — setiap kali `document.hidden` menjadi `true` selama attempt aktif,
    panggil `POST siswa.ujian.tab-switch` (fire-and-forget, tidak
    memblokir UI, tidak menampilkan alert ke siswa — sesuai keputusan produk
    "soft signal", siswa tidak diberi tahu bahwa ini terekam, supaya tidak
    mendorong siswa mencari cara mengelabui, dan supaya tidak terkesan
    seperti proctoring yang mengintimidasi).
  - Tombol "Selesai & Kumpulkan" memicu konfirmasi modal lalu `submit()`.
  - Refresh/navigasi keluar: tambahkan `window.addEventListener('beforeunload', ...)`
    dengan peringatan native browser ("Perubahan yang belum disimpan..."),
    murni sebagai pencegah kecelakaan — bukan pengaman data karena data
    sudah tersimpan lewat autosave per jawaban.
- `resources/js/Pages/Siswa/Ujian/Hasil.vue` — halaman hasil setelah submit:
  skor total, jumlah benar dari total soal, status (`selesai` vs
  `waktu_habis`), tanpa kunci jawaban per soal (lihat §6 method `hasil()`).

## 10. Export/Rekap Integrasi

Tambahkan method baru di `app/Services/Reports/Exports/GuruReportService.php`,
mengikuti kontrak `['headers' => [...], 'rows' => [...], 'context' => string, 'slug' => string]`
persis seperti method `tugas()` yang sudah ada:

```php
public function ujianHasil(KelasMapel $kelasMapel, Ujian $ujian): array
{
    // Query UjianAttempt::with(['siswa.user'])->where('ujian_id', $ujian->id)
    // ->whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelasMapel->kelas_id)->where('status', 'aktif'))
    // Kolom: No, Nama Siswa, NIS, Status, Skor, Waktu Mulai, Waktu Submit, Jumlah Tab-Switch
    // context: "{$kelasMapel->kelas->displayName()} - {$kelasMapel->mataPelajaran->nama_mapel} - {$ujian->judul}"
    // slug: Str::slug($ujian->judul)
}
```

Tambahkan di `app/Http/Controllers/ExportController.php`, method thin
`guruUjianHasilExcel`/`guruUjianHasilPdf`, pola persis meniru
`guruTugasExcel`/`guruTugasPdf`:

```php
public function guruUjianHasilExcel(Request $request, KelasMapel $kelasMapel, Ujian $ujian)
{
    $dataset = $this->guruReport->ujianHasil($kelasMapel, $ujian);

    return $this->excel->table('ujian_'.$dataset['slug'].'.xlsx', 'HASIL UJIAN', $dataset['context'], $dataset['headers'], $dataset['rows']);
}

public function guruUjianHasilPdf(Request $request, KelasMapel $kelasMapel, Ujian $ujian)
{
    $dataset = $this->guruReport->ujianHasil($kelasMapel, $ujian);

    return $this->pdf->table('ujian_'.$dataset['slug'].'.pdf', 'HASIL UJIAN', $dataset['context'], $dataset['headers'], $dataset['rows'], null, null, $this->pdf->teacherSigner($request));
}
```

Route sudah didaftarkan di §8 (`ujian.hasil.export.excel`/`.pdf`). Catatan:
`ExportController` sudah dirombak jadi controller tipis yang mendelegasikan
ke service (`$this->excel`, `$this->pdf`, `$this->guruReport` — lihat
constructor `ExportController` untuk nama property yang sudah ada persis).

## 11. Anti-Cheat / Tab-Switch — Detail

- Deteksi murni klien (`visibilitychange`), tidak ada pengecekan server-side
  tambahan (tidak mendeteksi devtools terbuka, tidak mendeteksi resize
  window, dsb — di luar scope).
- Data mentah: `ujian_attempt.tab_switch_log` (array timestamp ISO8601) dan
  `tab_switch_count` (denormalized counter untuk query cepat tanpa decode
  JSON, dipakai di tabel `Guru/Ujian/Hasil.vue` untuk sorting/filter).
- Presentasi ke guru: badge non-blocking di `Guru/Ujian/Hasil.vue` per baris
  siswa (mis. abu-abu jika 0, kuning jika 1–2, merah jika ≥3 — threshold
  bebas ditentukan implementer, bukan bagian kontrak wajib), klik untuk
  expand daftar timestamp mentah di `AttemptDetail.vue`. Ini murni informasi,
  TIDAK memblokir submit, TIDAK mengubah skor secara otomatis (guru yang
  menilai signifikansinya secara manual, sesuai keputusan produk "soft
  signal untuk guru review, bukan proctoring").

## 12. Testing Strategy

Tempatkan di `tests/Feature/`, ikuti pola penamaan existing
(`AbsensiExportAllTest.php`, `WhatsAppReminderSendTest.php` gaya
`<Domain><Skenario>Test.php`) dan pola helper `makeUser()`/seed fixture
manual yang dipakai `AbsensiExportAllTest.php` (baca file itu sebagai
referensi struktur test lengkap sebelum menulis test baru). Semua test pakai
`RefreshDatabase`.

Test wajib:

- `CbtSoalBankManagementTest.php` — guru membuat soal + opsi via
  `guru.soal-bank.store`; assert tersimpan dengan `is_benar` tepat 1 opsi
  true (atau validasi menolak jika 0/>1 opsi benar dikirim — tentukan aturan
  validasi ini secara eksplisit di `StoreSoalBankRequest`: **tepat satu**
  opsi `is_benar = true` wajib, minimal 2 opsi, maksimal 5 opsi).
- `CbtUjianBuilderTest.php` — guru membuat ujian dari kelas_mapel dengan
  memilih beberapa soal dari bank miliknya; assert baris `ujian_soal`
  tersimpan dengan poin benar; assert guru TIDAK bisa memilih `soal_bank_id`
  milik guru lain (expect 422/403).
- `CbtAttemptShuffleTest.php` — siswa memulai attempt; assert
  `urutan_soal_ids` tersimpan, berisi seluruh `ujian_soal.id` milik ujian
  tsb (tidak kurang/lebih), dan memanggil `mulai()` dua kali tidak membuat
  attempt kedua (idempotent, redirect ke attempt yang sama).
- `CbtSubmitAutoGradingTest.php` — siswa menjawab semua soal via
  `siswa.ujian.jawab`, lalu submit; assert `skor_total`/`skor_maksimal`
  terhitung benar sesuai opsi benar yang dipilih vs tidak.
- `CbtNilaiAkhirSyncTest.php` — setelah submit, assert `nilai_akhir` kolom
  yang sesuai `kategori_nilai` ujian (uji keempat kategori NH/STS/SAS/SAT
  terpisah, minimal salah satu kategori STS/SAS/SAT karena ini kasus BARU)
  terisi dengan rata-rata skor 0–100 yang benar; assert kolom lain di baris
  `nilai_akhir` yang sama (mis. `sum1`) tidak berubah (existing value tetap
  dipertahankan); assert overwrite terjadi saat sudah ada nilai manual
  sebelumnya (sesuai keputusan §7).
- `CbtIdorGuardTest.php` — siswa A tidak bisa akses
  `siswa.ujian.kerjakan`/`jawab`/`submit`/`hasil` milik attempt siswa B
  (expect 403); guru A tidak bisa akses/edit/hapus `soal_bank`/`ujian` milik
  guru B (expect 403); siswa tidak bisa memulai ujian dari kelas_mapel yang
  bukan kelasnya.
- `CbtTimeExpiryAutoSubmitTest.php` — set `batas_waktu` attempt ke masa lalu
  (via `Carbon::setTestNow` atau manipulasi langsung kolom di DB), lalu akses
  `kerjakan()`/`hasil()`; assert status berubah otomatis menjadi
  `waktu_habis` dan skor dihitung dari jawaban yang sempat tersimpan;
  assert endpoint `jawab()` setelah waktu habis ditolak (422/403), bahkan
  jika klien masih mencoba mengirim.
- `CbtTabSwitchLogTest.php` — panggil `siswa.ujian.tab-switch` beberapa
  kali; assert `tab_switch_count` bertambah dan `tab_switch_log` berisi
  timestamp; assert endpoint ditolak untuk attempt yang bukan miliknya atau
  sudah `selesai`.

## 13. Build Order / Milestones

- [x] **Tahap A — Skema & Model**
  - [x] Tulis migrasi `2026_09_25_000001_create_cbt_tables.php` sesuai §3.
  - [x] Buat 6 model baru sesuai §4 (`SoalBank`, `SoalBankOpsi`, `Ujian`, `UjianSoal`, `UjianAttempt`, `UjianAttemptJawaban`).
  - [x] Jalankan `php artisan migrate` di environment dev, verifikasi skema.
- [x] **Tahap B — Otorisasi**
  - [x] Buat `UjianPolicy`, `SoalBankPolicy`.
  - [x] Registrasi Gate di `AppServiceProvider`.
- [x] **Tahap C — Backend Guru: Bank Soal**
  - [x] `SoalBankController` + Form Request (`StoreSoalBankRequest`,
        `UpdateSoalBankRequest` dengan aturan "tepat satu opsi benar").
  - [x] Routes bank soal.
- [x] **Tahap D — Backend Guru: Ujian Builder**
  - [x] `UjianController` (guru) index/list/create/store/edit/update/destroy/hasil/detailJawaban.
  - [x] Form Request `StoreUjianRequest`/`UpdateUjianRequest` dengan IDOR
        guard kepemilikan `soal_bank_id`.
  - [x] Routes ujian guru.
- [x] **Tahap E — Backend Siswa: Attempt Flow**
  - [x] `UjianController` (siswa): index/mulai/kerjakan/jawab/submit/
        logTabSwitch/hasil.
  - [x] `CbtScoringService` (hitungSkor, submit, autoSubmitKarenaWaktuHabis).
  - [x] Routes ujian siswa.
- [x] **Tahap F — Auto-Grading & `nilai_akhir` Sync**
  - [x] Implementasi `syncNilaiUjian()` di `CbtScoringService`, verifikasi
        manual terhadap kasus overwrite manual STS/SAS/SAT & persistensi kolom existing.
- [x] **Tahap G — Frontend Guru**
  - [x] `Guru/SoalBank/Index.vue`.
  - [x] `Guru/Ujian/Index.vue`, `List.vue`, `Builder.vue`, `Hasil.vue`,
        `AttemptDetail.vue`.
- [x] **Tahap H — Frontend Siswa**
  - [x] `Siswa/Ujian/Index.vue`.
  - [x] `Siswa/Ujian/Kerjakan.vue` (timer, autosave, visibility API,
        beforeunload).
  - [x] `Siswa/Ujian/Hasil.vue`.
- [x] **Tahap I — Export/Rekap**
  - [x] `GuruReportService::ujianHasil()`.
  - [x] `ExportController::guruUjianHasilExcel/Pdf`.
  - [x] Routes export.
- [x] **Tahap J — Testing**
  - [x] Seluruh test di §12 (`CbtSoalBankManagementTest`, `CbtUjianBuilderTest`, `CbtAttemptShuffleTest`, `CbtSubmitAutoGradingTest`, `CbtNilaiAkhirSyncTest`, `CbtIdorGuardTest`, `CbtTimeExpiryAutoSubmitTest`, `CbtTabSwitchLogTest`).
  - [x] `php artisan test` (129 tests, 1106 assertions passed), Pint linter, `vue-tsc` typecheck (0 errors), build frontend (`vite build`) — semua lulus.
- [x] **Tahap K — Dokumentasi**
  - [x] Update dokumen ini dengan status audit implementasi selesai.

### Status Audit 2026-09-24

- **Database**: Migrasi `2026_09_25_000001_create_cbt_tables.php` dijalankan dengan sukses.
- **Backend & Models**: 6 model Eloquent (`SoalBank`, `SoalBankOpsi`, `Ujian`, `UjianSoal`, `UjianAttempt`, `UjianAttemptJawaban`) + `CbtScoringService` + Form Requests terintegrasi.
- **Frontend**: 6 halaman Vue Guru + 3 halaman Vue Siswa + navigasi sidebar menu.
- **Reports & Export**: Export Excel & PDF hasil ujian terintegrasi dengan `GuruReportService` dan `ExportController`.
- **Quality Assurance**: 129 PHPUnit tests (17 spesifik CBT) lulus 100%, `vue-tsc` type checking 0 error, build Vite sukses.

## 14. Eksplisit "Di Luar Scope Fase Ini"

- **Retake/attempt ulang** — sengaja ditunda. Fase ini: satu attempt per
  siswa per ujian, tanpa mekanisme guru mengizinkan pengulangan. Jika
  dibutuhkan nanti: tambah kolom `ujian.izinkan_retake` (boolean) dan
  `ujian_attempt.attempt_ke` (integer, hapus unique constraint tunggal, ganti
  jadi unique `(ujian_id, siswa_id, attempt_ke)`), plus keputusan baru soal
  bagaimana beberapa attempt yang sama kategori diagregasi ke `nilai_akhir`
  (skor tertinggi? rata-rata? terakhir?).
- **Tipe soal lain** (esai, benar-salah, menjodohkan) — fase ini pilihan
  ganda saja. Skema `soal_bank`/`soal_bank_opsi` dirancang agar bisa
  diperluas nanti dengan kolom `tipe_soal` di `soal_bank` tanpa migrasi
  destruktif, tapi tidak dibangun sekarang.
- **Hardening keamanan pengacakan** — urutan soal/opsi disimpan di server
  dan dikirim ke klien sebagai data biasa; seorang siswa yang cukup teknis
  (membaca response JSON/props Inertia secara langsung) berpotensi
  merekonstruksi informasi lebih dari yang dimaksud UI menampilkan. Ini
  diterima sebagai limitasi yang diketahui untuk fase ini (skala sekolah
  tunggal, bukan ujian berisiko tinggi/high-stakes), bukan sesuatu yang perlu
  diselesaikan sekarang.
- **Proctoring penuh** (webcam, rekam layar, deteksi wajah) — tidak
  dibangun; deteksi tab-switch adalah satu-satunya sinyal anti-cheat.
- **Berbagi bank soal antar guru** — bank soal ketat per `guru_id`. Fitur
  "bagikan ke guru lain"/"bank soal sekolah" adalah kandidat fase depan yang
  butuh desain kepemilikan/atribusi baru.
- **Import soal dari Excel/Word** — sangat umum diminta secara realistis
  untuk bank soal, secara eksplisit ditandai sebagai kandidat kuat fase 2,
  tapi tidak dibangun sekarang. Implementasi akan butuh parser dan validasi
  format template — cek `composer.json` untuk library spreadsheet yang
  sudah dipakai proyek ini (OpenSpout) sebagai titik awal.
- **Menampilkan kunci jawaban ke siswa setelah submit** — fase ini hanya
  menampilkan skor, bukan pembahasan per soal ke siswa. Bisa ditambah nanti
  sebagai opsi `ujian.tampilkan_kunci_setelah_submit` (boolean).
- **Scheduler/cron untuk auto-submit proaktif** — fase ini memakai lazy
  auto-submit saat attempt diakses (§6.1), bukan job terjadwal yang berjalan
  independen dari akses pengguna. Jika nanti dibutuhkan notifikasi WhatsApp
  "waktu ujian hampir habis" atau auto-submit tanpa perlu diakses siswa,
  perlu scheduled command baru — di luar scope sekarang.

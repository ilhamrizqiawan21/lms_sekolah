<?php

namespace Database\Seeders;

use App\Models\CapaianPembelajaran;
use App\Models\JurnalHarian;
use App\Models\KelasMapel;
use App\Models\Kurikulum;
use App\Models\KurikulumDokumen;
use App\Models\Siswa;
use App\Models\SoalBank;
use App\Models\SoalBankOpsi;
use App\Models\TahunAjaran;
use App\Models\TujuanPembelajaran;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianAttemptJawaban;
use App\Models\UjianSoal;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAdministrasiGuruSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') || ! config('app.demo_mode', false)) {
            throw new \RuntimeException('Seeder administrasi guru hanya boleh dijalankan saat DEMO_MODE=true dan bukan di production.');
        }

        $guru = User::query()
            ->where('username', 'guru')
            ->where('role_id', 2)
            ->first()
            ?? User::query()->where('role_id', 2)->orderBy('id')->first();
        $tahunAjaran = TahunAjaran::getAktif();

        if (! $guru || ! $tahunAjaran) {
            throw new \RuntimeException('Jalankan DemoUserSeeder dan DemoAcademicSeeder terlebih dahulu.');
        }

        $kelasMapel = KelasMapel::query()
            ->with(['kelas', 'mataPelajaran'])
            ->where('guru_id', $guru->id)
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->where('semester', '1')
            ->orderBy('kelas_id')
            ->orderBy('mapel_id')
            ->first();

        if (! $kelasMapel) {
            throw new \RuntimeException('Belum ada kelas-mapel untuk guru demo. Jalankan DemoAcademicSeeder terlebih dahulu.');
        }

        $kurikulum = Kurikulum::updateOrCreate(
            [
                'tahun_ajaran_id' => $tahunAjaran->id,
                'mapel_id' => $kelasMapel->mapel_id,
                'guru_id' => $guru->id,
                'semester' => '1',
                'nama' => "ATP Demo {$kelasMapel->mataPelajaran->nama_mapel}",
            ],
            [
                'jenis_kurikulum' => 'merdeka',
                'fase' => 'D',
                'deskripsi' => 'Kurikulum contoh untuk mencoba alur CP, TP, ATP, dokumen perencanaan, dan jurnal guru.',
                'status' => 'aktif',
            ]
        );

        $cp = CapaianPembelajaran::updateOrCreate(
            ['kurikulum_id' => $kurikulum->id, 'kode' => 'CP-DEMO-01'],
            [
                'elemen' => 'Pemahaman konsep',
                'deskripsi' => "Peserta didik memahami konsep dasar {$kelasMapel->mataPelajaran->nama_mapel} dan mampu menerapkannya dalam situasi sehari-hari.",
                'urutan' => 1,
            ]
        );

        $tpRows = [
            [
                'kode' => 'TP-DEMO-01',
                'bab' => 'Bab 1 — Konsep Dasar',
                'tujuan' => 'Peserta didik dapat menjelaskan konsep dasar dan menggunakan istilah penting secara tepat.',
                'materi_pokok' => 'Pengertian, unsur, dan contoh konsep dasar.',
                'indikator_ketercapaian' => 'Menjelaskan konsep dengan bahasa sendiri dan memberikan minimal dua contoh.',
                'alokasi_jp' => 8,
                'estimasi_pertemuan' => 4,
                'urutan' => 1,
            ],
            [
                'kode' => 'TP-DEMO-02',
                'bab' => 'Bab 2 — Penerapan',
                'tujuan' => 'Peserta didik dapat menerapkan konsep pada permasalahan kontekstual secara bertahap.',
                'materi_pokok' => 'Strategi penyelesaian masalah dan latihan kontekstual.',
                'indikator_ketercapaian' => 'Memilih strategi yang sesuai, menunjukkan langkah kerja, dan menyimpulkan hasil.',
                'alokasi_jp' => 10,
                'estimasi_pertemuan' => 5,
                'urutan' => 2,
            ],
            [
                'kode' => 'TP-DEMO-03',
                'bab' => 'Bab 3 — Komunikasi Hasil',
                'tujuan' => 'Peserta didik dapat menyajikan hasil pemikiran dan merefleksikan proses belajar.',
                'materi_pokok' => 'Presentasi, refleksi, dan umpan balik.',
                'indikator_ketercapaian' => 'Menyajikan hasil secara runtut dan menuliskan refleksi perbaikan.',
                'alokasi_jp' => 6,
                'estimasi_pertemuan' => 3,
                'urutan' => 3,
            ],
        ];

        $tujuanPembelajaran = collect($tpRows)->map(function (array $row) use ($kurikulum, $cp) {
            return TujuanPembelajaran::updateOrCreate(
                ['kurikulum_id' => $kurikulum->id, 'kode' => $row['kode']],
                [...$row, 'capaian_pembelajaran_id' => $cp->id, 'bentuk_asesmen' => 'Tes tertulis, praktik, dan refleksi']
            );
        });

        $kurikulum->kelasMapel()->syncWithoutDetaching([$kelasMapel->id]);

        $this->seedDocuments($kurikulum, $kelasMapel, $tujuanPembelajaran);
        $this->seedJurnal($guru, $tahunAjaran, $kelasMapel, $tujuanPembelajaran);
        $this->seedExam($guru, $kelasMapel);

        $this->command?->info("Data administrasi guru demo tersedia untuk {$kelasMapel->mataPelajaran->nama_mapel} {$kelasMapel->kelas->nama_kelas}.");
    }

    private function seedDocuments(Kurikulum $kurikulum, KelasMapel $kelasMapel, $tujuanPembelajaran): void
    {
        $rows = $tujuanPembelajaran->map(fn (TujuanPembelajaran $item) => [
            'tp_id' => $item->id,
            'bab' => $item->bab,
            'semester' => '1',
            'alokasi_jp' => $item->alokasi_jp,
        ])->values()->all();

        $this->document($kurikulum, [
            'kelas_mapel_id' => null,
            'jenis' => KurikulumDokumen::JENIS_ALOKASI_WAKTU,
            'semester' => null,
            'bab_key' => null,
        ], [
            'judul' => 'Alokasi Waktu Demo',
            'isi' => [
                'jp_per_minggu' => 2,
                'minggu_efektif' => ['1' => 18, '2' => 18],
                'bulan_mulai' => ['1' => 7, '2' => 1],
                'catatan' => 'Data contoh: sesuaikan dengan kalender pendidikan sekolah.',
            ],
        ]);

        $this->document($kurikulum, [
            'kelas_mapel_id' => null,
            'jenis' => KurikulumDokumen::JENIS_PROTA,
            'semester' => '1',
            'bab_key' => null,
        ], ['judul' => 'Program Tahunan Demo', 'isi' => ['rows' => $rows]]);

        $this->document($kurikulum, [
            'kelas_mapel_id' => null,
            'jenis' => KurikulumDokumen::JENIS_PROMES,
            'semester' => '1',
            'bab_key' => null,
        ], [
            'judul' => 'Program Semester Demo',
            'isi' => ['rows' => $rows, 'catatan' => 'Rencana semester contoh untuk uji tampilan dan ekspor.'],
        ]);

        $this->document($kurikulum, [
            'kelas_mapel_id' => null,
            'jenis' => KurikulumDokumen::JENIS_KKTP,
            'semester' => '1',
            'bab_key' => null,
        ], [
            'judul' => 'KKTP / KKM Demo',
            'isi' => [
                'skala' => 'interval',
                'kkm' => 75,
                'intervals' => [
                    ['range' => '0–40', 'label' => 'Belum berkembang'],
                    ['range' => '41–60', 'label' => 'Mulai berkembang'],
                    ['range' => '61–80', 'label' => 'Berkembang sesuai harapan'],
                    ['range' => '81–100', 'label' => 'Sangat berkembang'],
                ],
                'rows' => $tujuanPembelajaran->map(fn (TujuanPembelajaran $item) => [
                    'tp_id' => $item->id,
                    'indikator' => $item->indikator_ketercapaian,
                    'kkm' => 75,
                ])->values()->all(),
            ],
        ]);

        foreach ($tujuanPembelajaran as $item) {
            $this->document($kurikulum, [
                'kelas_mapel_id' => $kelasMapel->id,
                'jenis' => KurikulumDokumen::JENIS_MODUL_AJAR,
                'semester' => '1',
                'bab_key' => $item->bab,
            ], [
                'judul' => "Modul Ajar Demo — {$item->bab}",
                'isi' => [
                    'kesiapan' => 'Peserta didik telah mengenal istilah dasar dari pembelajaran sebelumnya.',
                    'tema_kbc' => '',
                    'karakteristik_materi' => $item->materi_pokok,
                    'profil_lulusan' => 'Bernalar kritis, mandiri, dan mampu berkomunikasi.',
                    'lintas_disiplin' => 'Bahasa dan literasi digital.',
                    'iklim' => 'Kolaboratif, aman untuk bertanya, dan menghargai umpan balik.',
                    'kerangka' => 'Problem based learning dengan diskusi kelompok dan presentasi singkat.',
                    'asesmen' => 'Diagnostik awal, observasi proses, kuis formatif, dan refleksi.',
                    'langkah' => [[
                        'topik' => $item->materi_pokok,
                        'pendahuluan' => 'Apersepsi dan penyampaian tujuan belajar.',
                        'inti' => 'Eksplorasi contoh, kerja kelompok, latihan, dan diskusi hasil.',
                        'penutup' => 'Rangkuman, umpan balik, dan refleksi satu menit.',
                    ]],
                ],
            ]);
        }
    }

    private function document(Kurikulum $kurikulum, array $identity, array $values): void
    {
        KurikulumDokumen::updateOrCreate(
            ['kurikulum_id' => $kurikulum->id, ...$identity],
            [...$values, 'status' => 'final']
        );
    }

    private function seedJurnal(User $guru, TahunAjaran $tahunAjaran, KelasMapel $kelasMapel, $tujuanPembelajaran): void
    {
        $jumlahSiswa = Siswa::query()->where('kelas_id', $kelasMapel->kelas_id)->where('status', 'aktif')->count();

        foreach ([
            ['offset' => 6, 'pertemuan' => 1, 'tp' => $tujuanPembelajaran[0]],
            ['offset' => 3, 'pertemuan' => 2, 'tp' => $tujuanPembelajaran[1]],
        ] as $row) {
            JurnalHarian::updateOrCreate(
                ['kelas_mapel_id' => $kelasMapel->id, 'tanggal' => now()->subDays($row['offset'])->toDateString()],
                [
                    'guru_id' => $guru->id,
                    'tahun_ajaran_id' => $tahunAjaran->id,
                    'pertemuan_ke' => $row['pertemuan'],
                    'jp' => 2,
                    'materi_pokok' => $row['tp']->materi_pokok,
                    'tujuan_pembelajaran' => $row['tp']->tujuan,
                    'kegiatan_pendahuluan' => 'Apersepsi dan pertanyaan pemantik.',
                    'kegiatan_inti' => 'Diskusi kelompok, latihan terarah, dan presentasi hasil.',
                    'kegiatan_penutup' => 'Rangkuman dan refleksi singkat.',
                    'hasil_pengamatan' => 'Sebagian besar siswa aktif; beberapa siswa memerlukan pendampingan tambahan.',
                    'refleksi' => 'Pertemuan berikutnya perlu menyediakan contoh bertingkat.',
                    'ket_kehadiran' => 'Kehadiran baik; data ini hanya untuk demo.',
                    'jumlah_siswa' => $jumlahSiswa,
                    'status' => 'final',
                ]
            );
        }
    }

    private function seedExam(User $guru, KelasMapel $kelasMapel): void
    {
        $exam = Ujian::updateOrCreate(
            ['kelas_mapel_id' => $kelasMapel->id, 'judul' => 'Analisis Butir Demo'],
            [
                'deskripsi' => 'Ujian contoh untuk mencoba halaman Analisis Butir Soal.',
                'durasi_menit' => 45,
                'kategori_nilai' => 'NH',
                'waktu_mulai' => now()->subDay(),
                'waktu_selesai' => now()->addMonths(3),
                'acak_soal' => false,
                'acak_opsi' => false,
                'anticheat_aktif' => true,
                'maks_pelanggaran' => 3,
                'wajib_fullscreen' => true,
            ]
        );

        $questions = [
            ['prompt' => 'Manakah pernyataan yang paling tepat untuk konsep dasar materi ini?', 'answer' => 'Konsep adalah gagasan yang membantu menjelaskan suatu hal.', 'wrong' => 'Konsep hanya berupa hafalan tanpa makna.'],
            ['prompt' => 'Langkah pertama ketika menyelesaikan masalah kontekstual adalah ...', 'answer' => 'Memahami informasi dan pertanyaan masalah.', 'wrong' => 'Langsung menebak jawaban akhir.'],
            ['prompt' => 'Contoh penerapan konsep yang baik harus memiliki ...', 'answer' => 'Hubungan yang jelas antara konsep dan situasi.', 'wrong' => 'Istilah yang sebanyak-banyaknya.'],
            ['prompt' => 'Refleksi belajar digunakan untuk ...', 'answer' => 'Mengetahui proses yang sudah baik dan yang perlu diperbaiki.', 'wrong' => 'Menghapus semua hasil kerja sebelumnya.'],
        ];
        $ujianSoal = collect();

        foreach ($questions as $index => $question) {
            $bank = SoalBank::updateOrCreate(
                ['guru_id' => $guru->id, 'mapel_id' => $kelasMapel->mapel_id, 'pertanyaan' => $question['prompt']],
                [
                    'tipe_soal' => SoalBank::TYPE_PILIHAN_GANDA,
                    'level_kognitif' => $index < 2 ? 'C2' : 'C3',
                    'topik' => 'Materi Demo',
                    'kesulitan' => $index === 3 ? SoalBank::KESULITAN_SULIT : SoalBank::KESULITAN_SEDANG,
                    'kategori_nilai' => 'NH',
                ]
            );
            $correct = SoalBankOpsi::updateOrCreate(
                ['soal_bank_id' => $bank->id, 'urutan' => 1],
                ['teks_opsi' => $question['answer'], 'is_benar' => true]
            );
            SoalBankOpsi::updateOrCreate(
                ['soal_bank_id' => $bank->id, 'urutan' => 2],
                ['teks_opsi' => $question['wrong'], 'is_benar' => false]
            );
            SoalBankOpsi::updateOrCreate(
                ['soal_bank_id' => $bank->id, 'urutan' => 3],
                ['teks_opsi' => 'Pernyataan yang tidak berkaitan dengan materi.', 'is_benar' => false]
            );
            $ujianSoal->push(UjianSoal::updateOrCreate(
                ['ujian_id' => $exam->id, 'soal_bank_id' => $bank->id],
                ['poin' => 20, 'urutan' => $index + 1]
            ));
        }

        $students = Siswa::query()
            ->where('kelas_id', $kelasMapel->kelas_id)
            ->where('status', 'aktif')
            ->orderBy('id')
            ->limit(5)
            ->get();
        $maxScore = $ujianSoal->sum('poin');

        foreach ($students as $studentIndex => $student) {
            $startedAt = now()->subDays(2)->addMinutes($studentIndex * 5);
            $attempt = UjianAttempt::updateOrCreate(
                ['ujian_id' => $exam->id, 'siswa_id' => $student->id],
                [
                    'status' => UjianAttempt::STATUS_SELESAI,
                    'seed' => 1000 + $student->id,
                    'urutan_soal_ids' => $ujianSoal->pluck('id')->values()->all(),
                    'waktu_mulai' => $startedAt,
                    'batas_waktu' => $startedAt->copy()->addMinutes(45),
                    'waktu_submit' => $startedAt->copy()->addMinutes(25),
                    'alasan_selesai' => null,
                    'skor_maksimal' => $maxScore,
                    'tab_switch_log' => $studentIndex === 2 ? [['at' => $startedAt->copy()->addMinutes(8)->toIso8601String(), 'reason' => 'visibilitychange']] : [],
                    'tab_switch_count' => $studentIndex === 2 ? 1 : 0,
                ]
            );
            $score = 0;

            foreach ($ujianSoal as $questionIndex => $item) {
                $options = SoalBankOpsi::query()->where('soal_bank_id', $item->soal_bank_id)->orderBy('urutan')->get();
                $correct = $options->firstWhere('is_benar', true);
                $isCorrect = (($studentIndex + $questionIndex) % 4) !== 0;
                $selected = $isCorrect ? $correct : $options->first(fn (SoalBankOpsi $option) => ! $option->is_benar);
                $points = $isCorrect ? (float) $item->poin : 0;
                $score += $points;

                UjianAttemptJawaban::updateOrCreate(
                    ['ujian_attempt_id' => $attempt->id, 'ujian_soal_id' => $item->id],
                    [
                        'soal_bank_opsi_id' => $selected?->id,
                        'urutan_opsi_ids' => $options->pluck('id')->values()->all(),
                        'is_benar' => $isCorrect,
                        'poin_didapat' => $points,
                        'dijawab_pada' => $startedAt->copy()->addMinutes(5 + $questionIndex * 3),
                    ]
                );
            }

            $attempt->update(['skor_total' => $score]);
        }
    }
}

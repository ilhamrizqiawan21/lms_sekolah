<?php

namespace Database\Seeders;

use App\Models\Absensi;
use App\Models\KelasDaring;
use App\Models\KelasMapel;
use App\Models\Materi;
use App\Models\NilaiAkhir;
use App\Models\PengumpulanTugas;
use App\Models\SikapSosial;
use App\Models\SikapSpiritual;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Tugas;
use App\Services\AttendanceScheduleService;
use Illuminate\Database\Seeder;

class DemoLmsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tahunAjaran = TahunAjaran::getAktif() ?? TahunAjaran::where('tahun', '2026/2027')->first();

        if (! $tahunAjaran) {
            return;
        }

        $kelasMapelList = KelasMapel::with(['kelas', 'mataPelajaran'])
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->where('semester', '1')
            ->orderBy('kelas_id')
            ->orderBy('mapel_id')
            ->get();

        // 3 bulan terakhir, supaya rekap & ekspor absensi punya riwayat lintas bulan.
        $bulanList = [
            now()->format('Y-m'),
            now()->subMonthNoOverflow()->format('Y-m'),
            now()->subMonthsNoOverflow(2)->format('Y-m'),
        ];

        $meetingCounter = 0;

        foreach ($kelasMapelList as $index => $kelasMapel) {
            $mapelNama = $kelasMapel->mataPelajaran->nama_mapel;

            Materi::updateOrCreate(
                ['kelas_mapel_id' => $kelasMapel->id, 'judul' => "Pengantar {$mapelNama}"],
                ['deskripsi' => "Materi pembuka untuk {$mapelNama} di kelas demo.", 'file_path' => null]
            );
            Materi::updateOrCreate(
                ['kelas_mapel_id' => $kelasMapel->id, 'judul' => "Latihan Lanjutan {$mapelNama}"],
                ['deskripsi' => 'Materi lanjutan untuk memperlihatkan alur belajar bertahap.', 'file_path' => null]
            );

            $tugasHarian = Tugas::updateOrCreate(
                ['kelas_mapel_id' => $kelasMapel->id, 'judul' => "Tugas Harian {$mapelNama}"],
                [
                    'deskripsi' => 'Kerjakan latihan singkat berdasarkan materi yang tersedia.',
                    'batas_waktu' => now()->addDays(5 + $index),
                    'kategori_nilai' => 'NH',
                ]
            );
            $tugasSts = Tugas::updateOrCreate(
                ['kelas_mapel_id' => $kelasMapel->id, 'judul' => "Ulangan Tengah Semester {$mapelNama}"],
                [
                    'deskripsi' => 'Evaluasi tengah semester untuk mengukur pemahaman siswa.',
                    'batas_waktu' => now()->addDays(20 + $index),
                    'kategori_nilai' => 'STS',
                ]
            );

            $siswaList = Siswa::where('kelas_id', $kelasMapel->kelas_id)
                ->where('status', 'aktif')
                ->orderBy('nis')
                ->get();

            foreach ($siswaList as $studentIndex => $siswa) {
                foreach ([$tugasHarian, $tugasSts] as $tugasIndex => $tugas) {
                    $mod = ($studentIndex + $tugasIndex) % 5;
                    $status = match (true) {
                        $mod === 0 => 'belum',
                        $mod === 1 => 'terlambat',
                        default => 'dinilai',
                    };
                    $sudahKumpul = $status !== 'belum';

                    PengumpulanTugas::updateOrCreate(
                        ['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id],
                        [
                            'status' => $status,
                            'nilai' => $sudahKumpul ? 70 + (($studentIndex + $index + $tugasIndex * 5) % 26) : null,
                            'teks_jawaban' => $sudahKumpul ? 'Jawaban demo siswa.' : null,
                            'catatan' => 'Data contoh, bukan data asli.',
                            'tanggal_kumpul' => $sudahKumpul ? now()->subDays($tugasIndex + ($studentIndex % 3)) : null,
                            'graded_at' => $status === 'dinilai' ? now()->subDays($tugasIndex) : null,
                        ]
                    );
                }

                NilaiAkhir::updateOrCreate(
                    [
                        'siswa_id' => $siswa->id,
                        'kelas_mapel_id' => $kelasMapel->id,
                        'tahun_ajaran_id' => $tahunAjaran->id,
                        'semester' => '1',
                    ],
                    [
                        'sum1' => 76 + (($studentIndex + $index) % 12),
                        'sum2' => 78 + (($studentIndex + $index) % 10),
                        'sum3' => 80 + (($studentIndex + $index) % 9),
                        'sum4' => 79 + (($studentIndex + $index) % 8),
                        'nilai_harian' => 80 + (($studentIndex + $index) % 10),
                        'sts' => 77 + (($studentIndex + $index) % 12),
                        'sas' => 79 + (($studentIndex + $index) % 11),
                        'sat' => null,
                    ]
                );

                SikapSpiritual::updateOrCreate(
                    [
                        'siswa_id' => $siswa->id,
                        'kelas_mapel_id' => $kelasMapel->id,
                        'tahun_ajaran_id' => $tahunAjaran->id,
                        'semester' => '1',
                    ],
                    [
                        'taqwa' => 3 + (($studentIndex + $index) % 3),
                        'kejujuran' => 3 + (($studentIndex + $index + 1) % 3),
                        'disiplin' => 3 + (($studentIndex + $index + 2) % 3),
                        'sabar' => 3 + (($studentIndex + $index) % 2),
                        'syukur' => 3 + (($studentIndex + $index + 1) % 2),
                        'tawadhu' => 3 + (($studentIndex + $index + 2) % 2),
                    ]
                );

                SikapSosial::updateOrCreate(
                    [
                        'siswa_id' => $siswa->id,
                        'kelas_mapel_id' => $kelasMapel->id,
                        'tahun_ajaran_id' => $tahunAjaran->id,
                        'semester' => '1',
                    ],
                    [
                        'empati' => 3 + (($studentIndex + $index) % 3),
                        'kerjasama' => 3 + (($studentIndex + $index + 1) % 3),
                        'toleransi' => 3 + (($studentIndex + $index + 2) % 3),
                        'percaya_diri' => 3 + (($studentIndex + $index) % 2),
                        'komunikasi' => 3 + (($studentIndex + $index + 1) % 2),
                    ]
                );
            }

            // Absensi mengikuti tanggal pertemuan riil dari JadwalMengajar (lihat
            // AttendanceScheduleService), agar konsisten dengan halaman Absensi & Ekspor.
            $onlineMeetingMarked = false;

            foreach ($bulanList as $bulan) {
                $meetings = AttendanceScheduleService::meetings($bulan, $kelasMapel);

                foreach ($meetings as $meetingIndex => $meeting) {
                    $isOnline = ! $onlineMeetingMarked
                        && $kelasMapel->pertemuan_per_minggu >= 2
                        && $meetingIndex === 1
                        && $bulan === $bulanList[0];
                    $kelasDaringId = null;

                    if ($isOnline) {
                        $kelasDaring = KelasDaring::firstOrCreate(
                            ['kelas_mapel_id' => $kelasMapel->id, 'tanggal' => $meeting['date']],
                            [
                                'guru_id' => $kelasMapel->guru_id,
                                'judul' => "Kelas Daring {$mapelNama}",
                                'deskripsi' => 'Sesi daring demo untuk memperlihatkan fitur kelas daring.',
                                'pelajaran_ke' => 1,
                                'meeting_url' => 'https://meet.demo.test/'.$kelasMapel->id.'-'.$meeting['date'],
                                'status' => 'selesai',
                            ]
                        );
                        $kelasDaringId = $kelasDaring->id;
                        $onlineMeetingMarked = true;
                    }

                    foreach ($siswaList as $siswa) {
                        $seed = ($siswa->id * 31 + $meetingCounter) % 20;
                        $status = match (true) {
                            $seed < 16 => 'hadir',
                            $seed < 18 => 'sakit',
                            $seed === 18 => 'izin',
                            default => 'alpha',
                        };

                        Absensi::updateOrCreate(
                            [
                                'siswa_id' => $siswa->id,
                                'kelas_mapel_id' => $kelasMapel->id,
                                'tanggal' => $meeting['date'],
                            ],
                            [
                                'status' => $status,
                                'keterangan' => match ($status) {
                                    'izin' => 'Data demo izin',
                                    'sakit' => 'Data demo sakit',
                                    default => null,
                                },
                                'is_daring' => $isOnline,
                                'kelas_daring_id' => $kelasDaringId,
                            ]
                        );

                        $meetingCounter++;
                    }
                }
            }
        }
    }
}

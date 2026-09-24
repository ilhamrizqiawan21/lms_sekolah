<?php

namespace Database\Seeders;

use App\Models\AbsensiWaliKelas;
use App\Models\BiodataSiswa;
use App\Models\CalendarEvent;
use App\Models\ChatMessage;
use App\Models\KelasMapel;
use App\Models\PengumpulanTugas;
use App\Models\Pengumuman;
use App\Models\PenangananSiswa;
use App\Models\PertemuanWaliKelas;
use App\Models\Siswa;
use App\Models\User;
use App\Models\WaliKelas;
use App\Models\WhatsAppMessageLog;
use Illuminate\Database\Seeder;

class DemoSupportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('username', 'admin')->first();

        $this->seedCalendarEvents($admin);
        $this->seedPengumuman($admin);
        $this->seedBiodataSiswa();
        $this->seedChatMessages();
        $this->seedWhatsAppLogs();
        $this->seedWaliKelasData();
    }

    /** Hari libur di luar jendela 3 bulan yang dipakai DemoLmsSeeder untuk absensi,
     *  supaya tidak ada baris absensi yatim akibat tanggal yang tiba-tiba jadi libur. */
    private function seedCalendarEvents(?User $admin): void
    {
        if (! $admin) {
            return;
        }

        $events = [
            ['title' => 'Libur Semester Ganjil', 'description' => 'Libur akhir semester ganjil.', 'event_date' => '2026-12-21', 'is_holiday' => true],
            ['title' => 'Hari Guru Nasional', 'description' => 'Peringatan Hari Guru Nasional.', 'event_date' => '2026-11-25', 'is_holiday' => true],
            ['title' => 'Rapat Koordinasi Guru', 'description' => 'Rapat rutin koordinasi program semester.', 'event_date' => '2026-10-10', 'is_holiday' => false],
        ];

        foreach ($events as $event) {
            CalendarEvent::updateOrCreate(
                ['title' => $event['title'], 'event_date' => $event['event_date']],
                [
                    'user_id' => $admin->id,
                    'description' => $event['description'],
                    'is_holiday' => $event['is_holiday'],
                    'scope' => 'school',
                    'is_done' => false,
                ]
            );
        }
    }

    private function seedPengumuman(?User $admin): void
    {
        if (! $admin) {
            return;
        }

        $items = [
            [
                'judul' => 'Selamat Datang di Semester Baru',
                'isi' => 'Pengumuman ini adalah contoh data demo untuk memperlihatkan tampilan pengumuman ke semua pengguna.',
                'target' => 'semua',
            ],
            [
                'judul' => 'Rapat Guru Bulanan',
                'isi' => 'Contoh pengumuman khusus guru mengenai jadwal rapat koordinasi bulanan.',
                'target' => 'guru',
            ],
            [
                'judul' => 'Pengumpulan Tugas Tepat Waktu',
                'isi' => 'Contoh pengumuman khusus siswa mengenai pentingnya mengumpulkan tugas tepat waktu.',
                'target' => 'siswa',
            ],
        ];

        foreach ($items as $item) {
            Pengumuman::updateOrCreate(
                ['judul' => $item['judul']],
                [
                    'isi' => $item['isi'],
                    'target' => $item['target'],
                    'target_kelas' => null,
                    'kelas_mapel_id' => null,
                    'created_by' => $admin->id,
                    'created_at' => now()->subDays(2),
                ]
            );
        }
    }

    private function seedBiodataSiswa(): void
    {
        $siswaList = Siswa::with('user')->orderBy('nis')->get();

        foreach ($siswaList as $index => $siswa) {
            BiodataSiswa::updateOrCreate(
                ['siswa_id' => $siswa->id],
                [
                    'nama_panggilan' => 'Siswa'.($index + 1),
                    'alamat' => 'Jl. Contoh Demo No. '.($index + 1).', Kota Demo',
                    'tempat_lahir' => 'Kota Demo',
                    'tanggal_lahir' => now()->subYears(13)->subDays($index * 10)->toDateString(),
                    'hobi' => ['Membaca', 'Olahraga', 'Menggambar', 'Musik'][$index % 4],
                    'cita_cita' => ['Dokter', 'Guru', 'Insinyur', 'Polisi', 'Pengusaha'][$index % 5],
                    'nama_ayah' => 'Bapak Demo '.($index + 1),
                    'pekerjaan_ayah' => 'Karyawan Swasta',
                    'nama_ibu' => 'Ibu Demo '.($index + 1),
                    'pekerjaan_ibu' => 'Wiraswasta',
                    'penghasilan_orangtua' => 3000000 + (($index % 5) * 1000000),
                    'jarak_rumah_km' => round(1 + ($index % 10) * 0.7, 2),
                    'transportasi' => ['Jalan Kaki', 'Sepeda', 'Diantar Orang Tua', 'Angkutan Umum'][$index % 4],
                    'kegiatan_luar_sekolah' => 'Mengikuti kegiatan ekstrakurikuler demo di lingkungan rumah.',
                ]
            );
        }
    }

    private function seedChatMessages(): void
    {
        $kelasMapelList = KelasMapel::with('guru')->aktif()->orderBy('kelas_id')->orderBy('mapel_id')->take(6)->get();

        foreach ($kelasMapelList as $kelasMapel) {
            $siswa = Siswa::with('user')->where('kelas_id', $kelasMapel->kelas_id)->where('status', 'aktif')->orderBy('nis')->first();

            if (! $siswa || ! $siswa->user_id || ! $kelasMapel->guru_id) {
                continue;
            }

            ChatMessage::firstOrCreate(
                ['kelas_mapel_id' => $kelasMapel->id, 'user_id' => $siswa->user_id, 'message' => 'Pak/Bu, materi minggu ini sudah bisa diakses?'],
                ['is_read' => true, 'created_at' => now()->subHours(5)]
            );
            ChatMessage::firstOrCreate(
                ['kelas_mapel_id' => $kelasMapel->id, 'user_id' => $kelasMapel->guru_id, 'message' => 'Sudah, silakan cek menu Materi ya.'],
                ['is_read' => false, 'created_at' => now()->subHours(4)]
            );
        }
    }

    private function seedWhatsAppLogs(): void
    {
        $terlambatList = PengumpulanTugas::with(['siswa', 'tugas.kelasMapel'])
            ->where('status', 'terlambat')
            ->take(10)
            ->get();

        foreach ($terlambatList as $item) {
            $kelasMapel = $item->tugas?->kelasMapel;

            if (! $item->siswa || ! $kelasMapel || ! $kelasMapel->guru_id) {
                continue;
            }

            WhatsAppMessageLog::firstOrCreate(
                [
                    'siswa_id' => $item->siswa_id,
                    'guru_id' => $kelasMapel->guru_id,
                    'jenis_template' => 'tugas_terlambat',
                ],
                [
                    'tugas_ids' => [$item->tugas_id],
                    'total_hari_terlambat' => 1,
                    'prepared_at' => now()->subDay(),
                    'sent_marked_at' => now()->subDay(),
                ]
            );
        }
    }

    private function seedWaliKelasData(): void
    {
        $waliKelasList = WaliKelas::aktif()->with('kelas')->get();

        foreach ($waliKelasList as $waliKelas) {
            $siswaList = Siswa::with('user')
                ->where('kelas_id', $waliKelas->kelas_id)
                ->where('status', 'aktif')
                ->orderBy('nis')
                ->get();

            PertemuanWaliKelas::updateOrCreate(
                ['wali_kelas_id' => $waliKelas->id, 'topik' => 'Sosialisasi Program Kelas Semester Ganjil'],
                ['tanggal' => now()->subDays(14)->toDateString(), 'hasil' => 'Orang tua/wali menyepakati program kelas dan jadwal komunikasi rutin.']
            );

            if ($siswaList->isNotEmpty()) {
                $target = $siswaList->first();

                PenangananSiswa::updateOrCreate(
                    ['wali_kelas_id' => $waliKelas->id, 'siswa_id' => $target->id, 'kondisi' => 'Sering terlambat mengumpulkan tugas'],
                    [
                        'deskripsi' => 'Contoh data demo penanganan siswa untuk memperlihatkan alur pencatatan wali kelas.',
                        'tindak_lanjut' => 'Dipanggil untuk konseling ringan dan diberi pengingat jadwal.',
                        'hasil' => null,
                        'status' => 'proses',
                    ]
                );
            }

            // Absensi harian wali kelas untuk beberapa hari kerja terakhir.
            $dates = $this->recentWeekdays(5);

            foreach ($siswaList as $studentIndex => $siswa) {
                foreach ($dates as $dateIndex => $date) {
                    $seed = ($siswa->id * 17 + $dateIndex) % 20;
                    $status = match (true) {
                        $seed < 17 => 'hadir',
                        $seed < 19 => 'sakit',
                        default => 'izin',
                    };

                    AbsensiWaliKelas::updateOrCreate(
                        ['wali_kelas_id' => $waliKelas->id, 'siswa_id' => $siswa->id, 'tanggal' => $date],
                        [
                            'status' => $status,
                            'keterangan' => $status === 'hadir' ? null : 'Data demo '.$status,
                        ]
                    );
                }
            }
        }
    }

    /** N tanggal hari kerja (Senin-Jumat) terakhir sampai hari ini, urut menaik. */
    private function recentWeekdays(int $count): array
    {
        $dates = [];
        $cursor = now()->copy();

        while (count($dates) < $count) {
            if ($cursor->isWeekday()) {
                $dates[] = $cursor->toDateString();
            }

            $cursor->subDay();
        }

        return array_reverse($dates);
    }
}

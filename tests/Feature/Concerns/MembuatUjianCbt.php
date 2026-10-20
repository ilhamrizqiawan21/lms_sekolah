<?php

namespace Tests\Feature\Concerns;

use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\SoalBank;
use App\Models\SoalBankOpsi;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianSoal;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Fixture bersama untuk tes ujian CBT: guru, kelas, satu ujian satu soal (poin 10), dan siswa yang mulai mengerjakan.
 */
trait MembuatUjianCbt
{
    private User $guru;

    private Kelas $kelas;

    private KelasMapel $kelasMapel;

    private Ujian $ujian;

    private SoalBankOpsi $opsiBenar;

    private function makeUser(string $nama, string $role): User
    {
        $roleModel = Role::firstOrCreate(['nama_role' => $role]);

        return User::create([
            'username' => strtolower(str_replace(' ', '-', $nama)).'-'.uniqid(),
            'email' => strtolower(str_replace(' ', '-', $nama)).'-'.uniqid().'@example.test',
            'password' => Hash::make('secret'),
            'is_password_default' => false,
            'nama_lengkap' => $nama,
            'nip_nis' => null,
            'jenis_kelamin' => null,
            'foto' => null,
            'role_id' => $roleModel->id,
            'is_active' => true,
        ]);
    }

    private function createSiswa(User $user, Kelas $kelas): Siswa
    {
        return Siswa::create([
            'user_id' => $user->id,
            'kelas_id' => $kelas->id,
            'nama_lengkap' => $user->nama_lengkap,
            'nis' => 'NIS'.uniqid(),
            'status' => 'aktif',
        ]);
    }

    /** @param  array<string, mixed>  $atributUjian  menimpa atribut ujian (mis. maks_pelanggaran) */
    private function siapkanUjian(array $atributUjian = []): void
    {
        $this->guru = $this->makeUser('Guru Mapel', 'guru');
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'TIK', 'nama_mapel' => 'Informatika', 'urutan' => 1]);
        $this->kelas = Kelas::create(['tingkat' => '10', 'nama_kelas' => '10-D']);

        $this->kelasMapel = KelasMapel::create([
            'kelas_id' => $this->kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $this->guru->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);

        $soal = SoalBank::create(['guru_id' => $this->guru->id, 'pertanyaan' => 'Soal TIK', 'kesulitan' => 'mudah']);
        $this->opsiBenar = SoalBankOpsi::create(['soal_bank_id' => $soal->id, 'teks_opsi' => 'A', 'is_benar' => true]);

        $this->ujian = Ujian::create($atributUjian + [
            'kelas_mapel_id' => $this->kelasMapel->id,
            'judul' => 'Ujian TIK',
            'durasi_menit' => 30,
            'kategori_nilai' => 'NH',
        ]);
        UjianSoal::create(['ujian_id' => $this->ujian->id, 'soal_bank_id' => $soal->id, 'poin' => 10, 'urutan' => 1]);
    }

    /** @return array{0: User, 1: UjianAttempt} */
    private function mulaiAttempt(string $nama = 'Siswa TIK'): array
    {
        $user = $this->makeUser($nama, 'siswa');
        $siswa = $this->createSiswa($user, $this->kelas);

        $this->actingAs($user)->post(route('siswa.ujian.mulai', $this->ujian->id));
        $attempt = UjianAttempt::where('ujian_id', $this->ujian->id)->where('siswa_id', $siswa->id)->firstOrFail();

        return [$user, $attempt];
    }

    private function kirimEvent(User $user, UjianAttempt $attempt, string $jenis)
    {
        return $this->actingAs($user)->postJson(route('siswa.ujian.event', $attempt->id), ['jenis' => $jenis]);
    }

    private function kirimHeartbeat(User $user, UjianAttempt $attempt)
    {
        return $this->actingAs($user)->postJson(route('siswa.ujian.heartbeat', $attempt->id));
    }
}

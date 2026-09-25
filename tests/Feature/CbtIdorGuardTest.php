<?php

namespace Tests\Feature;

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
use App\Models\UjianAttemptJawaban;
use App\Models\UjianSoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtIdorGuardTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_siswa_cannot_access_or_submit_another_students_attempt(): void
    {
        $guru = $this->makeUser('Guru Mapel', 'guru');
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'GEO', 'nama_mapel' => 'Geografi', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => '11', 'nama_kelas' => '11-IPS']);

        $km = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);

        $userA = $this->makeUser('Siswa A', 'siswa');
        $siswaA = $this->createSiswa($userA, $kelas);

        $userB = $this->makeUser('Siswa B', 'siswa');
        $siswaB = $this->createSiswa($userB, $kelas);

        $ujian = Ujian::create([
            'kelas_mapel_id' => $km->id,
            'judul' => 'Ujian Geografi',
            'durasi_menit' => 45,
            'kategori_nilai' => 'NH',
        ]);

        $soal = SoalBank::create(['guru_id' => $guru->id, 'pertanyaan' => 'Soal Geo', 'kesulitan' => 'mudah']);
        $opsi = SoalBankOpsi::create(['soal_bank_id' => $soal->id, 'teks_opsi' => 'A', 'is_benar' => true]);
        $us = UjianSoal::create(['ujian_id' => $ujian->id, 'soal_bank_id' => $soal->id, 'poin' => 10, 'urutan' => 1]);

        // Siswa A starts attempt
        $this->actingAs($userA)->post(route('siswa.ujian.mulai', $ujian->id));
        $attemptA = UjianAttempt::where('ujian_id', $ujian->id)->where('siswa_id', $siswaA->id)->first();
        $jawabanA = UjianAttemptJawaban::where('ujian_attempt_id', $attemptA->id)->first();

        // Siswa B attempts to view Siswa A's kerjakan page
        $this->actingAs($userB)->get(route('siswa.ujian.kerjakan', $attemptA->id))->assertStatus(403);

        // Siswa B attempts to answer on Siswa A's attempt
        $this->actingAs($userB)->postJson(route('siswa.ujian.jawab', $attemptA->id), [
            'attempt_jawaban_id' => $jawabanA->id,
            'jawaban_opsi_id' => $opsi->id,
        ])->assertStatus(403);

        // Siswa B attempts to submit Siswa A's attempt
        $this->actingAs($userB)->post(route('siswa.ujian.submit', $attemptA->id))->assertStatus(403);

        // Siswa B attempts to view Siswa A's hasil page
        $this->actingAs($userB)->get(route('siswa.ujian.hasil', $attemptA->id))->assertStatus(403);
    }

    public function test_guru_cannot_manage_another_gurus_ujian_or_soal(): void
    {
        $guru1 = $this->makeUser('Guru 1', 'guru');
        $guru2 = $this->makeUser('Guru 2', 'guru');

        $soal2 = SoalBank::create(['guru_id' => $guru2->id, 'pertanyaan' => 'Soal Guru 2', 'kesulitan' => 'mudah']);

        // Guru 1 tries to update/delete Guru 2's soal
        $this->actingAs($guru1)->put(route('guru.soal-bank.update', $soal2->id), [
            'pertanyaan' => 'Hack',
            'kesulitan' => 'sulit',
            'opsi' => [['teks_opsi' => 'A', 'is_benar' => true], ['teks_opsi' => 'B', 'is_benar' => false]],
        ])->assertStatus(403);

        $this->actingAs($guru1)->delete(route('guru.soal-bank.destroy', $soal2->id))->assertStatus(403);
    }

    public function test_siswa_cannot_start_ujian_from_different_class(): void
    {
        $guru = $this->makeUser('Guru Mapel', 'guru');
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'EKO', 'nama_mapel' => 'Ekonomi', 'urutan' => 1]);
        $kelas1 = Kelas::create(['tingkat' => '10', 'nama_kelas' => '10-A']);
        $kelas2 = Kelas::create(['tingkat' => '10', 'nama_kelas' => '10-B']);

        $km1 = KelasMapel::create([
            'kelas_id' => $kelas1->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);

        $userSiswa2 = $this->makeUser('Siswa Kelas 2', 'siswa');
        $siswa2 = $this->createSiswa($userSiswa2, $kelas2);

        $ujian1 = Ujian::create([
            'kelas_mapel_id' => $km1->id,
            'judul' => 'Ujian Kelas 10-A',
            'durasi_menit' => 30,
            'kategori_nilai' => 'NH',
        ]);

        // Siswa 2 tries to start Ujian for Kelas 1
        $this->actingAs($userSiswa2)->post(route('siswa.ujian.mulai', $ujian1->id))->assertStatus(403);
    }
}

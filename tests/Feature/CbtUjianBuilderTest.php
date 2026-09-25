<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\SoalBank;
use App\Models\SoalBankOpsi;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtUjianBuilderTest extends TestCase
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

    private function setupKelasMapel(User $guru): KelasMapel
    {
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'BIO', 'nama_mapel' => 'Biologi', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => '8', 'nama_kelas' => '8A']);

        return KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);
    }

    public function test_guru_can_create_ujian_from_own_bank_soal(): void
    {
        $guru = $this->makeUser('Guru Biologi', 'guru');
        $km = $this->setupKelasMapel($guru);

        $soal1 = SoalBank::create(['guru_id' => $guru->id, 'pertanyaan' => 'Soal 1', 'kesulitan' => 'mudah']);
        SoalBankOpsi::create(['soal_bank_id' => $soal1->id, 'teks_opsi' => 'A', 'is_benar' => true]);
        SoalBankOpsi::create(['soal_bank_id' => $soal1->id, 'teks_opsi' => 'B', 'is_benar' => false]);

        $soal2 = SoalBank::create(['guru_id' => $guru->id, 'pertanyaan' => 'Soal 2', 'kesulitan' => 'sedang']);
        SoalBankOpsi::create(['soal_bank_id' => $soal2->id, 'teks_opsi' => 'A', 'is_benar' => true]);
        SoalBankOpsi::create(['soal_bank_id' => $soal2->id, 'teks_opsi' => 'B', 'is_benar' => false]);

        $response = $this->actingAs($guru)->post(route('guru.ujian.store', $km->id), [
            'judul' => 'Ulangan Harian 1',
            'deskripsi' => 'Kerjakan dengan teliti',
            'durasi_menit' => 45,
            'kategori_nilai' => 'NH',
            'acak_soal' => true,
            'acak_opsi' => true,
            'soal' => [
                ['soal_bank_id' => $soal1->id, 'poin' => 50],
                ['soal_bank_id' => $soal2->id, 'poin' => 50],
            ],
        ]);

        $response->assertRedirect(route('guru.ujian.list', $km->id));
        $this->assertDatabaseHas('ujian', [
            'kelas_mapel_id' => $km->id,
            'judul' => 'Ulangan Harian 1',
            'durasi_menit' => 45,
            'kategori_nilai' => 'NH',
        ]);

        $ujian = Ujian::where('judul', 'Ulangan Harian 1')->first();
        $this->assertCount(2, $ujian->ujianSoal);
        $this->assertEquals(100.0, $ujian->ujianSoal->sum('poin'));
    }

    public function test_guru_cannot_use_soal_bank_belonging_to_another_guru(): void
    {
        $guru1 = $this->makeUser('Guru 1', 'guru');
        $guru2 = $this->makeUser('Guru 2', 'guru');
        $km = $this->setupKelasMapel($guru1);

        $soalGuru2 = SoalBank::create(['guru_id' => $guru2->id, 'pertanyaan' => 'Soal Rahasia Guru 2', 'kesulitan' => 'sulit']);
        SoalBankOpsi::create(['soal_bank_id' => $soalGuru2->id, 'teks_opsi' => 'A', 'is_benar' => true]);
        SoalBankOpsi::create(['soal_bank_id' => $soalGuru2->id, 'teks_opsi' => 'B', 'is_benar' => false]);

        $response = $this->actingAs($guru1)->post(route('guru.ujian.store', $km->id), [
            'judul' => 'Ujian Ilegal',
            'durasi_menit' => 30,
            'kategori_nilai' => 'NH',
            'acak_soal' => false,
            'acak_opsi' => false,
            'soal' => [
                ['soal_bank_id' => $soalGuru2->id, 'poin' => 10],
            ],
        ]);

        $response->assertSessionHasErrors(['soal']);
        $this->assertDatabaseMissing('ujian', ['judul' => 'Ujian Ilegal']);
    }
}

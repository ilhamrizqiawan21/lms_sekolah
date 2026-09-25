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
use App\Models\UjianSoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtSoalBankManagementTest extends TestCase
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

    public function test_guru_can_create_soal_bank_with_single_correct_option(): void
    {
        $guru = $this->makeUser('Guru Test', 'guru');
        $mapel = MataPelajaran::create(['kode' => 'IPA', 'nama_mapel' => 'Ilmu Pengetahuan Alam', 'urutan' => 1]);

        $response = $this->actingAs($guru)->post(route('guru.soal-bank.store'), [
            'mapel_id' => $mapel->id,
            'pertanyaan' => 'Apa rumus fotosintesis?',
            'topik' => 'Fotosintesis',
            'kesulitan' => 'sedang',
            'kategori_nilai' => 'NH',
            'opsi' => [
                ['teks_opsi' => '6CO2 + 6H2O -> C6H12O6 + 6O2', 'is_benar' => true],
                ['teks_opsi' => 'C6H12O6 -> 2C2H5OH + 2CO2', 'is_benar' => false],
                ['teks_opsi' => 'H2 + O2 -> H2O', 'is_benar' => false],
                ['teks_opsi' => 'NaCl -> Na + Cl', 'is_benar' => false],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('soal_bank', [
            'guru_id' => $guru->id,
            'mapel_id' => $mapel->id,
            'pertanyaan' => 'Apa rumus fotosintesis?',
            'topik' => 'Fotosintesis',
        ]);

        $soal = SoalBank::where('pertanyaan', 'Apa rumus fotosintesis?')->first();
        $this->assertNotNull($soal);
        $this->assertCount(4, $soal->opsi);
        $this->assertEquals(1, $soal->opsi()->where('is_benar', true)->count());
    }

    public function test_validation_rejects_soal_bank_with_no_or_multiple_correct_options(): void
    {
        $guru = $this->makeUser('Guru Test', 'guru');

        // 0 correct option
        $response1 = $this->actingAs($guru)->post(route('guru.soal-bank.store'), [
            'pertanyaan' => 'Pertanyaan tanpa kunci?',
            'kesulitan' => 'mudah',
            'opsi' => [
                ['teks_opsi' => 'Opsi A', 'is_benar' => false],
                ['teks_opsi' => 'Opsi B', 'is_benar' => false],
            ],
        ]);
        $response1->assertSessionHasErrors(['opsi']);

        // 2 correct options
        $response2 = $this->actingAs($guru)->post(route('guru.soal-bank.store'), [
            'pertanyaan' => 'Pertanyaan 2 kunci?',
            'kesulitan' => 'mudah',
            'opsi' => [
                ['teks_opsi' => 'Opsi A', 'is_benar' => true],
                ['teks_opsi' => 'Opsi B', 'is_benar' => true],
            ],
        ]);
        $response2->assertSessionHasErrors(['opsi']);
    }

    public function test_guru_can_update_soal_bank(): void
    {
        $guru = $this->makeUser('Guru Test', 'guru');
        $soal = SoalBank::create([
            'guru_id' => $guru->id,
            'pertanyaan' => 'Pertanyaan Lama',
            'kesulitan' => 'mudah',
        ]);
        SoalBankOpsi::create(['soal_bank_id' => $soal->id, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        SoalBankOpsi::create(['soal_bank_id' => $soal->id, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);

        $response = $this->actingAs($guru)->put(route('guru.soal-bank.update', $soal->id), [
            'pertanyaan' => 'Pertanyaan Baru',
            'kesulitan' => 'sulit',
            'opsi' => [
                ['teks_opsi' => 'Pilihan 1', 'is_benar' => false],
                ['teks_opsi' => 'Pilihan 2', 'is_benar' => true],
                ['teks_opsi' => 'Pilihan 3', 'is_benar' => false],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('soal_bank', [
            'id' => $soal->id,
            'pertanyaan' => 'Pertanyaan Baru',
            'kesulitan' => 'sulit',
        ]);
        $this->assertEquals(3, $soal->opsi()->count());
    }

    public function test_cannot_delete_soal_bank_when_used_in_ujian(): void
    {
        $guru = $this->makeUser('Guru Test', 'guru');
        $soal = SoalBank::create([
            'guru_id' => $guru->id,
            'pertanyaan' => 'Soal Terpakai',
            'kesulitan' => 'mudah',
        ]);

        // Mock usage in UjianSoal
        $roleModel = Role::firstOrCreate(['nama_role' => 'guru']);
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'MAT', 'nama_mapel' => 'Matematika', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => '7', 'nama_kelas' => '7A']);
        $km = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);
        $ujian = Ujian::create([
            'kelas_mapel_id' => $km->id,
            'judul' => 'Ujian 1',
            'durasi_menit' => 60,
            'kategori_nilai' => 'NH',
        ]);
        UjianSoal::create([
            'ujian_id' => $ujian->id,
            'soal_bank_id' => $soal->id,
            'poin' => 1,
            'urutan' => 1,
        ]);

        $response = $this->actingAs($guru)->delete(route('guru.soal-bank.destroy', $soal->id));
        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('soal_bank', ['id' => $soal->id]);
    }
}

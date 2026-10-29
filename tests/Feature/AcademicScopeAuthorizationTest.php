<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Pengumuman;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Tugas;
use App\Models\User;
use App\Services\CalendarTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class AcademicScopeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_excludes_historical_assignments_for_former_teacher_and_student(): void
    {
        $admin = $this->createUser('scope-admin', 'Scope Admin', 'admin');
        $formerTeacher = $this->createUser('former-teacher', 'Former Teacher', 'guru');
        $studentUser = $this->createUser('scope-student', 'Scope Student', 'siswa');
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'Scope']);
        Siswa::create([
            'user_id' => $studentUser->id,
            'nis' => 'scope-001',
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
        ]);
        $historicalYear = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => false]);
        $mapel = MataPelajaran::create(['kode' => 'SCP', 'nama_mapel' => 'Scope Test', 'urutan' => 1]);
        $historicalAssignment = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $formerTeacher->id,
            'tahun_ajaran_id' => $historicalYear->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 1,
        ]);
        $historicalTask = Tugas::create([
            'kelas_mapel_id' => $historicalAssignment->id,
            'judul' => 'Tugas Historis Scope',
            'deskripsi' => 'Tidak boleh masuk timeline.',
            'batas_waktu' => now()->startOfMonth()->addDays(10),
            'kategori_nilai' => 'NH',
        ]);
        $historicalAnnouncement = Pengumuman::create([
            'judul' => 'Pengumuman Historis Scope',
            'isi' => 'Tidak boleh masuk timeline.',
            'target' => 'kelas_mapel',
            'target_kelas' => json_encode([(string) $kelas->id]),
            'kelas_mapel_id' => $historicalAssignment->id,
            'created_by' => $admin->id,
        ]);

        $service = app(CalendarTimelineService::class);
        $formerTeacherEvents = $service->forUser($formerTeacher, now()->year, now()->month);
        $studentEvents = $service->forUser($studentUser, now()->year, now()->month);

        $this->assertFalse($formerTeacherEvents->contains('source_id', $historicalTask->id));
        $this->assertFalse($formerTeacherEvents->contains('source_id', $historicalAnnouncement->id));
        $this->assertFalse($studentEvents->contains('source_id', $historicalTask->id));
        $this->assertFalse($studentEvents->contains('source_id', $historicalAnnouncement->id));
    }

    public function test_former_teacher_cannot_list_view_or_target_historical_class_announcement(): void
    {
        $admin = $this->createUser('scope-announcement-admin', 'Scope Announcement Admin', 'admin');
        $formerTeacher = $this->createUser('former-announcement-teacher', 'Former Announcement Teacher', 'guru');
        $kelas = Kelas::create(['tingkat' => 'VIII', 'nama_kelas' => 'Former']);
        $historicalYear = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => false]);
        $mapel = MataPelajaran::create(['kode' => 'HIS', 'nama_mapel' => 'Historical Scope', 'urutan' => 1]);
        $historicalAssignment = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $formerTeacher->id,
            'tahun_ajaran_id' => $historicalYear->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 1,
        ]);
        $announcement = Pengumuman::create([
            'judul' => 'Pengumuman Assignment Lama',
            'isi' => 'Isi historis.',
            'target' => 'kelas_mapel',
            'target_kelas' => json_encode([(string) $kelas->id]),
            'kelas_mapel_id' => $historicalAssignment->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($formerTeacher)
            ->get(route('guru.pengumuman.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('pengumuman.data', 0));

        $this->actingAs($formerTeacher)
            ->get(route('guru.pengumuman.show', $announcement))
            ->assertForbidden();

        $this->actingAs($formerTeacher)
            ->post(route('guru.pengumuman.store'), [
                'judul' => 'Target Historis',
                'isi' => 'Tidak boleh dibuat.',
                'target' => 'kelas_mapel',
                'target_kelas_ids' => [$kelas->id],
            ])
            ->assertForbidden();
    }

    public function test_student_cannot_list_or_view_class_announcement_without_active_assignment(): void
    {
        $admin = $this->createUser('scope-student-admin', 'Scope Student Admin', 'admin');
        $studentUser = $this->createUser('former-student', 'Former Student', 'siswa');
        $kelas = Kelas::create(['tingkat' => 'IX', 'nama_kelas' => 'Former Student']);
        $student = Siswa::create([
            'user_id' => $studentUser->id,
            'nis' => 'scope-002',
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
        ]);
        $historicalYear = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => false]);
        $teacher = $this->createUser('historical-student-teacher', 'Historical Student Teacher', 'guru');
        $mapel = MataPelajaran::create(['kode' => 'HIS2', 'nama_mapel' => 'Historical Student Scope', 'urutan' => 1]);
        $historicalAssignment = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $teacher->id,
            'tahun_ajaran_id' => $historicalYear->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 1,
        ]);
        $announcement = Pengumuman::create([
            'judul' => 'Pengumuman Siswa Historis',
            'isi' => 'Isi historis siswa.',
            'target' => 'kelas_mapel',
            'target_kelas' => json_encode([(string) $kelas->id]),
            'kelas_mapel_id' => $historicalAssignment->id,
            'created_by' => $admin->id,
        ]);

        $this->assertSame($student->id, $studentUser->siswa->id);

        $this->actingAs($studentUser)
            ->get(route('siswa.pengumuman.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('pengumuman.data', 0));

        $this->actingAs($studentUser)
            ->get(route('siswa.pengumuman.show', $announcement))
            ->assertForbidden();
    }

    private function createUser(string $username, string $name, string $roleName): User
    {
        $role = Role::firstOrCreate(['nama_role' => $roleName]);

        return User::create([
            'username' => $username,
            'email' => $username.'@scope.test',
            'password' => Hash::make('secret'),
            'role_id' => $role->id,
            'nama_lengkap' => $name,
            'is_active' => true,
            'is_password_default' => false,
        ]);
    }
}

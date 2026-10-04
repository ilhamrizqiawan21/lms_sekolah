<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\ChatMessage;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'guru', 'siswa', 'kepala_sekolah'] as $role) {
            Role::create(['nama_role' => $role]);
        }
    }

    public function test_staff_user_rejects_weak_explicit_password_and_accepts_strong_one(): void
    {
        $admin = $this->makeUser('admin', 'admin-sec');
        $guruRoleId = Role::where('nama_role', 'guru')->value('id');
        $payload = [
            'username' => 'guru-baru',
            'nama_lengkap' => 'Guru Baru',
            'role_id' => $guruRoleId,
            'is_active' => '1',
        ];

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $payload + ['password' => 'abc123'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['username' => 'guru-baru']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $payload + ['password' => 'Str0ng!Passw0rd'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['username' => 'guru-baru']);
    }

    public function test_repeated_failed_logins_across_usernames_block_the_ip(): void
    {
        config(['security.auto_block_failed_logins' => 3]);
        RateLimiter::clear('login-ip-failures:'.sha1('127.0.0.1'));

        foreach (['a', 'b', 'c'] as $name) {
            $this->post(route('login.post'), ['username' => $name, 'password' => 'salah']);
        }

        $blocked = BlockedIp::where('ip_address', '127.0.0.1')->first();
        $this->assertNotNull($blocked);
        $this->assertTrue($blocked->blocked_until->isFuture());

        $this->get(route('login'))->assertForbidden();
    }

    public function test_auto_block_can_be_disabled(): void
    {
        config(['security.auto_block_failed_logins' => 0]);

        foreach (['a', 'b', 'c', 'd'] as $name) {
            $this->post(route('login.post'), ['username' => $name, 'password' => 'salah']);
        }

        $this->assertSame(0, BlockedIp::count());
    }

    public function test_student_opening_chat_only_marks_teacher_messages_read(): void
    {
        $guru = $this->makeUser('guru', 'guru-chat');
        $siswaA = $this->makeUser('siswa', 'siswa-chat-a');
        $siswaB = $this->makeUser('siswa', 'siswa-chat-b');
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'MTK', 'nama_mapel' => 'Matematika', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => '11', 'nama_kelas' => '11-A']);
        $km = KelasMapel::create([
            'kelas_id' => $kelas->id, 'mapel_id' => $mapel->id, 'guru_id' => $guru->id,
            'tahun_ajaran_id' => $ta->id, 'semester' => '1', 'status' => 'aktif',
        ]);
        foreach ([$siswaA, $siswaB] as $i => $u) {
            Siswa::create(['user_id' => $u->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => $u->nama_lengkap, 'nis' => 'N'.$i, 'status' => 'aktif']);
        }

        $fromGuru = ChatMessage::create(['user_id' => $guru->id, 'kelas_mapel_id' => $km->id, 'message' => 'halo']);
        $fromB = ChatMessage::create(['user_id' => $siswaB->id, 'kelas_mapel_id' => $km->id, 'message' => 'hai']);

        $this->actingAs($siswaA)->get(route('siswa.chat.show', $km))->assertOk();

        $this->assertTrue($fromGuru->fresh()->is_read);
        $this->assertFalse($fromB->fresh()->is_read);
    }

    private function makeUser(string $roleName, string $username): User
    {
        return User::create([
            'username' => $username,
            'email' => "{$username}@example.test",
            'password' => Hash::make('secret-password'),
            'is_password_default' => false,
            'nama_lengkap' => strtoupper($username),
            'role_id' => Role::where('nama_role', $roleName)->value('id'),
            'is_active' => true,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Services\SiswaLifecycleService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CredentialAndSeederHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_password_blocks_every_role_when_force_change_is_enabled(): void
    {
        config(['security.require_student_phone' => false]);
        config(['security.force_password_change' => true]);

        $routes = [
            'admin' => ['dashboard' => 'admin.dashboard', 'settings' => 'admin.pengaturan-akun'],
            'guru' => ['dashboard' => 'guru.dashboard', 'settings' => 'guru.pengaturan'],
            'siswa' => ['dashboard' => 'siswa.dashboard', 'settings' => 'siswa.pengaturan'],
            'kepala_sekolah' => ['dashboard' => 'kepsek.dashboard', 'settings' => 'kepsek.pengaturan'],
        ];

        foreach ($routes as $roleName => $routeNames) {
            $user = $this->makeDefaultUser($roleName);

            $this->actingAs($user)
                ->get(route($routeNames['dashboard']))
                ->assertRedirect(route($routeNames['settings']));
        }
    }

    public function test_temporary_password_does_not_block_access_when_force_change_is_disabled(): void
    {
        config(['security.require_password_change' => false]);
        config(['security.force_password_change' => false]);
        $user = $this->makeDefaultUser('admin', 'admin-no-password-redirect');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_student_can_clear_temporary_password_flag_from_the_allowed_settings_route(): void
    {
        config(['security.require_student_phone' => false]);
        $user = $this->makeDefaultUser('siswa', 'student-password-change');

        $this->actingAs($user)
            ->put(route('siswa.pengaturan.update'), [
                'current_password' => 'Temp#Password1!',
                'password' => 'NewStudent#Password1!',
                'password_confirmation' => 'NewStudent#Password1!',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse((bool) $user->fresh()->is_password_default);
        $this->assertTrue(Hash::check('NewStudent#Password1!', $user->fresh()->password));
    }

    public function test_new_student_receives_a_unique_temporary_password(): void
    {
        $role = Role::create(['nama_role' => 'siswa']);
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'New Student']);

        $created = app(SiswaLifecycleService::class)->create([
            'nis' => 'TEMP001',
            'nama_lengkap' => 'Siswa Temporary',
            'kelas_id' => $kelas->id,
            'jenis_kelamin' => 'L',
        ]);

        $this->assertNotSame(User::LEGACY_DEFAULT_PASSWORD, $created['password']);
        $this->assertGreaterThanOrEqual(10, strlen($created['password']));
        $this->assertTrue(Hash::check($created['password'], $created['user']->password));
        $this->assertTrue((bool) $created['user']->is_password_default);
        $this->assertSame($role->id, $created['user']->role_id);
    }

    public function test_database_seeder_does_not_create_demo_accounts_without_explicit_demo_mode(): void
    {
        config(['app.demo_mode' => false]);

        app(DatabaseSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_accounts_are_created_only_when_explicitly_enabled_outside_production(): void
    {
        config(['app.demo_mode' => true]);
        $this->seed(RoleSeeder::class);

        app(DemoUserSeeder::class)->run();

        $this->assertDatabaseHas('users', ['username' => 'admin', 'is_password_default' => false]);
    }

    public function test_demo_accounts_are_blocked_in_production_even_if_demo_mode_is_enabled(): void
    {
        config(['app.demo_mode' => true]);
        $this->app->detectEnvironment(fn () => 'production');

        app(DemoUserSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
    }

    private function makeDefaultUser(string $roleName, string $username = 'default-user'): User
    {
        $username = $username === 'default-user' ? "{$roleName}-default" : $username;
        $role = Role::firstOrCreate(['nama_role' => $roleName]);
        $user = User::create([
            'username' => $username,
            'email' => "{$username}@test.local",
            'password' => Hash::make('Temp#Password1!'),
            'is_password_default' => true,
            'nama_lengkap' => ucfirst($roleName),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        if ($roleName === 'siswa') {
            $kelas = Kelas::firstOrCreate(['tingkat' => 'VII', 'nama_kelas' => 'Credential']);
            Siswa::create([
                'user_id' => $user->id,
                'nis' => strtoupper($username),
                'kelas_id' => $kelas->id,
                'status' => 'aktif',
            ]);
        }

        return $user;
    }
}

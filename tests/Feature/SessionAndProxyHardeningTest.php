<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\Role;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SessionAndProxyHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'guru', 'siswa', 'kepala_sekolah'] as $role) {
            Role::create(['nama_role' => $role]);
        }
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_forwarded_client_ip_is_ignored_without_trusted_proxies(): void
    {
        $this->bootTrustedProxies(null);

        $this->failLoginFrom('203.0.113.9');

        $this->assertTrue(BlockedIp::where('ip_address', '127.0.0.1')->exists());
        $this->assertFalse(BlockedIp::where('ip_address', '203.0.113.9')->exists());
    }

    public function test_forwarded_client_ip_is_used_when_proxy_is_trusted(): void
    {
        $this->bootTrustedProxies('127.0.0.1, 10.0.0.0/8');

        $this->failLoginFrom('203.0.113.9');

        $this->assertTrue(BlockedIp::where('ip_address', '203.0.113.9')->exists());
        $this->assertFalse(BlockedIp::where('ip_address', '127.0.0.1')->exists());
    }

    public function test_session_is_invalidated_when_password_changes_elsewhere(): void
    {
        $guru = $this->makeUser('guru', 'guru-sesi');

        $this->actingAs($guru)->get(route('guru.dashboard'))->assertOk();

        // Simulate the password being changed from another device or by an admin reset.
        $guru->forceFill(['password' => Hash::make('Another!Passw0rd')])->save();

        $this->get(route('guru.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_changing_own_password_keeps_current_session(): void
    {
        $guru = $this->makeUser('guru', 'guru-ganti');

        $this->actingAs($guru)->get(route('guru.dashboard'))->assertOk();

        $this->put(route('guru.pengaturan.update'), [
            'current_password' => 'secret-password',
            'password' => 'New!Passw0rd2026',
            'password_confirmation' => 'New!Passw0rd2026',
        ])->assertSessionHasNoErrors();

        $this->get(route('guru.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($guru);
    }

    public function test_inactive_admin_cannot_use_password_reset_endpoints(): void
    {
        $admin = $this->makeUser('admin', 'admin-nonaktif');
        $guru = $this->makeUser('guru', 'guru-target');
        $admin->forceFill(['is_active' => false])->save();

        $this->actingAs($admin)
            ->post(route('admin.users.reset-password', $guru))
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('secret-password', $guru->fresh()->password));
        $this->assertGuest();
    }

    private function bootTrustedProxies(?string $proxies): void
    {
        config(['security.trusted_proxies' => $proxies]);
        TrustProxies::flushState();
        $this->app->getProvider(AppServiceProvider::class)->boot();
    }

    private function failLoginFrom(string $forwardedFor): void
    {
        config(['security.auto_block_failed_logins' => 1]);
        RateLimiter::clear('login-ip-failures:'.sha1('127.0.0.1'));
        RateLimiter::clear('login-ip-failures:'.sha1($forwardedFor));

        $this->withHeader('X-Forwarded-For', $forwardedFor)
            ->post(route('login.post'), ['username' => 'tidak-ada', 'password' => 'salah']);
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

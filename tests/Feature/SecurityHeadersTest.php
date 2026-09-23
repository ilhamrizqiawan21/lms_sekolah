<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_response_includes_browser_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

        $this->assertStringContainsString("frame-ancestors 'self'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("form-action 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_blocklist_check_fails_closed_when_its_storage_is_unavailable(): void
    {
        Schema::drop('blocked_ips');

        $this->get(route('login'))->assertStatus(503);
    }
}

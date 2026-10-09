<?php

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicPeriodShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_academic_year_and_semester_are_shared(): void
    {
        TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => false]);
        TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        Pengaturan::updateOrCreate(['key' => 'semester_aktif'], ['value' => '2']);

        $this->actingAs($this->makeGuru())
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('academic.tahun', '2026/2027')
                ->where('academic.semester', '2'));
    }

    public function test_academic_period_is_null_without_active_year(): void
    {
        $this->actingAs($this->makeGuru())
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('academic', null));
    }

    private function makeGuru(): User
    {
        return User::create([
            'username' => 'guru-periode',
            'email' => 'guru-periode@example.test',
            'password' => Hash::make('secret-password'),
            'is_password_default' => false,
            'nama_lengkap' => 'Guru Periode',
            'role_id' => Role::firstOrCreate(['nama_role' => 'guru'])->id,
            'is_active' => true,
        ]);
    }
}

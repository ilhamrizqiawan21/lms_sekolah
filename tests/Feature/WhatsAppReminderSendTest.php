<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Pengaturan;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Tugas;
use App\Models\User;
use App\Models\WhatsAppMessageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppReminderSendTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        Pengaturan::query()->updateOrCreate(['key' => 'semester_aktif'], ['value' => '1']);
        $taAktif = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $kelas = Kelas::create(['tingkat' => '7', 'nama_kelas' => 'A']);
        $mapel = MataPelajaran::create(['kode' => 'MTK', 'nama_mapel' => 'Matematika', 'urutan' => 1]);

        $guruRole = Role::firstOrCreate(['nama_role' => 'guru']);
        $guru = User::create([
            'username' => 'guru-wa-'.uniqid(),
            'nama_lengkap' => 'Guru WA',
            'password' => Hash::make('password'),
            'role_id' => $guruRole->id,
            'is_active' => true,
        ]);

        $kelasMapel = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $taAktif->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 2,
        ]);

        $siswaRole = Role::firstOrCreate(['nama_role' => 'siswa']);
        $siswaUser = User::create([
            'username' => 'siswa-wa-'.uniqid(),
            'nama_lengkap' => 'Siswa WA',
            'password' => Hash::make('password'),
            'role_id' => $siswaRole->id,
            'is_active' => true,
        ]);
        $siswa = Siswa::create([
            'user_id' => $siswaUser->id,
            'nis' => 'WA'.random_int(1000, 9999),
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
            'nomor_whatsapp' => '6281234567890',
            'whatsapp_opt_in' => true,
        ]);

        $tugas = Tugas::create([
            'kelas_mapel_id' => $kelasMapel->id,
            'judul' => 'Tugas Terlambat',
            'batas_waktu' => now()->subDays(2),
            'kategori_nilai' => 'NH',
        ]);

        config([
            'services.whatsapp.phone_number_id' => 'test-phone-id',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.template_name' => 'lms_pengingat_umum',
            'services.whatsapp.template_language' => 'id',
            'services.whatsapp.api_version' => 'v21.0',
        ]);

        return compact('guru', 'kelasMapel', 'tugas', 'siswa');
    }

    private function sendUrl(array $fixture): string
    {
        return route('guru.tugas.whatsapp', [$fixture['kelasMapel'], $fixture['tugas'], $fixture['siswa']]);
    }

    public function test_successful_send_marks_log_sent_with_wamid(): void
    {
        $fixture = $this->seedFixture();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TEST123']]], 200)]);

        $response = $this->actingAs($fixture['guru'])->postJson($this->sendUrl($fixture));

        $response->assertOk()->assertJson(['success' => true]);

        $log = WhatsAppMessageLog::where('siswa_id', $fixture['siswa']->id)->firstOrFail();
        $this->assertSame('sent', $log->status);
        $this->assertSame('wamid.TEST123', $log->wamid);
        $this->assertNotNull($log->sent_marked_at);
        $this->assertNull($log->error_message);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com/v21.0/test-phone-id/messages')
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '6281234567890'
                && $request['type'] === 'template'
                && $request['template']['name'] === 'lms_pengingat_umum'
                && $request['template']['language']['code'] === 'id'
                && $request['template']['components'][0]['type'] === 'body'
                && str_contains($request['template']['components'][0]['parameters'][0]['text'], 'Siswa WA');
        });
    }

    public function test_unapproved_template_error_marks_log_failed(): void
    {
        $fixture = $this->seedFixture();
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Template name does not exist in the translation', 'code' => 132001],
        ], 400)]);

        $response = $this->actingAs($fixture['guru'])->postJson($this->sendUrl($fixture));

        $response->assertOk()->assertJson(['success' => false]);

        $log = WhatsAppMessageLog::where('siswa_id', $fixture['siswa']->id)->firstOrFail();
        $this->assertSame('failed', $log->status);
        $this->assertSame('132001', $log->error_code);
        $this->assertStringContainsString('Template name does not exist', $log->error_message);
        $this->assertNull($log->sent_marked_at);
    }

    public function test_rate_limit_error_marks_log_failed(): void
    {
        $fixture = $this->seedFixture();
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Too many requests', 'code' => 130429],
        ], 429)]);

        $response = $this->actingAs($fixture['guru'])->postJson($this->sendUrl($fixture));

        $response->assertOk()->assertJson(['success' => false]);

        $log = WhatsAppMessageLog::where('siswa_id', $fixture['siswa']->id)->firstOrFail();
        $this->assertSame('failed', $log->status);
        $this->assertSame('130429', $log->error_code);
        $this->assertNull($log->sent_marked_at);
    }

    public function test_network_exception_is_handled_gracefully(): void
    {
        $fixture = $this->seedFixture();
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $response = $this->actingAs($fixture['guru'])->postJson($this->sendUrl($fixture));

        $response->assertOk()->assertJson(['success' => false]);

        $log = WhatsAppMessageLog::where('siswa_id', $fixture['siswa']->id)->firstOrFail();
        $this->assertSame('failed', $log->status);
        $this->assertNotNull($log->error_message);
    }

    public function test_unconfigured_gateway_simulates_success(): void
    {
        $fixture = $this->seedFixture();
        config([
            'services.whatsapp.phone_number_id' => null,
            'services.whatsapp.access_token' => null,
        ]);
        Http::fake();

        $response = $this->actingAs($fixture['guru'])->postJson($this->sendUrl($fixture));

        $response->assertOk()->assertJson(['success' => true]);
        Http::assertNothingSent();

        $log = WhatsAppMessageLog::where('siswa_id', $fixture['siswa']->id)->firstOrFail();
        $this->assertSame('sent', $log->status);
        $this->assertNull($log->wamid);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppReminderJob;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\WhatsAppMessageLog;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_job_can_be_dispatched_and_executed(): void
    {
        Queue::fake();

        $role = Role::firstOrCreate(['nama_role' => 'siswa']);
        $user = User::create([
            'username' => 'siswa-wa',
            'nama_lengkap' => 'Siswa WA',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $siswa = Siswa::create([
            'user_id' => $user->id,
            'nis' => 'WA001',
            'status' => 'aktif',
            'nomor_whatsapp' => '6281234567890',
            'whatsapp_opt_in' => true,
        ]);

        $log = WhatsAppMessageLog::create([
            'siswa_id' => $siswa->id,
            'guru_id' => 1,
            'jenis_template' => 'tugas_terlambat',
            'tugas_ids' => [1],
            'total_hari_terlambat' => 2,
            'prepared_at' => now(),
        ]);

        $service = app(WhatsAppService::class);
        $service->dispatchReminderJob($log->id, '6281234567890', 'Halo ini pengingat');

        Queue::assertPushed(SendWhatsAppReminderJob::class, function ($job) use ($log) {
            return $job->logId === $log->id && $job->phone === '6281234567890';
        });

        // Test eksekusi job langsung
        $job = new SendWhatsAppReminderJob($log->id, '6281234567890', 'Halo ini pengingat');
        $job->handle($service);

        $log->refresh();
        $this->assertNotNull($log->sent_marked_at);
    }
}

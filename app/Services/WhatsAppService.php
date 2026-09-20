<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\PengumpulanTugas;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\WhatsAppMessageLog;
use App\Support\WhatsAppPhone;

class WhatsAppService
{
    public function prepareLateTaskMessage(Siswa $siswa, Tugas $currentTask, int $guruId): array
    {
        $phone = WhatsAppPhone::normalize((string) $siswa->nomor_whatsapp);
        if ($phone === null) {
            throw new \InvalidArgumentException('Nomor WhatsApp siswa belum valid.');
        }

        $siswa->loadMissing('user');
        $course = $currentTask->kelasMapel;
        $tasks = Tugas::with(['kelasMapel.mataPelajaran', 'pengumpulan' => fn ($q) => $q->where('siswa_id', $siswa->id)])
            ->where('kelas_mapel_id', $course->id)->where('batas_waktu', '<', now())->orderBy('batas_waktu')->get();
        // Only remind students who have not submitted. A late submission is
        // still a valid submission and must not be requested again.
        $tasks = $tasks->filter(function (Tugas $task): bool {
            $status = $task->pengumpulan->first()?->status;

            return $status === null || $status === PengumpulanTugas::STATUS_BELUM;
        });
        $items = $tasks->map(function (Tugas $task) {
            $submitted = $task->pengumpulan->first()?->tanggal_kumpul;
            $days = (int) max(0, $task->batas_waktu->copy()->startOfDay()->diffInDays(($submitted ?? now())->copy()->startOfDay(), false));

            return ['task' => $task, 'days' => $days];
        })->filter(fn ($item) => $item['days'] > 0)->values();
        $totalDays = (int) $items->sum('days');
        $lines = $items->map(fn ($item, $index) => ($index + 1).". {$item['task']->kelasMapel->mataPelajaran->nama_mapel} - {$item['task']->judul}\n   Terlambat: {$item['days']} hari")->implode("\n\n");
        $defaultTemplate = "Halo {{nama_siswa}},\n\nBerikut tugas yang perlu segera diselesaikan:\n\n{{daftar_tugas}}\n\nTotal hari keterlambatan: {{total_hari_terlambat}}\n\nMohon segera menyelesaikan dan mengumpulkan tugas melalui LMS:\n{{url_lms}}";
        $configuredTemplate = Pengaturan::getValue('whatsapp_template_tugas_terlambat');
        $template = filled($configuredTemplate) ? trim($configuredTemplate) : $defaultTemplate;
        $message = str_replace(['{{nama_siswa}}', '{{daftar_tugas}}', '{{total_tugas}}', '{{total_hari_terlambat}}', '{{url_lms}}'], [$siswa->user?->nama_lengkap ?? $siswa->nis, $lines, (string) $items->count(), (string) $totalDays, config('app.url')], $template);
        $log = WhatsAppMessageLog::create(['siswa_id' => $siswa->id, 'guru_id' => $guruId, 'jenis_template' => 'tugas_terlambat', 'tugas_ids' => $items->pluck('task.id')->all(), 'total_hari_terlambat' => $totalDays, 'prepared_at' => now()]);

        return ['url' => 'https://wa.me/'.$phone.'?text='.rawurlencode($message), 'log_id' => $log->id, 'message' => $message];
    }

    public function dispatchReminderJob(int $logId, string $phone, string $message): void
    {
        \App\Jobs\SendWhatsAppReminderJob::dispatch($logId, $phone, $message);
    }

    public function sendDirectMessage(string $phone, string $message): bool
    {
        $gatewayUrl = config('services.whatsapp.gateway_url');
        $apiKey = config('services.whatsapp.api_key');

        // Jika gateway belum dikonfigurasi (default/MVP), lakukan log sebagai mock gateway berhasil
        if (blank($gatewayUrl) || blank($apiKey)) {
            \Illuminate\Support\Facades\Log::info("WhatsApp Simulated Send to {$phone}: {$message}");
            return true;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $apiKey,
            ])->timeout(10)->post($gatewayUrl, [
                'target' => $phone,
                'message' => $message,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("WhatsApp send error to {$phone}: " . $e->getMessage());
            return false;
        }
    }
}

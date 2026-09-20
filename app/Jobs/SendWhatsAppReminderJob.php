<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageLog;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWhatsAppReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $logId,
        public string $phone,
        public string $message
    ) {}

    public function handle(WhatsAppService $service): void
    {
        $log = WhatsAppMessageLog::find($this->logId);
        if (! $log) {
            Log::warning("SendWhatsAppReminderJob: Log ID {$this->logId} not found.");
            return;
        }

        $sent = $service->sendDirectMessage($this->phone, $this->message);

        if ($sent) {
            $log->update(['sent_marked_at' => now()]);
        }
    }
}

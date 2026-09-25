<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP transport for the Meta WhatsApp Cloud API (Graph API). Only knows
 * how to send a single-parameter template message and normalize the result —
 * message building/templating logic lives in WhatsAppService, not here.
 */
class MetaCloudApiClient
{
    /**
     * @return array{success: bool, message_id: ?string, error_code: ?string, error_message: ?string}
     */
    public function sendTemplateMessage(string $phone, string $templateName, string $languageCode, string $bodyText): array
    {
        $apiVersion = config('services.whatsapp.api_version');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');

        // Belum dikonfigurasi (mis. lokal/CI tanpa secret Meta) — simulasikan
        // sukses tanpa memanggil API sungguhan, konsisten dengan perilaku lama.
        if (blank($phoneNumberId) || blank($accessToken)) {
            Log::info("WhatsApp Simulated Send to {$phone}: {$bodyText}");

            return ['success' => true, 'message_id' => null, 'error_code' => null, 'error_message' => null];
        }

        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";

        // Meta rejects template text parameters that contain newlines; collapse
        // them to a single space so multi-line messages (built by WhatsAppService)
        // still send instead of failing with a template-parameter validation error.
        $sanitizedBodyText = trim(preg_replace('/\s*\R+\s*/u', ' ', $bodyText));

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => $languageCode],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $sanitizedBodyText],
                                ],
                            ],
                        ],
                    ],
                ]);

            $messageId = $response->json('messages.0.id');

            if ($response->successful() && filled($messageId)) {
                return ['success' => true, 'message_id' => $messageId, 'error_code' => null, 'error_message' => null];
            }

            $errorCode = $response->json('error.code');
            $errorMessage = $response->json('error.message') ?? 'Meta API mengembalikan respons tidak dikenal.';
            Log::error("WhatsApp Meta API error sending to {$phone}: [{$errorCode}] {$errorMessage}");

            return ['success' => false, 'message_id' => null, 'error_code' => $errorCode !== null ? (string) $errorCode : null, 'error_message' => $errorMessage];
        } catch (\Throwable $e) {
            Log::error("WhatsApp Meta API exception sending to {$phone}: ".$e->getMessage());

            return ['success' => false, 'message_id' => null, 'error_code' => null, 'error_message' => 'Gagal terhubung ke WhatsApp API: '.$e->getMessage()];
        }
    }
}

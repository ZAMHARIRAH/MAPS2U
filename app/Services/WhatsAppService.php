<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function enabled(): bool
    {
        return (bool) config('maps2u_notifications.whatsapp.enabled');
    }

    public function sendText(?string $phone, string $message): void
    {
        if (!$this->enabled() || !$phone) {
            return;
        }

        $token = config('maps2u_notifications.whatsapp.token');
        $phoneNumberId = config('maps2u_notifications.whatsapp.phone_number_id');
        $apiVersion = config('maps2u_notifications.whatsapp.api_version');
        $baseUrl = rtrim((string) config('maps2u_notifications.whatsapp.base_url'), '/');

        if (!$token || !$phoneNumberId) {
            Log::warning('MAPS2U WhatsApp is enabled but credentials are incomplete.');
            return;
        }

        $normalized = $this->normalizePhone($phone);

        try {
            Http::withToken($token)
                ->timeout(15)
                ->post("{$baseUrl}/{$apiVersion}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $normalized,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message,
                    ],
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::error('Failed to send MAPS2U WhatsApp notification.', [
                'phone' => $normalized,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        $country = (string) config('maps2u_notifications.whatsapp.default_country_code', '60');

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = $country . substr($digits, 1);
        }

        if (!str_starts_with($digits, $country) && strlen($digits) < 11) {
            $digits = $country . $digits;
        }

        return $digits;
    }
}

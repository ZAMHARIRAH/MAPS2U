<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class Maps2uEmailService
{
    public function __construct(private readonly GmailApiService $gmailApiService)
    {
    }

    public function sendView(string $toEmail, ?string $toName, string $subject, string $view, array $data = [], ?string $replyTo = null): void
    {
        if (!(bool) config('maps2u_notifications.email.enabled')) {
            return;
        }

        $html = View::make($view, $data)->render();

        try {
            $this->gmailApiService->sendHtml($toEmail, $toName, $subject, $html, $replyTo ?: config('maps2u_notifications.email.reply_to'));
        } catch (\Throwable $e) {
            Log::error('Failed to send MAPS2U Gmail API email.', [
                'to' => $toEmail,
                'subject' => $subject,
                'view' => $view,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

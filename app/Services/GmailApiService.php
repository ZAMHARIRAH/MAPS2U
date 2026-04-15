<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GmailApiService
{
    public function enabled(): bool
    {
        return (bool) config('maps2u_notifications.email.enabled');
    }

    public function configured(): bool
    {
        return (bool) config('services.gmail_oauth.client_id')
            && (bool) config('services.gmail_oauth.client_secret')
            && (bool) config('services.gmail_oauth.refresh_token')
            && (bool) $this->fromAddress();
    }

    public function sendHtml(string $toEmail, ?string $toName, string $subject, string $htmlBody, ?string $replyTo = null): void
    {
        if (!$this->enabled()) {
            return;
        }

        if (!$this->configured()) {
            throw new RuntimeException('Gmail API email is enabled but GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET / GOOGLE_GMAIL_REFRESH_TOKEN / GOOGLE_GMAIL_FROM are incomplete.');
        }

        $accessToken = $this->fetchAccessToken();
        $rawMessage = $this->buildRawMessage($toEmail, $toName, $subject, $htmlBody, $replyTo);

        Http::withToken($accessToken)
            ->timeout(20)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $rawMessage,
            ])
            ->throw();
    }

    private function fetchAccessToken(): string
    {
        $response = Http::asForm()
            ->timeout(20)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => (string) config('services.gmail_oauth.client_id'),
                'client_secret' => (string) config('services.gmail_oauth.client_secret'),
                'refresh_token' => (string) config('services.gmail_oauth.refresh_token'),
                'grant_type' => 'refresh_token',
            ])
            ->throw()
            ->json();

        $token = $response['access_token'] ?? null;
        if (!$token) {
            Log::error('Gmail OAuth token response did not contain access_token.', ['response' => $response]);
            throw new RuntimeException('Failed to retrieve Gmail API access token.');
        }

        return (string) $token;
    }

    private function buildRawMessage(string $toEmail, ?string $toName, string $subject, string $htmlBody, ?string $replyTo = null): string
    {
        $toHeader = $this->formatAddress($toEmail, $toName);
        $fromHeader = $this->formatAddress($this->fromAddress(), $this->fromName());
        $plainBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "
", $htmlBody)));
        $boundary = 'maps2u_'.bin2hex(random_bytes(12));

        $headers = [
            'From: '.$fromHeader,
            'To: '.$toHeader,
            'Subject: '.$this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
        ];

        if ($replyTo) {
            $headers[] = 'Reply-To: '.$replyTo;
        }

        $bodyLines = [
            '--'.$boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($plainBody ?: ' ')),
            '--'.$boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($htmlBody)),
            '--'.$boundary.'--',
            '',
        ];

        $message = implode("
", $headers)."

".implode("
", $bodyLines);

        return rtrim(strtr(base64_encode($message), '+/', '-_'), '=');
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?'.base64_encode($value).'?=';
    }

    private function formatAddress(string $email, ?string $name = null): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return $email;
        }

        return sprintf('%s <%s>', $this->encodeHeader($name), $email);
    }

    private function fromAddress(): string
    {
        return (string) (config('services.gmail_oauth.from') ?: config('mail.from.address'));
    }

    private function fromName(): string
    {
        return (string) (config('services.gmail_oauth.from_name') ?: config('mail.from.name'));
    }
}

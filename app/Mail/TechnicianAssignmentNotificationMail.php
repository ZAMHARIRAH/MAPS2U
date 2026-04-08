<?php

namespace App\Mail;

use App\Models\ClientRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TechnicianAssignmentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClientRequest $clientRequest,
        public string $recipientName,
        public string $subjectLine,
        public string $headline,
        public string $messageBody,
        public ?string $ctaUrl = null,
        public ?string $ctaLabel = null,
    ) {
    }

    public function build(): static
    {
        $mail = $this->subject($this->subjectLine)
            ->view('emails.technician-assignment');

        $replyTo = config('maps2u_notifications.email.reply_to');
        if ($replyTo) {
            $mail->replyTo($replyTo);
        }

        return $mail;
    }
}

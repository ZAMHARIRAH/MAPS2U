<?php

namespace App\Services;

use App\Models\ClientRequest;
use Illuminate\Support\Facades\Log;

class TechnicianCommunicationService
{
    public function __construct(
        private readonly WhatsAppService $whatsAppService,
        private readonly Maps2uEmailService $emailService,
    )
    {
    }

    public function notifyAssignment(ClientRequest $clientRequest): void
    {
        $technician = $clientRequest->assignedTechnician;
        if (!$technician) {
            return;
        }

        $loginUrl = route('technician.login');
        $taskTitle = $clientRequest->requestType?->name ?? 'Maintenance Request';
        $locationName = $clientRequest->location?->name ?? '-';
        $clientName = $clientRequest->full_name ?: $clientRequest->user?->name ?: 'Client';
        $subject = 'MAPS2U: New job assigned - ' . $clientRequest->request_code;
        $headline = 'You have been assigned to a new job';
        $messageBody = "Admin has assigned you to job {$clientRequest->request_code} for {$taskTitle}. Client: {$clientName}. Location: {$locationName}. Please log in to MAPS2U technician portal to review and continue the workflow.";

        if (config('maps2u_notifications.email.enabled') && $technician->email) {
            try {
                $this->emailService->sendView(
                    $technician->email,
                    $technician->name,
                    $subject,
                    'emails.technician-assignment',
                    [
                        'clientRequest' => $clientRequest,
                        'recipientName' => $technician->name ?: 'Technician',
                        'subjectLine' => $subject,
                        'headline' => $headline,
                        'messageBody' => $messageBody,
                        'ctaUrl' => $loginUrl,
                        'ctaLabel' => 'Open Technician Login',
                    ],
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send MAPS2U technician assignment email.', [
                    'request_code' => $clientRequest->request_code,
                    'technician_id' => $technician->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = "MAPS2U Technician Notification
Job ID: {$clientRequest->request_code}
Request Type: {$taskTitle}
Client: {$clientName}
Location: {$locationName}
Please log in to technician portal: {$loginUrl}";
        $this->whatsAppService->sendText($technician->phone_number, $message);
    }
}

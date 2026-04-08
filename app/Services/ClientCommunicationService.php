<?php

namespace App\Services;

use App\Mail\ClientStatusNotificationMail;
use App\Models\ClientRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ClientCommunicationService
{
    public function __construct(private readonly WhatsAppService $whatsAppService)
    {
    }

    public function notify(ClientRequest $clientRequest, string $event, array $context = []): void
    {
        $payload = $this->buildPayload($clientRequest, $event, $context);
        if (!$payload) {
            return;
        }

        if (config('maps2u_notifications.email.enabled') && $clientRequest->user?->email) {
            try {
                Mail::to($clientRequest->user->email)->send(new ClientStatusNotificationMail(
                    $clientRequest,
                    $payload['subject'],
                    $payload['headline'],
                    $payload['email_body'],
                    $payload['cta_url'] ?? null,
                    $payload['cta_label'] ?? null,
                ));
            } catch (\Throwable $e) {
                Log::error('Failed to send MAPS2U email notification.', [
                    'event' => $event,
                    'request_code' => $clientRequest->request_code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->whatsAppService->sendText($clientRequest->phone_number ?: $clientRequest->user?->phone_number, $payload['whatsapp_body']);
    }

    private function buildPayload(ClientRequest $r, string $event, array $context = []): ?array
    {
        $dashboardUrl = url('/client/dashboard');
        $requestsUrl = url('/client/requests');
        $taskTitle = $this->guessTaskTitle($r);
        $tech = $r->assignedTechnician;
        $techName = $tech?->name ?? 'Assigned Technician';
        $techPhone = $tech?->phone_number ?? '-';
        $techWhatsAppLink = $techPhone && $r->request_code
            ? 'https://wa.me/' . preg_replace('/\D+/', '', $techPhone) . '?text=' . rawurlencode('Assalamualaikum, I am contacting regarding job ' . $r->request_code)
            : null;

        return match ($event) {
            'admin_approved' => [
                'subject' => 'MAPS2U: Job approved - ' . $r->request_code,
                'headline' => 'Your request has been approved',
                'email_body' => "Your request {$r->request_code} for {$taskTitle} has been approved by admin and is moving to the next stage.",
                'whatsapp_body' => "MAPS2U Notification\nJob ID: {$r->request_code}\nTask Title: {$taskTitle}\nStatus: Approved by admin.",
                'cta_url' => $dashboardUrl,
                'cta_label' => 'Open Client Dashboard',
            ],
            'admin_rejected' => [
                'subject' => 'MAPS2U: Job rejected - ' . $r->request_code,
                'headline' => 'Your request has been rejected',
                'email_body' => "Your request {$r->request_code} has been rejected by admin. Remark: " . ($r->admin_approval_remark ?: 'No remark provided.') ,
                'whatsapp_body' => "MAPS2U Notification\nJob ID: {$r->request_code}\nStatus: Rejected\nRemark: " . ($r->admin_approval_remark ?: 'No remark provided.'),
                'cta_url' => $dashboardUrl,
                'cta_label' => 'Open Client Dashboard',
            ],
            'technician_assigned' => [
                'subject' => 'MAPS2U: Technician assigned - ' . $r->request_code,
                'headline' => 'A technician has been assigned',
                'email_body' => "Technician {$techName} has been assigned to your job {$r->request_code}. Phone number: {$techPhone}.",
                'whatsapp_body' => "MAPS2U Notification\nJob ID: {$r->request_code}\nTechnician: {$techName}\nPhone: {$techPhone}" . ($techWhatsAppLink ? "\nChat technician: {$techWhatsAppLink}" : ''),
                'cta_url' => $dashboardUrl,
                'cta_label' => 'View Assigned Technician',
            ],
            'returned_to_client' => [
                'subject' => 'MAPS2U: Request needs resubmission - ' . $r->request_code,
                'headline' => 'Technician requested an update',
                'email_body' => "Technician requested additional details for job {$r->request_code}. Please log in to the website and resubmit the request form.",
                'whatsapp_body' => "MAPS2U Notification\nJob ID: {$r->request_code}\nTechnician has returned your form for update. Please submit the updated form on the website.",
                'cta_url' => $requestsUrl,
                'cta_label' => 'Open Request List',
            ],
            'visit_site_remark' => [
                'subject' => 'MAPS2U: Visit site update - ' . $r->request_code,
                'headline' => 'Technician shared a site visit update',
                'email_body' => "Technician updated the site visit details for job {$r->request_code}. Remark: " . data_get($r->technician_review, 'visit_site_remark', 'No remark provided.'),
                'whatsapp_body' => "MAPS2U Notification\nJob ID: {$r->request_code}\nTechnician visit site update: " . data_get($r->technician_review, 'visit_site_remark', 'No remark provided.'),
                'cta_url' => $dashboardUrl,
                'cta_label' => 'Open Client Dashboard',
            ],
            'inspection_schedule' => [
                'subject' => 'MAPS2U: Inspection schedule - ' . $r->request_code,
                'headline' => 'Inspection date and time have been set',
                'email_body' => "Inspection for job {$r->request_code} is scheduled on " . optional($r->scheduled_date)?->format('d M Y') . ' at ' . ($r->scheduled_time ?: '-'),
                'whatsapp_body' => "MAPS2U Notification\nJob ID: {$r->request_code}\nInspection Schedule: " . optional($r->scheduled_date)?->format('d M Y') . ' ' . ($r->scheduled_time ?: '-'),
                'cta_url' => $dashboardUrl,
                'cta_label' => 'View Schedule',
            ],
            'invoice_uploaded' => [
                'subject' => 'MAPS2U: Feedback required - ' . $r->request_code,
                'headline' => 'Customer service report submitted and feedback will be needed',
                'email_body' => "Customer service report has been submitted for job {$r->request_code}. Please monitor the website for the feedback form and submit your review once it becomes available.",
                'whatsapp_body' => "MAPS2U Notification\nJob ID: {$r->request_code}\nCustomer service report has been submitted. Please log in to the website and complete the feedback form when available.",
                'cta_url' => $requestsUrl,
                'cta_label' => 'Open Request List',
            ],
            default => null,
        };
    }

    private function guessTaskTitle(ClientRequest $request): string
    {
        $answers = collect($request->answers ?? []);
        foreach (($request->requestType?->questions ?? []) as $question) {
            if ($question->question_type === 'remark') {
                $value = $answers->get($question->id);
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return $request->requestType?->name ?? 'Maintenance Request';
    }
}

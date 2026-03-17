<?php

namespace App\Http\Controllers;

use App\Models\ClientRequest;
use App\Services\ClientCommunicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TechnicianRequestController extends Controller
{
    public function __construct(private readonly ClientCommunicationService $communicationService)
    {
    }
    public function index()
    {
        return view('technician.requests.index', [
            'jobs' => ClientRequest::with(['user', 'requestType', 'location', 'department', 'relatedRequest'])
                ->where('assigned_technician_id', Auth::id())
                ->latest()
                ->get(),
        ]);
    }

    public function show(ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);

        return view('technician.requests.show', [
            'job' => $clientRequest->load(['user', 'requestType.questions.options', 'location', 'department', 'relatedRequest', 'assignedTechnician']),
        ]);
    }

    public function returnToClient(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);
        abort_if($clientRequest->technician_completed_at, 422, 'Job has been completed. Editing is locked.');

        $data = $request->validate([
            'technician_return_remark' => ['required', 'string', 'max:5000'],
        ]);

        $clientRequest->update([
            'technician_return_remark' => $data['technician_return_remark'],
            'status' => ClientRequest::STATUS_RETURNED,
        ]);

        $this->communicationService->notify($clientRequest->fresh(['user','requestType','assignedTechnician']), 'returned_to_client');

        return back()->with('success', 'Successful send request to client for resubmission.');
    }

    public function saveReview(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);
        abort_if($clientRequest->technician_completed_at, 422, 'Job has been completed. Editing is locked.');

        $data = $request->validate([
            'clarification_level' => ['required', 'in:critical,urgent,normal'],
            'repair_channel' => ['required', 'in:in_house_repair,vendor_required'],
            'repair_scale' => ['required', 'in:minor_repair,major_repair'],
            'processing_type' => ['required', 'in:internal,outsource'],
            'visit_site' => ['nullable', 'in:yes,no'],
            'visit_site_remark' => ['nullable', 'string'],
            'visit_site_files' => ['nullable', 'array'],
            'visit_site_files.*' => ['file', 'max:10240'],
        ]);

        $review = $clientRequest->technician_review ?? [];
        $review = array_merge($review, [
            'clarification_level' => $data['clarification_level'],
            'repair_channel' => $data['repair_channel'],
            'repair_scale' => $data['repair_scale'],
            'processing_type' => $data['processing_type'],
            'visit_site' => $data['visit_site'] ?? 'no',
            'visit_site_remark' => $data['visit_site_remark'] ?? null,
        ]);

        $visitFiles = $review['visit_site_files'] ?? [];
        foreach ($request->file('visit_site_files', []) as $file) {
            $visitFiles[] = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('technician-visit-site', 'public'),
                'mime_type' => $file->getClientMimeType(),
            ];
        }
        $review['visit_site_files'] = $visitFiles;

        $clientRequest->update([
            'technician_review' => $review,
            'technician_review_updated_at' => now(),
            'status' => ClientRequest::STATUS_UNDER_REVIEW,
        ]);

        $freshRequest = $clientRequest->fresh(['user','requestType','assignedTechnician']);
        if (data_get($freshRequest->technician_review, 'visit_site') === 'yes' && data_get($freshRequest->technician_review, 'visit_site_remark')) {
            $this->communicationService->notify($freshRequest, 'visit_site_remark');
        }

        return back()->with('success', 'Technician review submitted successfully.');
    }

    public function submitCosting(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);

        $data = $request->validate([
            'costing_items' => ['required', 'array', 'min:1'],
            'costing_items.*.equipment_type' => ['required', 'string', 'max:255'],
            'costing_items.*.equipment_price' => ['required', 'numeric', 'min:0'],
            'costing_receipts' => ['nullable', 'array'],
            'costing_receipts.*' => ['file', 'max:10240'],
        ]);

        $receipts = $clientRequest->costing_receipts ?? [];
        foreach ($request->file('costing_receipts', []) as $file) {
            $receipts[] = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('technician-costing-receipts', 'public'),
                'mime_type' => $file->getClientMimeType(),
            ];
        }

        $clientRequest->update([
            'costing_entries' => array_values($data['costing_items']),
            'costing_receipts' => $receipts,
            'status' => ClientRequest::STATUS_PENDING_APPROVAL,
        ]);

        return back()->with('success', 'Costing form submitted successfully.');
    }

    public function submitQuotation(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);

        $entries = [];
        for ($i = 1; $i <= 3; $i++) {
            $existing = collect($clientRequest->quotation_entries ?? [])->firstWhere('slot', $i) ?? [];
            $fileRule = $i === 1 && empty(data_get($existing, 'file.path')) ? 'required' : 'nullable';

            $request->validate([
                "quotation_{$i}_file" => [$fileRule, 'file', 'max:10240'],
                "quotation_{$i}_company_name" => ['nullable', 'string', 'max:255'],
                "quotation_{$i}_amount" => ['nullable', 'numeric', 'min:0'],
                "quotation_{$i}_summary_report" => ['nullable', 'string'],
                "quotation_{$i}_summary_files" => ['nullable', 'array'],
                "quotation_{$i}_summary_files.*" => ['file', 'max:10240'],
            ]);

            $amount = $request->input("quotation_{$i}_amount");
            if ($amount !== null && $amount !== '' && (float) $amount > 5000) {
                $request->validate(["quotation_{$i}_summary_report" => ['required', 'string']]);
            }

            $fileMeta = $existing['file'] ?? null;
            if ($request->hasFile("quotation_{$i}_file")) {
                $file = $request->file("quotation_{$i}_file");
                $fileMeta = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $file->store('technician-quotations', 'public'),
                    'mime_type' => $file->getClientMimeType(),
                ];
            }

            $summaryFiles = $existing['summary_files'] ?? [];
            foreach ($request->file("quotation_{$i}_summary_files", []) as $file) {
                $summaryFiles[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $file->store('technician-quotation-summaries', 'public'),
                    'mime_type' => $file->getClientMimeType(),
                ];
            }

            if ($fileMeta || $request->filled("quotation_{$i}_company_name") || $request->filled("quotation_{$i}_amount")) {
                $entries[] = [
                    'slot' => $i,
                    'file' => $fileMeta,
                    'company_name' => $request->input("quotation_{$i}_company_name"),
                    'amount' => $request->input("quotation_{$i}_amount"),
                    'summary_report' => $request->input("quotation_{$i}_summary_report"),
                    'summary_files' => $summaryFiles,
                ];
            }
        }

        $clientRequest->update([
            'quotation_entries' => $entries,
            'quotation_submitted_at' => now(),
            'status' => ClientRequest::STATUS_PENDING_APPROVAL,
        ]);

        return back()->with('success', 'Quotation form submitted successfully.');
    }

    public function saveWorkExecution(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);
        abort_if($clientRequest->technician_completed_at, 422, 'Job has been completed. Editing is locked.');
        abort_unless($clientRequest->approved_quotation_index !== null, 422, 'Admin approval is required before work execution.');

        $data = $request->validate([
            'payment_receipt_files' => ['nullable', 'array'],
            'payment_receipt_files.*' => ['file', 'max:10240'],
            'payment_type' => ['required', 'in:downpayment,full_payment'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'string', 'max:20'],
        ]);

        $receiptFiles = $clientRequest->payment_receipt_files ?? [];
        foreach ($request->file('payment_receipt_files', []) as $file) {
            $receiptFiles[] = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('technician-payment-receipts', 'public'),
                'mime_type' => $file->getClientMimeType(),
            ];
        }

        $clientRequest->update([
            'payment_receipt_files' => $receiptFiles,
            'payment_type' => $data['payment_type'],
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'],
            'status' => ClientRequest::STATUS_WORK_IN_PROGRESS,
        ]);

        $this->communicationService->notify($clientRequest->fresh(['user','requestType','assignedTechnician']), 'inspection_schedule');

        return back()->with('success', 'Work execution submitted successfully.');
    }

    public function updateInspectionTimer(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);
        abort_if($clientRequest->technician_completed_at, 422, 'Job has been completed. Editing is locked.');

        $request->validate([
            'timer_action' => ['required', 'in:start,stop'],
            'timer_decision' => ['nullable', 'in:proceed,amend'],
            'timer_remark' => ['nullable', 'string', 'max:255'],
        ]);

        $sessions = $clientRequest->inspection_sessions ?? [];
        $activeIndex = collect($sessions)->search(fn ($session) => empty($session['ended_at']));

        if ($request->input('timer_action') === 'start') {
            abort_if($activeIndex !== false, 422, 'An inspection timer is already running.');

            $sessions[] = [
                'started_at' => now('Asia/Kuala_Lumpur')->toDateTimeString(),
                'started_label' => now('Asia/Kuala_Lumpur')->format('d M Y H:i'),
                'remark' => count($sessions) > 0 ? 'amend' : 'initial',
            ];

            $clientRequest->update([
                'inspection_sessions' => array_values($sessions),
                'status' => ClientRequest::STATUS_WORK_IN_PROGRESS,
            ]);

            return back()->with('success', 'Inspection timer started successfully.');
        }

        abort_if($activeIndex === false, 422, 'No active inspection timer found.');
        $request->validate([
            'timer_decision' => ['required', 'in:proceed,amend'],
        ]);

        $startedAt = \Illuminate\Support\Carbon::parse($sessions[$activeIndex]['started_at']);
        $endedAt = now('Asia/Kuala_Lumpur');
        $durationSeconds = $endedAt->diffInSeconds($startedAt);

        $sessions[$activeIndex]['ended_at'] = $endedAt->toDateTimeString();
        $sessions[$activeIndex]['ended_label'] = $endedAt->format('d M Y H:i');
        $sessions[$activeIndex]['duration_seconds'] = $durationSeconds;
        $sessions[$activeIndex]['decision'] = $request->input('timer_decision');
        $sessions[$activeIndex]['remark'] = $request->input('timer_decision') === 'amend'
            ? ($request->input('timer_remark') ?: 'amend')
            : 'proceed';

        $clientRequest->update([
            'inspection_sessions' => array_values($sessions),
            'status' => ClientRequest::STATUS_WORK_IN_PROGRESS,
        ]);

        return back()->with('success', 'Inspection timer stopped successfully.');
    }

    public function saveInspectForm(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);
        abort_if($clientRequest->technician_completed_at, 422, 'Job has been completed. Editing is locked.');

        $data = $request->validate([
            'before_files' => ['nullable', 'array'],
            'before_files.*' => ['file', 'max:10240'],
            'after_files' => ['nullable', 'array'],
            'after_files.*' => ['file', 'max:10240'],
            'inspection_remark' => ['nullable', 'string'],
            'safety_checked' => ['required', 'accepted'],
            'safety_remark' => ['nullable', 'string'],
            'quality_checked' => ['required', 'accepted'],
            'quality_remark' => ['nullable', 'string'],
            'customer_satisfaction_checked' => ['required', 'accepted'],
            'customer_satisfaction_remark' => ['nullable', 'string'],
            'add_related_job' => ['nullable', 'boolean'],
        ]);

        $inspect = $clientRequest->inspect_data ?? [];
        $inspect['before_files'] = $inspect['before_files'] ?? [];
        $inspect['after_files'] = $inspect['after_files'] ?? [];
        foreach ($request->file('before_files', []) as $file) {
            $inspect['before_files'][] = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('inspect-before', 'public'),
                'mime_type' => $file->getClientMimeType(),
            ];
        }
        foreach ($request->file('after_files', []) as $file) {
            $inspect['after_files'][] = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('inspect-after', 'public'),
                'mime_type' => $file->getClientMimeType(),
            ];
        }

        $inspect = array_merge($inspect, [
            'inspection_remark' => $data['inspection_remark'] ?? null,
            'safety_checked' => true,
            'safety_remark' => $data['safety_remark'] ?? null,
            'quality_checked' => true,
            'quality_remark' => $data['quality_remark'] ?? null,
            'customer_satisfaction_checked' => true,
            'customer_satisfaction_remark' => $data['customer_satisfaction_remark'] ?? null,
            'add_related_job' => $request->boolean('add_related_job'),
        ]);

        $clientRequest->update([
            'inspect_data' => $inspect,
            'status' => ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW,
        ]);

        return back()->with('success', 'Inspect form submitted successfully.');
    }

    public function uploadInvoice(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);
        abort_if($clientRequest->technician_completed_at, 422, 'Job has been completed. Editing is locked.');

        $request->validate([
            'invoice_files' => ['required', 'array', 'min:1'],
            'invoice_files.*' => ['file', 'max:10240'],
        ]);

        $files = $clientRequest->invoice_files ?? [];
        foreach ($request->file('invoice_files', []) as $file) {
            $files[] = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('technician-invoices', 'public'),
                'mime_type' => $file->getClientMimeType(),
            ];
        }

        $clientRequest->update([
            'invoice_files' => $files,
            'invoice_uploaded_at' => now('Asia/Kuala_Lumpur'),
            'status' => 'Pending Technician Feedback',
        ]);

        $this->communicationService->notify($clientRequest->fresh(['user','requestType','assignedTechnician']), 'invoice_uploaded');

        return back()->with('success', 'Invoice uploaded successfully. Admin has been notified in the finance queue.');
    }

    public function submitCustomerService(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);
        abort_if(empty($clientRequest->invoice_files), 422, 'Upload invoice before completing the customer service report.');
        abort_if(empty($clientRequest->inspection_sessions), 422, 'Start and stop inspection timer before completing the customer service report.');

        $data = $request->validate([
            'description_of_work' => ['nullable', 'string'],
            'suggestion_recommendation' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
            'person_in_charge_signature' => ['required', 'string'],
            'verify_by_signature' => ['required', 'string'],
        ]);

        $existing = $clientRequest->customer_service_report ?? [];
        $attachments = $existing['attachments'] ?? [];
        foreach ($request->file('attachments', []) as $file) {
            $attachments[] = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('customer-service-report-attachments', 'public'),
                'mime_type' => $file->getClientMimeType(),
            ];
        }

        $report = [
            'technician_name' => $clientRequest->assignedTechnician?->name,
            'job_id' => $clientRequest->request_code,
            'date_inspection' => $clientRequest->inspectionDate(),
            'time_history' => array_values($clientRequest->inspection_sessions ?? []),
            'description_of_work' => $data['description_of_work'] ?? null,
            'suggestion_recommendation' => $data['suggestion_recommendation'],
            'attachments' => $attachments,
            'person_in_charge_signature' => $data['person_in_charge_signature'],
            'verify_by_signature' => $data['verify_by_signature'],
            'submitted_at' => now()->toDateTimeString(),
        ];

        $clientRequest->update([
            'customer_service_report' => $report,
            'technician_completed_at' => now(),
        ]);

        return back()->with('success', 'Customer service report submitted successfully. Technician completion has been recorded.');
    }
}

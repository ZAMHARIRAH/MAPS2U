<?php

namespace App\Http\Controllers;

use App\Models\ClientRequest;
use App\Models\Vendor;
use App\Services\ClientCommunicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class TechnicianRequestController extends Controller
{
    public function __construct(private readonly ClientCommunicationService $communicationService)
    {
    }
    public function index()
    {
        return view('technician.requests.index', [
            'jobs' => ClientRequest::with(['user', 'requestType', 'location', 'department', 'relatedRequest', 'assignedTechnician'])
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
            'vendors' => Vendor::orderBy('company_name')->get(),
        ]);
    }

    public function report(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);

        return view('technician.requests.report', [
            'job' => $clientRequest->load(['user', 'requestType.questions.options', 'location', 'department', 'relatedRequest', 'assignedTechnician']),
            'printMode' => $request->boolean('print'),
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
            'technician_review_updated_at' => now('Asia/Kuala_Lumpur'),
            'status' => ClientRequest::STATUS_UNDER_REVIEW,
        ]);

        $freshRequest = $clientRequest->fresh(['user','requestType','assignedTechnician']);
        if (data_get($freshRequest->technician_review, 'visit_site') === 'yes' && data_get($freshRequest->technician_review, 'visit_site_remark')) {
            $this->communicationService->notify($freshRequest, 'visit_site_remark');
        }

        return back()->with('success', 'Technician review submitted successfully.');
    }

    public function appendReviewRemark(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->assigned_technician_id === Auth::id(), 403);
        abort_if($clientRequest->technician_completed_at, 422, 'Job has been completed. Editing is locked.');

        $data = $request->validate([
            'remark' => ['required', 'string', 'max:5000'],
        ]);

        $logs = $clientRequest->admin_technician_remarks ?? [];
        $now = now('Asia/Kuala_Lumpur');
        $logs[] = [
            'sender_type' => 'technician',
            'sender_name' => (string) ($request->user()?->name ?? 'Technician'),
            'remark' => trim($data['remark']),
            'created_at' => $now->toDateTimeString(),
            'created_at_label' => $now->format('d M Y h:i A'),
        ];

        $clientRequest->update([
            'admin_technician_remarks' => array_values($logs),
        ]);

        return back()->with('success', 'Remark added to shared communication log.');
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

        $vendorIds = Vendor::orderBy('company_name')->pluck('id')->all();
        $entries = [];
        for ($i = 1; $i <= 3; $i++) {
            $existing = collect($clientRequest->quotation_entries ?? [])->firstWhere('slot', $i) ?? [];
            $fileRule = $i === 1 && empty(data_get($existing, 'file.path')) ? 'required' : 'nullable';

            $request->validate([
                "quotation_{$i}_file" => [$fileRule, 'file', 'max:10240'],
                "quotation_{$i}_vendor_id" => ['nullable', 'integer', \Illuminate\Validation\Rule::in($vendorIds)],
                "quotation_{$i}_company_name" => ['nullable', 'string', 'max:255'],
                "quotation_{$i}_manual_company_name" => ['nullable', 'string', 'max:255'],
                "quotation_{$i}_subject_to_approval" => ['nullable', 'boolean'],
                "quotation_{$i}_amount" => ['nullable', 'numeric', 'min:0'],
                "quotation_{$i}_summary_report" => ['nullable', 'string'],
                "quotation_{$i}_summary_files" => ['nullable', 'array'],
                "quotation_{$i}_summary_files.*" => ['file', 'max:10240'],
            ]);

            $amount = $request->input("quotation_{$i}_amount");
            if ($amount !== null && $amount !== '' && (float) $amount > 5000) {
                $request->validate(["quotation_{$i}_summary_report" => ['required', 'string']]);
            }

            $vendor = $request->filled("quotation_{$i}_vendor_id") ? Vendor::find($request->integer("quotation_{$i}_vendor_id")) : null;
            $subjectToApproval = $request->boolean("quotation_{$i}_subject_to_approval");

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

            $companyName = $subjectToApproval
                ? trim((string) $request->input("quotation_{$i}_manual_company_name"))
                : ($vendor?->company_name ?: $request->input("quotation_{$i}_company_name"));

            if ($fileMeta || $companyName || $request->filled("quotation_{$i}_amount")) {
                $entries[] = [
                    'slot' => $i,
                    'file' => $fileMeta,
                    'vendor_id' => $vendor?->id,
                    'company_name' => $companyName,
                    'subject_to_approval' => $subjectToApproval,
                    'amount' => $request->input("quotation_{$i}_amount"),
                    'summary_report' => $request->input("quotation_{$i}_summary_report"),
                    'summary_files' => $summaryFiles,
                    'vendor_snapshot' => $vendor ? [
                        'company_name' => $vendor->company_name,
                        'ssm_number' => $vendor->ssm_number,
                        'office_address' => $vendor->office_address,
                        'phone_number' => $vendor->phone_number,
                        'fax_number' => $vendor->fax_number,
                        'official_email' => $vendor->official_email,
                        'contact_person' => $vendor->contact_person,
                        'bank' => $vendor->bank,
                        'account_number_for_payment' => $vendor->account_number_for_payment,
                        'document_path' => $vendor->document_path,
                        'document_original_name' => $vendor->document_original_name,
                    ] : ($existing['vendor_snapshot'] ?? null),
                ];
            }
        }

        $clientRequest->update([
            'quotation_entries' => $entries,
            'quotation_submitted_at' => now('Asia/Kuala_Lumpur'),
            'quotation_return_remark' => null,
            'approved_quotation_index' => null,
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
            'payment_type' => ['required', 'in:balance,downpayment,full_payment'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'string', 'max:20'],
        ]);

        $submittedAt = now('Asia/Kuala_Lumpur');
        $receiptFiles = $clientRequest->payment_receipt_files ?? [];
        $newBatchFiles = [];
        foreach ($request->file('payment_receipt_files', []) as $file) {
            $meta = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('technician-payment-receipts', 'public'),
                'mime_type' => $file->getClientMimeType(),
                'uploaded_at' => $submittedAt->toDateTimeString(),
                'payment_type' => $data['payment_type'],
            ];
            $receiptFiles[] = $meta;
            $newBatchFiles[] = $meta;
        }

        $history = $clientRequest->payment_receipt_history ?? [];
        if (!empty($newBatchFiles)) {
            $history[] = [
                'payment_type' => $data['payment_type'],
                'uploaded_at' => $submittedAt->toDateTimeString(),
                'uploaded_label' => $submittedAt->format('d M Y H:i:s'),
                'files' => $newBatchFiles,
            ];
        }

        $clientRequest->update([
            'payment_receipt_files' => $receiptFiles,
            'payment_receipt_history' => array_values($history),
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

        $data = $request->validate([
            'timer_action' => ['required', 'in:start,stop'],
            'remark' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
            'person_in_charge' => ['nullable', 'string'],
            'verify_by' => ['nullable', 'string'],
            'verify_by_signed_at' => ['nullable', 'string'],
            'ended_at' => ['nullable', 'string'],
        ]);

        if ($data['timer_action'] === 'start') {
            abort_if($clientRequest->technician_log_started_at, 422, 'A technician log is already running.');

            $startedAt = now('Asia/Kuala_Lumpur');
            $clientRequest->update([
                'technician_log_started_at' => $startedAt->copy()->utc(),
                'technician_log_started_label' => $startedAt->format('Y-m-d H:i:s'),
                'status' => ClientRequest::STATUS_WORK_IN_PROGRESS,
            ]);

            return back()->with('success', 'Technician log started successfully.');
        }

        abort_if(!$clientRequest->technician_log_started_at, 422, 'No technician log is currently running.');

        $request->validate([
            'remark' => ['required', 'string'],
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'max:10240'],
            'person_in_charge' => ['required', 'string'],
            'verify_by' => ['required', 'string'],
            'verify_by_signed_at' => ['nullable', 'string'],
            'ended_at' => ['nullable', 'string'],
        ]);

        $startedAt = $this->resolveMalaysiaStartTime($clientRequest);
        $endedAt = $this->resolveMalaysiaEndTime($data['ended_at'] ?? null);
        $durationSeconds = max(0, $startedAt->diffInSeconds($endedAt, false));

        $attachments = [];
        foreach ($request->file('attachments', []) as $file) {
            $attachments[] = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('technician-daily-logs', 'public'),
                'mime_type' => $file->getClientMimeType(),
            ];
        }

        $sessions = $clientRequest->inspection_sessions ?? [];
        $sessions[] = [
            'started_at' => $startedAt->format('Y-m-d H:i:s'),
            'started_at_unix' => $startedAt->timestamp,
            'started_label' => $startedAt->format('d M Y H:i:s'),
            'ended_at' => $endedAt->format('Y-m-d H:i:s'),
            'ended_at_unix' => $endedAt->timestamp,
            'ended_label' => $endedAt->format('d M Y H:i:s'),
            'date_label' => $endedAt->format('d M Y'),
            'time_start' => $startedAt->format('H:i:s'),
            'time_end' => $endedAt->format('H:i:s'),
            'duration_seconds' => $durationSeconds,
            'duration_label' => $clientRequest->formattedDuration($durationSeconds),
            'remark' => $data['remark'],
            'technician_name' => $clientRequest->assignedTechnician?->name,
            'attachments' => $attachments,
            'person_in_charge' => $data['person_in_charge'],
            'verify_by' => $data['verify_by'],
            'recorded_at' => $endedAt->format('Y-m-d H:i:s'),
            'recorded_at_label' => $endedAt->format('d M Y h:i A'),
            'verify_by_signed_at' => $data['verify_by_signed_at'] ?? $endedAt->format('Y-m-d H:i:s'),
            'verify_by_signed_at_label' => $this->formatSignatureMoment($data['verify_by_signed_at'] ?? $endedAt->format('Y-m-d H:i:s')),
        ];

        $clientRequest->update([
            'inspection_sessions' => array_values($sessions),
            'technician_log_started_at' => null,
            'technician_log_started_label' => null,
            'status' => ClientRequest::STATUS_WORK_IN_PROGRESS,
        ]);

        return back()->with('success', 'Technician log saved successfully.');
    }

    protected function resolveMalaysiaEndTime(?string $value): Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return now('Asia/Kuala_Lumpur');
        }

        foreach (['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'd M Y H:i:s', 'd M Y h:i A', 'd M Y h:i:s A'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value, 'Asia/Kuala_Lumpur');
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($value, 'Asia/Kuala_Lumpur');
        } catch (\Throwable $e) {
            return now('Asia/Kuala_Lumpur');
        }
    }




    protected function formatSignatureMoment(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d\TH:i:sP', 'Y-m-d H:i:s', 'd M Y H:i:s', 'd M Y h:i A', 'd M Y h:i:s A'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value, 'Asia/Kuala_Lumpur')->format('d M Y h:i A');
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($value, 'Asia/Kuala_Lumpur')->format('d M Y h:i A');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    protected function resolveMalaysiaStartTime(ClientRequest $clientRequest): Carbon
    {
        $label = trim((string) ($clientRequest->technician_log_started_label ?? ''));
        if ($label !== '') {
            foreach (['Y-m-d H:i:s', 'd M Y H:i', 'd M Y H:i:s', 'd M Y h:i A', 'd M Y h:i:s A'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $label, 'Asia/Kuala_Lumpur');
                } catch (\Throwable $e) {
                }
            }

            try {
                return Carbon::parse($label, 'Asia/Kuala_Lumpur');
            } catch (\Throwable $e) {
            }
        }

        if ($clientRequest->technician_log_started_at instanceof Carbon) {
            return $clientRequest->technician_log_started_at->copy()->timezone('Asia/Kuala_Lumpur');
        }

        return Carbon::parse($clientRequest->technician_log_started_at, 'UTC')->timezone('Asia/Kuala_Lumpur');
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
        abort_unless($clientRequest->inspect_data, 422, 'Submit the inspection form before completing the customer service report.');
        abort_if(empty($clientRequest->inspection_sessions), 422, 'Complete at least one technician log before submitting the customer service report.');

        $data = $request->validate([
            'suggestion_recommendation' => ['nullable', 'string'],
            'attachments' => ['required', 'array', 'min:1'],
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

        $dailyLogAttachments = collect($clientRequest->inspection_sessions ?? [])
            ->flatMap(fn ($session) => $session['attachments'] ?? [])
            ->values()
            ->all();

        $submittedAt = now('Asia/Kuala_Lumpur');
        $report = [
            'technician_name' => $clientRequest->assignedTechnician?->name,
            'job_id' => $clientRequest->request_code,
            'date_inspection' => $submittedAt->format('d M Y'),
            'duration_of_work' => $clientRequest->formattedDuration($clientRequest->totalInspectionDurationSeconds()),
            'time_history' => array_values($clientRequest->inspection_sessions ?? []),
            'description_of_work' => $clientRequest->compiledDailyLogDescription(),
            'description_entries' => collect($clientRequest->inspection_sessions ?? [])->map(function ($session) use ($clientRequest) {
                $session = (array) $session;
                return [
                    'date_label' => $session['date_label'] ?? null,
                    'time_range' => trim((($session['time_start'] ?? '-') . ' - ' . ($session['time_end'] ?? '-'))),
                    'duration_label' => $session['duration_label'] ?? $clientRequest->formattedDuration($clientRequest->inspectionSessionDurationSeconds($session)),
                    'remark' => $session['remark'] ?? '-',
                    'verify_by_signed_at_label' => $session['verify_by_signed_at_label'] ?? null,
                    'verify_by_signature' => $session['verify_by'] ?? null,
                ];
            })->values()->all(),
            'suggestion_recommendation' => $data['suggestion_recommendation'] ?? null,
            'attachments' => $attachments,
            'daily_log_attachments' => $dailyLogAttachments,
            'person_in_charge_signature' => $data['person_in_charge_signature'],
            'verify_by_signature' => $data['verify_by_signature'],
            'submitted_at' => $submittedAt->toDateTimeString(),
        ];

        $clientRequest->update([
            'customer_service_report' => $report,
            'technician_completed_at' => $submittedAt,
            'invoice_uploaded_at' => $submittedAt,
            'status' => ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW,
        ]);

        $this->communicationService->notify($clientRequest->fresh(['user','requestType','assignedTechnician']), 'invoice_uploaded');

        return back()->with('success', 'Customer service report submitted successfully. Finance evidence is now ready and client feedback is available.');
    }
}

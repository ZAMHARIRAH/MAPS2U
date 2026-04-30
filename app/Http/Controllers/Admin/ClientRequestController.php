<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use App\Models\Department;
use App\Models\Location;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use App\Services\ClientCommunicationService;
use App\Services\TechnicianCommunicationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use App\Services\QuotationSignatureService;

class ClientRequestController extends Controller
{
    public function __construct(
        private readonly ClientCommunicationService $communicationService,
        private readonly TechnicianCommunicationService $technicianCommunicationService,
        private readonly QuotationSignatureService $quotationSignatureService,
    )
    {
    }
    public function index(Request $request)
    {
        /** @var User $admin */
        $admin = Auth::user();

        $query = $this->buildInboxQuery($request, $admin);

        $submissions = $query->orderByRaw("COALESCE(related_request_id, id) DESC")->orderByRaw("CASE WHEN related_request_id IS NULL THEN 0 ELSE 1 END")->latest()->paginate(20)->withQueryString();

        $clientRole = $admin->primaryHandledClientRole();
        $locationType = $clientRole === User::CLIENT_HQ ? Location::TYPE_HQ : Location::TYPE_BRANCH;
        $isViewer = $admin->isViewer();

        return view('admin.submissions.index', [
            'submissions' => $submissions,
            'statusOptions' => ClientRequest::adminVisibleStatusOptions(),
            'technicians' => User::where('role', User::ROLE_TECHNICIAN)->select(['id', 'name'])->orderBy('name')->get(),
            'requestTypes' => RequestType::select(['id', 'name'])->orderBy('name')->get(),
            'departments' => ($clientRole === User::CLIENT_HQ || $isViewer)
                ? Department::select(['id', 'name'])->orderBy('name')->get()
                : collect(),
            'locations' => Location::where('type', $locationType)->select(['id', 'name', 'type'])->orderBy('name')->get(),
            'hqLocations' => $isViewer
                ? Location::where('type', Location::TYPE_HQ)->select(['id', 'name', 'type'])->orderBy('name')->get()
                : collect(),
            'branchLocations' => $isViewer
                ? Location::where('type', Location::TYPE_BRANCH)->select(['id', 'name', 'type'])->orderBy('name')->get()
                : collect(),
            'clientRole' => $clientRole,
            'filters' => $request->all(),
        ]);
    }

    public function filteredPrint(Request $request)
    {
        /** @var User $admin */
        $admin = Auth::user();
        $items = $this->buildInboxQuery($request, $admin)->orderByRaw("COALESCE(related_request_id, id) DESC")->orderByRaw("CASE WHEN related_request_id IS NULL THEN 0 ELSE 1 END")->latest()->get();
        $statusFilter = (string) $request->input('status');

        return view('admin.submissions.print-filtered', [
            'items' => $items,
            'filters' => $request->all(),
            'statusFilter' => $statusFilter,
            'isCompletedWorkflow' => $statusFilter === ClientRequest::STATUS_COMPLETED,
            'feedbackSections' => $this->feedbackSections(),
            'printedAt' => now('Asia/Kuala_Lumpur'),
        ]);
    }

    public function print(Request $request, ClientRequest $clientRequest)
    {
        /** @var User $admin */
        $admin = Auth::user();
        abort_unless(in_array($clientRequest->effectiveClientRole(), $admin->handledClientRoles(), true), 403);

        return view('admin.submissions.print', [
            'submission' => $clientRequest->load(['user', 'requestType.questions.options', 'location', 'department', 'relatedRequest', 'assignedTechnician']),
            'printMode' => (bool) $request->boolean('print'),
            'feedbackSections' => $this->feedbackSections(),
        ]);
    }

    private function buildInboxQuery(Request $request, User $admin)
    {
        $handledRoles = $admin->handledClientRoles();

        $query = ClientRequest::with(['user', 'requestType', 'location', 'department', 'relatedRequest', 'assignedTechnician'])
            ->where(function ($query) use ($handledRoles) {
                $query->whereHas('user', fn ($userQuery) => $userQuery->whereIn('sub_role', $handledRoles))
                    ->orWhere(function ($legacyQuery) use ($handledRoles) {
                        $legacyQuery->where('inspect_data->source', 'bulk_import')
                            ->whereIn('inspect_data->legacy_client_role', $handledRoles);
                    });
            });

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($inner) use ($search) {
                $inner->where('request_code', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhereHas('requestType', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('department', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('assignedTechnician', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->input('status');

            if ($status === ClientRequest::STATUS_COMPLETED) {
                $query->where(function ($completedQuery) {
                    $completedQuery->whereNotNull('finance_completed_at')
                        ->orWhere('status', ClientRequest::STATUS_COMPLETED);
                });
            } elseif ($status === ClientRequest::STATUS_FINANCE_PENDING) {
                $query->whereNotNull('technician_completed_at')
                    ->whereNull('finance_completed_at')
                    ->whereNotNull('approved_quotation_index');
            } else {
                $query->where('status', $status)->whereNull('finance_completed_at');
            }
        }

        if ($request->filled('admin_approval_status')) {
            if ($request->input('admin_approval_status') === 'pending') {
                $query->whereNull('admin_approval_status');
            } else {
                $query->where('admin_approval_status', $request->input('admin_approval_status'));
            }
        }

        foreach (['assigned_technician_id', 'urgency_level', 'request_type_id', 'department_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($admin->isViewer()) {
            if ($request->filled('hq_location_id') && $request->filled('branch_location_id')) {
                $query->where(function ($inner) use ($request) {
                    $inner->where(function ($sub) use ($request) {
                        $sub->whereHas('user', fn ($q) => $q->where('sub_role', User::CLIENT_HQ))
                            ->where('location_id', $request->input('hq_location_id'));
                    })->orWhere(function ($sub) use ($request) {
                        $sub->whereHas('user', fn ($q) => $q->where('sub_role', User::CLIENT_KINDERGARTEN))
                            ->where('location_id', $request->input('branch_location_id'));
                    });
                });
            } elseif ($request->filled('hq_location_id')) {
                $query->whereHas('user', fn ($q) => $q->where('sub_role', User::CLIENT_HQ))
                    ->where('location_id', $request->input('hq_location_id'));
            } elseif ($request->filled('branch_location_id')) {
                $query->whereHas('user', fn ($q) => $q->where('sub_role', User::CLIENT_KINDERGARTEN))
                    ->where('location_id', $request->input('branch_location_id'));
            }
        } elseif ($request->filled('location_id')) {
            $query->where('location_id', $request->input('location_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        return $query;
    }

    public function show(ClientRequest $clientRequest)
    {
        /** @var User $admin */
        $admin = Auth::user();
        abort_unless(in_array($clientRequest->effectiveClientRole(), $admin->handledClientRoles(), true), 403);

        return view('admin.submissions.show', [
            'submission' => $clientRequest->load(['user', 'requestType.questions.options', 'location', 'department', 'relatedRequest', 'assignedTechnician']),
            'technicians' => User::where('role', User::ROLE_TECHNICIAN)->select(['id', 'name'])->orderBy('name')->get(),
            'vendors' => Vendor::orderBy('company_name')->get(),
        ]);
    }

    public function reviewDecision(Request $request, ClientRequest $clientRequest)
    {
        abort_if($request->user()?->isViewer(), 403, 'Viewer is view only.');

        $request->validate([
            'decision' => ['required', 'in:approved,rejected,subject_to_approval'],
            'admin_approval_remark' => ['nullable', 'string'],
            'admin_approved_remark' => ['nullable', 'string'],
            'subject_to_approval_remark' => ['nullable', 'string'],
        ]);

        if ($request->input('decision') === 'rejected') {
            $request->validate([
                'admin_approval_remark' => ['required', 'string'],
            ]);

            $clientRequest->update([
                'admin_approval_status' => 'rejected',
                'admin_approval_remark' => $request->string('admin_approval_remark')->toString(),
                'admin_approved_at' => now('Asia/Kuala_Lumpur'),
                'status' => ClientRequest::STATUS_REJECTED,
                'assigned_technician_id' => null,
            ]);

            $this->communicationService->notify($clientRequest->fresh(['user','requestType','assignedTechnician']), 'admin_rejected');

            return back()->with('success', 'Request rejected and client can now see the rejection remark.');
        }

        if ($request->input('decision') === 'subject_to_approval') {
            $request->validate([
                'subject_to_approval_remark' => ['required', 'string'],
            ]);

            $clientRequest->update([
                'admin_approval_status' => 'subject_to_approval',
                'subject_to_approval_remark' => $request->string('subject_to_approval_remark')->toString(),
                'subject_to_approval_requested_at' => now('Asia/Kuala_Lumpur'),
                'subject_to_approval_checked_at' => null,
                'admin_approval_remark' => null,
                'admin_approved_remark' => null,
                'admin_approved_at' => null,
                'status' => ClientRequest::STATUS_UNDER_REVIEW,
                'assigned_technician_id' => null,
            ]);

            return back()->with('success', 'Request marked as Subject To Approval. Assignment stays locked until the approval checkbox is ticked.');
        }

        $request->validate([
            'admin_approved_remark' => ['required', 'string'],
        ]);

        $clientRequest->update([
            'admin_approval_status' => 'approved',
            'admin_approval_remark' => null,
            'admin_approved_remark' => $request->string('admin_approved_remark')->toString(),
            'subject_to_approval_remark' => null,
            'subject_to_approval_requested_at' => null,
            'subject_to_approval_checked_at' => null,
            'admin_approved_at' => now('Asia/Kuala_Lumpur'),
            'status' => ClientRequest::STATUS_UNDER_REVIEW,
        ]);

        $this->communicationService->notify($clientRequest->fresh(['user','requestType','assignedTechnician']), 'admin_approved');

        return back()->with('success', 'Request approved. You can now assign a technician.');
    }

    public function toggleSubjectApproval(Request $request, ClientRequest $clientRequest)
    {
        abort_if($request->user()?->isViewer(), 403, 'Viewer is view only.');

        abort_unless($clientRequest->admin_approval_status === 'subject_to_approval', 422, 'This request is not in Subject To Approval stage.');

        $data = $request->validate([
            'approved' => ['required', 'boolean'],
        ]);

        $approved = (bool) $data['approved'];

        $clientRequest->update([
            'subject_to_approval_checked_at' => $approved ? now('Asia/Kuala_Lumpur') : null,
            'admin_approval_status' => $approved ? 'approved' : 'subject_to_approval',
            'status' => ClientRequest::STATUS_UNDER_REVIEW,
            'admin_approved_at' => $approved ? now('Asia/Kuala_Lumpur') : null,
            'assigned_technician_id' => $approved ? $clientRequest->assigned_technician_id : null,
        ]);

        return back()->with('success', $approved ? 'Final approval checkbox saved. Technician assignment is now enabled.' : 'Final approval checkbox unticked. Assignment is locked again.');
    }

    public function saveViewerSummary(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($request->user()?->isViewer(), 403, 'Only viewer can update this summary.');

        $data = $request->validate([
            'viewer_summary_remark' => ['required', 'string', 'max:10000'],
            'viewer_summary_signature' => ['required', 'string'],
        ]);

        $now = now('Asia/Kuala_Lumpur');
        $history = $clientRequest->viewer_summary_history ?? [];
        $history[] = [
            'remark' => trim($data['viewer_summary_remark']),
            'signature' => $data['viewer_summary_signature'],
            'updated_by_name' => (string) ($request->user()?->name ?? 'Viewer'),
            'updated_at' => $now->toDateTimeString(),
            'updated_at_label' => $now->format('d M Y h:i A'),
        ];

        $clientRequest->update([
            'viewer_summary_remark' => trim($data['viewer_summary_remark']),
            'viewer_summary_signature' => $data['viewer_summary_signature'],
            'viewer_summary_updated_by_name' => (string) ($request->user()?->name ?? 'Viewer'),
            'viewer_summary_updated_at' => $now,
            'viewer_summary_history' => array_values($history),
        ]);

        return back()->with('success', 'Viewer remark summary saved with history log.');
    }

    public function appendTechnicianReviewRemark(Request $request, ClientRequest $clientRequest)
    {
        abort_if($request->user()?->isViewer(), 403, 'Viewer is view only.');

        $data = $request->validate([
            'remark' => ['required', 'string', 'max:5000'],
        ]);

        $logs = $clientRequest->admin_technician_remarks ?? [];
        $now = now('Asia/Kuala_Lumpur');
        $logs[] = [
            'sender_type' => 'admin',
            'sender_name' => (string) ($request->user()?->name ?? 'Admin'),
            'remark' => trim($data['remark']),
            'created_at' => $now->toDateTimeString(),
            'created_at_label' => $now->format('d M Y h:i A'),
        ];

        $clientRequest->update([
            'admin_technician_remarks' => array_values($logs),
        ]);

        return back()->with('success', 'Admin remark added to technician review communication log.');
    }

    public function assign(Request $request, ClientRequest $clientRequest)
    {
        abort_if($request->user()?->isViewer(), 403, 'Viewer is view only.');

        abort_unless($clientRequest->admin_approval_status === 'approved', 422, 'Approve the request before assigning a technician.');

        $request->validate([
            'assigned_technician_id' => ['required', 'exists:users,id'],
        ]);

        $technician = User::findOrFail($request->integer('assigned_technician_id'));
        abort_unless($technician->role === User::ROLE_TECHNICIAN, 422);

        $clientRequest->update([
            'assigned_technician_id' => $technician->id,
            'assigned_at' => now('Asia/Kuala_Lumpur'),
            'status' => ClientRequest::STATUS_UNDER_REVIEW,
        ]);

        $freshRequest = $clientRequest->fresh(['user','requestType','assignedTechnician','location']);
        $this->communicationService->notify($freshRequest, 'technician_assigned');
        $this->technicianCommunicationService->notifyAssignment($freshRequest);

        return back()->with('success', 'Technician assigned successfully. Technician email and WhatsApp notification have been triggered.');
    }

    public function returnToClient(Request $request, ClientRequest $clientRequest)
    {
        abort_if($request->user()?->isViewer(), 403, 'Viewer is view only.');

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

    public function adminEditClientForm(Request $request, ClientRequest $clientRequest)
    {
        abort_if($request->user()?->isViewer(), 403, 'Viewer is view only.');

        $data = $request->validate([
            'urgency_level' => ['nullable', 'in:1,2,3'],
            'request_type_id' => ['required', 'exists:request_types,id'],
            'task_titles' => ['nullable', 'array'],
            'task_titles.*' => ['nullable', 'string'],
            'issue_updates' => ['nullable', 'array'],
        ]);

        $requestType = RequestType::with('questions')->findOrFail($data['request_type_id']);
        $answers = $clientRequest->answers ?? [];
        $taskValues = collect($data['task_titles'] ?? [])->filter(fn ($value) => trim((string) $value) !== '')->values();
        $issueValues = collect($data['issue_updates'] ?? []);

        foreach ($requestType->questions as $question) {
            if ($question->question_type === \App\Models\RequestQuestion::TYPE_TASK_TITLE) {
                $selected = $taskValues->shift();
                if ($selected !== null) {
                    $answers[$question->id] = ['value' => $selected];
                }
            }

            if ($question->question_type === \App\Models\RequestQuestion::TYPE_REMARK) {
                $replacement = $issueValues->get((string) $question->id);
                if ($replacement !== null) {
                    $answers[$question->id] = $replacement;
                }
            }
        }

        $clientRequest->update([
            'request_type_id' => $requestType->id,
            'urgency_level' => $data['urgency_level'] ?: $clientRequest->urgency_level,
            'answers' => $answers,
        ]);

        $this->communicationService->notify($clientRequest->fresh(['user','requestType','assignedTechnician']), 'admin_edited_form');

        return back()->with('success', 'Client request form updated successfully.');
    }

    public function updateReview(Request $request, ClientRequest $clientRequest)
    {
        abort_if($request->user()?->isViewer(), 403, 'Viewer is view only.');

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
        ]);

        return back()->with('success', 'Review details updated successfully.');
    }

    public function approveQuotation(Request $request, ClientRequest $clientRequest)
    {
        abort_if($request->user()?->isViewer(), 403, 'Viewer is view only.');

        $data = $request->validate([
            'approved_quotation_index' => ['required', 'integer', 'min:1', 'max:3'],
            'approval_signature' => ['required', 'string'],
            'create_vendor' => ['nullable', 'boolean'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'ssm_number' => ['nullable', 'string', 'max:255'],
            'office_address' => ['nullable', 'string'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'fax_number' => ['nullable', 'string', 'max:255'],
            'official_email' => ['nullable', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'account_number_for_payment' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'file', 'max:15360'],
        ]);

        $entries = collect($clientRequest->quotation_entries ?? []);
        $selected = $entries->firstWhere('slot', (int) $data['approved_quotation_index']) ?? [];
        $vendorSnapshot = $selected['vendor_snapshot'] ?? null;
        $vendorId = $selected['vendor_id'] ?? null;

        if (!empty($selected['subject_to_approval']) && empty($vendorId) && $request->boolean('create_vendor')) {
            $payload = [
                'company_name' => $data['company_name'] ?: ($selected['company_name'] ?? null),
                'ssm_number' => $data['ssm_number'] ?? null,
                'office_address' => $data['office_address'] ?? null,
                'phone_number' => $data['phone_number'] ?? null,
                'fax_number' => $data['fax_number'] ?? null,
                'official_email' => $data['official_email'] ?? null,
                'contact_person' => $data['contact_person'] ?? null,
                'bank' => $data['bank'] ?? null,
                'account_number_for_payment' => $data['account_number_for_payment'] ?? null,
            ];
            if (blank($payload['company_name'])) {
                return back()->withErrors(['company_name' => 'Company name is required for subject to approval vendor registration.'])->withInput();
            }
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $payload['document_path'] = $file->store('vendor-documents', 'public');
                $payload['document_original_name'] = $file->getClientOriginalName();
            }
            $vendor = Vendor::create($payload);
            $vendorId = $vendor->id;
            $vendorSnapshot = [
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
            ];
        }

        try {
            $entries = $entries->map(function ($entry, $index) use ($data, $vendorId, $vendorSnapshot) {
                $slot = $entry['slot'] ?? ($index + 1);
                if ($slot === (int) $data['approved_quotation_index']) {
                    if (!empty($entry['file']['path'])) {
                        $entry['source_file'] = $entry['file'];
                        $entry['file'] = $this->quotationSignatureService->embed($entry['file']['path'], $data['approval_signature']);
                    }
                    $entry['admin_signature'] = $data['approval_signature'];
                    $entry['admin_signed_at'] = now('Asia/Kuala_Lumpur')->toDateTimeString();
                    if ($vendorId) {
                        $entry['vendor_id'] = $vendorId;
                        $entry['vendor_snapshot'] = $vendorSnapshot;
                        $entry['company_name'] = data_get($vendorSnapshot, 'company_name', $entry['company_name'] ?? null);
                    }
                }
                return $entry;
            })->values()->all();
        } catch (\Throwable $e) {
            return back()->withErrors(['approval_signature' => $e->getMessage()])->withInput();
        }

        $clientRequest->update([
            'quotation_entries' => $entries,
            'approved_quotation_index' => (int) $data['approved_quotation_index'],
            'quotation_return_remark' => null,
            'status' => ClientRequest::STATUS_APPROVED,
        ]);

        return back()->with('success', 'Quotation approved successfully. Signature has been embedded into the approved quotation file.');
    }

    public function returnQuotation(Request $request, ClientRequest $clientRequest)
    {
        abort_if($request->user()?->isViewer(), 403, 'Viewer is view only.');

        $request->validate([
            'quotation_return_remark' => ['required', 'string'],
        ]);

        $entries = collect($clientRequest->quotation_entries ?? [])->map(function ($entry) {
            unset($entry['approved'], $entry['admin_signature'], $entry['admin_signed_at']);
            return $entry;
        })->values()->all();

        $clientRequest->update([
            'quotation_entries' => $entries,
            'quotation_return_remark' => $request->string('quotation_return_remark')->toString(),
            'approved_quotation_index' => null,
            'status' => ClientRequest::STATUS_UNDER_REVIEW,
        ]);

        return back()->with('success', 'Quotation returned to technician for rework.');
    }

    private function feedbackSections(): array
    {
        return [
            'reliability' => ['title' => 'Section 1 : Reliability', 'questions' => [
                'q1' => 'The department consistently delivers services without significant disruptions.',
                'q2' => 'Processes are reliable and meet expected timelines.',
            ]],
            'accessibility' => ['title' => 'Section 2 : Accessibility', 'questions' => [
                'q1' => "The department's services are easy to access when needed.",
                'q2' => "Information about the department's processes is readily available and clear.",
            ]],
            'responsiveness' => ['title' => 'Section 3 : Responsiveness', 'questions' => [
                'q1' => 'The department responds to service requests promptly.',
                'q2' => 'Follow-up actions are carried out in a timely manner after requests are submitted.',
            ]],
            'effectiveness' => ['title' => 'Section 4 : Effectiveness', 'questions' => [
                'q1' => 'The workflows are efficient and adequately support our team’s operational requirements.',
                'q2' => 'The department’s processes contribute effectively to achieving organizational objectives.',
            ]],
            'communication' => ['title' => 'Section 5 : Quality of Communication', 'questions' => [
                'q1' => 'The department provides regular updates on the status of ongoing services or requests.',
                'q2' => 'Communication from the department is clear, concise, and professional.',
            ]],
            'flexibility' => ['title' => 'Section 6 : Flexibility', 'questions' => [
                'q1' => 'The department is adaptable to changes in priorities or requirements.',
                'q2' => 'Processes can be easily adjusted to address unexpected challenges.',
            ]],
            'support' => ['title' => 'Section 7 : Employee Support', 'questions' => [
                'q1' => 'The department provides adequate guidance and support for understanding workflows.',
                'q2' => 'Feedback provided to the department is acknowledged and acted upon appropriately.',
            ]],
            'satisfaction' => ['title' => 'Section 8 : Overall Satisfaction', 'questions' => [
                'q1' => 'I am satisfied with the overall performance of the infrastructure management department in this year.',
            ]],
        ];
    }

    private function arrangeRelatedRequests(Collection $requests): Collection
    {
        $parents = collect();
        $children = collect();

        foreach ($requests as $request) {
            if ($request->related_request_id) {
                $children->push($request);
            } else {
                $parents->push($request);
            }
        }

        $ordered = collect();
        foreach ($parents as $parent) {
            $parent->setAttribute('is_related_child', false);
            $ordered->push($parent);
            $children->where('related_request_id', $parent->id)->sortByDesc('id')->each(function ($child) use ($ordered) {
                $child->setAttribute('is_related_child', true);
                $ordered->push($child);
            });
        }

        $remainingChildren = $children->filter(fn ($item) => !$ordered->contains('id', $item->id));
        foreach ($remainingChildren as $child) {
            $child->setAttribute('is_related_child', true);
            $ordered->push($child);
        }

        return $ordered;
    }
}

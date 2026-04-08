<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use App\Models\Department;
use App\Models\Location;
use App\Models\RequestType;
use App\Models\User;
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

        $submissions = $query->latest()->paginate(20)->withQueryString();

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
        $items = $this->buildInboxQuery($request, $admin)->latest()->get();
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
        abort_unless(in_array($clientRequest->user->sub_role, $admin->handledClientRoles(), true), 403);

        return view('admin.submissions.print', [
            'submission' => $clientRequest->load(['user', 'requestType.questions.options', 'location', 'department', 'relatedRequest', 'assignedTechnician']),
            'printMode' => (bool) $request->boolean('print'),
            'feedbackSections' => $this->feedbackSections(),
        ]);
    }

    private function buildInboxQuery(Request $request, User $admin)
    {
        $query = ClientRequest::with(['user', 'requestType', 'location', 'department', 'relatedRequest', 'assignedTechnician'])
            ->whereHas('user', fn ($query) => $query->whereIn('sub_role', $admin->handledClientRoles()));

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
                $query->whereNotNull('finance_completed_at');
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
        abort_unless(in_array($clientRequest->user->sub_role, $admin->handledClientRoles(), true), 403);

        return view('admin.submissions.show', [
            'submission' => $clientRequest->load(['user', 'requestType.questions.options', 'location', 'department', 'relatedRequest', 'assignedTechnician']),
            'technicians' => User::where('role', User::ROLE_TECHNICIAN)->select(['id', 'name'])->orderBy('name')->get(),
        ]);
    }

    public function reviewDecision(Request $request, ClientRequest $clientRequest)
    {
        abort_if($request->user()?->isViewer(), 403, 'Viewer is view only.');

        $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'admin_approval_remark' => ['nullable', 'string'],
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

        $clientRequest->update([
            'admin_approval_status' => 'approved',
            'admin_approval_remark' => null,
            'admin_approved_at' => now('Asia/Kuala_Lumpur'),
            'status' => ClientRequest::STATUS_UNDER_REVIEW,
        ]);

        $this->communicationService->notify($clientRequest->fresh(['user','requestType','assignedTechnician']), 'admin_approved');

        return back()->with('success', 'Request approved. You can now assign a technician.');
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
        ]);

        try {
            $entries = collect($clientRequest->quotation_entries ?? [])->map(function ($entry, $index) use ($data) {
                $slot = $entry['slot'] ?? ($index + 1);
                if ($slot === (int) $data['approved_quotation_index']) {
                    if (!empty($entry['file']['path'])) {
                        $entry['source_file'] = $entry['file'];
                        $entry['file'] = $this->quotationSignatureService->embed($entry['file']['path'], $data['approval_signature']);
                    }

                    $entry['admin_signature'] = $data['approval_signature'];
                    $entry['admin_signed_at'] = now('Asia/Kuala_Lumpur')->toDateTimeString();
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

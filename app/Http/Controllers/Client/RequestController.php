<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use App\Models\Department;
use App\Models\Location;
use App\Models\RequestQuestion;
use App\Models\RequestType;
use App\Models\TaskTitle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $editingRequest = null;

        if ($request->filled('edit')) {
            $editingRequest = ClientRequest::with(['requestType.questions.options'])
                ->visibleToClientEmail($user)
                ->where('status', ClientRequest::STATUS_RETURNED)
                ->find($request->integer('edit'));
        }

        $requests = ClientRequest::with(['requestType', 'location', 'department', 'relatedRequest', 'assignedTechnician'])
            ->visibleToClientEmail($user)
            ->latest()
            ->get();

        $activeTab = $request->query('tab', 'new');
        if (!in_array($activeTab, ['new', 'related'], true)) {
            $activeTab = 'new';
        }
        if ($editingRequest) {
            $activeTab = 'new';
        }

        $showFormPage = $request->boolean('form') || (bool) $editingRequest || (bool) $request->query('related_source');

        $relatedSourceRequests = $requests->filter(function (ClientRequest $item) {
            return data_get($item->inspect_data, 'add_related_job')
                && in_array($item->status, [ClientRequest::STATUS_WORK_IN_PROGRESS, ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW, ClientRequest::STATUS_COMPLETED, ClientRequest::STATUS_CLIENT_RETURNED], true);
        })->values()->map(function (ClientRequest $item) use ($requests) {
            $latestRelatedSubmission = $requests->where('related_request_id', $item->id)->sortByDesc('created_at')->first();
            $item->latest_related_submission = $latestRelatedSubmission;
            return $item;
        })->values();

        $relatedSourceRequest = null;
        if (!$editingRequest && $request->filled('related_source')) {
            $relatedSourceRequest = $relatedSourceRequests->firstWhere('id', $request->integer('related_source'));
            if ($relatedSourceRequest) {
                $activeTab = 'related';
            }
        }

        return view('client.requests.index', [
            'user' => $user,
            'locations' => $this->locationsFor($user),
            'departments' => $user->sub_role === User::CLIENT_HQ ? Department::where('is_active', true)->orderBy('name')->get() : collect(),
            'requestTypes' => RequestType::with('questions.options')
                ->visibleToClientRole($user->sub_role)
                ->where('is_active', true)
                ->get(),
            'taskTitles' => TaskTitle::where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'requests' => $requests,
            'editingRequest' => $editingRequest,
            'activeTab' => $activeTab,
            'relatedSourceRequests' => $relatedSourceRequests,
            'relatedSourceRequest' => $relatedSourceRequest,
            'feedbackSections' => $this->feedbackSections(),
            'showFormPage' => $showFormPage,
        ]);
    }


    public function show(ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->belongsToClient(Auth::user()), 403);

        return view('client.requests.show', [
            'requestItem' => $clientRequest->load(['user', 'requestType.questions.options', 'location', 'department', 'relatedRequest', 'assignedTechnician']),
            'feedbackSections' => $this->feedbackSections(),
            'taskTitles' => TaskTitle::where('is_active', true)->orderBy('title')->get(['id', 'title']),
        ]);
    }



    public function reportIndex(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isSsu(), 403);

        $allowedLocations = $this->locationsFor($user);
        $allowedLocationIds = $allowedLocations->pluck('id')->filter()->values();

        $query = ClientRequest::with(['location', 'requestType', 'department', 'assignedTechnician', 'relatedRequest'])
            ->whereIn('location_id', $allowedLocationIds);

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }
        if ($request->filled('status')) {
            $status = (string) $request->input('status');

            if ($status === ClientRequest::STATUS_COMPLETED) {
                $query->whereNotNull('finance_completed_at');
            } elseif ($status === ClientRequest::STATUS_FINANCE_PENDING) {
                $query->whereNotNull('technician_completed_at')->whereNull('finance_completed_at')->whereNotNull('approved_quotation_index');
            } else {
                $query->where('status', $status);
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $items = $query->latest()->get();

        return view('client.reports.index', [
            'items' => $items,
            'locations' => $allowedLocations,
            'filters' => $request->all(),
            'statusOptions' => ClientRequest::adminVisibleStatusOptions(),
        ]);
    }

    public function store(Request $request)
    {
        return $this->saveRequest($request, null);
    }

    public function update(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->belongsToClient(Auth::user()), 403);
        abort_unless($clientRequest->status === ClientRequest::STATUS_RETURNED, 403);

        return $this->saveRequest($request, $clientRequest);
    }

    public function submitFeedback(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->belongsToClient(Auth::user()), 403);
        abort_unless($clientRequest->status === ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW, 403);

        if ($request->boolean('agree_all')) {
            $validated = $request->validate([
                'agree_all_scale' => ['required', Rule::in(['1', '2', '3', '4', '5'])],
                'agree_all_confirmed' => ['accepted'],
                'additional_comments' => ['nullable', 'string'],
            ]);

            $scale = (int) $validated['agree_all_scale'];
            $ratings = [];
            foreach ($this->feedbackSections() as $sectionKey => $section) {
                foreach ($section['questions'] as $questionKey => $questionText) {
                    data_set($ratings, "$sectionKey.$questionKey", $scale);
                }
            }

            $clientRequest->update([
                'feedback' => [
                    'ratings' => $ratings,
                    'additional_comments' => $validated['additional_comments'] ?? '-',
                    'submission_mode' => 'agree_all',
                    'agree_all_scale' => $scale,
                ],
                'customer_review_submitted_at' => now(),
                'status' => ClientRequest::STATUS_COMPLETED,
            ]);

            return back()->with('success', 'Agree All feedback submitted successfully based on the selected scale. Job marked as completed.');
        }

        $rules = ['additional_comments' => ['nullable', 'string']];
        foreach ($this->feedbackSections() as $sectionKey => $section) {
            foreach ($section['questions'] as $questionKey => $questionText) {
                $rules["ratings.$sectionKey.$questionKey"] = ['required', Rule::in(['1', '2', '3', '4', '5'])];
            }
        }

        $validated = $request->validate($rules);

        $clientRequest->update([
            'feedback' => [
                'ratings' => $validated['ratings'] ?? [],
                'additional_comments' => $validated['additional_comments'] ?? '-',
                'submission_mode' => 'manual',
            ],
            'customer_review_submitted_at' => now(),
            'status' => ClientRequest::STATUS_COMPLETED,
        ]);

        return back()->with('success', 'Feedback submitted successfully. Job marked as completed.');
    }

    private function saveRequest(Request $request, ?ClientRequest $clientRequest)
    {
        /** @var User $user */
        $user = Auth::user();
        $requestType = RequestType::with('questions.options')
            ->visibleToClientRole($user->sub_role)
            ->findOrFail($request->integer('request_type_id'));

        $baseRules = [
            'location_id' => ['required', 'exists:locations,id'],
            'department_id' => [$user->sub_role === User::CLIENT_HQ ? 'required' : 'nullable', 'exists:departments,id'],
            'request_type_id' => ['required', 'exists:request_types,id'],
            'related_request_id' => ['nullable', 'exists:client_requests,id'],
            'related_source_id' => ['nullable', 'exists:client_requests,id'],
            'urgency_level' => [$requestType->urgency_enabled ? 'required' : 'nullable', Rule::in(['1', '2', '3'])],
            'attachments' => [$requestType->attachment_required && !$clientRequest?->attachments ? 'required' : 'nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];

        $dynamicRules = [];
        foreach ($requestType->questions as $question) {
            $key = 'answers.' . $question->id;
            if ($question->question_type === RequestQuestion::TYPE_REMARK) {
                $dynamicRules[$key] = [$question->is_required ? 'required' : 'nullable', 'string'];
            } elseif (in_array($question->question_type, [RequestQuestion::TYPE_RADIO, RequestQuestion::TYPE_TASK_TITLE], true)) {
                $dynamicRules[$key . '.value'] = [$question->is_required ? 'required' : 'nullable', 'string'];
                $dynamicRules[$key . '.other'] = ['nullable', 'string'];
            } elseif ($question->question_type === RequestQuestion::TYPE_DATE_RANGE) {
                $dynamicRules[$key . '.start'] = [$question->is_required ? 'required' : 'nullable', 'date'];
                $dynamicRules[$key . '.end'] = [$question->is_required ? 'required' : 'nullable', 'date', 'after_or_equal:' . $key . '.start'];
            } else {
                $dynamicRules[$key] = [$question->is_required ? 'required' : 'nullable', 'array'];
                $dynamicRules[$key . '.*.value'] = ['required', 'string'];
                $dynamicRules[$key . '.*.other'] = ['nullable', 'string'];
            }
        }

        $request->validate($baseRules + $dynamicRules);

        $relatedSource = null;
        if (!$clientRequest && $request->filled('related_source_id')) {
            $relatedSource = ClientRequest::with(['requestType.questions.options'])
                ->visibleToClientEmail($user)
                ->find($request->integer('related_source_id'));
        }

        $answers = [];
        foreach ($requestType->questions as $question) {
            $value = $request->input('answers.' . $question->id);

            if ($relatedSource && $question->question_type !== RequestQuestion::TYPE_REMARK && $value === null) {
                $value = data_get($relatedSource->answers, $question->id);
            }

            $answers[$question->id] = $value;
        }

        $attachments = $clientRequest?->attachments ?? [];
        foreach ($request->file('attachments', []) as $file) {
            $attachments[] = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('client-request-attachments', 'public'),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $relatedId = $request->integer('related_request_id') ?: null;
        if ($relatedSource) {
            $relatedId = $relatedSource->id;
        }

        $resubmittedStatus = $clientRequest ? ClientRequest::STATUS_CLIENT_RETURNED : ClientRequest::STATUS_UNDER_REVIEW;

        $payload = [
            'user_id' => $user->id,
            'request_type_id' => $relatedSource?->request_type_id ?? $requestType->id,
            'location_id' => $relatedSource?->location_id ?? $request->integer('location_id'),
            'department_id' => $user->sub_role === User::CLIENT_HQ ? ($relatedSource?->department_id ?? $request->integer('department_id')) : null,
            'full_name' => $user->name,
            'phone_number' => $user->phone_number,
            'urgency_level' => $requestType->urgency_enabled ? $request->integer('urgency_level') : null,
            'answers' => $answers,
            'attachments' => $attachments,
            'related_request_id' => $relatedId,
            'status' => $resubmittedStatus,
            'technician_return_remark' => null,
        ];

        if ($clientRequest) {
            $clientRequest->update($payload);
            $message = 'Request resubmitted successfully.';
        } else {
            ClientRequest::create($payload);
            $message = $relatedSource ? 'Related request submitted successfully.' : 'Request submitted successfully.';
        }

        return redirect()->route('client.requests.index')->with('success', $message);
    }

    private function locationsFor(User $user)
    {
        $type = $user->sub_role === User::CLIENT_HQ ? Location::TYPE_HQ : Location::TYPE_BRANCH;

        $query = Location::where('type', $type)
            ->where('is_active', true);

        if ($user->isSsu() && !$user->isMasterSsu()) {
            $branchIds = $user->assignedBranchIds();
            if (!empty($branchIds)) {
                $query->whereIn('id', $branchIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->orderBy('name')->get();
    }

    public function dashboardListRequests(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isSsu(), 403);

        $query = ClientRequest::with(['user', 'requestType', 'location', 'department', 'relatedRequest', 'assignedTechnician'])
            ->where(function ($query) {
                $query->whereHas('user', fn ($q) => $q->whereIn('sub_role', [User::CLIENT_KINDERGARTEN, User::CLIENT_SSU, User::CLIENT_MASTER_SSU]))
                    ->orWhere(function ($or) {
                        $or->whereNull('user_id')
                            ->where('inspect_data->legacy_client_role', User::CLIENT_KINDERGARTEN);
                    });
            });

        if (!$user->isMasterSsu()) {
            $branchIds = $user->assignedBranchIds();
            if (!empty($branchIds)) {
                $query->whereIn('location_id', $branchIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $items = $query->latest()->paginate(20)->withQueryString();

        return view('client.requests.dashboard-list', [
            'items' => $items,
            'user' => $user,
        ]);
    }

    public function dashboardListShow(ClientRequest $clientRequest)
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isSsu(), 403);
        $clientSubRole = $clientRequest->user?->sub_role ?? data_get($clientRequest->inspect_data, 'legacy_client_role');
        abort_unless(in_array($clientSubRole, [User::CLIENT_KINDERGARTEN, User::CLIENT_SSU, User::CLIENT_MASTER_SSU], true), 403);

        if (!$user->isMasterSsu()) {
            abort_unless(in_array((int) $clientRequest->location_id, $user->assignedBranchIds(), true), 403);
        }

        return view('client.requests.show', [
            'requestItem' => $clientRequest->load(['user', 'requestType.questions.options', 'location', 'department', 'relatedRequest', 'assignedTechnician']),
            'feedbackSections' => $this->feedbackSections(),
            'taskTitles' => TaskTitle::where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'monitorOnly' => true,
        ]);
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
}

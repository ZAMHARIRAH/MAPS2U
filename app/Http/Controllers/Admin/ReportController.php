<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function jobRequests(Request $request)
    {
        $admin = Auth::user();
        $query = ClientRequest::with(['user', 'location', 'department', 'requestType'])
            ->whereHas('user', fn ($q) => $q->whereIn('sub_role', $admin->handledClientRoles()));

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }
        if ($request->filled('client_name')) {
            $query->where('full_name', 'like', '%'.$request->string('client_name')->toString().'%');
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $items = $query->latest()->get();
        $clientRole = $admin->primaryHandledClientRole();
        $locationType = $clientRole === User::CLIENT_HQ ? Location::TYPE_HQ : Location::TYPE_BRANCH;

        return view('admin.reports.job-requests', [
            'items' => $items,
            'locations' => Location::where('type', $locationType)->orderBy('name')->get(),
            'clientRole' => $clientRole,
            'filters' => $request->all(),
        ]);
    }

    public function technicians(Request $request)
    {
        $items = $this->buildTechnicianReportQuery($request)->latest()->get();
        $reportItems = $items->filter(fn (ClientRequest $item) => (bool) $item->technician_completed_at && !empty($item->customer_service_report))->values();

        return view('admin.reports.technicians', [
            'items' => $items,
            'reportItems' => $reportItems,
            'technicians' => User::where('role', User::ROLE_TECHNICIAN)->orderBy('name')->get(),
            'filters' => $request->all(),
        ]);
    }

    public function mergedTechnicianDocuments(Request $request)
    {
        $items = $this->buildTechnicianReportQuery($request)
            ->latest()
            ->get()
            ->filter(fn (ClientRequest $item) => (bool) $item->technician_completed_at && !empty($item->customer_service_report))
            ->values();

        return view('admin.reports.technician-merged', [
            'items' => $items,
            'printMode' => (bool) $request->boolean('print'),
            'filters' => $request->all(),
            'feedbackSections' => $this->feedbackSections(),
        ]);
    }

    public function technicianJobReport(Request $request, ClientRequest $clientRequest)
    {
        $job = $this->loadReportRequest($clientRequest);
        abort_unless(!empty($job->customer_service_report), 404);

        return view('technician.requests.report', [
            'job' => $job,
            'printMode' => (bool) $request->boolean('print'),
            'backRoute' => route('admin.reports.technician'),
            'printRoute' => route('admin.reports.technician.job-report', [$job, 'print' => 1]),
        ]);
    }

    public function clientFeedbackReport(Request $request, ClientRequest $clientRequest)
    {
        $job = $this->loadReportRequest($clientRequest);
        abort_unless(!empty($job->feedback), 404);

        return view('admin.reports.client-feedback', [
            'job' => $job,
            'printMode' => (bool) $request->boolean('print'),
            'backRoute' => route('admin.reports.technician'),
            'printRoute' => route('admin.reports.technician.feedback-report', [$job, 'print' => 1]),
            'feedbackSections' => $this->feedbackSections(),
        ]);
    }


    private function buildTechnicianReportQuery(Request $request)
    {
        $query = ClientRequest::with(['assignedTechnician', 'requestType', 'location', 'department', 'relatedRequest'])
            ->whereNotNull('assigned_technician_id');

        if ($request->filled('technician_id')) {
            $query->where('assigned_technician_id', $request->integer('technician_id'));
        }
        if ($request->filled('month')) {
            [$year, $month] = explode('-', $request->input('month'));
            $query->whereYear('created_at', (int) $year)->whereMonth('created_at', (int) $month);
        }
        if ($request->filled('task')) {
            $query->whereHas('requestType', fn ($q) => $q->where('name', 'like', '%'.$request->string('task')->toString().'%'));
        }

        return $query;
    }

    private function loadReportRequest(ClientRequest $clientRequest): ClientRequest
    {
        return ClientRequest::with([
            'assignedTechnician',
            'requestType.questions.options',
            'location',
            'department',
            'relatedRequest',
            'user',
        ])->findOrFail($clientRequest->id);
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

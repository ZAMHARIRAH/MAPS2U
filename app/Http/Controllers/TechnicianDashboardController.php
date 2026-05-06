<?php

namespace App\Http\Controllers;

use App\Models\ClientRequest;
use Illuminate\Support\Facades\Auth;

class TechnicianDashboardController extends Controller
{
    public function index()
    {
        $baseQuery = ClientRequest::with(['requestType', 'location', 'relatedRequest'])
            ->where('assigned_technician_id', Auth::id());

        $jobs = (clone $baseQuery)->latest()->get();
        $urgencyFilter = request('urgency');
        $urgencyLevels = ['low' => 1, 'medium' => 2, 'high' => 3];

        $activeQuery = (clone $baseQuery)
            ->whereNull('technician_completed_at')
            ->where('status', '!=', ClientRequest::STATUS_COMPLETED);

        if (array_key_exists($urgencyFilter, $urgencyLevels)) {
            $activeQuery->where('urgency_level', $urgencyLevels[$urgencyFilter]);
        }

        $urgencyCounts = [
            'low' => (clone $baseQuery)->whereNull('technician_completed_at')->where('status', '!=', ClientRequest::STATUS_COMPLETED)->where('urgency_level', 1)->count(),
            'medium' => (clone $baseQuery)->whereNull('technician_completed_at')->where('status', '!=', ClientRequest::STATUS_COMPLETED)->where('urgency_level', 2)->count(),
            'high' => (clone $baseQuery)->whereNull('technician_completed_at')->where('status', '!=', ClientRequest::STATUS_COMPLETED)->where('urgency_level', 3)->count(),
        ];

        $activeJobs = $activeQuery
            ->orderByRaw("CASE
                WHEN status = ? THEN 0
                WHEN status = ? THEN 1
                WHEN status = ? THEN 2
                ELSE 3
            END", [
                ClientRequest::STATUS_PENDING_APPROVAL,
                ClientRequest::STATUS_UNDER_REVIEW,
                ClientRequest::STATUS_WORK_IN_PROGRESS,
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'jobs_page')
            ->withQueryString();

        return view('dashboards.technician', [
            'user' => Auth::user(),
            'jobCount' => $jobs->count(),
            'underReviewCount' => $jobs->where('status', ClientRequest::STATUS_UNDER_REVIEW)->count(),
            'pendingApprovalCount' => $jobs->where('status', ClientRequest::STATUS_PENDING_APPROVAL)->count(),
            'workProgressCount' => $jobs->where('status', ClientRequest::STATUS_WORK_IN_PROGRESS)->count(),
            'completedByTechnicianCount' => $jobs->filter(fn (ClientRequest $job) => (bool) $job->technician_completed_at || $job->status === ClientRequest::STATUS_COMPLETED)->count(),
            'completionPercent' => $jobs->count() > 0 ? round(($jobs->filter(fn (ClientRequest $job) => (bool) $job->technician_completed_at || $job->status === ClientRequest::STATUS_COMPLETED)->count() / $jobs->count()) * 100) : 0,
            'pendingCount' => $jobs->filter(fn (ClientRequest $job) => !$job->technician_completed_at && $job->status !== ClientRequest::STATUS_COMPLETED)->count(),
            'urgencyFilter' => $urgencyFilter,
            'urgencyCounts' => $urgencyCounts,
            'quickJobs' => $activeJobs,
        ]);
    }
}

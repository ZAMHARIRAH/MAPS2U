<?php

namespace App\Http\Controllers;

use App\Models\ClientRequest;
use Illuminate\Support\Facades\Auth;

class TechnicianDashboardController extends Controller
{
    public function index()
    {
        $jobs = ClientRequest::with(['requestType', 'location', 'relatedRequest'])
            ->where('assigned_technician_id', Auth::id())
            ->orderByRaw('CASE WHEN technician_completed_at IS NULL AND status != ? THEN 0 ELSE 1 END', [ClientRequest::STATUS_COMPLETED])
            ->latest()
            ->get();

        $activeJobs = $jobs->filter(fn (ClientRequest $job) => !$job->technician_completed_at && $job->status !== ClientRequest::STATUS_COMPLETED)->values();

        return view('dashboards.technician', [
            'user' => Auth::user(),
            'jobCount' => $jobs->count(),
            'underReviewCount' => $jobs->where('status', ClientRequest::STATUS_UNDER_REVIEW)->count(),
            'pendingApprovalCount' => $jobs->where('status', ClientRequest::STATUS_PENDING_APPROVAL)->count(),
            'workProgressCount' => $jobs->where('status', ClientRequest::STATUS_WORK_IN_PROGRESS)->count(),
            'completedByTechnicianCount' => $jobs->filter(fn (ClientRequest $job) => (bool) $job->technician_completed_at || $job->status === ClientRequest::STATUS_COMPLETED)->count(),
            'quickJobs' => $activeJobs->take(8),
        ]);
    }
}

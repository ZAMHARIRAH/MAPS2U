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
            ->latest()
            ->get();

        $activeJobs = $jobs->filter(fn (ClientRequest $job) => !$job->technician_completed_at)->values();

        return view('dashboards.technician', [
            'user' => Auth::user(),
            'jobCount' => $jobs->count(),
            'underReviewCount' => $jobs->where('status', ClientRequest::STATUS_UNDER_REVIEW)->count(),
            'pendingApprovalCount' => $jobs->where('status', ClientRequest::STATUS_PENDING_APPROVAL)->count(),
            'workProgressCount' => $jobs->where('status', ClientRequest::STATUS_WORK_IN_PROGRESS)->count(),
            'completedByTechnicianCount' => $jobs->filter(fn (ClientRequest $job) => (bool) $job->technician_completed_at)->count(),
            'quickJobs' => $activeJobs->take(8),
        ]);
    }
}

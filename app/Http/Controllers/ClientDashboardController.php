<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ClientRequest;
use App\Models\RequestType;
use Illuminate\Support\Facades\Auth;

class ClientDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $urgencyFilter = request('urgency');
        $urgencyLevels = ['low' => 1, 'medium' => 2, 'high' => 3];

        $latestQuery = ClientRequest::with(['requestType', 'relatedRequest', 'assignedTechnician', 'department', 'location'])
            ->visibleToClientEmail($user)
            ->where('status', '!=', ClientRequest::STATUS_COMPLETED);

        if (array_key_exists($urgencyFilter, $urgencyLevels)) {
            $latestQuery->where('urgency_level', $urgencyLevels[$urgencyFilter]);
        }

        $latestRequests = $latestQuery
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
            ->paginate(20, ['*'], 'requests_page')
            ->withQueryString();
        $relatedQueue = $latestRequests->getCollection()->filter(function (ClientRequest $request) {
            return data_get($request->inspect_data, 'add_related_job')
                && !in_array($request->status, [ClientRequest::STATUS_REJECTED], true);
        })->values();

        $requestTypeCount = RequestType::visibleToClientRole($user->sub_role)
            ->where('is_active', true)
            ->count();

        $allRequests = ClientRequest::visibleToClientEmail($user)
            ->get();

        $countableRequests = $allRequests->reject(fn (ClientRequest $request) => $request->status === ClientRequest::STATUS_REJECTED)->values();
        $activeRequestsForUrgency = $countableRequests->filter(fn (ClientRequest $request) => !in_array($request->status, [ClientRequest::STATUS_COMPLETED, ClientRequest::STATUS_REJECTED], true));

        return view('dashboards.client', [
            'user' => $user,
            'requestTypeCount' => $requestTypeCount,
            'myRequestCount' => $countableRequests->count(),
            'latestRequests' => $latestRequests,
            'returnedCount' => $allRequests->where('status', ClientRequest::STATUS_RETURNED)->count(),
            'completedCount' => $countableRequests->where('status', ClientRequest::STATUS_COMPLETED)->count(),
            'pendingCount' => $countableRequests->filter(fn (ClientRequest $request) => !in_array($request->status, [ClientRequest::STATUS_COMPLETED, ClientRequest::STATUS_REJECTED], true))->count(),
            'completionPercent' => $countableRequests->count() > 0 ? round(($countableRequests->where('status', ClientRequest::STATUS_COMPLETED)->count() / $countableRequests->count()) * 100) : 0,
            'urgencyFilter' => $urgencyFilter,
            'urgencyCounts' => [
                'low' => $activeRequestsForUrgency->where('urgency_level', 1)->count(),
                'medium' => $activeRequestsForUrgency->where('urgency_level', 2)->count(),
                'high' => $activeRequestsForUrgency->where('urgency_level', 3)->count(),
            ],
            'needActionCount' => $allRequests->whereIn('status', [
                ClientRequest::STATUS_RETURNED,
                ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW,
            ])->count(),
            'scheduledCount' => $allRequests->filter(fn (ClientRequest $request) => $request->upcomingScheduleDays() !== null)->count(),
            'upcomingSchedules' => $latestRequests->getCollection()->filter(fn (ClientRequest $request) => $request->upcomingScheduleDays() !== null),
            'relatedQueue' => $relatedQueue,
            'announcements' => Announcement::active()->ordered()->get(),
        ]);
    }
}

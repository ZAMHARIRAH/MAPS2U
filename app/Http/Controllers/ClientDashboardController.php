<?php

namespace App\Http\Controllers;

use App\Models\ClientRequest;
use App\Models\RequestType;
use Illuminate\Support\Facades\Auth;

class ClientDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $latestRequests = ClientRequest::with(['requestType', 'relatedRequest', 'assignedTechnician', 'department', 'location'])
            ->where('user_id', $user->id)
            ->latest()
            ->take(12)
            ->get();
        $relatedQueue = $latestRequests->filter(function (ClientRequest $request) {
            return data_get($request->inspect_data, 'add_related_job')
                && !in_array($request->status, [ClientRequest::STATUS_REJECTED], true);
        })->values();

        $requestTypeCount = RequestType::visibleToClientRole($user->sub_role)
            ->where('is_active', true)
            ->count();

        $allRequests = ClientRequest::where('user_id', $user->id)->get();

        $countableRequests = $allRequests->reject(fn (ClientRequest $request) => $request->status === ClientRequest::STATUS_REJECTED)->values();

        return view('dashboards.client', [
            'user' => $user,
            'requestTypeCount' => $requestTypeCount,
            'myRequestCount' => $countableRequests->count(),
            'latestRequests' => $latestRequests,
            'returnedCount' => $allRequests->where('status', ClientRequest::STATUS_RETURNED)->count(),
            'completedCount' => $countableRequests->where('status', ClientRequest::STATUS_COMPLETED)->count(),
            'pendingCount' => $countableRequests->filter(fn (ClientRequest $request) => !in_array($request->status, [ClientRequest::STATUS_COMPLETED, ClientRequest::STATUS_REJECTED], true))->count(),
            'needActionCount' => $allRequests->whereIn('status', [
                ClientRequest::STATUS_RETURNED,
                ClientRequest::STATUS_PENDING_CUSTOMER_REVIEW,
            ])->count(),
            'scheduledCount' => $allRequests->filter(fn (ClientRequest $request) => $request->upcomingScheduleDays() !== null)->count(),
            'upcomingSchedules' => $latestRequests->filter(fn (ClientRequest $request) => $request->upcomingScheduleDays() !== null),
            'relatedQueue' => $relatedQueue,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    public function index()
    {
        /** @var User $admin */
        $admin = Auth::user();
        $handledRoles = $admin->handledClientRoles();

        $requests = ClientRequest::with(['user', 'requestType', 'location', 'department', 'relatedRequest', 'assignedTechnician'])
            ->whereHas('user', fn ($query) => $query->whereIn('sub_role', $handledRoles))
            ->latest()
            ->get();

        $countableRequests = $requests->reject(fn (ClientRequest $request) => $request->status === ClientRequest::STATUS_REJECTED)->values();
        $completedCount = $countableRequests->filter(fn (ClientRequest $request) => (bool) $request->finance_completed_at)->count();
        $pendingCount = $countableRequests->filter(fn (ClientRequest $request) => !$request->finance_completed_at)->count();
        $recentRequests = $countableRequests
            ->reject(fn (ClientRequest $request) => in_array($request->adminWorkflowLabel(), ['Completed', ClientRequest::STATUS_REJECTED], true))
            ->values();
        $financeAlerts = $requests
            ->filter(fn (ClientRequest $request) => $request->hasFinancePending() && !$request->finance_completed_at)
            ->take(6)
            ->values();

        return view('dashboards.admin', [
            'admin' => $admin,
            'totalTask' => $countableRequests->count(),
            'pendingCount' => $pendingCount,
            'completedCount' => $completedCount,
            'completionPercent' => $countableRequests->count() > 0 ? round(($completedCount / $countableRequests->count()) * 100) : 0,
            'recentRequests' => $this->arrangeRelatedRequests($recentRequests->take(18)),
            'financeAlerts' => $financeAlerts,
        ]);
    }

    public function accounts()
    {
        /** @var User $admin */
        $admin = Auth::user();

        return view('admin.accounts.index', [
            'admin' => $admin,
            'clients' => User::where('role', User::ROLE_CLIENT)->whereIn('sub_role', $admin->handledClientRoles())->orderBy('name')->get(),
            'technicians' => User::where('role', User::ROLE_TECHNICIAN)->orderBy('name')->get(),
        ]);
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

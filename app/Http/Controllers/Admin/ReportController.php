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

        return view('admin.reports.job-requests', [
            'items' => $items,
            'locations' => Location::orderBy('name')->get(),
            'filters' => $request->all(),
        ]);
    }

    public function technicians(Request $request)
    {
        $query = ClientRequest::with(['assignedTechnician', 'requestType', 'location'])
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

        $items = $query->latest()->get();

        return view('admin.reports.technicians', [
            'items' => $items,
            'technicians' => User::where('role', User::ROLE_TECHNICIAN)->orderBy('name')->get(),
            'filters' => $request->all(),
        ]);
    }
}

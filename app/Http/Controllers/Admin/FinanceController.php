<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $admin */
        $admin = $request->user();

        $allRequests = ClientRequest::with(['user', 'requestType', 'assignedTechnician'])
            ->whereNotNull('technician_completed_at')
            ->whereHas('user', fn ($query) => $query->whereIn('sub_role', $admin->handledClientRoles()))
            ->latest('technician_completed_at')
            ->get()
            ->filter(fn (ClientRequest $item) => (bool) $item->approvedQuotation())
            ->values();

        $requests = $admin->isViewer() ? $allRequests : $allRequests->whereNull('finance_completed_at')->values();

        return view('admin.finance.index', [
            'requests' => $requests,
            'pendingCount' => $allRequests->whereNull('finance_completed_at')->count(),
            'completedCount' => $allRequests->whereNotNull('finance_completed_at')->count(),
            'isViewer' => $admin->isViewer(),
        ]);
    }

    public function show(Request $request, ClientRequest $clientRequest): View
    {
        /** @var User $admin */
        $admin = $request->user();
        abort_unless(in_array($clientRequest->user->sub_role, $admin->handledClientRoles(), true), 403);
        abort_if($admin->isViewer() && !$clientRequest->finance_completed_at, 403, 'Finance form has not been uploaded yet.');

        $clientRequest->load(['user', 'requestType', 'assignedTechnician']);
        return view('admin.finance.show', ['submission' => $clientRequest, 'isViewer' => $admin->isViewer()]);
    }

    public function store(Request $request, ClientRequest $clientRequest): RedirectResponse
    {
        abort_if($request->user()?->isViewer(), 403, 'Viewer is view only.');

        $data = $request->validate([
            'reference_code' => ['required', 'string', 'max:255'],
            'filled_finance_pdf' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ]);

        $admin = $request->user();
        $existing = $clientRequest->finance_form ?? [];
        $file = $request->file('filled_finance_pdf');
        $pdfPath = 'finance-forms/' . Str::slug($clientRequest->request_code ?: 'request') . '-' . now('Asia/Kuala_Lumpur')->format('YmdHis') . '.pdf';
        Storage::disk('public')->putFileAs('finance-forms', $file, basename($pdfPath));

        $financeForm = array_merge($existing, [
            'reference_code' => $data['reference_code'],
            'filled_pdf_path' => $pdfPath,
            'submitted_by' => $admin->name,
            'submitted_at' => now('Asia/Kuala_Lumpur')->toDateTimeString(),
        ]);

        $clientRequest->update([
            'finance_form' => $financeForm,
            'finance_completed_at' => now('Asia/Kuala_Lumpur'),
        ]);

        return back()->with('success', 'Finance PDF saved successfully. Admin completion has been recorded.');
    }
}

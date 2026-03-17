<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    public function index(): View
    {
        $allRequests = ClientRequest::with(['requestType', 'assignedTechnician'])
            ->whereNotNull('invoice_uploaded_at')
            ->latest('invoice_uploaded_at')
            ->get();
        $requests = $allRequests->whereNull('finance_completed_at')->values();

        return view('admin.finance.index', [
            'requests' => $requests,
            'pendingCount' => $requests->count(),
            'completedCount' => $allRequests->whereNotNull('finance_completed_at')->count(),
        ]);
    }

    public function show(ClientRequest $clientRequest): View
    {
        $clientRequest->load(['requestType', 'assignedTechnician']);
        return view('admin.finance.show', ['submission' => $clientRequest]);
    }

    public function store(Request $request, ClientRequest $clientRequest): RedirectResponse
    {
        $data = $request->validate([
            'reference_code' => ['required', 'string', 'max:255'],
            'filled_finance_pdf' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ]);

        $admin = $request->user();
        $existing = $clientRequest->finance_form ?? [];
        $file = $request->file('filled_finance_pdf');
        $pdfPath = 'finance-forms/' . Str::slug($clientRequest->request_code ?: 'request') . '-' . now()->format('YmdHis') . '.pdf';
        Storage::disk('public')->putFileAs('finance-forms', $file, basename($pdfPath));

        $financeForm = array_merge($existing, [
            'reference_code' => $data['reference_code'],
            'filled_pdf_path' => $pdfPath,
            'submitted_by' => $admin->name,
            'submitted_at' => now()->toDateTimeString(),
        ]);

        $clientRequest->update([
            'finance_form' => $financeForm,
            'finance_completed_at' => now(),
        ]);

        return back()->with('success', 'Finance PDF saved successfully. Admin completion has been recorded.');
    }
}

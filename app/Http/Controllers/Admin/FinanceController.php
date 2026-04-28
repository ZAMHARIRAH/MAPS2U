<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    public function index()
    {
        return $this->renderIndex(false);
    }

    public function mapsIndex()
    {
        abort_unless(Auth::user()?->canOpenMapsFinanceSupport(), 403);

        return $this->renderIndex(true);
    }

    public function show(ClientRequest $clientRequest)
    {
        return $this->renderShow($clientRequest, false);
    }

    public function mapsShow(ClientRequest $clientRequest)
    {
        abort_unless(Auth::user()?->canOpenMapsFinanceSupport(), 403);

        return $this->renderShow($clientRequest, true);
    }

    public function store(Request $request, ClientRequest $clientRequest)
    {
        return $this->saveFinance($request, $clientRequest, false);
    }

    public function mapsStore(Request $request, ClientRequest $clientRequest)
    {
        abort_unless(Auth::user()?->canOpenMapsFinanceSupport(), 403);

        return $this->saveFinance($request, $clientRequest, true);
    }

    protected function renderIndex(bool $mapsScope)
    {
        /** @var User $admin */
        $admin = Auth::user();
        $roles = $this->accessibleRoles($admin, $mapsScope);

        $allRequests = ClientRequest::with(['user', 'requestType', 'assignedTechnician'])
            ->whereHas('user', fn ($query) => $query->whereIn('sub_role', $roles))
            ->whereNotNull('approved_quotation_index')
            ->latest()
            ->get();

        $isViewer = $admin->isViewer();
        $pendingRequests = $allRequests->whereNull('finance_completed_at')->values();
        $completedRequests = $allRequests->whereNotNull('finance_completed_at')->values();
        $requests = $isViewer ? $allRequests : $pendingRequests;

        return view('admin.finance.index', [
            'requests' => $requests,
            'pendingRequests' => $pendingRequests,
            'completedRequests' => $completedRequests,
            'pendingCount' => $pendingRequests->count(),
            'completedCount' => $completedRequests->count(),
            'isViewer' => $isViewer,
            'mapsScope' => $mapsScope,
            'pageTitle' => $mapsScope ? 'Finance MAPS' : 'Finance',
            'pageIntro' => $mapsScope
                ? 'Admin AIM can help Admin MAPS submit finance forms for MAPS job requests. This does not unlock other MAPS admin edit actions.'
                : ($isViewer ? 'Viewer can monitor all HQ Staff and Kindergarten finance submissions in view-only mode.' : 'Signed approved quotation and payment receipt history from technician jobs will appear here for finance processing.'),
        ]);
    }

    protected function renderShow(ClientRequest $clientRequest, bool $mapsScope)
    {
        /** @var User $admin */
        $admin = Auth::user();
        abort_unless(in_array($clientRequest->user->sub_role, $this->accessibleRoles($admin, $mapsScope), true), 403);
        abort_if($admin->isViewer() && !$clientRequest->finance_completed_at, 403, 'Finance form has not been uploaded yet.');

        return view('admin.finance.show', [
            'submission' => $clientRequest,
            'isViewer' => $admin->isViewer(),
            'mapsScope' => $mapsScope,
            'backRoute' => $mapsScope ? 'admin.maps.finance.index' : 'admin.finance.index',
            'storeRoute' => $mapsScope ? 'admin.maps.finance.store' : 'admin.finance.store',
            'pageTitle' => $mapsScope ? 'Finance MAPS Form' : 'Finance Form',
        ]);
    }

    protected function saveFinance(Request $request, ClientRequest $clientRequest, bool $mapsScope)
    {
        /** @var User $admin */
        $admin = Auth::user();
        abort_if($admin->isViewer(), 403, 'Viewer is view only.');
        abort_unless(in_array($clientRequest->user->sub_role, $this->accessibleRoles($admin, $mapsScope), true), 403);

        $data = $request->validate([
            'reference_code' => ['required', 'string', 'max:255'],
            'filled_finance_pdf' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ]);

        $existing = $clientRequest->finance_form ?? [];
        $file = $request->file('filled_finance_pdf');
        $pdfPath = 'finance-forms/' . Str::slug($clientRequest->request_code ?: 'request') . '-' . now('Asia/Kuala_Lumpur')->format('YmdHis') . '.pdf';
        Storage::disk('public')->putFileAs('finance-forms', $file, basename($pdfPath));

        $financeForm = array_merge($existing, [
            'reference_code' => $data['reference_code'],
            'filled_pdf_path' => $pdfPath,
            'approved_amount' => data_get($existing, 'approved_amount', data_get($clientRequest->approvedQuotation(), 'amount')),
            'submitted_by_name' => (string) ($admin->name ?? 'Admin'),
            'submitted_by_role' => $admin->roleLabel(),
            'submitted_at' => now('Asia/Kuala_Lumpur')->toDateTimeString(),
        ]);

        $clientRequest->update([
            'finance_form' => $financeForm,
            'finance_completed_at' => now('Asia/Kuala_Lumpur'),
            'status' => ClientRequest::STATUS_COMPLETED,
        ]);

        return back()->with('success', 'Finance PDF saved successfully. Job has been marked as completed.');
    }

    protected function accessibleRoles(User $admin, bool $mapsScope): array
    {
        if ($mapsScope && $admin->canOpenMapsFinanceSupport()) {
            return [User::CLIENT_KINDERGARTEN];
        }

        return $admin->handledClientRoles();
    }
}

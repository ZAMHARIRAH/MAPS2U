<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\ClientCommunicationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ClientRequestController extends Controller
{
    public function __construct(private readonly ClientCommunicationService $communicationService)
    {
    }
    public function index()
    {
        /** @var User $admin */
        $admin = Auth::user();

        $submissions = ClientRequest::with(['user', 'requestType', 'location', 'department', 'relatedRequest', 'assignedTechnician'])
            ->whereHas('user', fn ($query) => $query->whereIn('sub_role', $admin->handledClientRoles()))
            ->latest()
            ->get();

        return view('admin.submissions.index', [
            'submissions' => $this->arrangeRelatedRequests($submissions),
        ]);
    }

    public function show(ClientRequest $clientRequest)
    {
        /** @var User $admin */
        $admin = Auth::user();
        abort_unless(in_array($clientRequest->user->sub_role, $admin->handledClientRoles(), true), 403);

        return view('admin.submissions.show', [
            'submission' => $clientRequest->load(['user', 'requestType.questions.options', 'location', 'department', 'relatedRequest', 'assignedTechnician']),
            'technicians' => User::where('role', User::ROLE_TECHNICIAN)->orderBy('name')->get(),
        ]);
    }

    public function reviewDecision(Request $request, ClientRequest $clientRequest)
    {
        $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'admin_approval_remark' => ['nullable', 'string'],
        ]);

        if ($request->input('decision') === 'rejected') {
            $request->validate([
                'admin_approval_remark' => ['required', 'string'],
            ]);

            $clientRequest->update([
                'admin_approval_status' => 'rejected',
                'admin_approval_remark' => $request->string('admin_approval_remark')->toString(),
                'admin_approved_at' => now(),
                'status' => ClientRequest::STATUS_REJECTED,
                'assigned_technician_id' => null,
            ]);

            $this->communicationService->notify($clientRequest->fresh(['user','requestType','assignedTechnician']), 'admin_rejected');

            return back()->with('success', 'Request rejected and client can now see the rejection remark.');
        }

        $clientRequest->update([
            'admin_approval_status' => 'approved',
            'admin_approval_remark' => $request->string('admin_approval_remark')->toString() ?: null,
            'admin_approved_at' => now(),
            'status' => ClientRequest::STATUS_UNDER_REVIEW,
        ]);

        $this->communicationService->notify($clientRequest->fresh(['user','requestType','assignedTechnician']), 'admin_approved');

        return back()->with('success', 'Request approved. You can now assign a technician.');
    }

    public function assign(Request $request, ClientRequest $clientRequest)
    {
        abort_unless($clientRequest->admin_approval_status === 'approved', 422, 'Approve the request before assigning a technician.');

        $request->validate([
            'assigned_technician_id' => ['required', 'exists:users,id'],
        ]);

        $technician = User::findOrFail($request->integer('assigned_technician_id'));
        abort_unless($technician->role === User::ROLE_TECHNICIAN, 422);

        $clientRequest->update([
            'assigned_technician_id' => $technician->id,
            'assigned_at' => now(),
            'status' => ClientRequest::STATUS_UNDER_REVIEW,
        ]);

        $this->communicationService->notify($clientRequest->fresh(['user','requestType','assignedTechnician']), 'technician_assigned');

        return back()->with('success', 'Technician assigned successfully.');
    }

    public function updateReview(Request $request, ClientRequest $clientRequest)
    {
        $data = $request->validate([
            'clarification_level' => ['required', 'in:critical,urgent,normal'],
            'repair_channel' => ['required', 'in:in_house_repair,vendor_required'],
            'repair_scale' => ['required', 'in:minor_repair,major_repair'],
            'processing_type' => ['required', 'in:internal,outsource'],
            'visit_site' => ['nullable', 'in:yes,no'],
            'visit_site_remark' => ['nullable', 'string'],
            'visit_site_files' => ['nullable', 'array'],
            'visit_site_files.*' => ['file', 'max:10240'],
        ]);

        $review = $clientRequest->technician_review ?? [];
        $review = array_merge($review, [
            'clarification_level' => $data['clarification_level'],
            'repair_channel' => $data['repair_channel'],
            'repair_scale' => $data['repair_scale'],
            'processing_type' => $data['processing_type'],
            'visit_site' => $data['visit_site'] ?? 'no',
            'visit_site_remark' => $data['visit_site_remark'] ?? null,
        ]);

        $visitFiles = $review['visit_site_files'] ?? [];
        foreach ($request->file('visit_site_files', []) as $file) {
            $visitFiles[] = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('technician-visit-site', 'public'),
                'mime_type' => $file->getClientMimeType(),
            ];
        }
        $review['visit_site_files'] = $visitFiles;

        $clientRequest->update([
            'technician_review' => $review,
            'technician_review_updated_at' => now(),
        ]);

        return back()->with('success', 'Review details updated successfully.');
    }

    public function approveQuotation(Request $request, ClientRequest $clientRequest)
    {
        $request->validate([
            'approved_quotation_index' => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        $clientRequest->update([
            'approved_quotation_index' => $request->integer('approved_quotation_index'),
            'quotation_return_remark' => null,
            'status' => ClientRequest::STATUS_APPROVED,
        ]);

        return back()->with('success', 'Quotation approved successfully.');
    }

    public function returnQuotation(Request $request, ClientRequest $clientRequest)
    {
        $request->validate([
            'quotation_return_remark' => ['required', 'string'],
        ]);

        $clientRequest->update([
            'quotation_return_remark' => $request->string('quotation_return_remark')->toString(),
            'approved_quotation_index' => null,
            'status' => ClientRequest::STATUS_UNDER_REVIEW,
        ]);

        return back()->with('success', 'Quotation returned to technician for rework.');
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

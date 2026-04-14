<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index()
    {
        return view('admin.vendors.index', ['vendors' => Vendor::latest()->get()]);
    }

    public function create()
    {
        return view('admin.vendors.create', ['vendor' => new Vendor()]);
    }

    protected function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'ssm_number' => ['nullable', 'string', 'max:255'],
            'office_address' => ['nullable', 'string', 'max:2000'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'fax_number' => ['nullable', 'string', 'max:255'],
            'official_email' => ['nullable', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'account_number_for_payment' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'file', 'max:15360'],
        ];
    }

    public function store(Request $request)
    {
        Vendor::create($this->extractPayload($request));
        return redirect()->route('admin.vendors.index')->with('success', 'Vendor created successfully.');
    }

    public function edit(Vendor $vendor)
    {
        return view('admin.vendors.create', compact('vendor'));
    }

    public function update(Request $request, Vendor $vendor)
    {
        $vendor->update($this->extractPayload($request, $vendor));
        return redirect()->route('admin.vendors.index')->with('success', 'Vendor updated successfully.');
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();
        return redirect()->route('admin.vendors.index')->with('success', 'Vendor deleted successfully.');
    }

    protected function extractPayload(Request $request, ?Vendor $vendor = null): array
    {
        $data = $request->validate($this->rules());
        $payload = $data;
        unset($payload['document']);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $payload['document_path'] = $file->store('vendor-documents', 'public');
            $payload['document_original_name'] = $file->getClientOriginalName();
        } elseif ($vendor) {
            $payload['document_path'] = $vendor->document_path;
            $payload['document_original_name'] = $vendor->document_original_name;
        }

        return $payload;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SsuController extends Controller
{
    public function index()
    {
        return view('admin.ssu.index', [
            'ssuAccounts' => User::where('role', User::ROLE_CLIENT)
                ->whereIn('sub_role', [User::CLIENT_SSU, User::CLIENT_MASTER_SSU])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create()
    {
        return view('admin.ssu.create', [
            'branches' => Location::where('type', Location::TYPE_BRANCH)->orderBy('name')->get(),
        ]);
    }

    public function show(User $ssu)
    {
        abort_unless($this->isSsuAccount($ssu), 404);

        return view('admin.ssu.show', [
            'ssu' => $ssu,
            'assignedBranches' => $ssu->assignedBranches(),
        ]);
    }

    public function edit(User $ssu)
    {
        abort_unless($this->isSsuAccount($ssu), 404);

        return view('admin.ssu.edit', [
            'ssu' => $ssu,
            'branches' => Location::where('type', Location::TYPE_BRANCH)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateSsu($request);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'role' => User::ROLE_CLIENT,
            'sub_role' => $data['sub_role'],
            'region_states' => array_values($data['branch_ids'] ?? []),
            'password' => $data['password'],
        ]);

        return redirect()->route('admin.ssu.index')->with('success', 'SSU account created successfully.');
    }

    public function update(Request $request, User $ssu)
    {
        abort_unless($this->isSsuAccount($ssu), 404);

        $data = $this->validateSsu($request, $ssu);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'sub_role' => $data['sub_role'],
            'region_states' => array_values($data['branch_ids'] ?? []),
        ];

        if (!empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $ssu->update($payload);

        return redirect()->route('admin.ssu.index')->with('success', 'SSU account updated successfully.');
    }

    public function destroy(User $ssu)
    {
        abort_unless($this->isSsuAccount($ssu), 404);

        $ssu->delete();

        return redirect()->route('admin.ssu.index')->with('success', 'SSU account deleted successfully.');
    }

    private function validateSsu(Request $request, ?User $ssu = null): array
    {
        $passwordRules = $ssu
            ? ['nullable', 'confirmed', 'min:8']
            : ['required', 'confirmed', 'min:8'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sub_role' => ['required', Rule::in([User::CLIENT_SSU, User::CLIENT_MASTER_SSU])],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ssu?->id)],
            'phone_number' => ['required', 'string', 'max:30'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['required', 'integer', Rule::exists('locations', 'id')],
            'password' => $passwordRules,
        ]);
    }

    private function isSsuAccount(User $user): bool
    {
        return $user->role === User::ROLE_CLIENT
            && in_array($user->sub_role, [User::CLIENT_SSU, User::CLIENT_MASTER_SSU], true);
    }
}

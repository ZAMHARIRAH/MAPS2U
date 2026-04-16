<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SsuController extends Controller
{
    public function index()
    {
        return view('admin.ssu.index', [
            'ssuAccounts' => User::where('role', User::ROLE_CLIENT)->where('sub_role', User::CLIENT_SSU)->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.ssu.create', ['stateOptions' => User::stateOptions()]);
    }

    public function show(User $ssu)
    {
        abort_unless($ssu->role === User::ROLE_CLIENT && $ssu->sub_role === User::CLIENT_SSU, 404);

        return view('admin.ssu.show', [
            'ssu' => $ssu,
        ]);
    }

    public function edit(User $ssu)
    {
        abort_unless($ssu->role === User::ROLE_CLIENT && $ssu->sub_role === User::CLIENT_SSU, 404);

        return view('admin.ssu.edit', [
            'ssu' => $ssu,
            'stateOptions' => User::stateOptions(),
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
            'sub_role' => User::CLIENT_SSU,
            'region_states' => array_values($data['region_states']),
            'password' => $data['password'],
        ]);

        return redirect()->route('admin.ssu.index')->with('success', 'SSU account created successfully.');
    }

    public function update(Request $request, User $ssu)
    {
        abort_unless($ssu->role === User::ROLE_CLIENT && $ssu->sub_role === User::CLIENT_SSU, 404);

        $data = $this->validateSsu($request, $ssu);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'region_states' => array_values($data['region_states']),
        ];

        if (!empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $ssu->update($payload);

        return redirect()->route('admin.ssu.index')->with('success', 'SSU account updated successfully.');
    }

    public function destroy(User $ssu)
    {
        abort_unless($ssu->role === User::ROLE_CLIENT && $ssu->sub_role === User::CLIENT_SSU, 404);

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
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ssu?->id)],
            'phone_number' => ['required', 'string', 'max:30'],
            'region_states' => ['required', 'array', 'min:1'],
            'region_states.*' => ['required', Rule::in(User::stateOptions())],
            'password' => $passwordRules,
        ]);
    }
}

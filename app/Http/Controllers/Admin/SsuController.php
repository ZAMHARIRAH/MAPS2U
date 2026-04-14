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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'phone_number' => ['required','string','max:30'],
            'region_states' => ['required','array','min:1'],
            'region_states.*' => ['required', Rule::in(User::stateOptions())],
            'password' => ['required','confirmed','min:8'],
        ]);

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
}

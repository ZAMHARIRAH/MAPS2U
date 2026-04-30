<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TechnicianController extends Controller
{
    public function index() { return view('admin.technicians.index', ['technicians' => User::where('role', User::ROLE_TECHNICIAN)->orderBy('name')->paginate(20)->withQueryString()]); }
    public function create() { return view('admin.technicians.create'); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'phone_number' => ['required','string','max:30'],
            'password' => ['required','confirmed','min:8'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'role' => User::ROLE_TECHNICIAN,
            'sub_role' => 'technician',
            'password' => $data['password'],
        ]);

        return redirect()->route('admin.technicians.index')->with('success', 'Technician account created successfully.');
    }

    public function show(User $technician)
    {
        abort_unless($technician->role === User::ROLE_TECHNICIAN, 404);
        return view('admin.technicians.show', compact('technician'));
    }

    public function edit(User $technician)
    {
        abort_unless($technician->role === User::ROLE_TECHNICIAN, 404);
        return view('admin.technicians.edit', compact('technician'));
    }

    public function update(Request $request, User $technician)
    {
        abort_unless($technician->role === User::ROLE_TECHNICIAN, 404);
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email,' . $technician->id],
            'phone_number' => ['required','string','max:30'],
            'password' => ['nullable','confirmed','min:8'],
        ]);
        $technician->fill(['name' => $data['name'], 'email' => $data['email'], 'phone_number' => $data['phone_number']]);
        if (!empty($data['password'])) { $technician->password = $data['password']; }
        $technician->save();
        return redirect()->route('admin.technicians.show', $technician)->with('success', 'Technician account updated successfully.');
    }

    public function destroy(User $technician)
    {
        abort_unless($technician->role === User::ROLE_TECHNICIAN, 404);
        if ($technician->profile_photo_path) { Storage::disk('public')->delete($technician->profile_photo_path); }
        $technician->delete();
        return redirect()->route('admin.technicians.index')->with('success', 'Technician account deleted successfully.');
    }
}

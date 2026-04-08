<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function showAdminLogin() { return view('auth.admin-login'); }
    public function showTechnicianLogin() { return view('auth.technician-login'); }
    public function showClientLogin() { return view('auth.client-login'); }
    public function showClientRegister() { return redirect()->route('client.login', ['register' => 1]); }

    public function adminLogin(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (Auth::attempt(array_merge($credentials, ['role' => User::ROLE_ADMIN]), $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->route('admin.dashboard');
        }
        return back()->withErrors(['email' => 'Invalid admin credentials.'])->onlyInput('email');
    }

    public function technicianLogin(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (Auth::attempt(array_merge($credentials, ['role' => User::ROLE_TECHNICIAN]), $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->route('technician.dashboard');
        }
        return back()->withErrors(['email' => 'Invalid technician credentials.'])->onlyInput('email');
    }

    public function clientLogin(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (Auth::attempt(array_merge($credentials, ['role' => User::ROLE_CLIENT]), $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->route('client.dashboard');
        }
        return back()->withErrors(['email' => 'Invalid client credentials.'])->onlyInput('email');
    }

    public function clientRegister(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'sub_role' => ['required', Rule::in([User::CLIENT_HQ, User::CLIENT_KINDERGARTEN])],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);
        User::create([
            'name' => $data['name'], 'email' => $data['email'], 'phone_number' => $data['phone_number'],
            'address' => $data['address'], 'role' => User::ROLE_CLIENT, 'sub_role' => $data['sub_role'], 'password' => $data['password'],
        ]);
        return redirect()->route('client.login')->with('success', 'Registration successful. Please login to continue.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home')->with('success', 'Logged out successfully.');
    }
}

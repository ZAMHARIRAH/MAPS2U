<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Services\Maps2uEmailService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(private readonly Maps2uEmailService $emailService)
    {
    }

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


    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetPasswordLink(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return back()->withErrors(['email' => 'Email address not found.'])->onlyInput('email');
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $resetUrl = route('password.reset', ['email' => $user->email, 'token' => $token]);

        $this->emailService->sendView(
            $user->email,
            $user->name,
            'MAPS2U Password Reset',
            'emails.password-reset-link',
            [
                'user' => $user,
                'resetUrl' => $resetUrl,
            ],
        );

        return redirect()->route('password.forgot')->with('success', 'Reset password form has been sent to your email address.');
    }

    public function showResetPasswordForm(Request $request)
    {
        return view('auth.reset-password', [
            'email' => (string) $request->query('email'),
            'token' => (string) $request->query('token'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if (!$record || !Hash::check($data['token'], $record->token)) {
            return back()->withErrors(['email' => 'This reset link is invalid or has expired.'])->withInput($request->except('password', 'password_confirmation'));
        }

        $createdAt = \Illuminate\Support\Carbon::parse($record->created_at);
        if ($createdAt->lt(now()->subHours(24))) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            return back()->withErrors(['email' => 'This reset link has expired. Please request a new one.'])->withInput($request->except('password', 'password_confirmation'));
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return back()->withErrors(['email' => 'User account not found.'])->withInput($request->except('password', 'password_confirmation'));
        }

        $user->update([
            'password' => $data['password'],
        ]);

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return redirect()->route('login.by.role', ['role' => $user->role])->with('success', 'Password updated successfully. Please login again with your new password.');
    }

    public function redirectLoginByRole(string $role)
    {
        return match ($role) {
            User::ROLE_ADMIN => redirect()->route('admin.login'),
            User::ROLE_TECHNICIAN => redirect()->route('technician.login'),
            default => redirect()->route('client.login'),
        };
    }


    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home')->with('success', 'Logged out successfully.');
    }
}

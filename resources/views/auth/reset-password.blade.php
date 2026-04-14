@extends('layouts.app', ['title' => 'Reset Password'])
@section('content')
<div class="auth-wrapper">
    <div class="welcome-block">
        <p class="eyebrow">WELCOME TO MAPS2U!</p>
        <h1>RESET PASSWORD</h1>
        <p>Create your new password and confirm it below.</p>
    </div>
    <div class="auth-card">
        <form method="POST" action="{{ route('password.update') }}">@csrf
            <input type="hidden" name="email" value="{{ old('email', $email) }}">
            <input type="hidden" name="token" value="{{ old('token', $token) }}">
            <label>Email Address</label>
            <input type="email" value="{{ old('email', $email) }}" disabled>
            <label>New Password</label>
            <input type="password" name="password" required>
            <label>Confirm Password</label>
            <input type="password" name="password_confirmation" required>
            <div class="action-row" style="margin-top:14px;">
                <button class="btn primary" type="submit">Submit New Password</button>
                <a class="btn ghost" href="{{ route('home') }}">Back</a>
            </div>
        </form>
    </div>
</div>
@endsection

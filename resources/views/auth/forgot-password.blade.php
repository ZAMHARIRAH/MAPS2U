@extends('layouts.app', ['title' => 'Forgot Password'])
@section('content')
<div class="auth-wrapper">
    <div class="welcome-block">
        <p class="eyebrow">WELCOME TO MAPS2U!</p>
        <h1>FORGOT PASSWORD</h1>
        <p>Enter your registered email address and the system will send a reset password form to your email.</p>
    </div>
    <div class="auth-card">
        <form method="POST" action="{{ route('password.email') }}">@csrf
            <label>Email Address</label>
            <input type="email" name="email" value="{{ old('email') }}" required>
            <div class="action-row" style="margin-top:14px;">
                <button class="btn primary" type="submit">Send Reset Form</button>
                <a class="btn ghost" href="{{ route('home') }}">Back</a>
            </div>
        </form>
    </div>
</div>
@endsection

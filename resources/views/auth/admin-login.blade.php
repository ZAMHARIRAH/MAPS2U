@extends('layouts.app', ['title' => 'Admin Login'])
@section('content')
<div class="auth-wrapper"><div class="welcome-block"><p class="eyebrow">WELCOME TO MAPS2U!</p><h1>ADMIN LOGIN</h1></div><div class="auth-card"><form method="POST" action="{{ route('admin.login.submit') }}">@csrf <label>Email Address</label><input type="email" name="email" value="{{ old('email') }}" required><label>Password</label><input type="password" name="password" required><button class="btn primary block">Login</button><p class="helper-text center-text" style="margin-top:14px;"><a href="{{ route('password.forgot') }}" class="inline-link">Forgot Password?</a></p></form></div></div>
@endsection

@extends('layouts.app', ['title' => 'Client Login'])
@section('content')
<div class="auth-split-shell client-auth-shell">
    <section class="auth-side-panel scenic-panel">
        <span class="hero-kicker">WELCOME TO MAPS2U</span>
        <h1>Client Access</h1>
        <p>Login to submit a new request, review job progress, resubmit returned forms, and complete customer feedback.</p>
        <div class="badge-row compact">
            <span class="badge neutral">HQ Staff</span>
            <span class="badge neutral">Kindergarten</span>
            <span class="badge neutral">SSU</span>
        </div>
    </section>

    <section class="auth-main-panel">
        <div class="auth-card premium-auth-card">
            <div class="auth-card-head">
                <span class="hero-kicker">Client Login</span>
                <h2>Sign in to MAPS2U</h2>
            </div>
            <form method="POST" action="{{ route('client.login.submit') }}">
                @csrf
                <label>Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required>
                <label>Password</label>
                <input type="password" name="password" required>
                <button class="btn primary block" type="submit">Login</button>
                <p class="helper-text center-text" style="margin-top:14px;">Doesn't have an account? <a href="{{ route('client.register') }}" class="inline-link" id="open-register-panel">Register here</a></p>
            <p class="helper-text center-text" style="margin-top:14px;"><a href="{{ route('password.forgot') }}" class="inline-link">Forgot Password?</a></p></form>
        </div>

        <div class="auth-card premium-auth-card hidden-form-block" id="client-register-panel">
            <div class="auth-card-head">
                <span class="hero-kicker">Client Registration</span>
                <h2>Create a new account</h2>
            </div>
            @include('auth.client-register-form')
        </div>
    </section>
</div>

<script>
const registerLink = document.getElementById('open-register-panel');
const registerPanel = document.getElementById('client-register-panel');
registerLink?.addEventListener('click', function(event){
    event.preventDefault();
    registerPanel?.classList.add('show-block');
    registerPanel?.scrollIntoView({behavior:'smooth', block:'start'});
});
@if(request()->boolean('register') || $errors->has('name') || $errors->has('phone_number') || $errors->has('address') || $errors->has('sub_role') || $errors->has('password') || $errors->has('email'))
registerPanel?.classList.add('show-block');
@endif
</script>
@endsection

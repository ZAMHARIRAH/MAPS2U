@extends('layouts.app', ['title' => 'Client Registration'])
@section('content')
<div class="auth-split-shell client-auth-shell single-register-shell">
    <section class="auth-side-panel scenic-panel">
        <span class="hero-kicker">MAPS2U REGISTRATION</span>
        <h1>Create Your Client Account</h1>
        <p>Register once and manage every request, review, and update directly from the client portal.</p>
    </section>
    <section class="auth-main-panel">
        <div class="auth-card premium-auth-card">
            <div class="auth-card-head">
                <span class="hero-kicker">Client Registration</span>
                <h2>Register Account</h2>
            </div>
            @include('auth.client-register-form')
        </div>
    </section>
</div>
@endsection

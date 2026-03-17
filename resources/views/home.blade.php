@extends('layouts.app', ['title' => 'MAPS2U Homepage'])
@section('content')
<section class="landing-hero landing-hero-centered">
    <div class="landing-hero-backdrop"></div>
    <div class="landing-hero-content landing-hero-shell">
        <h1>MAPS2U</h1>
        <div class="landing-role-buttons landing-role-buttons-centered">
            <a class="btn primary role-cta" href="{{ route('admin.login') }}">ADMIN</a>
            <a class="btn secondary role-cta" href="{{ route('technician.login') }}">TECHNICIAN</a>
            <a class="btn accent role-cta" href="{{ route('client.login') }}">CLIENT</a>
        </div>
    </div>
</section>

<section class="landing-info-stack">
    <article class="panel landing-panel" id="about">
        <h3>About Us</h3>
    </article>
    <article class="panel landing-panel" id="contact">
        <h3>Contact Us</h3>
        <p>Email: support@maps2u.test</p>
        <p>Phone: +60 12-345 6789</p>
    </article>
</section>
@endsection

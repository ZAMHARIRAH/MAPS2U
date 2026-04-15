@extends('layouts.app', ['title' => 'MAPS2U Homepage'])
@section('content')
<section class="landing-hero landing-hero-centered">
    <div class="landing-hero-backdrop"></div>
    <div class="landing-hero-content landing-hero-shell">
        <h1>MAPS2U</h1>
        <p class="landing-hero-subtitle"> </p>
        <div class="landing-role-buttons landing-role-buttons-centered single-cta-wrap">
            <a class="btn accent role-cta single-role-cta" href="{{ route('client.login') }}">JOB REQUEST</a>
        </div>
    </div>
</section>

<section class="landing-info-stack">
    <article class="panel landing-panel" id="announcement">
        <div class="panel-head compact-head">
            <div>
                <h3>Announcement</h3>
                <p class="helper-text"> </p>
            </div>
        </div>

        @forelse($announcements as $announcement)
            <div class="announcement-item">
                <div class="announcement-item-head">
                    <strong>{{ $announcement->title }}</strong>
                    <span class="badge {{ $announcement->priorityBadgeClass() }}">{{ $announcement->priorityLabel() }}</span>
                </div>
                <p>{{ $announcement->content }}</p>
            </div>
        @empty
            <p class="helper-text">No announcement available right now.</p>
        @endforelse
    </article>
    <article class="panel landing-panel" id="contact">
        <h3>Contact Us</h3>
        <p>Email: support@maps2u.test</p>
        <p>Phone: +60 12-345 6789</p>
    </article>
</section>
@endsection

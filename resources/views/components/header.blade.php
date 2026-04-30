@php
    $user = auth()->user();
    $notifications = collect();
    $unreadNotificationCount = 0;

    if ($user) {
        $storedNotifications = \App\Models\SystemNotification::where('user_id', $user->id)->latest()->take(8)->get();
        $unreadNotificationCount = \App\Models\SystemNotification::where('user_id', $user->id)->whereNull('read_at')->count();
        $notifications = $storedNotifications->map(fn ($item) => [
            'title' => $item->title,
            'body' => $item->body,
            'url' => route('notifications.open', $item),
            'read_at' => $item->read_at,
        ]);
    }
@endphp

<header class="topbar">
    <div class="topbar-left">
        @auth
            <button class="icon-btn sidebar-toggle-btn" type="button" data-sidebar-toggle aria-label="Toggle navigation">☰</button>
        @endauth
        <div class="brand-mark">MAPS2U</div>
    </div>

    @guest
        <nav class="top-nav">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('home') }}#announcement">Announcement</a>
            <a href="{{ route('home') }}#contact">Contact Us</a>
        </nav>
    @else
        <div class="clock-pill" data-live-clock></div>
    @endguest

    <div class="topbar-right">
        @guest
            <a class="icon-btn login-icon-link" href="{{ route('admin.login') }}" aria-label="Admin login" title="Admin login">
                <span class="login-icon-symbol">🛡️</span>
                <span class="login-icon-label">Admin</span>
            </a>
            <a class="icon-btn login-icon-link" href="{{ route('technician.login') }}" aria-label="Technician login" title="Technician login">
                <span class="login-icon-symbol">🛠️</span>
                <span class="login-icon-label">Technician</span>
            </a>
        @else
            <div class="dropdown-shell">
                <button class="icon-btn notification-bell-btn" type="button" data-bell-toggle aria-label="Notifications">🔔 @if($unreadNotificationCount > 0)<span class="notification-count">{{ $unreadNotificationCount }}</span>@endif</button>
                <div class="dropdown-panel narrow">
                    <h4>Notifications</h4>
                    @forelse($notifications as $item)
                        <div class="dropdown-notice">
                            <strong>{{ $item['title'] }}</strong>
                            <p>{{ $item['body'] }}</p>
                            <a href="{{ $item['url'] }}">Open</a>
                        </div>
                    @empty
                        <p>No notifications.</p>
                    @endforelse
                </div>
            </div>
            <div class="dropdown-shell">
                <button class="profile-chip" type="button" data-profile-toggle>
                    <img src="{{ $user->profilePhotoUrl() }}" alt="Profile">
                    <span>{{ $user->name }}</span>
                </button>
                <div class="dropdown-panel">
                    <a href="{{ route('profile.show') }}">View Profile</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="link-btn">Logout</button>
                    </form>
                </div>
            </div>
        @endguest
    </div>
</header>

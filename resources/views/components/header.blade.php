@php
    $user = auth()->user();
    $notifications = collect();

    if ($user?->isAdmin()) {
        $scopedRoles = $user->handledClientRoles();
        $financePending = \App\Models\ClientRequest::whereHas('user', fn ($query) => $query->whereIn('sub_role', $scopedRoles))
            ->whereNotNull('invoice_uploaded_at')
            ->whereNull('finance_completed_at')
            ->latest('invoice_uploaded_at')
            ->take(5)
            ->get();

        $notifications = $financePending->map(function ($item) {
            return [
                'title' => $item->request_code . ' invoice uploaded',
                'body' => 'Technician uploaded invoice. Open Finance to complete payment form.',
                'url' => route('admin.finance.show', $item),
            ];
        });
    } elseif ($user?->isTechnician()) {
        $jobAlerts = \App\Models\ClientRequest::where('assigned_technician_id', $user->id)
            ->where(function ($query) {
                $query->whereIn('status', [
                    \App\Models\ClientRequest::STATUS_UNDER_REVIEW,
                    \App\Models\ClientRequest::STATUS_PENDING_APPROVAL,
                    \App\Models\ClientRequest::STATUS_WORK_IN_PROGRESS,
                ])->orWhereNotNull('quotation_return_remark');
            })->latest()->take(5)->get();

        $notifications = $jobAlerts->map(function ($item) {
            $body = $item->quotation_return_remark
                ? 'Quotation not approved. Please rework and resubmit the quotation form.'
                : 'Open the job request workspace to continue the workflow.';
            return [
                'title' => $item->request_code . ' needs attention',
                'body' => $body,
                'url' => route('technician.job-requests.show', $item),
            ];
        });
    } elseif ($user?->isClient()) {
        $returned = \App\Models\ClientRequest::where('user_id', $user->id)
            ->where('status', \App\Models\ClientRequest::STATUS_RETURNED)
            ->latest()->take(3)->get();
        $scheduled = \App\Models\ClientRequest::where('user_id', $user->id)
            ->whereNotNull('scheduled_date')
            ->latest()->take(8)->get()
            ->filter(fn ($item) => $item->upcomingScheduleDays() !== null)
            ->take(3);

        $returnedNotifications = collect($returned->map(function ($item) {
            return [
                'title' => $item->request_code . ' returned for update',
                'body' => 'Technician requested resubmission. Open your request list to update the form.',
                'url' => route('client.requests.index', ['edit' => $item->id]),
            ];
        })->all());

        $scheduledNotifications = collect($scheduled->map(function ($item) {
            $days = $item->upcomingScheduleDays();
            $label = $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : $days . ' days left');
            return [
                'title' => $item->request_code . ' schedule reminder',
                'body' => $label . ' - ' . $item->scheduled_date->format('d M Y') . ' ' . ($item->scheduled_time ?: ''),
                'url' => route('client.requests.index'),
            ];
        })->all());

        $notifications = $returnedNotifications->concat($scheduledNotifications)->values();
    }
@endphp

<header class="topbar">
    <div class="topbar-left">
        <div class="brand-mark">MAPS2U</div>
    </div>

    @guest
        <nav class="top-nav">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('home') }}#about">About Us</a>
            <a href="{{ route('home') }}#contact">Contact Us</a>
        </nav>
    @else
        <div class="clock-pill" data-live-clock></div>
    @endguest

    <div class="topbar-right">
        @auth
            <div class="dropdown-shell">
                <button class="icon-btn" type="button" data-bell-toggle aria-label="Notifications">🔔</button>
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
        @endauth
    </div>
</header>

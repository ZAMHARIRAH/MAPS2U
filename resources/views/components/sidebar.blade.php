@php($user = auth()->user())
<aside class="sidebar">
    <div class="sidebar-head">
        <h3>{{ $user->roleLabel() }}</h3>
        <p>Dashboard Navigation</p>
    </div>

    <nav class="sidebar-nav">
        @if($user->isAdmin())
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <a href="{{ route('profile.show') }}">My Profile</a>
            <a href="{{ route('admin.technicians.index') }}">Manage Technician</a>
            <details class="nav-group" {{ request()->routeIs('admin.request-types.*') || request()->routeIs('admin.incoming-requests.*') ? 'open' : '' }}>
                <summary>Request</summary>
                <div class="nav-submenu">
                    <a href="{{ route('admin.request-types.index') }}">Request Types</a>
                    <a href="{{ route('admin.incoming-requests.index') }}">Incoming Requests</a>
                </div>
            </details>
            <a href="{{ route('admin.finance.index') }}">Finance</a>
            <details class="nav-group" {{ request()->routeIs('admin.locations.*') ? 'open' : '' }}>
                <summary>Locations</summary>
                <div class="nav-submenu">
                    <a href="{{ route('admin.locations.index', 'hq') }}">HQ Locations</a>
                    <a href="{{ route('admin.locations.index', 'branch') }}">Branches</a>
                </div>
            </details>
            <a href="{{ route('admin.departments.index') }}">Departments</a>
            <details class="nav-group" {{ request()->routeIs('admin.reports.*') ? 'open' : '' }}>
                <summary>Report</summary>
                <div class="nav-submenu">
                    <a href="{{ route('admin.reports.job-request') }}">Job Request</a>
                    <a href="{{ route('admin.reports.technician') }}">Technician</a>
                </div>
            </details>
            <a href="{{ route('admin.accounts.index') }}">View Account</a>
        @elseif($user->isTechnician())
            <a href="{{ route('technician.dashboard') }}">Dashboard</a>
            <a href="{{ route('technician.job-requests.index') }}">Job Request</a>
            <a href="{{ route('profile.show') }}">My Profile</a>
        @else
            <a href="{{ route('client.dashboard') }}">Dashboard</a>
            <details class="nav-group" {{ request()->routeIs('client.requests.*') ? 'open' : '' }}>
                <summary>Request</summary>
                <div class="nav-submenu">
                    <a href="{{ route('client.requests.index', ['tab' => 'new']) }}">New Request</a>
                    <a href="{{ route('client.requests.index', ['tab' => 'related']) }}">Related Job Request</a>
                </div>
            </details>
            <a href="{{ route('profile.show') }}">My Profile</a>
        @endif
    </nav>
</aside>

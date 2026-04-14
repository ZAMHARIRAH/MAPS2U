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
            @if($user->isViewer())
                <details class="nav-group" {{ request()->routeIs('admin.incoming-requests.*') ? 'open' : '' }}>
                    <summary>Monitoring</summary>
                    <div class="nav-submenu">
                        <a href="{{ route('admin.incoming-requests.index') }}">All Request</a>
                        <a href="{{ route('admin.finance.index') }}">Finance</a>
                        <a href="{{ route('admin.accounts.index') }}">View Account</a>
                    </div>
                </details>
                <details class="nav-group" {{ request()->routeIs('admin.reports.*') ? 'open' : '' }}>
                    <summary>Report</summary>
                    <div class="nav-submenu">
                        <a href="{{ route('admin.reports.job-request') }}">Job Request</a>
                        <details class="nav-group" {{ request()->routeIs('admin.reports.locations*') ? 'open' : '' }}>
                            <summary>Locations</summary>
                            <div class="nav-submenu">
                                <a href="{{ route('admin.reports.locations') }}">Current Report</a>
                                <details class="nav-group" {{ request()->routeIs('admin.reports.locations.archive*') ? 'open' : '' }}>
                                    <summary>Year Compile</summary>
                                    <div class="nav-submenu">
                                        @forelse(\App\Models\ReportArchive::where('report_type', \App\Models\ReportArchive::TYPE_LOCATIONS)->orderByDesc('archive_year')->pluck('archive_year') as $archiveYear)
                                            <a href="{{ route('admin.reports.locations.archive.show', $archiveYear) }}">{{ $archiveYear }}</a>
                                        @empty
                                            <span class="helper-text" style="padding:8px 14px;display:block;color:rgba(255,255,255,.72);">No archive yet</span>
                                        @endforelse
                                    </div>
                                </details>
                            </div>
                        </details>
                        <details class="nav-group" {{ request()->routeIs('admin.reports.branches*') ? 'open' : '' }}>
                            <summary>Branches</summary>
                            <div class="nav-submenu">
                                <a href="{{ route('admin.reports.branches') }}">Current Report</a>
                                <details class="nav-group" {{ request()->routeIs('admin.reports.branches.archive*') ? 'open' : '' }}>
                                    <summary>Year Compile</summary>
                                    <div class="nav-submenu">
                                        @forelse(\App\Models\ReportArchive::where('report_type', \App\Models\ReportArchive::TYPE_BRANCHES)->orderByDesc('archive_year')->pluck('archive_year') as $archiveYear)
                                            <a href="{{ route('admin.reports.branches.archive.show', $archiveYear) }}">{{ $archiveYear }}</a>
                                        @empty
                                            <span class="helper-text" style="padding:8px 14px;display:block;color:rgba(255,255,255,.72);">No archive yet</span>
                                        @endforelse
                                    </div>
                                </details>
                            </div>
                        </details>
                        <a href="{{ route('admin.reports.technician') }}">Technician</a>
                    </div>
                </details>
            @else
                <details class="nav-group" {{ request()->routeIs('admin.technicians.*') || request()->routeIs('admin.ssu.*') ? 'open' : '' }}>
                    <summary>Manage Staff</summary>
                    <div class="nav-submenu">
                        <a href="{{ route('admin.technicians.index') }}">Manage Staff</a>
                        <a href="{{ route('admin.ssu.index') }}">Manage SSU</a>
                    </div>
                </details>
                <details class="nav-group" {{ request()->routeIs('admin.request-types.*') || request()->routeIs('admin.incoming-requests.*') ? 'open' : '' }}>
                    <summary>Request</summary>
                    <div class="nav-submenu">
                        <a href="{{ route('admin.incoming-requests.index') }}">All Request</a>
                        <a href="{{ route('admin.request-types.index') }}">Request Type</a>
                    </div>
                </details>
                <a href="{{ route('admin.finance.index') }}">Finance</a>
                <a href="{{ route('admin.vendors.index') }}">Vendor</a>
                @if($user->canOpenMapsFinanceSupport())
                    <details class="nav-group" {{ request()->routeIs('admin.maps.*') ? 'open' : '' }}>
                        <summary>Admin MAPS</summary>
                        <div class="nav-submenu">
                            <a href="{{ route('admin.maps.dashboard') }}">Dashboard MAPS</a>
                            <a href="{{ route('admin.maps.finance.index') }}">Finance MAPS</a>
                        </div>
                    </details>
                @endif
                <details class="nav-group" {{ request()->routeIs('admin.locations.*') ? 'open' : '' }}>
                    <summary>Locations</summary>
                    <div class="nav-submenu">
                        <a href="{{ route('admin.locations.index', 'hq') }}">HQ Locations</a>
                        <a href="{{ route('admin.locations.index', 'branch') }}">Branches</a>
                    </div>
                </details>
                <a href="{{ route('admin.departments.index') }}">Departments</a>
                <a href="{{ route('admin.tasks.index') }}">Manage Task</a>
                <a href="{{ route('admin.announcements.index') }}" class="{{ request()->routeIs('admin.announcements.*') ? 'active-nav-link' : '' }}">Announcements</a>
                <details class="nav-group" {{ request()->routeIs('admin.reports.*') ? 'open' : '' }}>
                    <summary>Report</summary>
                    <div class="nav-submenu">
                        <a href="{{ route('admin.reports.job-request') }}">Job Request</a>
                        <details class="nav-group" {{ request()->routeIs('admin.reports.locations*') ? 'open' : '' }}>
                            <summary>Locations</summary>
                            <div class="nav-submenu">
                                <a href="{{ route('admin.reports.locations') }}">Current Report</a>
                                <details class="nav-group" {{ request()->routeIs('admin.reports.locations.archive*') ? 'open' : '' }}>
                                    <summary>Year Compile</summary>
                                    <div class="nav-submenu">
                                        @forelse(\App\Models\ReportArchive::where('report_type', \App\Models\ReportArchive::TYPE_LOCATIONS)->orderByDesc('archive_year')->pluck('archive_year') as $archiveYear)
                                            <a href="{{ route('admin.reports.locations.archive.show', $archiveYear) }}">{{ $archiveYear }}</a>
                                        @empty
                                            <span class="helper-text" style="padding:8px 14px;display:block;color:rgba(255,255,255,.72);">No archive yet</span>
                                        @endforelse
                                    </div>
                                </details>
                            </div>
                        </details>
                        <details class="nav-group" {{ request()->routeIs('admin.reports.branches*') ? 'open' : '' }}>
                            <summary>Branches</summary>
                            <div class="nav-submenu">
                                <a href="{{ route('admin.reports.branches') }}">Current Report</a>
                                <details class="nav-group" {{ request()->routeIs('admin.reports.branches.archive*') ? 'open' : '' }}>
                                    <summary>Year Compile</summary>
                                    <div class="nav-submenu">
                                        @forelse(\App\Models\ReportArchive::where('report_type', \App\Models\ReportArchive::TYPE_BRANCHES)->orderByDesc('archive_year')->pluck('archive_year') as $archiveYear)
                                            <a href="{{ route('admin.reports.branches.archive.show', $archiveYear) }}">{{ $archiveYear }}</a>
                                        @empty
                                            <span class="helper-text" style="padding:8px 14px;display:block;color:rgba(255,255,255,.72);">No archive yet</span>
                                        @endforelse
                                    </div>
                                </details>
                            </div>
                        </details>
                        <a href="{{ route('admin.reports.technician') }}">Technician</a>
                    </div>
                </details>
                <a href="{{ route('admin.accounts.index') }}">View Account</a>
            @endif
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
            @if($user->isSsu())
                <a href="{{ route('client.reports.index') }}">Report</a>
            @endif
            <a href="{{ route('profile.show') }}">My Profile</a>
        @endif
    </nav>
</aside>

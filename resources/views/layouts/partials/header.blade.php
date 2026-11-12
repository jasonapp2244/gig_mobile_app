@php
    $adminUser = auth('admin')->user();
    $adminName = trim((string) ($adminUser->name ?? '')) ?: trans('messages.admin_name');
    $adminInitials = collect(preg_split('/\s+/', $adminName))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp
<!--start header -->
<header>
    <div class="topbar d-flex align-items-center">
        <nav class="navbar navbar-expand gap-3">
            <div class="mobile-toggle-menu"><i class='bx bx-menu'></i></div>

            {{-- Page title: filled from the active sidebar item (see footer_script) --}}
            <div class="gig-page-heading d-flex flex-column justify-content-center">
                <div class="gig-eyebrow">Gig Admin</div>
                <h1 class="gig-title" id="gigPageTitle">@yield('page_title', 'Dashboard')</h1>
            </div>

            {{-- app.js initialises PerfectScrollbar on these; they must exist but are not shown. --}}
            <div class="gig-js-holders" aria-hidden="true">
                <div class="app-container"></div>
                <div class="header-notifications-list"></div>
                <div class="header-message-list"></div>
            </div>

            <div class="user-box dropdown ms-auto px-2">
                <a class="d-flex align-items-center nav-link dropdown-toggle gap-2 dropdown-toggle-nocaret"
                    href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="gig-avatar" aria-hidden="true">{{ $adminInitials ?: 'A' }}</span>
                    <div class="user-info">
                        <p class="user-name mb-0">{{ $adminName }}</p>
                        @if ($adminUser?->email)
                            <p class="designattion mb-0">{{ $adminUser->email }}</p>
                        @endif
                    </div>
                    <i class='bx bx-chevron-down text-muted d-none d-sm-inline'></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('setting.view.profile') }}">
                            <i class="bx bx-user-circle fs-5"></i><span>Admin Profile</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('setting.change.password') }}">
                            <i class="bx bx-lock-alt fs-5"></i><span>Change Password</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('logout') }}">
                            <i class="bx bx-log-out-circle fs-5"></i><span>{{ trans('messages.logout') }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</header>
<!--end header -->

<!--start header -->
<header>
    <div class="topbar d-flex align-items-center">
        <nav class="navbar navbar-expand gap-3">
            <div class="mobile-toggle-menu"><i class='bx bx-menu'></i>
            </div>
            <div class="search-bar d-lg-block d-none" data-bs-toggle="modal" data-bs-target="#SearchModal">
                {{-- <a href="avascript:;" class="btn d-flex align-items-center"><i class='bx bx-search'></i>Search</a> --}}
            </div>
            <div class="top-menu ms-auto">
                <ul class="navbar-nav align-items-center gap-1">
                    {{-- <li class="nav-item dropdown dropdown-laungauge d-none d-sm-flex">
                        <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="javascript:;" data-bs-toggle="dropdown"><i class='bx bx-globe'></i></a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <form action="{{ route('change.language') }}" method="POST" style="display:inline;">
                                @csrf
                                <select name="locale" onchange="this.form.submit()" class="form-select">
                                    <option value="en" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>{{ trans('messages.language_english') }}</option>
                                    <option value="es" {{ app()->getLocale() == 'es' ? 'selected' : '' }}>{{ trans('messages.language_spanish') }}</option>
                                    <option value="ur" {{ app()->getLocale() == 'ur' ? 'selected' : '' }}>{{ trans('messages.language_urdu') }}</option>
                                    <option value="fr" {{ app()->getLocale() == 'fr' ? 'selected' : '' }}>{{ trans('messages.language_french') }}</option>
                                </select>
                            </form>
                        </div>
                    </li> --}}
                    <li class="nav-item dark-mode d-none d-sm-flex">
                    </li>
                    <li class="nav-item dropdown dropdown-app">
                        <div class="dropdown-menu dropdown-menu-end p-0">
                            <div class="app-container p-2 my-2">
                                <div class="row gx-0 gy-2 row-cols-3 justify-content-center p-2">
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item dropdown dropdown-large">
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="header-notifications-list">
                            </div>
                        </div>
                    </li>
                    <li class="nav-item dropdown dropdown-large">

                        <div class="dropdown-menu dropdown-menu-end">

                            <div class="header-message-list">
                            </div>

                        </div>
                    </li>
                </ul>
            </div>
            <div class="user-box dropdown px-3">
                <a class="d-flex align-items-center nav-link dropdown-toggle gap-3 dropdown-toggle-nocaret"
                    href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    {{-- <img src="assets/images/avatars/avatar-2.png" class="user-img" alt="user avatar"> --}}
                    <div class="user-info">
                        <p class="user-name mb-0">{{ trans('messages.admin_name') }}</p>
                        {{-- <p class="designattion mb-0">Email</p> --}}
                    </div>
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

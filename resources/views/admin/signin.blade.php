<!doctype html>
<html lang="en">
@include('layouts.partials.header_script')

<body class="gig-auth">
    <main class="gig-auth-shell">
        {{-- Brand panel (hidden on small screens) --}}
        <section class="gig-auth-brand d-none d-lg-flex">
            <div class="gig-auth-pattern" aria-hidden="true"></div>
            <div class="gig-auth-brand-inner">
                <div class="d-flex align-items-center gap-3">
                    <span class="gig-auth-logo-tile">
                        <img src="{{ asset('admin/images/logo-icon.png') }}" alt="Gig logo">
                    </span>
                    <div>
                        <div class="gig-auth-wordmark">{{ trans('messages.app_name') }}</div>
                        <div class="gig-auth-wordmark-sub">Admin Panel</div>
                    </div>
                </div>

                <div class="gig-auth-copy">
                    <h2>Run your gig platform from one place.</h2>
                    <p>Users, jobs, payments, listings, support and app analytics — all in a single, secure workspace.</p>
                </div>

                <ul class="gig-auth-points list-unstyled mb-0">
                    <li><i class='bx bx-line-chart'></i><span>Live Firebase Analytics &amp; dashboards</span></li>
                    <li><i class='bx bx-wallet'></i><span>Income tracker &amp; payment monitoring</span></li>
                    <li><i class='bx bx-shield-quarter'></i><span>Role-protected admin access</span></li>
                </ul>

                <div class="gig-auth-brand-foot">© {{ date('Y') }} {{ trans('messages.app_name') }}. All rights reserved.</div>
            </div>
        </section>

        {{-- Sign-in form --}}
        <section class="gig-auth-form-side">
            <div class="gig-auth-card">
                <div class="text-center mb-4">
                    <img src="{{ asset('admin/images/logo-icon.png') }}" alt="Gig logo" class="gig-auth-card-logo">
                    <h1 class="gig-auth-title">Welcome back</h1>
                    <p class="gig-auth-subtitle mb-0">{{ trans('messages.gig_admin') }} · {{ trans('messages.please_log_in_to_your_account') }}</p>
                </div>

                {{-- Success Message --}}
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert" id="loginSuccess">
                        <i class="bx bx-check-circle fs-5"></i>
                        <span>{{ session('success') }}</span>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                {{-- Error Message --}}
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert" id="loginError">
                        <i class="bx bx-error-circle fs-5"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form class="row g-3" method="POST" action="{{ route('admin.signin') }}">
                    @csrf
                    <div class="col-12">
                        <label for="inputEmailAddress" class="form-label">{{ trans('messages.email') }}</label>
                        <div class="gig-input-icon">
                            <i class='bx bx-envelope'></i>
                            <input type="email" name="email" class="form-control form-control-lg" id="inputEmailAddress"
                                placeholder="you@company.com" value="{{ old('email') }}" autocomplete="username" required autofocus>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="inputChoosePassword" class="form-label">Password</label>
                        <div class="input-group gig-input-icon" id="show_hide_password">
                            <i class='bx bx-lock-alt'></i>
                            <input type="password" name="password" class="form-control form-control-lg" id="inputChoosePassword"
                                placeholder="Enter your password" autocomplete="current-password" required>
                            <a href="javascript:void(0);" class="input-group-text" aria-label="Show or hide password">
                                <i class='bx bx-hide'></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-12 pt-1">
                        <button type="submit" class="btn btn-primary btn-lg w-100 gig-auth-submit">
                            {{ trans('messages.sign_in') }} <i class='bx bx-right-arrow-alt ms-1'></i>
                        </button>
                    </div>
                </form>

                <p class="gig-auth-note mb-0">
                    <i class='bx bx-lock'></i> Authorised administrators only. Activity is logged.
                </p>
            </div>
        </section>
    </main>

    <script src="{{ asset('admin/js/jquery.min.js') }}"></script>
    <script src="{{ asset('admin/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        $(function () {
            // Show / hide password
            $('#show_hide_password a').on('click', function (event) {
                event.preventDefault();
                var $input = $('#show_hide_password input');
                var show = $input.attr('type') === 'password';
                $input.attr('type', show ? 'text' : 'password');
                $('#show_hide_password a i').toggleClass('bx-hide', !show).toggleClass('bx-show', show);
            });

            // Fade the error message after 5 seconds
            var errorAlert = document.getElementById('loginError');
            if (errorAlert) {
                setTimeout(function () {
                    errorAlert.style.transition = 'opacity 0.5s';
                    errorAlert.style.opacity = '0';
                    setTimeout(function () { errorAlert.remove(); }, 500);
                }, 5000);
            }
        });
    </script>
</body>

</html>

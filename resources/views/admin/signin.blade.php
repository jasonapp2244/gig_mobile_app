<!doctype html>
<html lang="en">
{{-- Include Navbar --}}
@include('layouts.partials.header_script')

<body class="">
    <!--wrapper-->
    <div class="wrapper">
        <div class="wrapper">
            <div class="section-authentication-signin d-flex align-items-center justify-content-center my-5 my-lg-0">
                <div class="container">
                    <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3">
                        <div class="col mx-auto">
                            <div class="card mb-0">
                                <div class="card-body">
                                    <div class="p-4">
                                        <div class="mb-3 text-center">
                                            <img src="{{ asset('admin/images/logo-icon.png') }}" h="60"
                                                alt="" />
                                        </div>
                                        <div class="text-center mb-4">
                                            <h5 class="">{{ trans('messages.gig_admin') }}</h5>
                                            <p class="mb-0">{{ trans('messages.please_log_in_to_your_account') }}</p>
                                        </div>
                                        {{-- Success Message --}}
                                        @if(session('success'))
                                            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert" id="loginSuccess">
                                                <i class="bx bx-check-circle fs-5"></i>
                                                <span>{{ session('success') }}</span>
                                                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                                            </div>
                                        @endif
                                        {{-- Error Message --}}
                                        @if ($errors->any())
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert"
                                                id="loginError">
                                                {{ $errors->first() }}
                                            </div>
                                        @endif
                                        <div class="form-body">
                                            <form class="row g-3" method="POST" action="{{ route('admin.signin') }}">
                                                @csrf
                                                <div class="col-12">
                                                    <label for="inputEmailAddress" class="form-label">{{ trans('messages.email') }}</label>
                                                    <input type="email" name="email" class="form-control"
                                                        id="inputEmailAddress" placeholder="john@example.com"
                                                        autocomplete="username" required>
                                                </div>
                                                <div class="col-12">
                                                    <label for="inputChoosePassword" class="form-label">Password</label>
                                                    <div class="input-group" id="show_hide_password">
                                                        <input type="password" name="password" class="form-control"
                                                            id="inputChoosePassword" placeholder="johan1234"
                                                            autocomplete="current-password" required>
                                                        <a href="javascript:void(0);"
                                                            class="input-group-text bg-transparent">
                                                            <i class='bx bx-hide'></i>
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-grid">
                                                        <button type="submit" class="btn" style="color: #fff; background-color: #0d6efd;">{{ trans('messages.sign_in') }}</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end row-->
                </div>
            </div>
        </div>
        <!--end page wrapper -->
    </div>
    <!--end wrapper-->
    <!--start switcher pending-->
    @include('layouts.partials.footer_script')
       <script>
            document.addEventListener("DOMContentLoaded", function() {
                let errorAlert = document.getElementById("loginError");
                if (errorAlert) {
                    setTimeout(() => {
                        errorAlert.style.transition = "opacity 0.5s";
                        errorAlert.style.opacity = "0";
                        setTimeout(() => errorAlert.remove(), 500);
                    }, 5000);
                }
            });
        </script>
</body>

</html>

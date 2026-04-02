@extends('layouts.admin')

@section('content')
<div class="page-wrapper">
    <div class="page-content">

        {{-- Page Title --}}
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Settings</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item"><a href="{{ route('setting.view.profile') }}">Account Setting</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Change Password</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-5 col-lg-6 col-md-8">
                <div class="card radius-10 shadow-sm border-0">

                    {{-- Card Header --}}
                    <div class="card-header border-0 py-3 px-4" style="background: linear-gradient(135deg, #1a73e8 0%, #4a90d9 100%);">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white bg-opacity-25 rounded-circle p-2">
                                <i class="bx bx-lock-alt text-white fs-4"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 text-white fw-semibold">Change Password</h5>
                                <small class="text-white-50">Update your account password</small>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">

                        {{-- Alerts --}}
                        @if(session('success'))
                            <div class="alert alert-success border-0 d-flex align-items-center gap-2 mb-4" role="alert">
                                <i class="bx bx-check-circle fs-5"></i>
                                <span>{{ session('success') }}</span>
                                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="alert alert-danger border-0 d-flex align-items-start gap-2 mb-4" role="alert">
                                <i class="bx bx-error-circle fs-5 mt-1"></i>
                                <ul class="mb-0 ps-2">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        <form action="{{ route('setting.change.password.update') }}" method="POST" id="changePasswordForm">
                            @csrf

                            {{-- Current Password --}}
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary small text-uppercase tracking-wide">
                                    Current Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bx bx-key text-muted"></i>
                                    </span>
                                    <input type="password" name="current_password" id="current_password"
                                        class="form-control border-start-0 ps-0 placeholder-sm @error('current_password') is-invalid @enderror"
                                        placeholder="Enter current password">
                                    <span class="input-group-text bg-light border-start-0 cursor-pointer"
                                        onclick="togglePassword('current_password', 'eye1')">
                                        <i class="bx bx-hide text-muted" id="eye1"></i>
                                    </span>
                                    @error('current_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            {{-- New Password --}}
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary small text-uppercase">
                                    New Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bx bx-lock text-muted"></i>
                                    </span>
                                    <input type="password" name="new_password" id="new_password"
                                        class="form-control border-start-0 ps-0 placeholder-sm @error('new_password') is-invalid @enderror"
                                        placeholder="Min 8 characters"
                                        oninput="checkStrength(this.value)">
                                    <span class="input-group-text bg-light border-start-0 cursor-pointer"
                                        onclick="togglePassword('new_password', 'eye2')">
                                        <i class="bx bx-hide text-muted" id="eye2"></i>
                                    </span>
                                    @error('new_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                {{-- Password Strength Bar --}}
                                <div class="mt-2" id="strengthWrapper" style="display:none;">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" id="strengthBar" role="progressbar" style="width:0%; transition: width 0.4s;"></div>
                                    </div>
                                    <small id="strengthText" class="mt-1 d-block"></small>
                                </div>
                            </div>

                            {{-- Confirm Password --}}
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary small text-uppercase">
                                    Confirm New Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bx bx-lock-open text-muted"></i>
                                    </span>
                                    <input type="password" name="new_password_confirmation" id="new_password_confirmation"
                                        class="form-control border-start-0 ps-0 placeholder-sm"
                                        placeholder="Re-enter new password">
                                    <span class="input-group-text bg-light border-start-0 cursor-pointer"
                                        onclick="togglePassword('new_password_confirmation', 'eye3')">
                                        <i class="bx bx-hide text-muted" id="eye3"></i>
                                    </span>
                                </div>
                            </div>

                            {{-- Buttons --}}
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-lg btn-primary fw-semibold"
                                    style="background: linear-gradient(135deg, #1a73e8 0%, #4a90d9 100%); border: none;">
                                    <i class="bx bx-check me-1"></i> Update Password
                                </button>
                                <a href="{{ route('setting.view.profile') }}" class="btn btn-lg fw-semibold"
                                    style="background-color: #6c757d; color: #fff; border: none;">
                                    <i class="bx bx-arrow-back me-1"></i> Cancel
                                </a>
                            </div>

                        </form>
                    </div>

                    {{-- Card Footer Tip --}}
                    <div class="card-footer bg-light border-0 text-center py-3">
                        <small class="text-muted">
                            <i class="bx bx-info-circle me-1"></i>
                            Use at least 8 characters with letters and numbers.
                        </small>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<style>
    .tracking-wide { letter-spacing: 0.05em; }
    .cursor-pointer { cursor: pointer; }
    .input-group-text { border-color: #dee2e6; }
    .form-control:focus { box-shadow: 0 0 0 0.2rem rgba(26,115,232,0.2); border-color: #1a73e8; }
    .placeholder-sm::placeholder { font-size: 0.8rem; color: #adb5bd; }
</style>

<script>
    function togglePassword(fieldId, iconId) {
        const field = document.getElementById(fieldId);
        const icon  = document.getElementById(iconId);
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.replace('bx-hide', 'bx-show');
        } else {
            field.type = 'password';
            icon.classList.replace('bx-show', 'bx-hide');
        }
    }

    function checkStrength(value) {
        const bar     = document.getElementById('strengthBar');
        const text    = document.getElementById('strengthText');
        const wrapper = document.getElementById('strengthWrapper');

        wrapper.style.display = value.length ? 'block' : 'none';

        let strength = 0;
        if (value.length >= 8)                        strength++;
        if (/[A-Z]/.test(value))                      strength++;
        if (/[0-9]/.test(value))                      strength++;
        if (/[^A-Za-z0-9]/.test(value))               strength++;

        const levels = [
            { width: '25%', color: '#dc3545', label: 'Weak' },
            { width: '50%', color: '#fd7e14', label: 'Fair' },
            { width: '75%', color: '#ffc107', label: 'Good' },
            { width: '100%', color: '#198754', label: 'Strong' },
        ];

        const level = levels[strength - 1] || levels[0];
        bar.style.width           = level.width;
        bar.style.backgroundColor = level.color;
        text.textContent          = 'Strength: ' + level.label;
        text.style.color          = level.color;
    }
</script>
@endsection

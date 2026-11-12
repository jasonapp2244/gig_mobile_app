@extends('layouts.admin')

@section('content')
@php
    $initials = collect(preg_split('/\s+/', trim((string) ($admin->name ?? ''))))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp
<div class="page-wrapper">
    <div class="page-content">
        <div class="row">
            {{-- Profile summary --}}
            <div class="col-lg-4">
                <div class="card radius-10">
                    <div class="gig-profile">
                        <div class="gig-profile-media {{ $admin->profile_image ? '' : 'no-photo' }}">
                            @if ($admin->profile_image)
                                <img src="{{ asset('storage/' . $admin->profile_image) }}" alt="{{ $admin->name }}"
                                    onerror="this.parentNode.classList.add('no-photo')">
                            @endif
                            <div class="gig-avatar-lg" aria-hidden="true">{{ $initials ?: 'A' }}</div>
                        </div>
                        <h5 class="gig-profile-name">{{ $admin->name ?? 'N/A' }}</h5>
                        <div class="gig-profile-sub">{{ $admin->email ?? 'N/A' }}</div>
                        <div class="d-flex justify-content-center gap-2 mt-3">
                            <span class="gig-chip gig-chip-brand"><i class='bx bx-shield-quarter'></i>Administrator</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Profile details --}}
            <div class="col-lg-8">
                <div class="card radius-10">
                    <div class="card-header d-flex align-items-center">
                        <div class="gig-card-head">
                            <span class="gig-card-icon"><i class='bx bx-user-circle'></i></span>
                            <div>
                                <h6>User Profile</h6>
                                <small>Your admin account details</small>
                            </div>
                        </div>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('setting.change.password') }}" class="btn btn-sm btn-outline-secondary">
                                <i class='bx bx-lock-alt'></i> Change Password
                            </a>
                            <a href="{{ route('setting.edit.profile', $admin->id) }}" class="btn btn-sm btn-primary">
                                <i class='bx bx-edit-alt'></i> Edit Profile
                            </a>
                        </div>
                    </div>
                    <div class="card-body pt-1 pb-2">
                        <dl class="gig-dl">
                            <div>
                                <dt>{{ trans('messages.user_name') }}</dt>
                                <dd>{{ $admin->name ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt>{{ trans('messages.user_email') }}</dt>
                                <dd>{{ $admin->email ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt>{{ trans('messages.user_phone') }}</dt>
                                <dd class="{{ $admin->phone_number ? '' : 'is-empty' }}">{{ $admin->phone_number ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt>Address</dt>
                                <dd class="{{ $admin->address_one ? '' : 'is-empty' }}">{{ $admin->address_one ?? 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

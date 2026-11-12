@extends('layouts.admin')
@section('content')
    @php
        $initials = collect(preg_split('/\s+/', trim((string) $user->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $isActive = $user->status === 'active';
    @endphp
    <div class="page-wrapper">
        <div class="page-content">
            <div class="row">
                {{-- Profile summary --}}
                <div class="col-lg-4">
                    <div class="card radius-10">
                        <div class="gig-profile">
                            <div class="gig-profile-media {{ $user->profile_image ? '' : 'no-photo' }}">
                                @if ($user->profile_image)
                                    <img src="{{ asset('storage/profile_images/' . $user->profile_image) }}" alt="{{ $user->name }}"
                                        onerror="this.parentNode.classList.add('no-photo')">
                                @endif
                                <div class="gig-avatar-lg" aria-hidden="true">{{ $initials ?: 'U' }}</div>
                            </div>
                            <h5 class="gig-profile-name">{{ $user->name }}</h5>
                            <div class="gig-profile-sub">{{ $user->email }}</div>
                            <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
                                <span class="gig-chip gig-chip-brand"><i class='bx bx-user'></i>{{ ucfirst($user->role) }}</span>
                                <span class="gig-chip {{ $isActive ? 'gig-chip-success' : 'gig-chip-danger' }}">
                                    <span class="dot"></span>{{ ucfirst($user->status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    {{-- Details --}}
                    <div class="card radius-10">
                        <div class="card-header">
                            <div class="gig-card-head">
                                <span class="gig-card-icon"><i class='bx bx-id-card'></i></span>
                                <div>
                                    <h6>{{ trans('messages.user_record') }}</h6>
                                    <small>Account details</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-1 pb-2">
                            <dl class="gig-dl">
                                <div>
                                    <dt>{{ trans('messages.name') }}</dt>
                                    <dd>{{ $user->name }}</dd>
                                </div>
                                <div>
                                    <dt>{{ trans('messages.email') }}</dt>
                                    <dd>{{ $user->email }}</dd>
                                </div>
                                <div>
                                    <dt>{{ trans('messages.phone') }}</dt>
                                    <dd class="{{ $user->phone_number ? '' : 'is-empty' }}">{{ $user->phone_number ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt>{{ trans('messages.joined_on') }}</dt>
                                    <dd>
                                        {{ $user->created_at
                                            ? $user->created_at->timezone(config('app.timezone'))->format('M d, Y h:i A')
                                            : trans('messages.never') }}
                                    </dd>
                                </div>
                                <div>
                                    <dt>{{ trans('messages.last_login') }}</dt>
                                    <dd>
                                        {{ $user->last_login_at
                                            ? $user->last_login_at->timezone(config('app.timezone'))->format('M d, Y – h:i A')
                                            : trans('messages.never') }}
                                    </dd>
                                </div>
                                <div>
                                    <dt>{{ trans('messages.role') }}</dt>
                                    <dd>{{ ucfirst($user->role) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {{-- Status --}}
                    <div class="card radius-10">
                        <div class="card-header">
                            <div class="gig-card-head">
                                <span class="gig-card-icon"><i class='bx bx-shield-quarter'></i></span>
                                <div>
                                    <h6>Account status</h6>
                                    <small>Inactive users cannot use the app</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.users.update', $user) }}" method="POST">
                                @csrf
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label" for="userStatus">Status</label>
                                        <select name="status" id="userStatus" class="form-select">
                                            <option value="active"
                                                {{ $user->status === 'active' ? 'selected' : '' }}>Active
                                            </option>
                                            <option value="inactive"
                                                {{ $user->status === 'inactive' ? 'selected' : '' }}>
                                                Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-7 d-flex flex-wrap gap-2 justify-content-md-end">
                                        <a href="{{ route('admin.users') }}" class="btn btn-outline-secondary">
                                            <i class='bx bx-arrow-back'></i> Back to Users
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class='bx bx-check'></i> Update Status
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

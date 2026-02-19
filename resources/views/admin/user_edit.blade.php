@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="card radius-10">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0">{{ trans('messages.user_record') }}</h6>
                        </div>
                    </div>
                </div>
                <div class="card-body">

                    <div class="card  border-0 rounded-3">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <!-- User Image on Left -->
                                <div class="col-md-4 text-center mb-3 mb-md-0">
                                    <img src="{{ asset('storage/profile_images/' . $user->profile_image) }}"
                                        alt="{{ $user->name }}" class="rounded-circle img-fluid shadow-sm"
                                        style="max-width: 150px;img.rounded-circle.img-fluid.shadow-sm  border: 4px solid #f1f1f1;  height: 150px; width: 150px;">
                                    <h5 class="mt-3 fw-bold">{{ $user->name }}</h5>
                                </div>
                                <!-- User Records on Right -->
                                <div class="col-md-8">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex">
                                                <label class="fw-bold me-2 w-50">{{ trans('messages.name') }}:</label>
                                                <p class="mb-0">{{ $user->name }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex">
                                                <label class="fw-bold me-2 w-50">{{ trans('messages.email') }}:</label>
                                                <p class="mb-0">{{ $user->email }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex">
                                                <label class="fw-bold me-2 w-50">{{ trans('messages.phone') }}:</label>
                                                <p class="mb-0">{{ $user->phone_number }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex">
                                                <label class="fw-bold me-2 w-50">{{ trans('messages.joined_on') }}:</label>
                                                <p class="mb-0">
                                                    {{ $user->created_at
                                                        ? $user->created_at->timezone(config('app.timezone'))->format('M d, Y h:i A')
                                                        : trans('messages.never') }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="d-flex">
                                                <label
                                                    class="fw-bold me-2 w-50">{{ trans('messages.last_login') }}:</label>
                                                <p class="mb-0">
                                                    {{ $user->last_login_at
                                                        ? $user->last_login_at->timezone(config('app.timezone'))->format('M d, Y – h:i A')
                                                        : trans('messages.never') }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="d-flex">
                                                <label class="fw-bold me-2 w-50">{{ trans('messages.role') }}:</label>
                                                <p class="mb-0">{{ ucfirst($user->role) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-md-3 ms-auto d-table">
                                        <form action="{{ route('admin.users.update', $user) }}" method="POST">
                                            @csrf
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Status</label>
                                                <select name="status" class="form-select">
                                                    <option value="active"
                                                        {{ $user->status === 'active' ? 'selected' : '' }}>Active
                                                    </option>
                                                    <option value="inactive"
                                                        {{ $user->status === 'inactive' ? 'selected' : '' }}>
                                                        Inactive</option>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Update Status</button>
                                            <a href="{{ route('admin.users') }}" class="btn btn-secondary ms-2">Back to
                                                Users</a>
                                        </form>
                                    </div>


                                </div> <!-- row -->
                            </div> <!-- card-body -->
                        </div> <!-- card -->
                    </div>
                    <!-- Status Update Dropdown Below -->
                </div>
            </div>
        </div>
    </div>
@endsection

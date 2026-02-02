@extends('layouts.admin')

@section('content')
<div class="page-wrapper">
    <div class="page-content">
        <div class="card radius-10">
            <div class="card-header">
                <h6 class="mb-0">User Profile</h6>
            </div>
            <div class="card-body">
                <div class="row align-items-center">

                    {{-- Profile Picture --}}
   <div class="col-md-3 text-center">
                     <img src="{{ $admin->profile_image ? asset('storage/'.$admin->profile_image) : asset('admin/profile_image/default.jpg') }}"
     alt="Admin"
     class="rounded-circle p-1"
     style="max-width:140px; height:140px; object-fit:cover;">


                    </div>


                    {{-- Profile Details --}}
                    <div class="col-md-9">
                        <div class="row mb-3">
                            <div class="col-sm-4 fw-bold">{{ trans('messages.user_name') }}:</div>
                            <div class="col-sm-8">{{ $admin->name ?? 'N/A' }}</div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-sm-4 fw-bold">{{ trans('messages.user_email') }}:</div>
                            <div class="col-sm-8">{{ $admin->email ?? 'N/A' }}</div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-sm-4 fw-bold">{{ trans('messages.user_phone') }}:</div>
                            <div class="col-sm-8">{{ $admin->phone_number ?? 'N/A' }}</div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-sm-4 fw-bold">Address:</div>
                            <div class="col-sm-8">{{ $admin->address_one ?? 'N/A' }}</div>
                        </div>

                        <a href="{{ route('setting.edit.profile', $admin->id) }}" class="btn btn-sm btn-primary">
                            Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

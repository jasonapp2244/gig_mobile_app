@extends('layouts.admin')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="card radius-10">
                <div class="card-header">
                    <h6 class="mb-0">Edit Profile</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('setting.update.profile') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <!-- Left side: Profile Image -->
                         <div class="col-md-6 text-center">
                            <div class="mb-3">
                                <img id="profileImagePreview"
                                     src="{{ $admin->profile_image ? asset('storage/'.$admin->profile_image) : asset('admin/images/default-avatar.png') }}"
                                     class="rounded-circle border"
                                     style="width: 150px; height: 150px; object-fit: cover;">
                            </div>
                            <div class="mb-3">
                                <label class="btn btn-outline-dark">
                                    change photo
                                    <input type="file" id="profileImageInput" name="profile_image" accept="image/*" hidden>
                                </label>
                            </div>
                        </div>
                            <!-- Right side: Profile Form -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">{{ trans('messages.user_name') }}</label>
                                    <input type="text" name="name" class="form-control"
                                        value="{{ old('name', $admin->name) }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ trans('messages.user_email') }}</label>
                                    <input type="email" name="email" class="form-control"
                                        value="{{ old('email', $admin->email) }}" disabled>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ trans('messages.user_phone') }}</label>
                                    <input type="text" name="phone" class="form-control"
                                        value="{{ old('phone_number', $admin->phone_number) }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ trans('messages.address') }}</label>
                                    <input type="text" name="address" class="form-control"
                                        value="{{ old('address_one', $admin->address_one) }}">
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">save change</button>
                                    <a href="{{ route('setting.view.profile') }}"
                                        class="btn btn-secondary">{{ trans('messages.cancel') }}</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JS: Live Preview for Profile Image -->
    <script>
        document.getElementById("profileImageInput").addEventListener("change", function(event) {
            let reader = new FileReader();
            reader.onload = function() {
                document.getElementById("profileImagePreview").src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        });
    </script>
@endsection

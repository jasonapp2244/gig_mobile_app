@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="card radius-10">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0">{{ trans('messages.recent_user_activities') }}</h6>
                        </div>

                    </div>
                </div>


                <div class="card-body">
                    <div class="table-responsive">
                        <table id="user_table" class="table table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ trans('messages.s_no') }}</th>
                                    <th>{{ trans('messages.user') }}</th>
                                    <th>{{ trans('messages.email') }}</th>
                                    <th>{{ trans('messages.join_on') }}</th>
                                    <th>{{ trans('messages.action') }}</th>
                                </tr>
                            </thead>
                            <tbody id="usersBody">
                                @foreach ($users as $index => $user)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->email ?? 'Social Login' }}</td>
                                        <td>
                                            {{ $user->created_at
                                                ? $user->created_at->timezone(config('app.timezone'))->format('M d, Y')
                                                : trans('messages.never') }}
                                        </td>
                                        <td>

                                            <a href="{{ url('admin/user', $user->id) }}"
                                                class="btn btn-sm btn-success">Edit</a>

                                            {{-- <i class="bx bx-edit"></i> --}}
                                            </a>
                                            @if ($user->status === 'active')
                                                <span class="badge bg-success">{{ trans('messages.unblock') }}</span>
                                            @else
                                                <span class="badge bg-danger">{{ trans('messages.block') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Refresh every 1 minute (60000ms) - testing
            setInterval(loadUsers, 60000);
        });
    </script>
@endpush

@extends('layouts.admin')


@section('content')
    <style>
        canvas#chart22 {
            width: 100% !important;
        }
    </style>
    <div class="page-wrapper">
        <div class="page-content">
            <div class="row">
                {{-- Chart (Left side) --}}
                {{-- Stats Cards (Right side) --}}
                <div class="col-md-6">
                    <div class="row row-cols-2">
                        <!-- Total Users -->
                        <div class="col mb-3">
                            <div class="card radius-10">
                                <div class="card-body d-flex align-items-center">
                                    <div>
                                        <p class="mb-0 text-secondary">{{ trans('messages.total_users') }}</p>
                                        <h4 class="my-1" id="totalUsers">{{ $users }}</h4>
                                        {{-- <h4 class="my-1" id="totalUsers">{{ $users }}</h4> --}}
                                    </div>
                                    <div class="widgets-icons bg-light-primary text-primary ms-auto">
                                        <i class="bx bxs-user"></i> <!-- User icon -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Support Query -->
                        <div class="col mb-3">
                            <div class="card radius-10">
                                <div class="card-body d-flex align-items-center">
                                    <div>
                                        <p class="mb-0 text-secondary">Total Support Query</p>
                                        <h4 class="my-1" id="totalSupport">{{ $total_support_email }}</h4>

                                    </div>
                                    <div class="widgets-icons bg-light-success text-success ms-auto">
                                        <i class="bx bxs-message-dots"></i> <!-- Message icon -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Support Read Query -->
                        <div class="col mb-3">
                            <div class="card radius-10">
                                <div class="card-body d-flex align-items-center">
                                    <div>
                                        <p class="mb-0 text-secondary">Total Support Read Query</p>
                                        <h4 class="my-1" id="totalReadSupport">{{ $total_read_email }}</h4>
                                    </div>
                                    <div class="widgets-icons bg-light-info text-info ms-auto">
                                        <i class="bx bxs-check-circle"></i> <!-- Read icon -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Support Pending Query -->
                        <div class="col mb-3">
                            <div class="card radius-10">
                                <div class="card-body d-flex align-items-center">
                                    <div>
                                        <p class="mb-0 text-secondary">Total Support Pending Query</p>
                                        <h4 class="my-1" id="totalPendingSupport">{{ $total_pending_email }}</h4>
                                    </div>
                                    <div class="widgets-icons bg-light-warning text-warning ms-auto">
                                        <i class="bx bxs-time-five"></i> <!-- Pending icon -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Tasks -->
                        <div class="col mb-3">
                            <div class="card radius-10">
                                <div class="card-body d-flex align-items-center">
                                    <div>
                                        <p class="mb-0 text-secondary">{{ trans('messages.total_tasks') }}</p>
                                        <h4 class="my-1" id="totalTasks">{{ $tasks }}</h4>
                                    </div>
                                    <div class="widgets-icons bg-light-secondary text-secondary ms-auto">
                                        <i class="bx bxs-task"></i> <!-- Task icon -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gig Workers -->
                        <div class="col mb-3">
                            <div class="card radius-10">
                                <div class="card-body d-flex align-items-center">
                                    <div>
                                        <p class="mb-0 text-secondary">{{ trans('messages.gig_workers') }}</p>
                                        <h4 class="my-1" id="totalEmployers">{{ $employers }}</h4>
                                    </div>
                                    <div class="widgets-icons bg-light-danger text-danger ms-auto">
                                        <i class="bx bxs-briefcase"></i> <!-- Gig/Work icon -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Revenue -->
                        <div class="col">
                            <div class="card radius-10">
                                <div class="card-body d-flex align-items-center">
                                    <div>
                                        <p class="mb-0 text-secondary">{{ trans('messages.revenue') }}</p>
                                        <h4 class="my-1" id="totalRevenue">${{ $task_payments }}</h4>
                                    </div>
                                    <div class="widgets-icons bg-light-warning text-warning ms-auto">
                                        <i class='bx bxs-dollar-circle'></i> <!-- Revenue icon -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card radius-10">
                        <div class="card-header">
                            <h6 class="mb-0">{{ trans('messages.total_active_users') }}</h6>
                        </div>
                        <div class="card-body">
                            <div style="height:400px;">
                                <canvas id="chart22"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent Activities (below full width) --}}
            <div class="card radius-10">
                <div class="card-header">
                    <h5 class="mb-0">Recent Activities</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Activity</th>
                                    <th>Time Ago</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody id="recentActivitiesTable">

                                {{-- First Load (From Controller) --}}
                                @foreach ($recent_activities as $activity)
                                    <tr>
                                        <td>{{ $activity->name }}</td>
                                        <td>{{ $activity->activity }}</td>
                                        <td>
                                            {{ \Carbon\Carbon::parse($activity->created_at)->timezone(config('app.timezone'))->diffForHumans() }}
                                        </td>
                                        <td>
                                            @if ($activity->activity_type === 'support_email')
                                                <span class="badge bg-warning">Support</span>
                                            @else
                                                @if ($activity->status === 'active')
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            {{ \Carbon\Carbon::parse($activity->created_at)->timezone(config('app.timezone'))->format('M d, Y') }}
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

<!-- Chart.js CDN -->
@push('scripts')
    <script>
        $(document).ready(function() {
            loadChartData();
            loadRecentActivities();
            updateRecentActivities();
            refreshDashboardData();
            setInterval(loadChartData, 4000);
            setInterval(loadRecentActivities, 5000);
            setInterval(updateRecentActivities, 6000);
            setInterval(refreshDashboardData, 7000);
        });
    </script>
@endpush

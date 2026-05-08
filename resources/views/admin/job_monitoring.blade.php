@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="card radius-10">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0">{{ trans('messages.job_monitoring') }}</h6>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="user_table" class="table table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ trans('messages.s_no') }}</th>
                                    <th>{{ trans('messages.job_id') }}</th>
                                    <th>Employer</th>
                                    <th>{{ trans('messages.post_date') }}</th>
                                    <th>{{ trans('messages.status') }}</th>
                                    <th>Payment</th>
                                    <th>{{ trans('messages.action') }}</th>
                                </tr>
                            </thead>
                            <tbody id="jobsBody">
                                @foreach ($jobs as $index => $job)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $job->id }}</td>
                                        <td>{{ $job->employer_name }}</td>
                                        <td>{{ $job->created_at?->format('Y-m-d') }}</td>
                                        <td>
                                            @php
                                                $statusColor = match($job->status) {
                                                    'completed'  => 'success',
                                                    'incomplete' => 'danger',
                                                    'ongoing'    => 'info',
                                                    'cancelled'  => 'secondary',
                                                    default      => 'warning',
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $statusColor }}">
                                                {{ ucfirst($job->status ?: 'N/A') }}
                                            </span>
                                        </td>
                                        <td>
                                            @if ($job->taskPayments->isNotEmpty())
                                                @php $lastPayment = $job->taskPayments->last(); @endphp
                                                <span class="badge bg-{{ $lastPayment->payment_status == 'paid' ? 'success' : 'danger' }}">
                                                    {{ $lastPayment->payment_status }}
                                                </span>
                                                <small class="d-block text-muted">${{ number_format($lastPayment->payment, 2) }}</small>
                                            @else
                                                <span class="badge bg-secondary">No Payment</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.jobs.show', $job->id) }}" class="btn btn-sm btn-dark">
                                                {{ trans('messages.view') }}
                                            </a>
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
            setInterval(loadJobs, 60000);
        });
    </script>
@endpush


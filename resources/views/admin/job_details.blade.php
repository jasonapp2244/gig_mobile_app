@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="card radius-10">
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0">{{ trans('messages.job_details') }}</h6>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card border-0 rounded-3">
                        <div class="card-body p-4">
                            <div class="row align-items-center">

                                {{-- Employer Details --}}
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">{{ trans('messages.user_name') }}:</label>
                                        <p class="mb-0">{{ $job->user->name ?? 'N/A' }}</p>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">{{ trans('messages.user_email') }}:</label>
                                        <p class="mb-0">{{ $job->user->email ?? 'N/A' }}</p>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">{{ trans('messages.user_phone') }}:</label>
                                        <p class="mb-0">{{ $job->user->phone_number ?? 'N/A' }}</p>
                                    </div>
                                </div>

                                {{-- <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">{{ trans('messages.employer_name') }}:</label>
                                        <p class="mb-0">{{ $job->employer->employer_name ?? 'N/A' }}</p>
                                    </div>
                                </div> --}}
                                {{-- Job Details --}}
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">Employer:</label>
                                        <p class="mb-0">{{ $job->employer_name }}</p>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">{{ trans('messages.job_type') }}:</label>
                                        <p class="mb-0">{{ $job->job_type ?? 'N/A' }}</p>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">{{ trans('messages.location') }}:</label>
                                        <p class="mb-0">{{ $job->location ?? 'N/A' }}</p>
                                    </div>
                                </div>

                                {{-- Task Start / End Time --}}
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">Task Start Time:</label>
                                        <p class="mb-0">
                                            {{ $job->task_date_time
                                                ? \Carbon\Carbon::parse($job->task_date_time)->format('M d, Y h:i A')
                                                : 'N/A' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">Task End Time:</label>
                                        <p class="mb-0">
                                            {{ $job->task_end_date_time
                                                ? \Carbon\Carbon::parse($job->task_end_date_time)->format('M d, Y h:i A')
                                                : 'N/A' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">{{ trans('messages.schedule_date') }}:</label>
                                        <p class="mb-0">
                                            {{ $job->created_at
                                                ? \Carbon\Carbon::parse($job->created_at)->timezone(config('app.timezone'))->format('M d, Y h:i A')
                                                : 'N/A' }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Task Status --}}
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">{{ trans('messages.task_status') }}:</label>
                                        <p class="mb-0">
                                            @if ($job->status === 'pending')
                                                <span
                                                    class="badge bg-warning text-dark">{{ trans('messages.pending') }}</span>
                                            @elseif ($job->status === 'completed')
                                                <span class="badge bg-success">{{ trans('messages.completed') }}</span>
                                            @elseif ($job->status === 'cancelled')
                                                <span class="badge bg-danger">{{ trans('messages.cancelled') }}</span>
                                            @else
                                                <span class="badge bg-info">{{ ucfirst($job->status) }}</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                {{-- Payment Status --}}
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex">
                                        <label class="fw-bold me-2 w-50">{{ trans('messages.payment_status') }}:</label>
                                        <p class="mb-0">
                                            @forelse ($job->taskPayments as $payment)
                                                @if ($payment->payment_status === 'paid')
                                                    <span class="badge bg-success">{{ trans('messages.paid') }}</span>
                                                @elseif ($payment->payment_status === 'pending')
                                                    <span
                                                        class="badge bg-warning text-dark">{{ trans('messages.pending') }}</span>
                                                @else
                                                    <span
                                                        class="badge bg-secondary">{{ ucfirst($payment->payment_status) }}</span>
                                                @endif
                                            @empty
                                                <span class="badge bg-secondary">{{ trans('messages.no_payment') }}</span>
                                            @endforelse
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <a type="button" class="btn btn-sm btn-primary"
                                href="{{ route('admin.jobMonitoring') }}">{{ trans('messages.back') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">

            {{-- Header --}}
            <div class="card radius-10">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    @if ($user->profile_image_url)
                        <img src="{{ $user->profile_image_url }}" alt="" class="rounded-circle" width="56" height="56"
                             style="object-fit:cover;" onerror="this.style.display='none'">
                    @endif
                    <div>
                        <h5 class="mb-0">{{ $user->name ?: $user->user_name }}</h5>
                        <small class="text-muted">{{ $user->email }}</small>
                    </div>
                    <div class="ms-auto text-end">
                        <div class="text-secondary small">Last used income tracker</div>
                        <div class="fw-semibold" id="itLastUsed">–</div>
                        <small class="text-muted" id="itUpdated"></small>
                    </div>
                    <a href="{{ route('admin.income-tracker.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
                </div>
            </div>

            {{-- Summary cards --}}
            @php
                $cards = [
                    'earned'            => ['Total earned', 'success'],
                    'pending_total'     => ['Pending (incl. owed)', 'warning'],
                    'paid'              => ['Paid', 'success'],
                    'received'          => ['Received', 'success'],
                    'owed'              => ['Owed', 'danger'],
                    'borrowed'          => ['Borrowed', 'secondary'],
                    'partial_paid'      => ['Partial — paid so far', 'info'],
                    'partial_remaining' => ['Partial — remaining', 'info'],
                    'return'            => ['Returned', 'dark'],
                    'entries'           => ['Entries', 'primary'],
                ];
            @endphp
            <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5">
                @foreach ($cards as $key => [$label, $color])
                    <div class="col mb-3">
                        <div class="card radius-10 mb-0 h-100 border-start border-3 border-{{ $color }}">
                            <div class="card-body">
                                <p class="mb-0 text-secondary small">{{ $label }}</p>
                                <h5 class="my-1" data-it-sum="{{ $key }}">–</h5>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Filters --}}
            <div class="card radius-10">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1" for="itRange">Date range</label>
                            <select id="itRange" class="form-select form-select-sm">
                                <option value="all">All time</option>
                                <option value="today">Today</option>
                                <option value="7">Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2 it-custom-range d-none">
                            <label class="form-label small mb-1" for="itFrom">From</label>
                            <input type="date" id="itFrom" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 col-md-2 it-custom-range d-none">
                            <label class="form-label small mb-1" for="itTo">To</label>
                            <input type="date" id="itTo" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1" for="itStatus">Entry status</label>
                            <select id="itStatus" class="form-select form-select-sm">
                                <option value="">All statuses</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1" for="itAction">Activity type</label>
                            <select id="itAction" class="form-select form-select-sm">
                                <option value="">All activity</option>
                                @foreach ($actions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                {{-- Activity timeline --}}
                <div class="col-12">
                    <div class="card radius-10">
                        <div class="card-header"><h6 class="mb-0">Activity timeline</h6></div>
                        <div class="card-body">
                            <div class="table-responsive" style="max-height:420px; overflow-y:auto;">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Activity</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itTimeline">
                                        <tr><td colspan="3" class="text-center text-muted">Loading…</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Income entries --}}
                <div class="col-12">
                    <div class="card radius-10">
                        <div class="card-header"><h6 class="mb-0">Income entries</h6></div>
                        <div class="card-body">
                            <div class="table-responsive" style="max-height:600px; overflow-y:auto;">
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Amount</th>
                                            <th>Paid</th>
                                            <th>Remaining</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Note</th>
                                            <th>Source</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itEntriesBody">
                                        <tr><td colspan="9" class="text-center text-muted">Loading…</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('admin/js/income-tracker.js') }}"></script>
    <script>
        $(function() {
            IncomeTracker.initUserDetail({
                url: "{{ route('admin.income-tracker.user-data', $user->id) }}",
                seconds: {{ $refreshSeconds }}
            });
        });
    </script>
@endpush

@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="card radius-10">
                <div class="card-header">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h6 class="mb-0">Income Tracker Users (<span id="itUserCount">0</span>)</h6>
                        <small class="text-muted" id="itUpdated"></small>
                        <input type="search" id="itUserSearch" class="form-control form-control-sm ms-auto"
                               style="max-width:260px;" placeholder="Search name or email">
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Last used</th>
                                    <th>Actions <small class="text-muted">(30d / total)</small></th>
                                    <th>Entries</th>
                                    <th>Earned</th>
                                    <th>Pending</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="itUsersBody">
                                <tr><td colspan="8" class="text-center text-muted">Loading…</td></tr>
                            </tbody>
                        </table>
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
            IncomeTracker.initUsers({
                url: "{{ route('admin.income-tracker.users-data') }}",
                seconds: {{ $refreshSeconds }}
            });
        });
    </script>
@endpush

@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="card radius-10">
                <div class="card-header">
                    <h6 class="mb-0">{{ trans('messages.support_tickets') }}</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="supportsTable" class="table table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>{{ trans('messages.name') }}</th>
                                    <th>{{ trans('messages.email') }}</th>
                                    <th>Subject</th>
                                    <th>{{ trans('messages.status') }}</th>
                                    <th>Email Date</th>
                                    <th>{{ trans('messages.action') }}</th>
                                </tr>
                            </thead>
                            <tbody id="supportsBody">
                                @foreach ($supports as $index => $support)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $support->name }}</td>
                                        <td><a href="mailto:{{ $support->email }}">{{ $support->email }}</a></td>
                                        <td>{{ $support->subject }}</td>
                                        <td>
                                            <span
                                                class="badge
                                                @if ($support->is_read == 1) bg-success
                                                @elseif ($support->status == 'sent') bg-warning
                                                @elseif ($support->status == 'open') bg-warning
                                                @else bg-secondary @endif">
                                                {{ $support->is_read == 1 ? 'Read' : ucfirst($support->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $support->created_at->timezone(config('app.timezone'))->format('d F Y') }}
                                        </td>
                                        <td> <a href="{{ route('support.show', $support->id) }}"
                                                class="btn btn-sm btn-dark"> {{ trans('messages.view') }} </a> </td>
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
            setInterval(loadSupports, 60000);
        });
    </script>
@endpush

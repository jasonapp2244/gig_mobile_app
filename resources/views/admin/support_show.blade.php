@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="row justify-content-center">
                <div class="col-xl-9">
                    <div class="card radius-10">
                        <div class="card-header d-flex align-items-center">
                            <div class="gig-card-head">
                                <span class="gig-card-icon"><i class='bx bx-envelope'></i></span>
                                <div>
                                    <h6>Support Mail Details</h6>
                                    <small>{{ $support->subject }}</small>
                                </div>
                            </div>
                            <a href="{{ route('support.index') }}" class="btn btn-sm btn-outline-secondary ms-auto">
                                <i class='bx bx-arrow-back'></i> {{ trans('messages.back') }}
                            </a>
                        </div>
                        <div class="card-body">
                            <dl class="gig-dl mb-3">
                                <div>
                                    <dt>{{ trans('messages.name') }}</dt>
                                    <dd>{{ $support->name }}</dd>
                                </div>
                                <div>
                                    <dt>{{ trans('messages.email') }}</dt>
                                    <dd>
                                        <a href="https://mail.google.com/mail/?view=cm&fs=1&to={{ $support->email }}"
                                            target="_blank" rel="noopener">{{ $support->email }}</a>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Subject</dt>
                                    <dd>{{ $support->subject }}</dd>
                                </div>
                            </dl>

                            <div class="gig-section-title">Message</div>
                            <div class="gig-message">{{ $support->message }}</div>

                            @if (session('success'))
                                <div class="alert alert-success mt-4 mb-0">{{ session('success') }}</div>
                            @endif
                            @if (session('error'))
                                <div class="alert alert-danger mt-4 mb-0">{{ session('error') }}</div>
                            @endif

                            @if ($support->response)
                                <div class="gig-section-title mt-4">
                                    Last reply sent
                                    @if ($support->responded_at)
                                        <small class="text-muted fw-normal">
                                            ({{ $support->responded_at->timezone(config('app.timezone'))->format('d F Y, h:i A') }})
                                        </small>
                                    @endif
                                </div>
                                <div class="gig-message" style="white-space: pre-line;">{{ $support->response }}</div>
                            @endif

                            {{-- Reply from the panel: emailed to the customer, Reply-To the support mailbox --}}
                            <form action="{{ route('support.respond', $support->id) }}" method="POST" class="mt-4">
                                @csrf
                                <label for="support-response" class="gig-section-title d-block">
                                    Reply to {{ $support->email }}
                                </label>
                                <textarea id="support-response" name="response" rows="5" maxlength="5000" required
                                    class="form-control @error('response') is-invalid @enderror"
                                    placeholder="Write your reply. It is emailed to the customer; their reply comes to {{ config('mail.support.address') }}.">{{ old('response') }}</textarea>
                                @error('response')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <button type="submit" class="btn btn-success mt-2">
                                    <i class='bx bx-send'></i> Send reply
                                </button>
                            </form>

                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <a href="https://mail.google.com/mail/?view=cm&fs=1&to={{ $support->email }}"
                                    target="_blank" rel="noopener" class="btn btn-primary">
                                    <i class='bx bx-reply'></i> Reply by email
                                </a>
                                <a href="{{ route('support.index') }}" class="btn btn-outline-secondary">
                                    {{ trans('messages.back') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

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

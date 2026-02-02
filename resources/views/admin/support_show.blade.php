@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="card radius-10 col-lg-6">
                <div class="card-header">
                    <h6 class="mb-0">Support Mail Details</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <strong>{{ trans('messages.name') }}: </strong> {{ $support->name }}
                            </div>
                            <div class="mb-3">
                                <strong>{{ trans('messages.email') }}: </strong>
                                <a href="https://mail.google.com/mail/?view=cm&fs=1&to={{ $support->email }}"
                                    target="_blank" style="text-decoration: underline;">
                                    {{ $support->email }}
                                </a>

                            </div>
                            <div class="mb-3">
                                <strong>Subject:</strong> {{ $support->subject }}
                            </div>
                            <div class="mb-3">
                                <strong>Message:</strong>
                                <p>{{ $support->message }}</p>
                            </div>
                        </div>
                    </div>
                    {{-- Response Form --}}
                    {{-- <form method="POST" action="{{ route('admin.support.respond', $support->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ trans('messages.response') }}</label>
                            <textarea name="response" class="form-control" rows="4" required>{{ $support->response }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ trans('messages.send_response') }}</button>
                        <a href="{{ route('admin.support.index') }}"
                            class="btn btn-secondary">{{ trans('messages.back') }}</a>
                    </form> --}}

                    <div class="mt-3">
                        <a href="{{ route('support.index') }}" class="btn btn-primary">
                            {{ trans('messages.back') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

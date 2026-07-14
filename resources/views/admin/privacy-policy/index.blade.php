@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">

            <!-- Breadcrumb -->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Privacy Policy</div>
            </div>

            <!-- Flash Messages -->
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show mt-3 shadow-sm rounded" role="alert">
                    <i class="bx bx-check-circle me-2"></i>
                    <strong>Success!</strong> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show mt-3 shadow-sm rounded" role="alert">
                    <i class="bx bx-x-circle me-2"></i>
                    <strong>Error!</strong> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show mt-3 shadow-sm rounded" role="alert">
                    <i class="bx bx-x-circle me-2"></i>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="row">
                <!-- Left Column: Form -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">{{ $policy ? 'Update Policy' : 'Publish Policy' }}</h5>

                            <form action="{{ route('admin.privacy-policy.store') }}" method="POST">
                                @csrf

                                <div class="mb-3">
                                    <label for="title" class="form-label">Title</label>
                                    <input type="text" name="title" id="title" class="form-control"
                                        value="{{ old('title', $policy->title ?? '') }}"
                                        placeholder="Enter policy title" required>
                                </div>

                                <div class="mb-3">
                                    <label for="effective_date" class="form-label">Effective Date</label>
                                    <input type="date" name="effective_date" id="effective_date" class="form-control"
                                        value="{{ old('effective_date', $policy && $policy->effective_date ? $policy->effective_date->format('Y-m-d') : '') }}">
                                </div>

                                <div class="mb-3">
                                    <label for="content" class="form-label">Content</label>
                                    <textarea name="content" id="content" class="form-control" rows="16"
                                        placeholder="Enter privacy policy content" required>{{ old('content', $policy->content ?? '') }}</textarea>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-save me-1"></i>
                                    {{ $policy ? 'Update Policy' : 'Publish Policy' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Info Card -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Current Policy</h5>

                            @if ($policy)
                                <div class="mb-3">
                                    <label class="form-label text-muted mb-1">Title</label>
                                    <p class="fw-bold">{{ $policy->title }}</p>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-muted mb-1">Status</label>
                                    <div>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-muted mb-1">Effective Date</label>
                                    <p>{{ $policy->effective_date ? $policy->effective_date->format('d F Y') : 'Not set' }}</p>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-muted mb-1">Last Updated</label>
                                    <p>{{ $policy->updated_at->format('d F Y, h:i A') }}</p>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="bx bx-file text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">No privacy policy published yet.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

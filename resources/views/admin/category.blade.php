@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">

            <!-- Breadcrumb -->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">{{ trans('messages.category') }}</div>
                {{-- <a type="button" class="btn btn-sm btn-primary"   href="{{ route('admin.categories') }}"
                                                class="btn btn-sm btn-success"><i class="bx bx-plus"></i> {{ trans('messages.add_category') }}</a> --}}

            </div>
            <!-- Flash Messages -->
            {{-- Success Message --}}
            {{-- @if (session('success'))
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
            @endif --}}

            <!-- Add Category Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">{{ isset($editCategory) ? trans('messages.edit_category') : trans('messages.add_new_category') }}</h5>

                    <form
                        action="{{ isset($editCategory) ? route('admin.categories.update', $editCategory->id) : route('admin.categories.store') }}"
                        method="POST">

                        @csrf
                        @if (isset($editCategory))
                            @method('POST') {{-- you can also use PUT if your route is put --}}
                        @endif

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ trans('messages.category_title') }}</label>
                                <input type="text" name="category" class="form-control"
                                    value="{{ $editCategory->category ?? old('category') }}"
                                    placeholder="{{ trans('messages.enter_category_title') }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ trans('messages.status') }}</label>
                                <select name="status" class="form-select" required>
                                    <option value="active"
                                        {{ isset($editCategory) && $editCategory->status == 1 ? 'selected' : '' }}>
                                        {{ trans('messages.active') }}</option>
                                    <option value="inactive"
                                        {{ isset($editCategory) && $editCategory->status == 0 ? 'selected' : '' }}>
                                        {{ trans('messages.inactive') }}</option>
                                </select>
                            </div>

                            <div class="col-md-1 mb-3 d-flex align-items-end">
                                <button type="submit"
                                    class="btn btn-primary w-100 d-flex align-items-center justify-content-center">
                                    <i class="bx circle me-2"></i>
                                    {{ isset($editCategory) ? trans('messages.update') : trans('messages.save') }}
                                </button>
                            </div>

                        </div>
                    </form>
                </div>
            </div>
            <!-- Category List -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">{{ trans('messages.category_list') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>{{ trans('messages.title') }}</th>
                                    <th>{{ trans('messages.status') }}</th>
                                    <th>{{ trans('messages.created_at') }}</th>
                                    <th>{{ trans('messages.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $key => $category)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $category->category }}</td>
                                        <td>
                                            <span class="badge {{ $category->status == 1 ? 'bg-success' : 'bg-warning' }}">
                                                {{ $category->status == 1 ? trans('messages.active') : trans('messages.inactive') }}
                                            </span>

                                        </td>
                                         <td>
                                            {{ $category->created_at->timezone(config('app.timezone'))->format('d F Y') }}
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.categories.edit', $category->id) }}"
                                                class="btn btn-sm btn-success">{{ trans('messages.edit') }}</a>
                                            {{-- <form action="{{ route('admin.categories.delete', $category->id) }}"
                                                method="POST" style="display:inline;">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Delete this category?')">Delete</button>
                                            </form> --}}
                                            <!-- Delete Button -->
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                                data-bs-target="#deleteModal{{ $category->id }}">
                                                <i class="bx bx-trash"></i> {{ trans('messages.delete') }}
                                            </button>

                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal{{ $category->id }}" tabindex="-1"
                                                aria-labelledby="deleteModalLabel{{ $category->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title"
                                                                id="deleteModalLabel{{ $category->id }}">
                                                                <i class="bx bx-trash me-2"></i> {{ trans('messages.confirm_delete') }}
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white"
                                                                data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>

                                                        <div class="modal-body">
                                                            {{ trans('messages.are_you_sure_delete') }}
                                                            <strong>{{ $category->category }}</strong>?
                                                            {{ trans('messages.this_action_cannot_undone') }}
                                                        </div>

                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">
                                                                {{ trans('messages.cancel') }}
                                                            </button>

                                                            <!-- Delete Form -->
                                                            <form
                                                                action="{{ route('admin.categories.delete', $category->id) }}"
                                                                method="POST">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-danger">
                                                                    {{ trans('messages.yes_delete') }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </td>
                                    </tr>
                                @endforeach
                                @if ($categories->isEmpty())
                                    <tr>
                                        <td colspan="5" class="text-center">{{ trans('messages.no_categories_found') }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

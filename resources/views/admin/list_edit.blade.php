@extends('layouts.admin')

@section('content')
<div class="page-wrapper">
    <div class="page-content">
        <div class="row justify-content-center">
            <div class="col-lg-9">

                <div class="card radius-10">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="mb-0"><i class='bx bx-edit me-1'></i> Edit List</h6>
                            <a href="{{ route('admin.list.index') }}" class="btn btn-sm btn-secondary">
                                <i class='bx bx-arrow-back'></i> Back to Lists
                            </a>
                        </div>
                    </div>

                    <div class="card-body">

                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('admin.list.update', $list->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title"
                                           class="form-control @error('title') is-invalid @enderror"
                                           value="{{ old('title', $list->title) }}" required>
                                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                    <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                        <option value="">-- Select Category --</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}"
                                                {{ old('category_id', $list->category_id) == $cat->id ? 'selected' : '' }}>
                                                {{ $cat->category }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" name="new_price"
                                               class="form-control @error('new_price') is-invalid @enderror"
                                               value="{{ old('new_price', $list->new_price) }}" placeholder="0.00">
                                    </div>
                                    @error('new_price')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Condition <span class="text-danger">*</span></label>
                                    <select name="condition" class="form-select @error('condition') is-invalid @enderror" required>
                                        <option value="">-- Select --</option>
                                        @foreach(['new', 'used'] as $cond)
                                            <option value="{{ $cond }}"
                                                {{ old('condition', $list->condition) === $cond ? 'selected' : '' }}>
                                                {{ ucfirst($cond) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('condition')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Location <span class="text-danger">*</span></label>
                                    <input type="text" name="location"
                                           class="form-control @error('location') is-invalid @enderror"
                                           value="{{ old('location', $list->location) }}" required>
                                    @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-select @error('status') is-invalid @enderror">
                                        <option value="active"   {{ old('status', $list->status ?? 'active') === 'active'   ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status', $list->status ?? 'active') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" rows="3"
                                              class="form-control @error('description') is-invalid @enderror">{{ old('description', $list->description) }}</textarea>
                                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                {{-- ── Existing Saved Images (with individual delete) ── --}}
                                @if($list->images->isNotEmpty())
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Current Images</label>
                                    <div class="d-flex flex-wrap gap-2" id="currentImages">
                                        @foreach($list->images as $img)
                                        <div class="position-relative" id="imgWrap-{{ $img->id }}"
                                             style="width:100px;height:100px;flex-shrink:0;">
                                            <img src="/storage/{{ $img->path }}"
                                                 alt="{{ $img->image_name }}"
                                                 class="rounded border w-100 h-100"
                                                 style="object-fit:cover;"
                                                 onerror="this.parentElement.querySelector('.img-fallback').style.display='flex';this.style.display='none';">
                                            {{-- fallback when image file missing --}}
                                            <div class="img-fallback rounded border bg-light w-100 h-100 align-items-center justify-content-center"
                                                 style="display:none;">
                                                <i class='bx bx-image text-muted' style="font-size:2rem;"></i>
                                            </div>
                                            {{-- Delete button — URL passed via data attribute for reliability --}}
                                            <button type="button"
                                                    class="btn btn-danger btn-sm d-flex align-items-center justify-content-center position-absolute top-0 end-0 btn-delete-img"
                                                    style="width:22px;height:22px;border-radius:50%;padding:0;font-size:12px;z-index:10;"
                                                    data-image-id="{{ $img->id }}"
                                                    data-delete-url="{{ route('admin.list.image.destroy', $img->id) }}"
                                                    title="Remove this image">
                                                <i class='bx bx-x'></i>
                                            </button>
                                        </div>
                                        @endforeach
                                    </div>
                                    <small class="text-muted">
                                        Click <i class='bx bx-x text-danger'></i> to delete a saved image.
                                        Upload below to add more images.
                                    </small>
                                </div>
                                @endif

                                {{-- ── Upload New Images (simple preview, no remove) ── --}}
                                <div class="col-12">
                                    <label class="form-label fw-semibold">
                                        Add / Replace Images
                                        <small class="text-muted fw-normal">(max 3 — jpg/jpeg/png, 2MB each)</small>
                                    </label>
                                    <input type="file" name="images[]" id="imageInput"
                                           class="form-control @error('images') is-invalid @enderror"
                                           multiple accept="image/jpg,image/jpeg,image/png">
                                    <small class="text-muted">Leave empty to keep current images unchanged.</small>
                                    @error('images')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                    {{-- Live preview --}}
                                    <div id="imagePreview" class="d-flex flex-wrap gap-2 mt-3"></div>
                                </div>

                            </div>

                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class='bx bx-save'></i> Update List
                                </button>
                                <a href="{{ route('admin.list.index') }}" class="btn btn-secondary px-4">Cancel</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {

    // ── Delete a saved image via AJAX (event delegation — works with dynamic DOM) ──
    $(document).on('click', '.btn-delete-img', function () {
        var btn      = $(this);
        var imageId  = btn.data('image-id');
        var delUrl   = btn.data('delete-url');
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        Swal.fire({
            title: 'Delete this image?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin" style="font-size:11px;"></i>');

            $.ajax({
                url:     delUrl,
                method:  'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                success: function (res) {
                    if (res.success) {
                        $('#imgWrap-' + imageId).fadeOut(300, function () { $(this).remove(); });
                        Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Image removed.', timer: 1500, showConfirmButton: false });
                    }
                },
                error: function (xhr) {
                    btn.prop('disabled', false).html('<i class="bx bx-x"></i>');
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Could not delete image. Please try again.' });
                }
            });
        });
    });

    // ── Simple preview for new uploads (no remove button on edit form) ────────
    document.getElementById('imageInput').addEventListener('change', function () {
        var preview = document.getElementById('imagePreview');
        preview.innerHTML = '';
        var MAX   = 3;
        var count = 0;

        Array.from(this.files).forEach(function (file) {
            if (!file.type.startsWith('image/') || count >= MAX) return;
            count++;
            var reader = new FileReader();
            reader.onload = function (e) {
                var div = document.createElement('div');
                div.style.cssText = 'width:100px;height:100px;flex-shrink:0;';
                div.innerHTML = '<img src="' + e.target.result + '" class="rounded border w-100 h-100" style="object-fit:cover;">';
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });

        if (this.files.length > MAX) {
            var warn = document.createElement('small');
            warn.className = 'text-warning d-block mt-1 w-100';
            warn.textContent = 'Only the first ' + MAX + ' images will be uploaded.';
            preview.appendChild(warn);
        }
    });

});
</script>
@endpush

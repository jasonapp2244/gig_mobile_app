@extends('layouts.admin')

@section('content')
<div class="page-wrapper">
    <div class="page-content">
        <div class="row justify-content-center">
            <div class="col-lg-9">

                <div class="card radius-10">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="mb-0"><i class='bx bx-plus me-1'></i> Add New List</h6>
                            <a href="{{ route('admin.list.index') }}" class="btn btn-sm btn-secondary">
                                <i class='bx bx-arrow-back'></i> Back to Lists
                            </a>
                        </div>
                    </div>

                    <div class="card-body">

                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('admin.list.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title"
                                           class="form-control @error('title') is-invalid @enderror"
                                           value="{{ old('title') }}" placeholder="Enter list title" required>
                                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                    <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                        <option value="">-- Select Category --</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
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
                                               value="{{ old('new_price') }}" placeholder="0.00">
                                    </div>
                                    @error('new_price')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Condition <span class="text-danger">*</span></label>
                                    <select name="condition" class="form-select @error('condition') is-invalid @enderror" required>
                                        <option value="">-- Select --</option>
                                        @foreach(['new', 'used'] as $cond)
                                            <option value="{{ $cond }}" {{ old('condition') === $cond ? 'selected' : '' }}>
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
                                           value="{{ old('location') }}" placeholder="Enter location" required>
                                    @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-select @error('status') is-invalid @enderror">
                                        <option value="active"   {{ old('status', 'active') === 'active'   ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" rows="3"
                                              class="form-control @error('description') is-invalid @enderror"
                                              placeholder="Enter description...">{{ old('description') }}</textarea>
                                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                {{-- Images --}}
                                <div class="col-12">
                                    <label class="form-label fw-semibold">
                                        Images
                                        <small class="text-muted fw-normal">(max 3 — jpg/jpeg/png, 2MB each)</small>
                                    </label>
                                    <input type="file" name="images[]" id="imageInput"
                                           class="form-control @error('images') is-invalid @enderror"
                                           multiple accept="image/jpg,image/jpeg,image/png">
                                    @error('images')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                    {{-- Live preview thumbnails --}}
                                    <div id="imagePreview" class="d-flex flex-wrap gap-2 mt-3"></div>
                                </div>

                            </div>

                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class='bx bx-save'></i> Save List
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
    // ── Add form: image picker WITH per-image remove (X) button ───────────────
    const MAX_IMG = 3;
    let selectedFiles = [];
    const imgInput = document.getElementById('imageInput');

    imgInput.addEventListener('change', function () {
        Array.from(this.files).forEach(function (file) {
            if (!file.type.startsWith('image/')) return;
            if (selectedFiles.length >= MAX_IMG) return;
            selectedFiles.push(file);
        });
        this.value = ''; // reset so same file can be reselected
        syncInput();
        renderPreviews();
    });

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        syncInput();
        renderPreviews();
    }

    function syncInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(function (f) { dt.items.add(f); });
        imgInput.files = dt.files;
        // Disable picker when max reached
        imgInput.disabled = selectedFiles.length >= MAX_IMG;
    }

    function renderPreviews() {
        const preview = document.getElementById('imagePreview');
        preview.innerHTML = '';

        selectedFiles.forEach(function (file, i) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const wrap = document.createElement('div');
                wrap.className = 'position-relative';
                wrap.style.cssText = 'width:100px;height:100px;flex-shrink:0;';
                wrap.innerHTML = `
                    <img src="${e.target.result}"
                         class="rounded border w-100 h-100"
                         style="object-fit:cover;">
                    <button type="button"
                            onclick="removeFile(${i})"
                            class="btn btn-danger d-flex align-items-center justify-content-center position-absolute top-0 end-0"
                            style="width:22px;height:22px;border-radius:50%;padding:0;font-size:12px;line-height:1;"
                            title="Remove">
                        <i class='bx bx-x'></i>
                    </button>`;
                preview.appendChild(wrap);
            };
            reader.readAsDataURL(file);
        });

        // Count label
        if (selectedFiles.length > 0) {
            const label = document.createElement('small');
            label.className = 'text-muted d-block mt-1 w-100';
            label.textContent = `${selectedFiles.length} / ${MAX_IMG} image${selectedFiles.length > 1 ? 's' : ''} selected`;
            preview.appendChild(label);
        }
    }
</script>
@endpush

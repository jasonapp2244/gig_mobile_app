@extends('layouts.admin')

@section('content')
<div class="page-wrapper">
    <div class="page-content">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card radius-10">
            <div class="card-header">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">
                        <i class='bx bx-list-ul me-1'></i> List Management
                    </h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-secondary" id="lastRefreshed">
                            <i class='bx bx-time-five'></i> Last refreshed: Just now
                        </span>
                        <button class="btn btn-sm btn-outline-primary" id="manualRefreshBtn" onclick="loadLists()">
                            <i class='bx bx-refresh'></i> Refresh
                        </button>
                        <a href="{{ route('admin.list.create') }}" class="btn btn-sm btn-primary">
                            <i class='bx bx-plus'></i> Add List
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table id="list_table" class="table table-striped table-bordered align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Image</th>
                                <th>Title</th>
                                <th>User</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Location</th>
                                <th>Posted On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="listsBody">
                            @foreach ($lists as $index => $list)
                                @php
                                    $firstImg = $list->images->first();
                                    $imgSrc   = $firstImg ? '/storage/' . $firstImg->path : null;
                                @endphp
                                <tr id="row-{{ $list->id }}">
                                    <td>{{ $index + 1 }}</td>

                                    <td>
                                        <div class="position-relative" style="width:52px;height:52px;cursor:pointer;"
                                             onclick="viewList({{ $list->id }})">
                                            @if($imgSrc)
                                                <img src="{{ $imgSrc }}" alt=""
                                                     class="rounded w-100 h-100"
                                                     style="object-fit:cover;"
                                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                            @endif
                                            <div class="rounded bg-light w-100 h-100 align-items-center justify-content-center"
                                                 style="{{ $imgSrc ? 'display:none;' : 'display:flex;' }}position:absolute;top:0;left:0;">
                                                <i class='bx bx-image text-muted' style="font-size:1.4rem;"></i>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="fw-semibold">{{ $list->title }}</td>
                                    <td>{{ optional($list->user)->name ?? 'Admin' }}</td>
                                    <td>{{ optional($list->category)->category ?? 'N/A' }}</td>
                                    <td>{{ $list->new_price ? '$'.number_format($list->new_price, 2) : '—' }}</td>
                                    <td>{{ $list->location }}</td>
                                    <td>{{ $list->created_at?->format('M d, Y') }}</td>

                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-info text-white" title="View"
                                                    onclick="viewList({{ $list->id }})">
                                                <i class='bx bx-show'></i>
                                            </button>
                                            <a href="{{ route('admin.list.edit', $list->id) }}"
                                               class="btn btn-sm btn-success" title="Edit">
                                                <i class='bx bx-edit'></i>
                                            </a>
                                            <button class="btn btn-sm btn-danger" title="Delete"
                                                    onclick="confirmDelete({{ $list->id }})">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- View Modal --}}
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class='bx bx-show me-1'></i> List Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-info" role="status"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class='bx bx-trash'></i> Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this list? All associated images will also be removed.
                This action <strong>cannot be undone</strong>.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class='bx bx-trash'></i> Yes, Delete
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // ── Route URL helpers (works for any XAMPP subdirectory) ──────────────────
    const FETCH_URL  = '{{ route("admin.list.fetch") }}';
    const SHOW_URL   = '{{ route("admin.list.show",         ["id" => "__ID__"]) }}';
    const DELETE_URL = '{{ route("admin.list.destroy",      ["id" => "__ID__"]) }}';

    const REFRESH_INTERVAL = 5 * 60 * 1000;
    let deleteListId = null;
    let autoRefreshTimer = null;

    // ── Init ──────────────────────────────────────────────────────────────────
    $(document).ready(function () {
        initDataTable();
        startAutoRefresh();
    });

    // ── DataTable — no scrollX, let Bootstrap table-responsive handle overflow ─
    function initDataTable() {
        if ($.fn.DataTable.isDataTable('#list_table')) {
            $('#list_table').DataTable().destroy();
        }
        return $('#list_table').DataTable({
            responsive: false,
            autoWidth: false,
            order: [[7, 'desc']],
            pageLength: 10,
            columnDefs: [
                { orderable: false, targets: [1, 8] },
                { width: '40px',  targets: [0] },
                { width: '70px',  targets: [1] },
                { width: '100px', targets: [5] },
                { width: '100px', targets: [7] },
                { width: '120px', targets: [8] }
            ],
            language: {
                search: "Search:",
                emptyTable: "No lists found."
            }
        });
    }

    // ── Auto Refresh ──────────────────────────────────────────────────────────
    function startAutoRefresh() {
        if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        autoRefreshTimer = setInterval(loadLists, REFRESH_INTERVAL);
    }

    function loadLists() {
        $('#manualRefreshBtn').html('<i class="bx bx-loader-alt bx-spin"></i> Loading...');

        $.ajax({
            url: FETCH_URL,
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (response) {
                if (typeof response !== 'object' || !response.success) return;

                let html = '';
                response.lists.forEach(function (list) {
                    const price = list.new_price
                        ? '$' + parseFloat(list.new_price).toFixed(2) : '—';

                    // Image cell — identical structure to Blade partial
                    const imgHtml = list.image_url
                        ? `<img src="${list.image_url}" alt="" class="rounded w-100 h-100"
                               style="object-fit:cover;"
                               onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">`
                        : '';
                    const imgCell = `
                        <div class="position-relative" style="width:52px;height:52px;cursor:pointer;"
                             onclick="viewList(${list.id})">
                            ${imgHtml}
                            <div class="rounded bg-light w-100 h-100 align-items-center justify-content-center"
                                 style="${list.image_url ? 'display:none;' : 'display:flex;'}position:absolute;top:0;left:0;">
                                <i class='bx bx-image text-muted' style='font-size:1.4rem;'></i>
                            </div>
                        </div>`;

                    html += `
                    <tr id="row-${list.id}">
                        <td>${list.index}</td>
                        <td>${imgCell}</td>
                        <td class="fw-semibold">${list.title}</td>
                        <td>${list.posted_by}</td>
                        <td>${list.category}</td>
                        <td>${price}</td>
                        <td>${list.location}</td>
                        <td>${list.created_at}</td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-info text-white" title="View"
                                        onclick="viewList(${list.id})">
                                    <i class='bx bx-show'></i>
                                </button>
                                <a href="${list.edit_url}" class="btn btn-sm btn-success" title="Edit">
                                    <i class='bx bx-edit'></i>
                                </a>
                                <button class="btn btn-sm btn-danger" title="Delete"
                                        onclick="confirmDelete(${list.id})">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
                });

                if ($.fn.DataTable.isDataTable('#list_table')) {
                    $('#list_table').DataTable().destroy();
                }
                $('#listsBody').html(html);
                initDataTable();

                const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                $('#lastRefreshed').html(`<i class='bx bx-time-five'></i> Last refreshed: ${timeStr}`);
            },
            error: function (xhr) {
                if (xhr.status === 401 || (xhr.responseURL && xhr.responseURL.includes('/login'))) {
                    clearInterval(autoRefreshTimer);
                    showToast('Session expired. Please <a href="/login" class="text-white fw-bold">log in again</a>.', 'warning', 8000);
                }
            },
            complete: function () {
                $('#manualRefreshBtn').html('<i class="bx bx-refresh"></i> Refresh');
            }
        });
    }

    // ── View Modal ────────────────────────────────────────────────────────────
    function viewList(id) {
        $('#viewModalBody').html(
            '<div class="text-center py-4"><div class="spinner-border text-info" role="status"></div></div>'
        );
        bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal')).show();

        $.ajax({
            url: SHOW_URL.replace('__ID__', id),
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (res) {
                if (!res.success) {
                    $('#viewModalBody').html('<div class="alert alert-warning">No data found.</div>');
                    return;
                }
                const l = res.list;

                let imagesHtml = '';
                if (l.all_images && l.all_images.length) {
                    l.all_images.forEach(function (url) {
                        imagesHtml += `
                            <div class="position-relative" style="width:110px;height:110px;flex-shrink:0;">
                                <img src="${url}" class="rounded border w-100 h-100"
                                     style="object-fit:cover;"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div class="rounded border bg-light w-100 h-100 align-items-center justify-content-center"
                                     style="display:none;position:absolute;top:0;left:0;">
                                    <i class='bx bx-image text-muted' style='font-size:2rem;'></i>
                                </div>
                            </div>`;
                    });
                } else {
                    imagesHtml = '<span class="text-muted fst-italic">No images</span>';
                }

                const price = l.new_price ? '$' + parseFloat(l.new_price).toFixed(2) : '—';

                $('#viewModalBody').html(`
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-2">${imagesHtml}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Title</small>
                            <span class="fw-semibold">${l.title}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Posted By</small>
                            <span>${l.posted_by}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Category</small>
                            <span>${l.category}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Price</small>
                            <span class="fw-semibold text-success">${price}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Location</small>
                            <span>${l.location}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Condition</small>
                            <span>${l.condition ?? '—'}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Posted On</small>
                            <span>${l.created_at}</span>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">Description</small>
                            <p class="mb-0 mt-1">${l.description ?? '<span class="text-muted fst-italic">No description</span>'}</p>
                        </div>
                    </div>`);
            },
            error: function () {
                $('#viewModalBody').html('<div class="alert alert-danger">Failed to load details. Please try again.</div>');
            }
        });
    }

    // ── Delete ────────────────────────────────────────────────────────────────
    function confirmDelete(id) {
        deleteListId = id;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
    }

    $('#confirmDeleteBtn').on('click', function () {
        if (!deleteListId) return;
        const btn = $(this);
        btn.html('<i class="bx bx-loader-alt bx-spin"></i> Deleting...').prop('disabled', true);

        $.ajax({
            url: DELETE_URL.replace('__ID__', deleteListId),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (res) {
                if (res.success) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).hide();
                    $(`#row-${deleteListId}`).fadeOut(400, function () { $(this).remove(); });
                    showToast('List deleted successfully.', 'success');
                    deleteListId = null;
                }
            },
            error: function () { showToast('Failed to delete. Please try again.', 'danger'); },
            complete: function () {
                btn.html('<i class="bx bx-trash"></i> Yes, Delete').prop('disabled', false);
            }
        });
    });

    // ── Toast ─────────────────────────────────────────────────────────────────
    function showToast(message, type = 'success', duration = 3500) {
        const id = 'toast_' + Date.now();
        $('body').append(`
            <div id="${id}" class="toast align-items-center text-white bg-${type} border-0 show position-fixed bottom-0 end-0 m-3"
                 role="alert" style="z-index:9999">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`);
        setTimeout(() => $(`#${id}`).remove(), duration);
    }
</script>
@endpush

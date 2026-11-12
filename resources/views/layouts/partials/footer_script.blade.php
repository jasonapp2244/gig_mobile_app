<script src="{{ asset('admin/js/jquery.min.js') }}"></script>
<!-- Bootstrap JS -->
<script src="{{ asset('admin/js/bootstrap.bundle.min.js') }}"></script>
{{-- notification --}}
<script src="{{ asset('admin/plugins/notifications/js/lobibox.min.js') }}"></script>
<script src="{{ asset('admin/plugins/notifications/js/notifications.min.js') }}"></script>
<script src="{{ asset('admin/plugins/notifications/js/notification-custom-script.js') }}"></script>
<!-- Plugins -->


<!-- 5️⃣ Moment.js (before custom.js) -->
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="{{ asset('admin/plugins/simplebar/js/simplebar.min.js') }}"></script>
<script src="{{ asset('admin/plugins/metismenu/js/metisMenu.min.js') }}"></script>
<script src="{{ asset('admin/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>
<script src="{{ asset('admin/plugins/vectormap/jquery-jvectormap-2.0.2.min.js') }}"></script>
<script src="{{ asset('admin/plugins/vectormap/jquery-jvectormap-world-mill-en.js') }}"></script>
<script src="{{ asset('admin/plugins/chartjs/js/chart.js') }}"></script>
<script src="{{ asset('admin/js/index.js') }}?v={{ @filemtime(public_path('admin/js/index.js')) }}"></script>
{{-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="{{ asset('admin/js/gig-charts.js') }}?v={{ @filemtime(public_path('admin/js/gig-charts.js')) }}"></script>
<script src="{{ asset('admin/js/custom.js') }}"></script>








{{-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> --}}
{{-- datatable --}}
<script src="{{ asset('admin/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('admin/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<script>
    $(document).ready(function() {
        $('#example').DataTable();
    });
</script>

<!-- Chart.js CDN -->



{{-- alert script --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    @if (session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: "{{ session('success') }}",
            timer: 3000,
            showConfirmButton: false
        });
    @endif

    @if (session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: "{{ session('error') }}",
            timer: 3000,
            showConfirmButton: false
        });
    @endif
</script>


<script>
    $(document).ready(function() {
        if ($('#user_table').length) {
            $('#user_table').DataTable({
                lengthChange: true,
                lengthMenu: [10, 20, 50, 100],
                order: [],
            });
        }
        if ($('#supportsTable').length) {
            $('#supportsTable').DataTable({
                lengthChange: true,
                lengthMenu: [10, 20, 50, 100],
                order: [],
            });
        }
    });
</script>

<!--Password show & hide js -->
<script>
    $(document).ready(function() {
        $("#show_hide_password a").on('click', function(event) {
            event.preventDefault();
            if ($('#show_hide_password input').attr("type") == "text") {
                $('#show_hide_password input').attr('type', 'password');
                $('#show_hide_password i').addClass("bx-hide");
                $('#show_hide_password i').removeClass("bx-show");
            } else if ($('#show_hide_password input').attr("type") == "password") {
                $('#show_hide_password input').attr('type', 'text');
                $('#show_hide_password i').removeClass("bx-hide");
                $('#show_hide_password i').addClass("bx-show");
            }
        });
    });
</script>

<!-- App JS -->
<script src="{{ asset('admin/js/app.js') }}"></script>

<script>
    new PerfectScrollbar(".app-container")
</script>

<script>
    // Top-bar page title: the deepest active sidebar link, or a name for detail pages
    // that have no menu entry. Display only — never throws into the page.
    $(function () {
        setTimeout(function () {
            try {
                var $active = $('.metismenu li.mm-active > a').last();
                var title = $.trim($active.find('.menu-title').text() || $active.text());
                if (!title) {
                    var path = window.location.pathname;
                    var map = [
                        [/^\/admin\/jobs\/\d+/, 'Job Details'],
                        [/^\/admin\/list\/create/, 'Add List'],
                        [/^\/admin\/list\/\d+\/edit/, 'Edit List'],
                        [/^\/admin\/list\/\d+/, 'List Details'],
                        [/^\/admin\/user\/\d+/, 'Edit User'],
                        [/^\/view-user\/\d+/, 'User Details'],
                        [/^\/support\/\d+/, 'Support Ticket'],
                        [/^\/admin\/income-tracker\/\d+/, 'Income Tracker · User'],
                        [/^\/setting\/edit-profile/, 'Edit Profile']
                    ];
                    for (var i = 0; i < map.length && !title; i++) {
                        if (map[i][0].test(path)) { title = map[i][1]; }
                    }
                }
                if (title) {
                    $('#gigPageTitle').text(title);
                    document.title = title + ' | Gig Admin';
                }
            } catch (e) { /* cosmetic only */ }
        }, 0);
    });
</script>



@stack('scripts')

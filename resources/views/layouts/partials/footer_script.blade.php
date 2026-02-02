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
<script src="{{ asset('admin/js/index.js') }}"></script>
{{-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        var table = $('#user_table').DataTable({
            lengthChange: true,
            lengthMenu: [10, 20, 50, 100],
            // buttons: ['copy', 'excel', 'pdf', 'print']
        });

        table.buttons().container()
            .appendTo('#example2_wrapper .col-md-6:eq(0)');
    });
</script>



<script>
    $(document).ready(function() {
        var table = $('#supportsTable').DataTable({
            lengthChange: true,
            lengthMenu: [10, 20, 50, 100],
            // buttons: ['copy', 'excel', 'pdf', 'print']
        });

        table.buttons().container()
            .appendTo('#supportsTable .col-md-6:eq(0)');
    });
</script>


<script>
    $(document).ready(function() {
        var table = $('#jobsTable').DataTable({
            lengthChange: true,
            lengthMenu: [10, 20, 50, 100],
            // buttons: ['copy', 'excel', 'pdf', 'print']
        });

        table.buttons().container()
            .appendTo('#jobsTable .col-md-6:eq(0)');
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



@stack('scripts')

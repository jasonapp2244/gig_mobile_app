<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
   <meta name="csrf-token" content="{{ csrf_token() }}">
    <!--favicon-->
    <!-- Favicon -->
    <link rel="icon" href="{{ asset('admin/images/favicon-32x32.png') }}" type="image/png" />

    <!-- Plugins -->
    <link rel="stylesheet" href="{{ asset('admin/plugins/notifications/css/lobibox.min.css') }}" />
    <link href="{{ asset('admin/plugins/vectormap/jquery-jvectormap-2.0.2.css') }}" rel="stylesheet" />
    <link href="{{ asset('admin/plugins/simplebar/css/simplebar.css') }}" rel="stylesheet" />
    <link href="{{ asset('admin/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet" />
    <link href="{{ asset('admin/plugins/metismenu/css/metisMenu.min.css') }}" rel="stylesheet" />
    {{-- datatable --}}
    <link href="{{ asset('admin/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />


    <!-- Loader -->
    {{-- <link href="{{ asset('admin/css/pace.min.css') }}" rel="stylesheet" /> --}}
    {{-- <script src="{{ asset('admin/js/pace.min.js') }}"></script> --}}

    <!-- Bootstrap CSS -->
    <link href="{{ asset('admin/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('admin/css/bootstrap-extended.css') }}" rel="stylesheet">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <!-- App and Icons CSS -->
    <link href="{{ asset('admin/css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('admin/css/icons.css') }}" rel="stylesheet">

    <!-- Theme Style CSS -->
    <link href="{{ asset('admin/css/dark-theme.css') }}" rel="stylesheet" />
    <link href="{{ asset('admin/css/semi-dark.css') }}" rel="stylesheet" />
    <link href="{{ asset('admin/css/header-colors.css') }}" rel="stylesheet" />

    <!-- Gig brand theme (loaded last; visual overrides only) -->
    <link href="{{ asset('admin/css/gig-theme.css') }}?v={{ @filemtime(public_path('admin/css/gig-theme.css')) }}" rel="stylesheet" />

    <meta name="theme-color" content="#111827">
    <title>@yield('title')Gig Admin</title>
</head>

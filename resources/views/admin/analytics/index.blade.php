@extends('layouts.admin')
@section('content')
    <div class="page-wrapper">
        <div class="page-content">

            <div class="card radius-10">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <div>
                            <h5 class="mb-0">Firebase Analytics</h5>
                            <small class="text-muted">
                                Gig mobile app{{ $projectId ? ' · project ' . $projectId : '' }}
                                <span id="gaProperty"></span>
                            </small>
                        </div>
                        <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
                            <small class="text-muted" id="gaUpdated"></small>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Date range">
                                @foreach ($ranges as $range)
                                    <button type="button" class="btn btn-outline-primary ga-range{{ $range === 28 ? ' active' : '' }}"
                                            data-range="{{ $range }}">Last {{ $range }} days</button>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" id="gaRefresh">
                                <i class='bx bx-refresh'></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Loading --}}
            <div id="gaLoading" class="card radius-10">
                <div class="card-body text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Loading Firebase Analytics…
                </div>
            </div>

            {{-- Not connected / error --}}
            <div id="gaProblem" class="card radius-10 border-start border-4 border-warning d-none">
                <div class="card-body">
                    <h6 class="mb-1"><i class='bx bx-error-circle text-warning'></i> <span id="gaProblemTitle"></span></h6>
                    <p class="text-muted mb-3" id="gaProblemMessage"></p>

                    <div id="gaSetup">
                    <p class="mb-2 fw-semibold">To connect Firebase Analytics (one-time setup):</p>
                    <ol class="mb-3">
                        <li class="mb-2">
                            In Google Cloud project <code>{{ $projectId ?? 'gigapp-f7ebc' }}</code>, enable the
                            <a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com?project={{ $projectId ?? 'gigapp-f7ebc' }}"
                               target="_blank" rel="noopener">Google Analytics Data API</a> and the
                            <a href="https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com?project={{ $projectId ?? 'gigapp-f7ebc' }}"
                               target="_blank" rel="noopener">Google Analytics Admin API</a>.
                        </li>
                        <li class="mb-2">
                            In Google Analytics → Admin → <strong>Property access management</strong>, add
                            @if ($serviceAccountEmail)
                                <code id="gaServiceEmail">{{ $serviceAccountEmail }}</code>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 ms-1" id="gaCopyEmail">Copy</button>
                            @else
                                the Firebase service account
                            @endif
                            as a <strong>Viewer</strong>.
                        </li>
                        <li>
                            Optional: put the GA4 property ID in <code>.env</code> as <code>GA4_PROPERTY_ID</code>
                            (Firebase console → Project settings → Integrations → Google Analytics).
                            Without it the first property the service account can see is used.
                        </li>
                    </ol>
                    </div>
                    <a href="#" id="gaActivation" class="btn btn-sm btn-warning d-none" target="_blank" rel="noopener">Enable the API in Google Cloud</a>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="gaRetry">Check again</button>
                </div>
            </div>

            {{-- Data --}}
            <div id="gaData" class="d-none">
                <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4" id="gaKpis"></div>

                <div class="row">
                    <div class="col-12 col-xl-8 d-flex">
                        <div class="card radius-10 w-100">
                            <div class="card-header"><h6 class="mb-0">Daily users</h6></div>
                            <div class="card-body">
                                <div style="position:relative;height:300px;"><canvas id="gaDailyChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4 d-flex">
                        <div class="card radius-10 w-100">
                            <div class="card-header"><h6 class="mb-0">Platforms</h6></div>
                            <div class="card-body">
                                <div style="position:relative;height:300px;"><canvas id="gaPlatformChart"></canvas></div>
                                <p class="text-center text-muted mb-0 d-none" id="gaPlatformEmpty">No platform data in this range.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-xl-6 d-flex">
                        @include('admin.analytics.partials.table', ['id' => 'gaScreens', 'title' => 'Top screens', 'columns' => ['Screen', 'Views', 'Users']])
                    </div>
                    <div class="col-12 col-xl-6 d-flex">
                        @include('admin.analytics.partials.table', ['id' => 'gaEvents', 'title' => 'Top events', 'columns' => ['Event', 'Count', 'Users']])
                    </div>
                    <div class="col-12 col-xl-6 d-flex">
                        @include('admin.analytics.partials.table', ['id' => 'gaCountries', 'title' => 'Top countries', 'columns' => ['Country', 'Users']])
                    </div>
                    <div class="col-12 col-xl-6 d-flex">
                        @include('admin.analytics.partials.table', ['id' => 'gaVersions', 'title' => 'App versions', 'columns' => ['Version', 'Users']])
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var DATA_URL = "{{ route('admin.analytics.data') }}";
            var REFRESH_URL = "{{ route('admin.analytics.refresh') }}";
            var range = 28;
            var charts = {};

            var PROBLEM_TITLES = {
                not_configured: 'Firebase Analytics is not configured',
                api_disabled: 'Google Analytics API is turned off for this Firebase project',
                no_access: 'The service account cannot read Google Analytics yet',
                rate_limited: 'Google Analytics is busy',
                google_unavailable: 'Google Analytics is not responding',
                error: 'Could not load Firebase Analytics'
            };
            // Only these are fixed by the one-time setup steps
            var SETUP_PROBLEMS = ['not_configured', 'api_disabled', 'no_access'];

            var KPIS = [
                { key: 'activeUsers', label: 'Active users', icon: 'bx-user', color: 'primary' },
                { key: 'newUsers', label: 'New users', icon: 'bx-user-plus', color: 'success' },
                { key: 'sessions', label: 'Sessions', icon: 'bx-mobile-alt', color: 'info' },
                { key: 'screenPageViews', label: 'Screen views', icon: 'bx-show', color: 'warning' },
                { key: 'averageEngagementTime', label: 'Avg. engagement / user', icon: 'bx-time-five', color: 'secondary', format: 'duration' },
                { key: 'engagementRate', label: 'Engagement rate', icon: 'bx-pulse', color: 'danger', format: 'percent' },
                { key: 'eventCount', label: 'Events', icon: 'bx-bolt-circle', color: 'dark' }
            ];

            function esc(value) {
                return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            function num(value) {
                return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
            }

            function format(value, type) {
                if (type === 'percent') {
                    return (Number(value || 0) * 100).toFixed(1) + '%';
                }
                if (type === 'duration') {
                    var s = Math.round(Number(value || 0));
                    return Math.floor(s / 60) + 'm ' + (s % 60) + 's';
                }
                return num(value);
            }

            function changeBadge(change) {
                if (change === null || change === undefined) {
                    return '<small class="text-muted">no previous data</small>';
                }
                var up = change >= 0;
                return '<small class="text-' + (up ? 'success' : 'danger') + '">' +
                    '<i class="bx ' + (up ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt') + '"></i>' +
                    Math.abs(change) + '% vs previous ' + range + ' days</small>';
            }

            function kpiCard(label, value, icon, color, footer) {
                return '<div class="col"><div class="card radius-10"><div class="card-body">' +
                    '<div class="d-flex align-items-center"><div>' +
                    '<p class="mb-0 text-secondary">' + esc(label) + '</p>' +
                    '<h4 class="my-1">' + value + '</h4>' + footer +
                    '</div><div class="widgets-icons bg-light-' + color + ' text-' + color + ' ms-auto">' +
                    '<i class="bx ' + icon + '"></i></div></div></div></div></div>';
            }

            function renderKpis(data) {
                var html = '';
                KPIS.forEach(function (k) {
                    var kpi = data.kpis[k.key] || { value: 0, change: null };
                    html += kpiCard(k.label, format(kpi.value, k.format), k.icon, k.color, changeBadge(kpi.change));
                });
                var live = data.realtime === null || data.realtime === undefined ? '–' : num(data.realtime);
                html += kpiCard('Users right now', live, 'bx-broadcast', 'success',
                    '<small class="text-muted">active in the last 30 minutes</small>');
                $('#gaKpis').html(html);
            }

            function renderTable(id, rows, cells) {
                var $body = $('#' + id + ' tbody');
                if (!rows.length) {
                    $body.html('<tr><td colspan="' + $('#' + id + ' thead th').length + '" class="text-center text-muted">No data in this range.</td></tr>');
                    return;
                }
                $body.html(rows.map(function (row) {
                    return '<tr>' + cells(row).map(function (c, i) {
                        return '<td' + (i ? ' class="text-end"' : '') + '>' + c + '</td>';
                    }).join('') + '</tr>';
                }).join(''));
            }

            function drawChart(id, config) {
                if (charts[id]) {
                    charts[id].destroy();
                }
                charts[id] = new Chart(document.getElementById(id).getContext('2d'), config);
            }

            function renderCharts(data) {
                drawChart('gaDailyChart', {
                    type: 'line',
                    data: {
                        labels: data.daily.map(function (d) { return d.date.slice(5); }),
                        datasets: [
                            { label: 'Active users', data: data.daily.map(function (d) { return d.active; }),
                              borderColor: '#fa0101', backgroundColor: 'rgba(250,1,1,.08)', fill: true, tension: .3 },
                            { label: 'New users', data: data.daily.map(function (d) { return d.new; }),
                              borderColor: '#ffa500', backgroundColor: 'rgba(255,165,0,.10)', fill: true, tension: .3 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                               scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
                });

                var platforms = data.platforms.filter(function (p) { return p.activeUsers > 0; });
                $('#gaPlatformEmpty').toggleClass('d-none', platforms.length > 0);
                $('#gaPlatformChart').toggleClass('d-none', platforms.length === 0);
                drawChart('gaPlatformChart', {
                    type: 'doughnut',
                    data: {
                        labels: platforms.map(function (p) { return p.platform; }),
                        datasets: [{ data: platforms.map(function (p) { return p.activeUsers; }),
                                     backgroundColor: ['#fa3c01', '#111827', '#ffa500', '#9ca3af', '#0ea5e9'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                });
            }

            function render(data) {
                $('#gaProperty').text(data.property_id ? '· GA4 property ' + data.property_id : '');
                renderKpis(data);
                renderCharts(data);
                renderTable('gaScreens', data.screens, function (r) {
                    return [esc(r.unifiedScreenName || '(no screen name)'), num(r.screenPageViews), num(r.activeUsers)];
                });
                renderTable('gaEvents', data.events, function (r) {
                    return [esc(r.eventName), num(r.eventCount), num(r.totalUsers)];
                });
                renderTable('gaCountries', data.countries, function (r) {
                    return [esc(r.country || '(unknown)'), num(r.activeUsers)];
                });
                renderTable('gaVersions', data.versions, function (r) {
                    return [esc(r.appVersion || '(unknown)'), num(r.activeUsers)];
                });
                $('#gaUpdated').text('Updated ' + new Date(data.generated_at).toLocaleString() +
                    (data.stale ? ' (Google is busy; showing the last loaded numbers)' : ''));
            }

            function showProblem(data) {
                $('#gaProblemTitle').text(PROBLEM_TITLES[data.status] || PROBLEM_TITLES.error);
                $('#gaProblemMessage').text((data.message || '') +
                    (typeof data.realtime === 'number' ? ' Users in the app right now: ' + num(data.realtime) + '.' : ''));
                $('#gaSetup').toggleClass('d-none', SETUP_PROBLEMS.indexOf(data.status) === -1);
                $('#gaActivation').toggleClass('d-none', !data.activation_url).attr('href', data.activation_url || '#');
                $('#gaProblem').removeClass('d-none');
            }

            function load() {
                $('#gaLoading').removeClass('d-none');
                $('#gaProblem, #gaData').addClass('d-none');
                $('#gaUpdated').text('');

                $.getJSON(DATA_URL, { range: range })
                    .done(function (data) {
                        if (data.status === 'ok') {
                            $('#gaData').removeClass('d-none');
                            render(data);
                        } else {
                            showProblem(data);
                        }
                    })
                    .fail(function (xhr) {
                        showProblem({ status: 'error', message: 'The server returned HTTP ' + xhr.status + '.' });
                    })
                    .always(function () {
                        $('#gaLoading').addClass('d-none');
                    });
            }

            function refresh() {
                $.ajax({ url: REFRESH_URL, method: 'POST',
                         headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } })
                    .always(load);
            }

            $('.ga-range').on('click', function () {
                $('.ga-range').removeClass('active');
                $(this).addClass('active');
                range = Number($(this).data('range'));
                load();
            });
            $('#gaRefresh, #gaRetry').on('click', refresh);
            $('#gaCopyEmail').on('click', function () {
                var btn = this;
                navigator.clipboard.writeText($('#gaServiceEmail').text()).then(function () {
                    $(btn).text('Copied');
                });
            });

            load();
        });
    </script>
@endpush

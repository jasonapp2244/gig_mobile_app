// Income Tracker admin pages: dashboard widget, users list, user detail.
// Every page loads once, then refreshes in the background every N seconds.
// Refresh pauses while the tab is hidden and runs immediately when it is shown again.
// A failed refresh keeps what is on screen and simply tries again next interval.
(function (window, $) {
    'use strict';

    var IT = {};

    var ACTIONS = {
        viewed:          { label: 'Opened',          color: 'secondary' },
        created:         { label: 'Added entry',     color: 'primary' },
        status_changed:  { label: 'Status changed',  color: 'info' },
        partial_payment: { label: 'Partial payment', color: 'warning' },
        updated:         { label: 'Updated',         color: 'dark' },
        deleted:         { label: 'Deleted',         color: 'danger' }
    };

    var STATUS_COLORS = {
        paid: 'success', received: 'success', partial: 'info', pending: 'warning',
        owed: 'danger', borrowed: 'secondary', 'return': 'dark'
    };

    // Highlight style for rows that arrived in the latest refresh.
    $('<style>')
        .text('.it-new{animation:itFlash 4s ease-out}' +
              '@keyframes itFlash{0%{background-color:rgba(255,193,7,.45)}100%{background-color:transparent}}')
        .appendTo('head');

    IT.esc = function (value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    IT.money = function (value) {
        return '$' + Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    IT.statusBadge = function (status) {
        if (!status) return '<span class="badge bg-light text-dark">N/A</span>';
        return '<span class="badge bg-' + (STATUS_COLORS[status] || 'secondary') + '">' + IT.esc(status) + '</span>';
    };

    IT.actionBadge = function (action) {
        var a = ACTIONS[action] || { label: action, color: 'secondary' };
        return '<span class="badge bg-' + a.color + '">' + IT.esc(a.label) + '</span>';
    };

    IT.activityRow = function (a, withUser) {
        var cells = '';
        if (withUser) {
            cells += '<td><a href="' + IT.esc(a.detail_url) + '">' + IT.esc(a.user_name) + '</a></td>';
        }
        cells += '<td>' + IT.actionBadge(a.action) + '</td>';
        cells += '<td class="text-wrap" style="min-width:220px;">' + IT.esc(a.description) +
            (a.backfilled ? ' <small class="text-muted">(before tracking)</small>' : '') + '</td>';
        cells += '<td class="text-nowrap">' + IT.esc(a.time) + '<br><small class="text-muted">' + IT.esc(a.ago) + '</small></td>';
        return '<tr data-id="' + Number(a.id) + '">' + cells + '</tr>';
    };

    IT.highlight = function ($rows) {
        $rows.removeClass('it-new');
        void ($rows.length && $rows[0].offsetWidth); // restart the animation
        $rows.addClass('it-new');
    };

    // Run fn every `seconds`, paused while the tab is hidden.
    IT.poll = function (fn, seconds) {
        var timer = null;
        var ms = Math.max(30, Number(seconds) || 300) * 1000;

        function start() { if (!timer) timer = setInterval(fn, ms); }
        function stop() { if (timer) { clearInterval(timer); timer = null; } }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stop();
            } else {
                fn();
                start();
            }
        });

        start();
    };

    // Prepend new timeline/feed rows, or replace everything on a full load.
    function renderActivities($tbody, activities, incremental, withUser, maxRows, emptyText) {
        var cols = withUser ? 4 : 3;

        if (!incremental) {
            $tbody.html(activities.length
                ? activities.map(function (a) { return IT.activityRow(a, withUser); }).join('')
                : '<tr class="it-empty"><td colspan="' + cols + '" class="text-center text-muted">' + IT.esc(emptyText) + '</td></tr>');
            return;
        }

        if (!activities.length) return;

        $tbody.find('tr.it-empty').remove();
        var $rows = $(activities.map(function (a) { return IT.activityRow(a, withUser); }).join(''));
        $tbody.prepend($rows);
        IT.highlight($rows);

        if (maxRows) $tbody.find('tr').slice(maxRows).remove();
    }

    function maxId(activities, current) {
        return activities.reduce(function (m, a) { return Math.max(m, Number(a.id)); }, current);
    }

    // ---------------------------------------------------------------
    // Dashboard widget
    // ---------------------------------------------------------------
    IT.initDashboard = function (opts) {
        var lastId = 0;
        var chart = null;
        var $feed = $('#itRecentActivities');

        function renderChart(trend) {
            var el = document.getElementById('itTrendChart');
            if (!el || typeof Chart === 'undefined') return;

            if (chart) {
                chart.data.labels = trend.labels;
                chart.data.datasets[0].data = trend.users;
                chart.data.datasets[1].data = trend.actions;
                chart.update('none');
                return;
            }

            chart = new Chart(el.getContext('2d'), {
                type: 'line',
                data: {
                    labels: trend.labels,
                    datasets: [
                        { label: 'Active users', data: trend.users, borderColor: 'rgba(13,110,253,1)', backgroundColor: 'rgba(13,110,253,.15)', fill: true, tension: .3 },
                        { label: 'Actions', data: trend.actions, borderColor: 'rgba(255,193,7,1)', backgroundColor: 'rgba(255,193,7,.1)', fill: false, tension: .3 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        function load() {
            $.ajax({
                url: opts.url,
                data: lastId ? { after_id: lastId } : {},
                success: function (res) {
                    if (!res.success) return;
                    var s = res.stats;
                    $('#itActiveToday').text(s.active_today);
                    $('#itActive7d').text(s.active_7d);
                    $('#itActive30d').text(s.active_30d);
                    $('#itActionsToday').text(s.actions_today);
                    renderChart(s.trend);
                    renderActivities($feed, res.activities, lastId > 0, true, 15, 'No income tracker activity yet.');
                    lastId = maxId(res.activities, lastId);
                    $('#itUpdated').text('Updated ' + res.updated_at);
                }
            });
        }

        load();
        IT.poll(load, opts.seconds);
    };

    // ---------------------------------------------------------------
    // Users list
    // ---------------------------------------------------------------
    IT.initUsers = function (opts) {
        var users = [];
        var seen = null; // user_id -> last_activity_ts from the previous load
        var changed = {};
        var $tbody = $('#itUsersBody');
        var $search = $('#itUserSearch');

        function render() {
            var term = ($search.val() || '').toLowerCase().trim();
            var rows = users.filter(function (u) {
                return !term ||
                    String(u.name || '').toLowerCase().indexOf(term) !== -1 ||
                    String(u.email || '').toLowerCase().indexOf(term) !== -1;
            });

            if (!rows.length) {
                $tbody.html('<tr><td colspan="8" class="text-center text-muted">' +
                    (users.length ? 'No users match your search.' : 'No one has used the income tracker yet.') + '</td></tr>');
                return;
            }

            $tbody.html(rows.map(function (u, i) {
                return '<tr data-user="' + Number(u.user_id) + '"' + (changed[u.user_id] ? ' class="it-new"' : '') + '>' +
                    '<td>' + (i + 1) + '</td>' +
                    '<td><a href="' + IT.esc(u.detail_url) + '" class="fw-semibold">' + IT.esc(u.name) + '</a>' +
                        '<br><small class="text-muted">' + IT.esc(u.email) + '</small></td>' +
                    '<td class="text-nowrap">' + IT.esc(u.last_activity) + '<br><small class="text-muted">' + IT.esc(u.last_activity_ago) + '</small></td>' +
                    '<td>' + Number(u.actions_30d) + ' <small class="text-muted">/ ' + Number(u.total_actions) + '</small></td>' +
                    '<td>' + Number(u.entries) + '</td>' +
                    '<td class="text-success">' + IT.money(u.earned) + '</td>' +
                    '<td class="text-warning">' + IT.money(u.pending_total) + '</td>' +
                    '<td><a href="' + IT.esc(u.detail_url) + '" class="btn btn-sm btn-dark">View</a></td>' +
                    '</tr>';
            }).join(''));
            changed = {};
        }

        function load() {
            $.ajax({
                url: opts.url,
                success: function (res) {
                    if (!res.success) return;
                    var next = {};
                    res.users.forEach(function (u) {
                        next[u.user_id] = u.last_activity_ts;
                        if (seen && seen[u.user_id] !== u.last_activity_ts) changed[u.user_id] = true;
                    });
                    seen = next;
                    users = res.users;
                    render();
                    $('#itUserCount').text(users.length);
                    $('#itUpdated').text('Updated ' + res.updated_at);
                }
            });
        }

        $search.on('input', render);
        load();
        IT.poll(load, opts.seconds);
    };

    // ---------------------------------------------------------------
    // User detail
    // ---------------------------------------------------------------
    IT.initUserDetail = function (opts) {
        var lastId = 0;
        var $timeline = $('#itTimeline');
        var $entries = $('#itEntriesBody');
        var $range = $('#itRange');

        function filters() {
            return {
                from: $('#itFrom').val() || '',
                to: $('#itTo').val() || '',
                status: $('#itStatus').val() || '',
                action: $('#itAction').val() || ''
            };
        }

        function applyRange() {
            var r = $range.val();
            var custom = r === 'custom';
            $('.it-custom-range').toggleClass('d-none', !custom);
            if (custom) return;

            var today = moment().format('YYYY-MM-DD');
            var from = '';
            if (r === 'today') from = today;
            if (r === '7') from = moment().subtract(6, 'days').format('YYYY-MM-DD');
            if (r === '30') from = moment().subtract(29, 'days').format('YYYY-MM-DD');
            $('#itFrom').val(from);
            $('#itTo').val(from ? today : '');
        }

        function renderSummary(s) {
            $('[data-it-sum]').each(function () {
                var key = $(this).data('it-sum');
                $(this).text(key === 'entries' ? Number(s[key] || 0) : IT.money(s[key]));
            });
        }

        function renderEntries(entries) {
            if (!entries.length) {
                $entries.html('<tr><td colspan="9" class="text-center text-muted">No income entries for this filter.</td></tr>');
                return;
            }
            $entries.html(entries.map(function (e) {
                return '<tr>' +
                    '<td class="text-wrap">' + IT.esc(e.title) + '</td>' +
                    '<td>' + IT.money(e.amount) + '</td>' +
                    '<td>' + IT.money(e.paid) + '</td>' +
                    '<td>' + IT.money(e.remaining) + '</td>' +
                    '<td>' + IT.statusBadge(e.status) + '</td>' +
                    '<td class="text-nowrap">' + IT.esc(e.date) + '</td>' +
                    '<td class="text-wrap">' + IT.esc(e.note) + '</td>' +
                    '<td><span class="badge bg-' + (e.source === 'Manual' ? 'primary' : 'light text-dark') + '">' + IT.esc(e.source) + '</span></td>' +
                    '<td class="text-nowrap"><small>' + IT.esc(e.created) + '</small></td>' +
                    '</tr>';
            }).join(''));
        }

        function load() {
            var params = filters();
            if (lastId) params.after_id = lastId;

            $.ajax({
                url: opts.url,
                data: params,
                success: function (res) {
                    if (!res.success) return;
                    renderSummary(res.summary);
                    $('#itLastUsed').text(res.last_used || 'Never');
                    renderEntries(res.entries);
                    renderActivities($timeline, res.timeline, lastId > 0, false, null, 'No activity for this filter.');
                    lastId = maxId(res.timeline, lastId);
                    $('#itUpdated').text('Updated ' + res.updated_at);
                }
            });
        }

        // Any filter change reloads the timeline from scratch.
        function reload() {
            lastId = 0;
            load();
        }

        $range.on('change', function () { applyRange(); reload(); });
        $('#itFrom, #itTo, #itStatus, #itAction').on('change', reload);

        applyRange();
        load();
        IT.poll(load, opts.seconds);
    };

    window.IncomeTracker = IT;
})(window, jQuery);

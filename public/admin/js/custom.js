// resources/js/custom.js
// ---------------- Recent Activities ----------------

//all users record dashboard
function refreshDashboardData() {
    if (!$('#totalUsers').length) return; // only run on dashboard page
    $.ajax({
        url: "/admin/dashboard/data",
        method: "GET",
        success: function(response) {
            if (response.success) {
                $("#totalUsers").text(response.data.users);
                $("#totalTasks").text(response.data.tasks);
                $("#totalEmployers").text(response.data.employers);
                $("#totalRevenue").text("$" + response.data.task_payments);
                $("#totalSupport").text(response.data.total_support_email);
                $("#totalReadSupport").text(response.data.total_read_email);
                $("#totalPendingSupport").text(response.data.total_pending_email);
            }
        },
        error: function(xhr) { console.error("refreshDashboardData failed", xhr.status); }
    });
}

function loadRecentActivities() {
    // $.ajax({
    //     url: "admin/dashboard/recent-activities",
    //     type: "GET",
    //     success: function(response) {
    //         if (response.status) {
    //             let rows = "";
    //             response.data.forEach(function(activity) {
    //                 let statusBadge =
    //                     activity.status === "active" ?
    //                     '<span class="badge bg-success">Active</span>' :
    //                     '<span class="badge bg-danger">Inactive</span>';
    //                 rows += `
    //                         <tr>
    //                             <td>${activity.name} (ID: ${activity.id})</td>
    //                             <td>${activity.activity}</td>
    //                             <td>${moment(
    //                                 activity.created_at
    //                             ).fromNow()}</td>
    //                             <td>${statusBadge}</td>
    //                             <td>${moment(activity.created_at).format(
    //                                 "MMM DD, YYYY"
    //                             )}</td>
    //                         </tr>`;
    //             });
    //             $("#recentActivitiesTable").html(rows);
    //         }
    //     },
    // });
}
// ---------------- Chart Data ----------------
let myChart;


function loadChartData() {
    var chartEl = document.getElementById("chart22");
    if (!chartEl) return; // only run on dashboard page
    $.ajax({
        url: "/admin/chart-data",
        type: "GET",
        success: function(response) {
            if (response.success) {
                let ctx = chartEl.getContext("2d");
                if (myChart) myChart.destroy();
                myChart = new Chart(ctx, {
                    type: "bar",
                    data: {
                        labels: response.labels,
                        datasets: [{
                            label: "User Registrations",
                            data: response.values,
                            backgroundColor: "rgba(75, 192, 192, 0.2)",
                            borderColor: "rgba(75, 192, 192, 1)",
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true } },
                    },
                });
            }
        },
    });
}

//update recent all activity
function updateRecentActivities() {
    if (!$('#recentActivitiesTable').length) return; // only run on dashboard page
    $.ajax({
        url: "/admin/recent-activities",
        type: "GET",
        success: function(response) {
            if (response.success && response.html) {
                $("#recentActivitiesTable").html(response.html);
            } else {
                console.error("Recent activities data not available", response);
            }
        },
        error: function(xhr, status, error) {
            console.error("Failed to load recent activities:", error);
        },
    });
}

// ---------------- Jobs ----------------

function loadJobs() {
    if (!$('#jobsBody').length) return; // only run on job monitoring page
    $.ajax({
        url: "/job-monitoring-fetch",
        type: "GET",
        success: function(response) {
            if (response.success) {
                let tbody = "";
                response.jobs.forEach(function(job, index) {
                    let employerName = job.employer_name || 'N/A';

                    let paymentStatus = `<span class="badge bg-secondary">No Payment</span>`;
                    if (job.task_payments && job.task_payments.length > 0) {
                        let lastPayment = job.task_payments[job.task_payments.length - 1];
                        let badgeClass = lastPayment.payment_status === "paid" ? "success" : "danger";
                        let amount = parseFloat(lastPayment.payment || 0).toFixed(2);
                        paymentStatus = `<span class="badge bg-${badgeClass}">${lastPayment.payment_status}</span>
                                         <small class="d-block text-muted">$${amount}</small>`;
                    }

                    tbody += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${job.id}</td>
                            <td>${employerName}</td>
                            <td>${new Date(job.created_at).toLocaleDateString()}</td>
                            <td><span class="badge bg-${
                                job.status === 'completed'  ? 'success'   :
                                job.status === 'incomplete' ? 'danger'    :
                                job.status === 'ongoing'    ? 'info'      :
                                job.status === 'cancelled'  ? 'secondary' : 'warning'
                            }">${job.status ? job.status.charAt(0).toUpperCase() + job.status.slice(1) : 'N/A'}</span></td>
                            <td>${paymentStatus}</td>
                            <td><a href="/admin/jobs/${job.id}" class="btn btn-sm btn-dark">View</a></td>
                        </tr>`;
                });

                // Destroy DataTable first, update rows, then reinitialize
                if ($.fn.DataTable.isDataTable('#user_table')) {
                    $('#user_table').DataTable().destroy();
                }
                $("#jobsBody").html(tbody);
                $('#user_table').DataTable({
                    lengthChange: true,
                    lengthMenu: [10, 20, 50, 100],
                    order: [],
                });
            }
        },
    });
}

// ---------------- Users ----------------
function loadUsers() {
    if (!$('#usersBody').length) return; // only run on users page
    $.ajax({
        url: "/admin/fetch-users",
        type: "GET",
        success: function(response) {
            if (response.success) {
                let tbody = "";
                response.users.forEach(function(user, index) {
                    tbody += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${user.name}</td>
                            <td>${user.email ? user.email : 'Social Login'}</td>
                            <td>${new Date(user.created_at).toLocaleDateString()}</td>
                            <td>
                                <a href="/admin/user/${user.id}" class="btn btn-sm btn-success">Edit</a>
                                ${user.status === "active"
                                    ? '<span class="badge bg-success">Active</span>'
                                    : '<span class="badge bg-danger">Inactive</span>'
                                }
                            </td>
                        </tr>`;
                });
                if ($.fn.DataTable.isDataTable('#user_table')) {
                    $('#user_table').DataTable().destroy();
                }
                $("#usersBody").html(tbody);
                $('#user_table').DataTable({
                    lengthChange: true,
                    lengthMenu: [10, 20, 50, 100],
                    order: [],
                });
            }
        },
        error: function(xhr) { console.error("loadUsers failed", xhr.status); }
    });
}

//support email
function loadSupports() {
    if (!$('#supportsBody').length) return; // only run on support page
    $.ajax({
        url: "/admin/fetch-supports",
        type: "GET",
        success: function(response) {
            if (response.success) {
                let rows = "";
                response.supports.forEach(function(support, index) {
                    let badge =
                        support.is_read == 1 ?
                        '<span class="badge bg-success">Read</span>' :
                        `<span class="badge bg-warning">${
                                  support.status.charAt(0).toUpperCase() +
                                  support.status.slice(1)
                              }</span>`;

                    rows += `
                    <tr>
                    <td>${index + 1}</td>
                    <td>${support.name}</td>
                    <td><a href="mailto:${support.email}">${support.email}</a></td>
                    <td>${support.subject}</td>
                    <td>${badge}</td>
                    <td>${support.created_at_formatted}</td>
                    <td>
                        <a href="/support/${support.id}" class="btn btn-sm btn-dark"> View </a>
                    </td>
                    </tr>`;
                });
                if ($.fn.DataTable.isDataTable('#supportsTable')) {
                    $('#supportsTable').DataTable().destroy();
                }
                $("#supportsBody").html(rows);
                $('#supportsTable').DataTable({
                    lengthChange: true,
                    lengthMenu: [10, 20, 50, 100],
                    order: [],
                });
            }
        },
    });
}

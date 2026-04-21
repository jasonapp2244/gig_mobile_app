// resources/js/custom.js

// ---------------- Recent Activities ----------------
function loadRecentActivities() {
    $.ajax({
        url: "admin/dashboard/recent-activities",
        type: "GET",
        success: function(response) {
            if (response.status) {
                let rows = "";
                response.data.forEach(function(activity) {
                    let statusBadge =
                        activity.status === "active" ?
                        '<span class="badge bg-success">Active</span>' :
                        '<span class="badge bg-danger">Inactive</span>';

                    rows += `
                        <tr>
                            <td>${activity.name} (ID: ${activity.id})</td>
                            <td>${activity.activity}</td>
                            <td>${moment(activity.created_at).fromNow()}</td>
                            <td>${statusBadge}</td>
                            <td>${moment(activity.created_at).format(
                                "MMM DD, YYYY"
                            )}</td>
                        </tr>`;
                });
                $("#recentActivitiesTable").html(rows);
            }
        },
    });
}

// ---------------- Chart Data ----------------
let myChart;

function loadChartData() {
    alert('fdfd');
    $.ajax({
        url: "/admin/chart-data",
        type: "GET",
        success: function(response) {
            if (response.success) {
                let ctx = document.getElementById("chart22").getContext("2d");
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
                        }, ],
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

// ---------------- Jobs ----------------
function loadJobs() {
    $.ajax({
        url: "/job-monitoring-fetch",
        type: "GET",
        success: function(response) {
            if (response.success) {
                let tbody = "";
                response.jobs.forEach(function(job, index) {
                    // employer_name is pre-resolved in controller (handles relation + column + position fallback)
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
                            <td><span class="badge bg-${job.status === "completed" ? "success" : "warning"}">${job.status}</span></td>
                            <td>${paymentStatus}</td>
                            <td><a href="/admin/jobs/${job.id}" class="btn btn-sm btn-dark">View</a></td>
                        </tr>`;
                });
                $("#jobsBody").html(tbody);
            }
        },
    });
}

// ---------------- Users ----------------
function loadUsers() {
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
                                ${
                                    user.status === "active"
                                        ? '<span class="badge bg-success">Unblock</span>'
                                        : '<span class="badge bg-danger">Block</span>'
                                }
                            </td>
                        </tr>`;
                });
                $("#usersBody").html(tbody);
            }
        },
    });
}

//support email
function loadSupports() {
    $.ajax({
        url: "/admin/fetch-supports",
        type: "GET",
        success: function(response) {
            if (response.success) {
                let rows = "";
                response.supports.forEach(function(support, index) {
                    let badge = support.is_read == 1 ?
                        '<span class="badge bg-success">Read</span>' :
                        `<span class="badge bg-warning">${support.status.charAt(0).toUpperCase() + support.status.slice(1)}</span>`;

                    rows += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${support.name}</td>
                            <td><a href="mailto:${support.email}">${support.email}</a></td>
                            <td>${support.subject}</td>
                            <td>${badge}</td>
                            <td>${support.created_at_formatted}</td>
                           <td> <a href="{{ route('support.show', $support->id) }}" class="btn btn-sm btn-dark"> {{ trans('messages.view') }} </a> </td>
                        </tr>`;
                });
                $("#supportsBody").html(rows);
            }
        },
        error: function() {
            alert("Error fetching support emails.");
        }
    });
}

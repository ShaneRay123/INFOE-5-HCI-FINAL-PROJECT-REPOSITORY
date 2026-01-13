<?php
// Include necessary files
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin (counselor role for this context)
if (!isLoggedIn() || !isAdmin()) {
    redirect("../index.php");
}

$user_id = $_SESSION["user_id"];
$user = getUserDetails($user_id);

// --- 1. APPOINTMENT METRICS ---

// Function to fetch appointment counts by status and period
function getAppointmentCount($conn, $counselor_id, $status, $period = 'monthly') {
    $count = 0;
    $date_clause = '';

    switch ($period) {
        case 'daily':
            $date_clause = "AND DATE(appointment_date) = CURDATE()";
            break;
        case 'weekly':
            $date_clause = "AND YEARWEEK(appointment_date) = YEARWEEK(CURDATE())";
            break;
        case 'monthly':
            $date_clause = "AND MONTH(appointment_date) = MONTH(CURDATE()) AND YEAR(appointment_date) = YEAR(CURDATE())";
            break;
        case 'total':
            $date_clause = ""; // All-time total
            break;
    }

    $query = "SELECT COUNT(id) as count FROM appointments WHERE counselor_id = ? AND status = ? $date_clause";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "is", $counselor_id, $status);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = mysqli_fetch_assoc($result)['count'];
        mysqli_stmt_close($stmt);
    }
    return $count;
}

// Monthly Appointments
$app_monthly_pending = getAppointmentCount($conn, $user_id, 'pending', 'monthly');
$app_monthly_accepted = getAppointmentCount($conn, $user_id, 'accepted', 'monthly');
$app_monthly_successful = getAppointmentCount($conn, $user_id, 'successful', 'monthly');
$app_monthly_cancelled = getAppointmentCount($conn, $user_id, 'cancelled', 'monthly');
$app_total_monthly = $app_monthly_pending + $app_monthly_accepted + $app_monthly_successful + $app_monthly_cancelled;

// Total Appointments (All Time)
$app_total_accepted = getAppointmentCount($conn, $user_id, 'accepted', 'total');
$app_total_successful = getAppointmentCount($conn, $user_id, 'successful', 'total');
$app_total_cancelled = getAppointmentCount($conn, $user_id, 'cancelled', 'total');
$app_total_all_time = $app_total_accepted + $app_total_successful + $app_total_cancelled + getAppointmentCount($conn, $user_id, 'pending', 'total');


// --- 2. ASSESSMENT METRICS ---

// Function to fetch assessment counts by submission status and period (Filtered by assessments CREATED by the counselor)
function getAssessmentCount($conn, $counselor_id, $status, $period = 'monthly') {
    $count = 0;
    $date_clause = '';

    switch ($period) {
        case 'daily':
            $date_clause = "AND DATE(sa.submitted_at) = CURDATE()";
            break;
        case 'weekly':
            $date_clause = "AND YEARWEEK(sa.submitted_at) = YEARWEEK(CURDATE())";
            break;
        case 'monthly':
            $date_clause = "AND MONTH(sa.submitted_at) = MONTH(CURDATE()) AND YEAR(sa.submitted_at) = YEAR(CURDATE())";
            break;
        case 'total_created':
            // Count total unique assessments created by the counselor (not submissions)
            $query = "SELECT COUNT(id) as count FROM assessments WHERE created_by = ?";
            if ($stmt = mysqli_prepare($conn, $query)) {
                mysqli_stmt_bind_param($stmt, "i", $counselor_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $count = mysqli_fetch_assoc($result)['count'];
                mysqli_stmt_close($stmt);
            }
            return $count;
        case 'total_submissions':
            // Total assignments (submissions) made by students from this counselor's assessments
            $query = "SELECT COUNT(sa.id) as count FROM student_assessments sa JOIN assessments a ON sa.assessment_id = a.id WHERE a.created_by = ?";
            if ($stmt = mysqli_prepare($conn, $query)) {
                mysqli_stmt_bind_param($stmt, "i", $counselor_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $count = mysqli_fetch_assoc($result)['count'];
                mysqli_stmt_close($stmt);
            }
            return $count;
    }

    $query = "SELECT COUNT(sa.id) as count 
              FROM student_assessments sa
              JOIN assessments a ON sa.assessment_id = a.id
              WHERE a.created_by = ? AND sa.status = ? $date_clause";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "is", $counselor_id, $status);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = mysqli_fetch_assoc($result)['count'];
        mysqli_stmt_close($stmt);
    }
    return $count;
}

// Monthly Assessments
$ass_monthly_completed = getAssessmentCount($conn, $user_id, 'completed', 'monthly');
$ass_monthly_pending = getAssessmentCount($conn, $user_id, 'pending', 'monthly'); // Assessments assigned but not submitted this month (approximation)
$ass_total_monthly = $ass_monthly_completed; // Only count completed for monthly performance

// Total Assessments (All Time)
$ass_total_created = getAssessmentCount($conn, $user_id, '', 'total_created');
$ass_total_submissions = getAssessmentCount($conn, $user_id, '', 'total_submissions');
$ass_total_completed = getAssessmentCount($conn, $user_id, 'completed', 'total');
$ass_total_pending = getAssessmentCount($conn, $user_id, 'pending', 'total');

// Completion Rate Calculation
$completion_rate = ($ass_total_submissions > 0) 
                     ? round(($ass_total_completed / $ass_total_submissions) * 100, 1) 
                     : 0;

// Success Rate Calculation (Successful Appointments / Total Completed Appointments)
$app_success_rate = ($app_total_successful + $app_total_cancelled > 0) 
                      ? round(($app_total_successful / ($app_total_successful + $app_total_cancelled)) * 100, 1) 
                      : 0;

// --- 3. CHART DATA (Last 6 Months Completed Assessments) ---

$chart_data = [];
$labels = [];
$assessment_counts = [];

for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    
    // Count completed assessments submitted within the month
    $query = "SELECT COUNT(sa.id) as count 
              FROM student_assessments sa
              JOIN assessments a ON sa.assessment_id = a.id
              WHERE a.created_by = ? AND sa.status = 'completed'
              AND sa.submitted_at BETWEEN ? AND ?";
    
    $count = 0;
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = mysqli_fetch_assoc($result)['count'];
        mysqli_stmt_close($stmt);
    }

    $labels[] = $month_label;
    $assessment_counts[] = $count;
}

$chart_labels_json = json_encode($labels);
$assessment_counts_json = json_encode($assessment_counts);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counselor Performance Report</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
    <style>
    /* Add the same or similar styling as dashboard.php for consistency */
    body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .sidebar { background-color: #2b2b2b; min-height: 100vh; color: white; }
    .sidebar-link { color: rgba(255, 255, 255, 0.8); padding: 10px 15px; display: block; text-decoration: none; transition: all 0.25s ease-in-out; border-radius: 5px; margin-bottom: 5px; }
    .sidebar-link:hover, .sidebar-link.active { color: white; background-color: #b30104; }
    .navbar-dark.bg-dark { background-color: #1e1e1e !important; }
    .content { padding: 20px; }
    .card { margin-bottom: 20px; border: none; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .stat-card { } 
    .stat-card:hover { } 
    .border-left-primary { border-left: 0.25rem solid #007bff !important; }
    .border-left-success { border-left: 0.25rem solid #28a745 !important; }
    .border-left-info { border-left: 0.25rem solid #17a2b8 !important; }
    .border-left-warning { border-left: 0.25rem solid #ffc107 !important; }
    .border-left-danger { border-left: 0.25rem solid #dc3545 !important; }
    .text-primary { color: #b30104 !important; }
    .bg-primary { background-color: #b30104 !important; }
    .btn-primary { background-color: #b30104; border: none; }
    .btn-primary:hover { background-color: #8a0103; }
    .badge-primary { background-color: #b30104; color: white; }
    </style>

</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Student Wellness Hub - Admin</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user["username"]); ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar py-3">
                <div class="user-info mb-3 text-center">
                    <div class="h5"><?php echo htmlspecialchars($user["full_name"]); ?></div>
                    <div class="small"><?php echo htmlspecialchars($user["email"]); ?></div>
                    <div class="badge badge-danger">Counselor</div>
                </div>
                <hr>
                <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="user_management.php" class="sidebar-link"><i class="fas fa-users"></i> User Management</a>
                <a href="assessment_management.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> Assessment Tools</a>
                <a href="appointment_management.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="view_assessments.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> View Assessments</a>
                
                </div>

            <div class="col-md-9 col-lg-10 content">
                
                <a href="dashboard.php" class="btn btn-sm btn-danger mb-3"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <a href="export_performance.php?format=pdf" class="btn btn-sm btn-info mb-3" target="_blank"><i class="fas fa-file-pdf"></i> Download PDF</a>
                <h2 class="mb-4 text-primary"><i class="fas fa-chart-line"></i> Counselor Performance Report</h2>
                <p class="lead">Performance metrics for **<?php echo htmlspecialchars($user["full_name"]); ?>**.</p>
                
                <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="fas fa-clipboard-check"></i> Assessment Performance Summary</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-xl-3 col-md-6">
                                <div class="card stat-card border-left-success">
                                    <div class="card-body">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Assessments Created</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $ass_total_created; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card stat-card border-left-info">
                                    <div class="card-body">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Submissions (All Time)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $ass_total_submissions; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card stat-card border-left-primary">
                                    <div class="card-body">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Assessments Completed (This Month)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $ass_monthly_completed; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card stat-card border-left-warning">
                                    <div class="card-body">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Overall Completion Rate</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $completion_rate; ?>%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-calendar-alt"></i> Appointment Performance Summary</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-xl-3 col-md-6">
                                <div class="card stat-card border-left-primary">
                                    <div class="card-body">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Appointments (All Time)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $app_total_all_time; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card stat-card border-left-success">
                                    <div class="card-body">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Successful Appointments (This Month)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $app_monthly_successful; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card stat-card border-left-danger">
                                    <div class="card-body">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Cancelled Appointments (All Time)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $app_total_cancelled; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card stat-card border-left-warning">
                                    <div class="card-body">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Appointment Success Rate</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $app_success_rate; ?>%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-info text-white">
                                <h4 class="mb-0"><i class="fas fa-chart-pie"></i> Completed Assessments Distribution (Last 6 Months)</h4>
                            </div>
                            <div class="card-body" style="height: 400px;"> 
                                <canvas id="assessmentTrendChart"></canvas>
                                <hr>
                                <p class="small text-muted mb-0">This chart shows the percentage distribution of completed assessments, created by you, over the last six months.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h4 class="mb-0"><i class="fas fa-table"></i> Detailed Monthly Metrics (<?php echo date('F Y'); ?>)</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Metric</th>
                                        <th>Assessments</th>
                                        <th>Appointments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Completed / Successful</strong></td>
                                        <td><span class="badge badge-success"><?php echo $ass_monthly_completed; ?></span></td>
                                        <td><span class="badge badge-success"><?php echo $app_monthly_successful; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Pending / Accepted</strong></td>
                                        <td><span class="badge badge-warning"><?php echo $ass_monthly_pending; ?></span></td>
                                        <td><span class="badge badge-info"><?php echo $app_monthly_accepted; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Pending Requests</strong></td>
                                        <td>N/A</td>
                                        <td><span class="badge badge-warning"><?php echo $app_monthly_pending; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Cancelled / Declined</strong></td>
                                        <td>N/A</td>
                                        <td><span class="badge badge-danger"><?php echo $app_monthly_cancelled; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td class="bg-light"><strong>TOTAL MONTHLY ACTIVITY</strong></td>
                                        <td class="bg-light"><strong><?php echo $ass_total_monthly; ?></strong></td>
                                        <td class="bg-light"><strong><?php echo $app_total_monthly; ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <a href="appointment_management.php" class="btn btn-primary"><i class="fas fa-calendar-check"></i> Manage Appointments</a>
                        <a href="view_assessments.php" class="btn btn-success"><i class="fas fa-chart-bar"></i> View Assessment Results</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        // Chart.js script for Assessment Trend (Pie Chart)
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('assessmentTrendChart').getContext('2d');
            var assessmentTrendChart = new Chart(ctx, {
                type: 'pie', 
                data: {
                    labels: <?php echo $chart_labels_json; ?>,
                    datasets: [{
                        label: 'Completed Assessments',
                        data: <?php echo $assessment_counts_json; ?>,
                        backgroundColor: [
                            '#007bff', // primary
                            '#28a745', // success
                            '#17a2b8', // info
                            '#ffc107', // warning
                            '#dc3545', // danger
                            '#6c757d'  // secondary
                        ], 
                        borderColor: '#ffffff', // White border for separation
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var dataset = data.datasets[tooltipItem.datasetIndex];
                                var total = dataset.data.reduce(function(previousValue, currentValue) {
                                    return previousValue + currentValue;
                                });
                                var currentValue = dataset.data[tooltipItem.index];
                                var percentage = Math.round(((currentValue / total) * 100) * 10) / 10;
                                return data.labels[tooltipItem.index] + ': ' + currentValue + ' (' + percentage + '%)';
                            }
                        }
                    },
                    legend: {
                        display: true,
                        position: 'right', 
                        labels: {
                            boxWidth: 12
                        }
                    },
                    title: {
                        display: false
                    }
                }
            });
        });
    </script>
</body>
</html>
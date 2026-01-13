<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect("../login.php");
}

// Check if user is an admin
if (!isAdmin()) {
    redirect("../index.php");
}

// Get user information
$user = getUserDetails($_SESSION["user_id"]);

// Count total users
$users_query = "SELECT COUNT(*) as total_users FROM users WHERE role = 'student'";
$users_result = mysqli_query($conn, $users_query);
$total_users = mysqli_fetch_assoc($users_result)['total_users'];

// Count assessments sent
$assessments_query = "SELECT COUNT(*) as total_assessments FROM student_assessments";
$assessments_result = mysqli_query($conn, $assessments_query);
$total_assessments = mysqli_fetch_assoc($assessments_result)['total_assessments'];

// Count pending appointments
$appointments_query = "SELECT COUNT(*) as pending_appointments FROM appointments WHERE status = 'pending'";
$appointments_result = mysqli_query($conn, $appointments_query);
$pending_appointments = mysqli_fetch_assoc($appointments_result)['pending_appointments'];

// Recent assessments
$recent_assessments_query = "SELECT sa.id, u.full_name, a.title, sa.status, sa.submitted_at
                           FROM student_assessments sa
                           JOIN users u ON sa.student_id = u.id
                           JOIN assessments a ON sa.assessment_id = a.id
                           ORDER BY sa.submitted_at DESC
                           LIMIT 5";
$recent_assessments = mysqli_query($conn, $recent_assessments_query);

// Recent appointments
$recent_appointments_query = "SELECT a.id, u.full_name, a.appointment_date, a.appointment_time, a.status
                            FROM appointments a
                            JOIN users u ON a.student_id = u.id
                            ORDER BY a.created_at DESC
                            LIMIT 5";
$recent_appointments = mysqli_query($conn, $recent_appointments_query);

// Get daily, weekly, and monthly assessment counts
$daily_assessments_query = "SELECT COUNT(*) as count FROM student_assessments WHERE DATE(submitted_at) = CURDATE()";
$daily_result = mysqli_query($conn, $daily_assessments_query);
$daily_assessments = mysqli_fetch_assoc($daily_result)['count'];

$weekly_assessments_query = "SELECT COUNT(*) as count FROM student_assessments WHERE YEARWEEK(submitted_at) = YEARWEEK(CURDATE())";
$weekly_result = mysqli_query($conn, $weekly_assessments_query);
$weekly_assessments = mysqli_fetch_assoc($weekly_result)['count'];

$monthly_assessments_query = "SELECT COUNT(*) as count FROM student_assessments WHERE MONTH(submitted_at) = MONTH(CURDATE()) AND YEAR(submitted_at) = YEAR(CURDATE())";
$monthly_result = mysqli_query($conn, $monthly_assessments_query);
$monthly_assessments = mysqli_fetch_assoc($monthly_result)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
    /* === General === */
body {
    background-color: #f8f9fa;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    overflow-x: hidden;
    scroll-behavior: smooth;
}

/* === Sidebar (no animation) === */
.sidebar {
    background-color: #2b2b2b;
    min-height: calc(100vh - 56px);
    color: white;
}

.sidebar-link {
    color: rgba(255, 255, 255, 0.8);
    padding: 10px 15px;
    display: block;
    text-decoration: none;
    transition: all 0.25s ease-in-out;
    border-radius: 5px;
    margin-bottom: 5px;
}

.sidebar-link:hover {
    color: white;
    background-color: rgba(179, 1, 4, 0.6);
    transform: translateX(3px);
}

.sidebar-link.active {
    color: white;
    background-color: #b30104;
}

.sidebar-link i {
    margin-right: 10px;
}

/* === Navbar (no animation) === */
.navbar-dark.bg-dark {
    background-color: #1e1e1e !important;
}

/* === Content === */
.content {
    padding: 20px;
    animation: fadeIn 0.5s ease-in-out;
}

/* === Cards === */
.card {
    margin-bottom: 20px;
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease-in-out;
    animation: fadeInUp 0.5s ease-in-out;
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

.card-header.bg-primary { background-color: #b30104 !important; }
.card-header.bg-success { background-color: #008f39 !important; }
.card-header.bg-info { background-color: #008b8b !important; }
.card-header.bg-warning { background-color: #d4a017 !important; }

/* === Stat Cards === */
.stat-card {
    border-radius: 10px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    animation: fadeInUp 0.5s ease-in-out;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

/* === Buttons === */
.btn {
    transition: all 0.25s ease-in-out;
}

.btn-primary {
    background-color: #b30104;
    border: none;
}

.btn-primary:hover {
    background-color: #8a0103;
    transform: translateY(-2px);
}

.btn-success {
    background-color: #008f39;
    border: none;
}

.btn-success:hover {
    background-color: #006f2d;
    transform: translateY(-2px);
}

.btn-info {
    background-color: #008b8b;
    border: none;
}

.btn-info:hover {
    background-color: #006666;
    transform: translateY(-2px);
}

.btn-warning {
    background-color: #d4a017;
    border: none;
}

.btn-warning:hover {
    background-color: #b88c0f;
    transform: translateY(-2px);
}

/* === Alerts === */
.alert-success {
    background-color: #e6f4ea;
    border-left: 5px solid #008f39;
    color: #155724;
    animation: fadeIn 0.4s ease;
}

.alert-danger {
    background-color: #fdeaea;
    border-left: 5px solid #b30104;
    color: #721c24;
    animation: fadeIn 0.4s ease;
}

/* === Table Header === */
.table thead th {
    background-color: #f5f5f5;
    border-bottom: 2px solid #b30104;
}

/* === Animations === */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

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
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar py-3">
                <div class="user-info mb-3 text-center">
                    <div class="h5"><?php echo htmlspecialchars($user["full_name"]); ?></div>
                    <div class="small"><?php echo htmlspecialchars($user["email"]); ?></div>
                    <div class="badge badge-danger">Counselor</div>
                </div>
                <hr>
                <a href="dashboard.php" class="sidebar-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="user_management.php" class="sidebar-link"><i class="fas fa-users"></i> User Management</a>
                <a href="assessment_management.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> Assessment Tools</a>
                <a href="appointment_management.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="view_assessments.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> View Assessments</a>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content">
                <?php
                $flash = getFlashMessage();
                if ($flash) {
                    echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">
                            ' . $flash['message'] . '
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                          </div>';
                }
                ?>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>
                    <div>
                        <a href="assessment_management.php?action=add" class="btn btn-success">
                            <i class="fas fa-plus"></i> New Assessment
                        </a>
                        <a href="appointment_management.php" class="btn btn-primary ml-2">
                            <i class="fas fa-calendar-plus"></i> Manage Appointments
                        </a>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card border-left-primary">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Students</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card border-left-success">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Assessments</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_assessments; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card border-left-info">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Pending Appointments</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pending_appointments; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card border-left-warning">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Daily Assessments</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $daily_assessments; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Assessment Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <td><strong>Daily Assessments:</strong></td>
                                                <td><?php echo $daily_assessments; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Weekly Assessments:</strong></td>
                                                <td><?php echo $weekly_assessments; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Monthly Assessments:</strong></td>
                                                <td><?php echo $monthly_assessments; ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="view_assessments.php" class="btn btn-primary">View All Assessments</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Recent Appointments</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (mysqli_num_rows($recent_appointments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Student</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($appointment = mysqli_fetch_assoc($recent_appointments)): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($appointment['full_name']); ?></td>
                                                        <td>
                                                            <?php echo formatDate($appointment['appointment_date']); ?><br>
                                                            <small><?php echo formatTime($appointment['appointment_time']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if ($appointment['status'] == 'pending'): ?>
                                                                <span class="badge badge-warning">Pending</span>
                                                            <?php elseif ($appointment['status'] == 'accepted'): ?>
                                                                <span class="badge badge-success">Confirmed</span>
                                                            <?php elseif ($appointment['status'] == 'declined'): ?>
                                                                <span class="badge badge-danger">Declined</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-secondary">Cancelled</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="p-4 text-center">
                                        <p class="text-muted">No appointments found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer text-center">
                                <a href="appointment_management.php" class="btn btn-success">Manage Appointments</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard-check"></i> Recent Assessment Submissions</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (mysqli_num_rows($recent_assessments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Student</th>
                                            <th>Assessment</th>
                                            <th>Status</th>
                                            <th>Submitted</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($assessment = mysqli_fetch_assoc($recent_assessments)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($assessment['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($assessment['title']); ?></td>
                                                <td>
                                                    <?php if ($assessment['status'] == 'pending'): ?>
                                                        <span class="badge badge-warning">Pending</span>
                                                    <?php elseif ($assessment['status'] == 'completed'): ?>
                                                        <span class="badge badge-success">Completed</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Cancelled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $assessment['submitted_at'] ? formatDate($assessment['submitted_at']) : 'Not submitted'; ?></td>
                                                <td>
                                                    <?php if ($assessment['status'] == 'completed'): ?>
                                                        <a href="view_assessments.php?id=<?php echo $assessment['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled>Not Available</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-4 text-center">
                                <p class="text-muted">No assessments found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center">
                        <a href="view_assessments.php" class="btn btn-info">View All Assessments</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

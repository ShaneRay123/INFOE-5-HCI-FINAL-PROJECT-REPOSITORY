<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect("../login.php");
}

// Check if user is a student
if (!isStudent()) {
    redirect("../index.php");
}

// Get user information
$user = getUserDetails($_SESSION["user_id"]);

// --------------------------------------------------------------------------
// --- START: AUTOMATIC ASSESSMENT ASSIGNMENT FOR NEW STUDENTS ON LOGIN ---
// --------------------------------------------------------------------------

$current_user_id = (int)$_SESSION["user_id"];

// 1. Get all existing assessment IDs
$all_assessments_query = "SELECT id FROM assessments";
$all_assessments_result = mysqli_query($conn, $all_assessments_query);
$all_assessment_ids = [];
while ($row = mysqli_fetch_assoc($all_assessments_result)) {
    $all_assessment_ids[] = (int)$row['id'];
}

if (!empty($all_assessment_ids)) {

    // 2. Get assessment IDs already assigned to this student
    $assigned_assessments_query = "SELECT assessment_id FROM student_assessments WHERE student_id = ?";
    $stmt_check = mysqli_prepare($conn, $assigned_assessments_query);
    mysqli_stmt_bind_param($stmt_check, "i", $current_user_id);
    mysqli_stmt_execute($stmt_check);
    $assigned_result = mysqli_stmt_get_result($stmt_check);

    $already_assigned_ids = [];
    while ($row = mysqli_fetch_assoc($assigned_result)) {
        $already_assigned_ids[] = (int)$row['assessment_id'];
    }
    mysqli_stmt_close($stmt_check);

    // 3. Determine assessments that need to be assigned
    // Finds IDs in $all_assessment_ids that are NOT in $already_assigned_ids
    $unassigned_ids = array_diff($all_assessment_ids, $already_assigned_ids);

    if (!empty($unassigned_ids)) {

        // 4. Assign the missing assessments
        // NOTE: We explicitly set status to 'pending' to make them visible on the dashboard
        $insert_student_assessment = "INSERT INTO student_assessments (student_id, assessment_id, status) VALUES (?, ?, 'pending')";
        $stmt_insert = mysqli_prepare($conn, $insert_student_assessment);

        $assignments_made = 0;
        foreach ($unassigned_ids as $assessment_id_to_assign) {
            mysqli_stmt_bind_param($stmt_insert, "ii", $current_user_id, $assessment_id_to_assign);
            if (mysqli_stmt_execute($stmt_insert)) {
                $assignments_made++;
            }
        }
        mysqli_stmt_close($stmt_insert);

        if ($assignments_made > 0) {
            // Optional: Set a flash message to inform the student of the new assignments
            setFlashMessage("Welcome! You have been automatically assigned " . $assignments_made . " new assessment(s) to complete.", "info");
        }
    }
}

// --------------------------------------------------------------------------
// --- END: AUTOMATIC ASSESSMENT ASSIGNMENT FOR NEW STUDENTS ON LOGIN ---
// --------------------------------------------------------------------------


// Get pending assessments
$pending_assessments_query = "SELECT sa.id, a.title, sa.status, sa.submitted_at 
                            FROM student_assessments sa 
                            JOIN assessments a ON sa.assessment_id = a.id 
                            WHERE sa.student_id = ? AND sa.status = 'pending'";
$stmt = mysqli_prepare($conn, $pending_assessments_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);
$pending_assessments = mysqli_stmt_get_result($stmt);

// Get upcoming appointments
// The query is designed to show upcoming appointments (date >= today)
$appointments_query = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, u.full_name as counselor_name 
                    FROM appointments a 
                    JOIN users u ON a.counselor_id = u.id 
                    WHERE a.student_id = ? AND a.status != 'cancelled' AND a.appointment_date >= CURDATE() 
                    ORDER BY a.appointment_date, a.appointment_time";
$stmt = mysqli_prepare($conn, $appointments_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);
$appointments = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
    body {
    background-color: #f8f9fa;
}

/* Sidebar (unchanged — no animation) */
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
    transition: 0.3s;
    border-radius: 5px;
    margin-bottom: 5px;
}

.sidebar-link:hover {
    color: white;
    background-color: rgba(179, 1, 4, 0.6);
    text-decoration: none;
}

.sidebar-link.active {
    color: white;
    background-color: #b30104;
}

.sidebar-link i {
    margin-right: 10px;
}

/* Navbar */
.navbar-dark.bg-dark {
    background-color: #1e1e1e !important;
}

/* Content */
.content {
    padding: 20px;
}

/* Card styling */
.card {
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: none;
    border-radius: 8px;
}

/* Card header */
.card-header {
    background-color: #b30104 !important;
    color: white;
    font-weight: bold;
}

/* Buttons */
.btn-primary {
    background-color: #b30104;
    border: none;
}
.btn-primary:hover {
    background-color: #8a0103;
}

.btn-outline-primary {
    color: #b30104;
    border-color: #b30104;
}
.btn-outline-primary:hover {
    background-color: #b30104;
    color: white;
}

.btn-success {
    background-color: #008f39;
    border: none;
}
.btn-success:hover {
    background-color: #006f2d;
}

.btn-info {
    background-color: #17a2b8;
    border: none;
}
.btn-info:hover {
    background-color: #117a8b;
}

.btn-outline-success {
    color: #008f39;
    border-color: #008f39;
}
.btn-outline-success:hover {
    background-color: #008f39;
    color: white;
}

/* ===== ANIMATIONS ===== */

/* Fade-up animation */
@keyframes fadeUp {
    0% {
        opacity: 0;
        transform: translateY(20px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Fade-in animation */
@keyframes fadeIn {
    0% { opacity: 0; }
    100% { opacity: 1; }
}

/* Apply fade-in to navbar and content */

.content {
    animation: fadeIn 0.8s ease-out;
}

/* Apply slide-up animation to jumbotron and cards */
.jumbotron {
    animation: fadeUp 0.8s ease-out;
}

.card {
    animation: fadeUp 0.7s ease-out;
    animation-fill-mode: both;
}

/* Staggered timing for smooth entrance */
.card:nth-child(1) { animation-delay: 0.2s; }
.card:nth-child(2) { animation-delay: 0.4s; }
.card:nth-child(3) { animation-delay: 0.6s; }

/* Button hover motion */
.btn {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

/* Smooth alert fade-in */
.alert {
    animation: fadeIn 0.6s ease-out;
}

/* Jumbotron subtle hover lift */
.jumbotron:hover {
    transform: translateY(-2px);
    transition: transform 0.3s ease;
}

</style>

</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Student Wellness Hub</a>
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
                            <a class="dropdown-item" href="view_profile.php"><i class="fas fa-user"></i> Profile</a>
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
            <div class="col-md-3 col-lg-2 sidebar py-3">
                <div class="user-info mb-3 text-center">
                    <div class="h5"><?php echo htmlspecialchars($user["full_name"]); ?></div>
                    <div class="small"><?php echo htmlspecialchars($user["email"]); ?></div>
                </div>
                <hr>
                <a href="dashboard.php" class="sidebar-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="take_assessment.php" class="sidebar-link"><i class="fas fa-tasks"></i> Take Assessment</a>
            
                <a href="manage_appointment.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Manage Appointments</a>
                <a href="update_information.php" class="sidebar-link"><i class="fas fa-user-edit"></i> Update Information</a>
                <a href="view_schedule.php" class="sidebar-link"><i class="fas fa-calendar-alt"></i> View Schedule</a>
            </div>
            
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
                
                <div class="jumbotron">
                    <h1 class="display-4">Hello, <?php echo htmlspecialchars($user["full_name"]); ?>!</h1>
                    <p class="lead">Welcome to your Student Wellness Hub dashboard. Here you can manage your assessments and appointments.</p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-tasks"></i> Pending Assessments
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($pending_assessments) > 0): ?>
                                    <div class="list-group">
                                        <?php while ($assessment = mysqli_fetch_assoc($pending_assessments)): ?>
                                            <a href="take_assessment.php?id=<?php echo $assessment['id']; ?>" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($assessment['title']); ?></h5>
                                                </div>
                                                <p class="mb-1">Status: <span class="badge badge-warning">Pending</span></p>
                                                <div class="mt-2">
                                                    <a href="take_assessment.php?id=<?php echo $assessment['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit"></i> Take Assessment
                                                    </a>
                                                    <a href="view_assessment_details.php?id=<?php echo $assessment['id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </a>
                                                </div>
                                            </a>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No pending assessments.</p>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <a href="take_assessment.php" class="btn btn-outline-primary btn-sm">View All Assessments</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-calendar-check"></i> Appointments
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($appointments) > 0): ?>
                                    <div class="list-group">
                                        <?php while ($appointment = mysqli_fetch_assoc($appointments)): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1">Counselor: <?php echo htmlspecialchars($appointment['counselor_name']); ?></h5>
                                                    
                                                    <?php if ($appointment['status'] == 'pending'): ?>
                                                        <span class="badge badge-warning">Pending</span>
                                                    <?php elseif ($appointment['status'] == 'accepted'): ?>
                                                        <span class="badge badge-success">Confirmed</span>
                                                    <?php elseif ($appointment['status'] == 'declined'): ?>
                                                        <span class="badge badge-danger">Declined</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-info"><?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></span>
                                                    <?php endif; ?>
                                                    </div>
                                                <p class="mb-1">
                                                    Date: <?php echo formatDate($appointment['appointment_date']); ?><br>
                                                    Time: <?php echo formatTime($appointment['appointment_time']); ?>
                                                </p>
                                                <div class="mt-2">
                                                    <?php if ($appointment['status'] == 'pending'): ?>
                                                        <a href="manage_appointment.php?id=<?php echo $appointment['id']; ?>&action=cancel" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel</a>
                                                    <?php endif; ?>
                                                    <a href="view_appointment_details.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No upcoming appointments.</p>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <a href="manage_appointment.php" class="btn btn-outline-success btn-sm">Manage Appointments</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <i class="fas fa-chart-line"></i> Quick Actions
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 text-center mb-3">
                                        <a href="take_assessment.php" class="btn btn-primary btn-lg btn-block">
                                            <i class="fas fa-tasks fa-2x mb-2"></i><br>
                                            Take Assessment
                                        </a>
                                    </div>
                                    <div class="col-md-4 text-center mb-3">
                                        <a href="manage_appointment.php" class="btn btn-success btn-lg btn-block">
                                            <i class="fas fa-calendar-plus fa-2x mb-2"></i><br>
                                            Schedule Appointment
                                        </a>
                                    </div>
                                    <div class="col-md-4 text-center mb-3">
                                        <a href="view_profile.php" class="btn btn-info btn-lg btn-block">
                                            <i class="fas fa-user fa-2x mb-2"></i><br>
                                            View Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
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

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

// Get statistics
// Count completed assessments
$completed_assessments_query = "SELECT COUNT(*) as count FROM student_assessments WHERE student_id = ? AND status = 'completed'";
$stmt = mysqli_prepare($conn, $completed_assessments_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);
$completed_result = mysqli_stmt_get_result($stmt);
$completed_assessments = mysqli_fetch_assoc($completed_result)['count'];

// Count pending assessments
$pending_assessments_query = "SELECT COUNT(*) as count FROM student_assessments WHERE student_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($conn, $pending_assessments_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);
$pending_result = mysqli_stmt_get_result($stmt);
$pending_assessments = mysqli_fetch_assoc($pending_result)['count'];

// Count upcoming appointments
$upcoming_appointments_query = "SELECT COUNT(*) as count FROM appointments WHERE student_id = ? AND appointment_date >= CURDATE() AND status != 'cancelled'";
$stmt = mysqli_prepare($conn, $upcoming_appointments_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);
$upcoming_result = mysqli_stmt_get_result($stmt);
$upcoming_appointments = mysqli_fetch_assoc($upcoming_result)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        .sidebar {
            background-color: #343a40;
            min-height: calc(100vh - 56px);
            color: white;
        }
        .sidebar-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 10px 15px;
            display: block;
            text-decoration: none;
            transition: 0.3s;
        }
        .sidebar-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            text-decoration: none;
        }
        .sidebar-link.active {
            color: white;
            background-color: #007bff;
        }
        .sidebar-link i {
            margin-right: 10px;
        }
        .content {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .profile-header {
            background: linear-gradient(to right, #4b6cb7, #182848);
            color: white;
            padding: 2rem;
            border-radius: 5px;
            margin-bottom: 2rem;
        }
        .profile-avatar {
            font-size: 5rem;
            background-color: rgba(255,255,255,0.2);
            border-radius: 50%;
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        .stat-card {
            text-align: center;
            border-radius: 5px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            color: white;
        }
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
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
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar py-3">
                <div class="user-info mb-3 text-center">
                    <div class="h5"><?php echo htmlspecialchars($user["full_name"]); ?></div>
                    <div class="small"><?php echo htmlspecialchars($user["email"]); ?></div>
                </div>
                <hr>
                <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="take_assessment.php" class="sidebar-link"><i class="fas fa-tasks"></i> Take Assessment</a>
                <a href="view_assessment.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> View Assessments</a>
                <a href="manage_appointment.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Manage Appointments</a>
                <a href="update_information.php" class="sidebar-link"><i class="fas fa-user-edit"></i> Update Information</a>
                <a href="view_schedule.php" class="sidebar-link"><i class="fas fa-calendar-alt"></i> View Schedule</a>
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
                
                <div class="profile-header text-center">
                    <div class="profile-avatar">
                        <?php if(!empty($user['profile_photo']) && $user['profile_photo'] != 'default.jpg'): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Photo" class="img-thumbnail rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <h2><?php echo htmlspecialchars($user["full_name"]); ?></h2>
                    <p class="mb-0"><?php echo htmlspecialchars($user["email"]); ?></p>
                    <p class="small">Member since: <?php echo formatDate($user["created_at"]); ?></p>
                    <a href="update_information.php" class="btn btn-light mt-2">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </a>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="stat-card bg-primary">
                            <div class="stat-icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <h3><?php echo $completed_assessments; ?></h3>
                            <p class="mb-0">Completed Assessments</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-warning">
                            <div class="stat-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <h3><?php echo $pending_assessments; ?></h3>
                            <p class="mb-0">Pending Assessments</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-success">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h3><?php echo $upcoming_appointments; ?></h3>
                            <p class="mb-0">Upcoming Appointments</p>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-id-card"></i> Account Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($user["username"]); ?></p>
                                <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user["full_name"]); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user["email"]); ?></p>
                                <p><strong>Student Number:</strong> <?php echo !empty($user["student_number"]) ? htmlspecialchars($user["student_number"]) : '<em>Not specified</em>'; ?></p>
                                <p><strong>Department:</strong> <?php echo !empty($user["department"]) ? htmlspecialchars($user["department"]) : '<em>Not specified</em>'; ?></p>
                                <p><strong>Birthday:</strong> <?php echo !empty($user["birthday"]) ? formatDate($user["birthday"]) : '<em>Not specified</em>'; ?></p>
                                <?php 
                                // Calculate age if birthday is set
                                if (!empty($user["birthday"])) {
                                    $birthDate = new DateTime($user["birthday"]);
                                    $today = new DateTime('today');
                                    $age = $birthDate->diff($today)->y;
                                    echo "<p><strong>Age:</strong> " . $age . " years</p>";
                                } else {
                                    echo "<p><strong>Age:</strong> <em>Not specified</em></p>";
                                }
                                ?>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Role:</strong> Student</p>
                                <p><strong>Course Year:</strong> <?php echo !empty($user["course_year"]) ? htmlspecialchars($user["course_year"]) : '<em>Not specified</em>'; ?></p>
                                <p><strong>Section:</strong> <?php echo !empty($user["section"]) ? htmlspecialchars($user["section"]) : '<em>Not specified</em>'; ?></p>
                                <p><strong>Address:</strong> <?php echo !empty($user["address"]) ? htmlspecialchars($user["address"]) : '<em>Not specified</em>'; ?></p>
                                <p><strong>Account Created:</strong> <?php echo formatDate($user["created_at"]); ?></p>
                                <p><strong>Password:</strong> ••••••••• <a href="update_information.php" class="text-primary ml-2"><small>Change</small></a></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="update_information.php" class="btn btn-primary">
                        <i class="fas fa-user-edit"></i> Update Information
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

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

// Check if specific assessment ID is provided
if (isset($_GET['id'])) {
    $assessment_id = $_GET['id'];
    
    // Get assessment details
    $assessment_query = "SELECT sa.id, a.title, a.description, sa.status, sa.submitted_at 
                      FROM student_assessments sa 
                      JOIN assessments a ON sa.assessment_id = a.id 
                      WHERE sa.id = ? AND sa.student_id = ?";
    $stmt = mysqli_prepare($conn, $assessment_query);
    mysqli_stmt_bind_param($stmt, "ii", $assessment_id, $_SESSION["user_id"]);
    mysqli_stmt_execute($stmt);
    $assessment_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($assessment_result) == 0) {
        setFlashMessage("Assessment not found.", "danger");
        redirect("view_assessment.php");
    }
    
    $assessment = mysqli_fetch_assoc($assessment_result);
    
    // Get responses for this assessment
    $responses_query = "SELECT q.question_text, q.question_type, r.response_text 
                     FROM responses r 
                     JOIN questions q ON r.question_id = q.id 
                     WHERE r.student_assessment_id = ?";
    $stmt = mysqli_prepare($conn, $responses_query);
    mysqli_stmt_bind_param($stmt, "i", $assessment_id);
    mysqli_stmt_execute($stmt);
    $responses = mysqli_stmt_get_result($stmt);
    
} else {
    // Get all completed assessments
    $assessments_query = "SELECT sa.id, a.title, sa.status, sa.submitted_at 
                       FROM student_assessments sa 
                       JOIN assessments a ON sa.assessment_id = a.id 
                       WHERE sa.student_id = ? AND sa.status = 'completed' 
                       ORDER BY sa.submitted_at DESC";
    $stmt = mysqli_prepare($conn, $assessments_query);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
    mysqli_stmt_execute($stmt);
    $assessments = mysqli_stmt_get_result($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Assessments - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
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
        }
        .sidebar-link:hover {
            color: white;
            background-color: #b30104;
            text-decoration: none;
        }
        .sidebar-link.active {
            color: white;
            background-color: #b30104;
        }
        .sidebar-link i {
            margin-right: 10px;
        }
        .content {
            padding: 20px;
        }
         /* Navbar */
    .navbar-dark.bg-dark {
        background-color: #1e1e1e !important;
    }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #b30104;
            color: #fff;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
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

    <!-- Page Layout -->
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
                <a href="view_assessment.php" class="sidebar-link active"><i class="fas fa-clipboard-list"></i> View Assessments</a>
                <a href="manage_appointment.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Manage Appointments</a>
                <a href="update_information.php" class="sidebar-link"><i class="fas fa-user-edit"></i> Update Information</a>
                <a href="view_schedule.php" class="sidebar-link"><i class="fas fa-calendar-alt"></i> View Schedule</a>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-clipboard-list"></i> Completed Assessments</h4>
                    </div>
                    <div class="card-body">
                        <p class="lead">View your completed assessment history.</p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You haven't completed any assessments yet.
                        </div>
                        <div class="mt-3">
                            <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                            <a href="take_assessment.php" class="btn btn-primary"><i class="fas fa-tasks"></i> Take an Assessment</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

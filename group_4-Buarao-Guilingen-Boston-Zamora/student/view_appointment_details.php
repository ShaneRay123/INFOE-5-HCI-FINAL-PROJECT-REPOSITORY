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

// Check if appointment ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage("Appointment ID not provided.", "danger");
    redirect("dashboard.php");
}

$appointment_id = $_GET['id'];

// Get appointment details
$appointment_query = "SELECT a.*, 
                    u.full_name as counselor_name, 
                    u.email as counselor_email,
                    u.department as counselor_department
                    FROM appointments a 
                    JOIN users u ON a.counselor_id = u.id 
                    WHERE a.id = ? AND a.student_id = ?";
$stmt = mysqli_prepare($conn, $appointment_query);
mysqli_stmt_bind_param($stmt, "ii", $appointment_id, $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    setFlashMessage("Appointment not found.", "danger");
    redirect("dashboard.php");
}

$appointment = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - Wellness Hub</title>
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
        .appointment-details {
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 20px;
            border-radius: 5px;
        }
        .status-badge {
            font-size: 1rem;
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
                <a href="manage_appointment.php" class="sidebar-link active"><i class="fas fa-calendar-check"></i> Manage Appointments</a>
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
                
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4><i class="fas fa-calendar-check"></i> Appointment Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h3>Appointment with <?php echo htmlspecialchars($appointment['counselor_name']); ?></h3>
                            <p class="lead">
                                <?php
                                if ($appointment['status'] == 'pending') {
                                    echo '<span class="badge badge-warning status-badge">Pending</span>';
                                } elseif ($appointment['status'] == 'accepted') {
                                    echo '<span class="badge badge-success status-badge">Confirmed</span>';
                                } elseif ($appointment['status'] == 'declined') {
                                    echo '<span class="badge badge-danger status-badge">Declined</span>';
                                } else {
                                    echo '<span class="badge badge-secondary status-badge">Cancelled</span>';
                                }
                                ?>
                            </p>
                        </div>
                        
                        <div class="appointment-details mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5><i class="fas fa-calendar-alt"></i> Date & Time</h5>
                                    <p class="lead"><?php echo formatDate($appointment['appointment_date']); ?> at <?php echo formatTime($appointment['appointment_time']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h5><i class="fas fa-clock"></i> Status</h5>
                                    <p>
                                        <?php 
                                        if ($appointment['status'] == 'pending') {
                                            echo 'Your appointment is waiting for counselor confirmation';
                                        } elseif ($appointment['status'] == 'accepted') {
                                            echo 'Your appointment has been confirmed by the counselor';
                                        } elseif ($appointment['status'] == 'declined') {
                                            echo 'Your appointment was declined by the counselor';
                                        } else {
                                            echo 'Your appointment was cancelled';
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h5><i class="fas fa-user-md"></i> Counselor Information</h5>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($appointment['counselor_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($appointment['counselor_email']); ?></p>
                            <p><strong>Department:</strong> <?php echo !empty($appointment['counselor_department']) ? htmlspecialchars($appointment['counselor_department']) : 'Not specified'; ?></p>
                            
                            <?php if (!empty($appointment['notes'])): ?>
                                <hr>
                                <h5><i class="fas fa-sticky-note"></i> Notes</h5>
                                <div class="card">
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($appointment['notes'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center">
                            <?php if ($appointment['status'] == 'pending' || $appointment['status'] == 'accepted'): ?>
                                <a href="manage_appointment.php?id=<?php echo $appointment_id; ?>&action=cancel" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                    <i class="fas fa-times"></i> Cancel Appointment
                                </a>
                            <?php endif; ?>
                            
                            <a href="manage_appointment.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-list"></i> View All Appointments
                            </a>
                            
                            <a href="dashboard.php" class="btn btn-primary ml-2">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
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

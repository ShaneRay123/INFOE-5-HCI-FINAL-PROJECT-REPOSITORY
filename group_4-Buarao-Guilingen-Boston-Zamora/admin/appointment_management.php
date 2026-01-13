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

// Handle appointment actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $appointment_id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'accept') {
        $update_query = "UPDATE appointments SET status = 'accepted' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "i", $appointment_id);
        
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage("Appointment accepted successfully.", "success");
        } else {
            setFlashMessage("Error updating appointment.", "danger");
        }
    } elseif ($action == 'decline') {
        $update_query = "UPDATE appointments SET status = 'declined' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "i", $appointment_id);
        
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage("Appointment declined successfully.", "success");
        } else {
            setFlashMessage("Error updating appointment.", "danger");
        }
    }
    
    redirect("appointment_management.php");
}

// Handle new appointment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_appointment'])) {
    $student_id = $_POST['student_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $notes = sanitize($_POST['notes']);
    
    // Validate input
    $errors = [];
    
    if (empty($student_id)) {
        $errors[] = "Please select a student.";
    }
    
    if (empty($appointment_date)) {
        $errors[] = "Please select a date.";
    } else {
        // Check if date is in the future
        $selected_date = new DateTime($appointment_date);
        $today = new DateTime();
        if ($selected_date < $today) {
            $errors[] = "Please select a future date.";
        }
    }
    
    if (empty($appointment_time)) {
        $errors[] = "Please select a time.";
    }
    
    // If no errors, create appointment
    if (empty($errors)) {
        $insert_query = "INSERT INTO appointments (student_id, counselor_id, appointment_date, appointment_time, notes, status) 
                       VALUES (?, ?, ?, ?, ?, 'accepted')";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "iisss", $student_id, $_SESSION['user_id'], $appointment_date, $appointment_time, $notes);
        
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage("Appointment scheduled successfully.", "success");
            redirect("appointment_management.php");
        } else {
            setFlashMessage("Error scheduling appointment.", "danger");
        }
    } else {
        $error_msg = implode("<br>", $errors);
        setFlashMessage($error_msg, "danger");
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// Get all appointments for this counselor with more student details
$query = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes, 
         u.full_name as student_name, u.email as student_email, u.department, u.course_year, u.section, u.id as student_id
         FROM appointments a 
         JOIN users u ON a.student_id = u.id 
         WHERE a.counselor_id = ?";

$params = [$_SESSION['user_id']];
$types = "i";

if (!empty($status_filter)) {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_filter)) {
    $query .= " AND a.appointment_date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$query .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$appointments = mysqli_stmt_get_result($stmt);

// Get students for dropdown
$students_query = "SELECT id, full_name, email FROM users WHERE role = 'student' ORDER BY full_name";
$students = mysqli_query($conn, $students_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Management - Wellness Hub</title>
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
            background-color: #dc3545;
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
                <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="user_management.php" class="sidebar-link"><i class="fas fa-users"></i> User Management</a>
                <a href="assessment_management.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> Assessment Tools</a>
                <a href="appointment_management.php" class="sidebar-link active"><i class="fas fa-calendar-check"></i> Appointments</a>
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
                
                <h2><i class="fas fa-calendar-check"></i> Appointment Management</h2>
                <p class="lead">Schedule and manage counseling appointments with students.</p>
                
                <div class="row">
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-calendar-plus"></i> Schedule New Appointment</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="form-group">
                                        <label for="student_id">Select Student</label>
                                        <select class="form-control" id="student_id" name="student_id" required>
                                            <option value="">-- Select Student --</option>
                                            <?php while ($student = mysqli_fetch_assoc($students)): ?>
                                                <option value="<?php echo $student['id']; ?>">
                                                    <?php echo htmlspecialchars($student['full_name']) . ' (' . htmlspecialchars($student['email']) . ')'; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="appointment_date">Date</label>
                                        <input type="date" class="form-control" id="appointment_date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="appointment_time">Time</label>
                                        <input type="time" class="form-control" id="appointment_time" name="appointment_time" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="notes">Notes (Optional)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any notes about this appointment..."></textarea>
                                    </div>
                                    <button type="submit" name="schedule_appointment" class="btn btn-primary btn-block">Schedule Appointment</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Appointments</h5>
                                <div>
                                    <button class="btn btn-sm btn-light" type="button" data-toggle="collapse" data-target="#filterCollapse">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                </div>
                            </div>
                            
                            <div class="collapse" id="filterCollapse">
                                <div class="card-body bg-light">
                                    <form action="" method="get" class="row">
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label>Status</label>
                                                <select class="form-control" name="status">
                                                    <option value="">All Statuses</option>
                                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="accepted" <?php echo $status_filter == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                                    <option value="declined" <?php echo $status_filter == 'declined' ? 'selected' : ''; ?>>Declined</option>
                                                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label>Date</label>
                                                <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <div class="form-group mb-0 w-100">
                                                <button type="submit" class="btn btn-primary btn-block">Filter</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="card-body p-0">
                                <?php if (mysqli_num_rows($appointments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Student</th>
                                                    <th>Date & Time</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($appointment = mysqli_fetch_assoc($appointments)): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($appointment['student_name']); ?></strong><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['student_email']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php echo formatDate($appointment['appointment_date']); ?><br>
                                                            <small><?php echo formatTime($appointment['appointment_time']); ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if ($appointment['status'] == 'pending'): ?>
                                                                <span class="badge badge-warning">Pending</span>
                                                            <?php elseif ($appointment['status'] == 'accepted'): ?>
                                                                <span class="badge badge-success">Accepted</span>
                                                            <?php elseif ($appointment['status'] == 'declined'): ?>
                                                                <span class="badge badge-danger">Declined</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-secondary">Cancelled</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($appointment['status'] == 'pending'): ?>
                                                                <a href="appointment_management.php?id=<?php echo $appointment['id']; ?>&action=accept" class="btn btn-sm btn-success">
                                                                    <i class="fas fa-check"></i> Accept
                                                                </a>
                                                                <a href="appointment_management.php?id=<?php echo $appointment['id']; ?>&action=decline" class="btn btn-sm btn-danger">
                                                                    <i class="fas fa-times"></i> Decline
                                                                </a>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#notesModal<?php echo $appointment['id']; ?>">
                                                                <i class="fas fa-sticky-note"></i> Notes
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-secondary view-student-details" 
                                                                    data-name="<?php echo htmlspecialchars($appointment['student_name']); ?>"
                                                                    data-email="<?php echo htmlspecialchars($appointment['student_email']); ?>"
                                                                    data-department="<?php echo htmlspecialchars($appointment['department']); ?>"
                                                                    data-year="<?php echo htmlspecialchars($appointment['course_year']); ?>"
                                                                    data-section="<?php echo htmlspecialchars($appointment['section']); ?>">
                                                                <i class="fas fa-eye"></i> View Details
                                                            </button>
                                                            
                                                            <!-- Notes Modal -->
                                                            <div class="modal fade" id="notesModal<?php echo $appointment['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="notesModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog" role="document">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title" id="notesModalLabel">Appointment Notes</h5>
                                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                                <span aria-hidden="true">&times;</span>
                                                                            </button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <?php if (!empty($appointment['notes'])): ?>
                                                                                <p><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                                                                            <?php else: ?>
                                                                                <p class="text-muted">No notes available for this appointment.</p>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="p-4 text-center">
                                        <p class="text-muted">No appointments found with the current filters.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Student Details Modal -->
                <div class="modal fade" id="studentDetailsModal" tabindex="-1" role="dialog" aria-labelledby="studentDetailsModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="studentDetailsModalLabel">Student Details</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%">Full Name</th>
                                        <td id="modal-student-name"></td>
                                    </tr>
                                    <tr>
                                        <th>Email</th>
                                        <td id="modal-student-email"></td>
                                    </tr>
                                    <tr>
                                        <th>Department</th>
                                        <td id="modal-student-department"></td>
                                    </tr>
                                    <tr>
                                        <th>Course Year</th>
                                        <td id="modal-student-year"></td>
                                    </tr>
                                    <tr>
                                        <th>Section</th>
                                        <td id="modal-student-section"></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
    <script>
    $(document).ready(function() {
        $('.view-student-details').click(function() {
            const name = $(this).data('name');
            const email = $(this).data('email');
            const department = $(this).data('department') || 'Not specified';
            const year = $(this).data('year') || 'Not specified';
            const section = $(this).data('section') || 'Not specified';
            
            $('#modal-student-name').text(name);
            $('#modal-student-email').text(email);
            $('#modal-student-department').text(department);
            $('#modal-student-year').text(year);
            $('#modal-student-section').text(section);
            
            $('#studentDetailsModal').modal('show');
        });
    });
    </script>
</body>
</html>

<?php
require_once '../config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect("../login.php");
}

if (!isAdmin()) {
    redirect("../index.php");
}

$user = getUserDetails($_SESSION["user_id"]);

// Define current page for dynamic sidebar highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to generate time slots
function getTimeSlots($start, $end, $interval = 30) {
    $slots = [];
    $current = strtotime($start);
    $end = strtotime($end);
    while ($current <= $end) {
        $slots[] = date('H:i', $current);
        $current = strtotime("+{$interval} minutes", $current);
    }
    return $slots;
}

// =========================================================================
// 1. Handle Appointment Actions (Accept / Decline / Success / Cancel)
//    - 'accept' on a 'reschedule' status confirms the new time.
//    - 'decline' on a 'reschedule' status closes the request and sets status to 'declined'.
// =========================================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $appointment_id = $_GET['id'];
    $action = $_GET['action'];

    $new_status = '';
    if ($action === 'accept') {
        $new_status = 'accepted';
    } elseif ($action === 'decline') {
        $new_status = 'declined'; 
    } elseif ($action === 'success') {
        $new_status = 'successful';
    } elseif ($action === 'cancel') {
        $new_status = 'cancelled';
    }
    
    if ($new_status) {
        $update_query = "UPDATE appointments SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $new_status, $appointment_id);
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage("Appointment updated to '{$new_status}' successfully.", "success");
        } else {
            setFlashMessage("Error updating appointment.", "danger");
        }
        redirect("appointment_management.php");
    }
}

// =========================================================================
// 2. AJAX Note Update Handler 
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notes'])) {
    // This is an AJAX request, so we echo JSON response instead of redirecting
    header('Content-Type: application/json');
    
    $appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
    $new_notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';

    if ($appointment_id > 0) {
        $update_query = "UPDATE appointments SET notes = ? WHERE id = ? AND counselor_id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        // Ensure the counselor can only update their own appointment notes
        mysqli_stmt_bind_param($stmt, "sii", $new_notes, $appointment_id, $_SESSION['user_id']); 
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Notes updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Appointment ID.']);
    }
    // IMPORTANT: Exit after AJAX response to prevent rendering the full HTML page
    exit; 
}
// =========================================================================

// Handle new appointment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_appointment'])) {
    $student_id = $_POST['student_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $notes = sanitize($_POST['notes']);

    $errors = [];

    if (empty($student_id)) $errors[] = "Please select a student.";
    if (empty($appointment_date)) $errors[] = "Please select a date.";
    if (empty($appointment_time)) $errors[] = "Please select a time.";

    if (!empty($appointment_date)) {
        $selected_date = DateTime::createFromFormat('m/d/Y', $appointment_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0); 
        
        if (!$selected_date) {
            $errors[] = "Invalid date format.";
        } elseif ($selected_date < $today) {
            $errors[] = "Please select a future date or today's date.";
        }
    }

    if (empty($errors)) {
        $db_date = $selected_date->format('Y-m-d');

        $appt_time = DateTime::createFromFormat('H:i', $appointment_time);
        if ($appt_time) {
            $start_window = clone $appt_time;
            $end_window = clone $appt_time;
            $start_window->modify('-29 minutes');
            $end_window->modify('+29 minutes');

            $start_time = $start_window->format('H:i:s');
            $end_time = $end_window->format('H:i:s');

            $check_query = "
                SELECT id FROM appointments 
                WHERE counselor_id = ? 
                  AND appointment_date = ? 
                  AND appointment_time BETWEEN ? AND ? 
                  AND status IN ('accepted','pending')
            ";
            $stmt_check = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($stmt_check, "isss", $_SESSION['user_id'], $db_date, $start_time, $end_time);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);

            if (mysqli_num_rows($result_check) > 0) {
                $errors[] = "This time slot is already booked or too close to another appointment (30-min gap required).";
            }
        }
    }

    if (empty($errors)) {
        $insert_query = "
            INSERT INTO appointments (student_id, counselor_id, appointment_date, appointment_time, notes, status)
            VALUES (?, ?, ?, ?, ?, 'accepted')
        ";
        $stmt = mysqli_prepare($conn, $insert_query);
        $db_date = $selected_date->format('Y-m-d');
        mysqli_stmt_bind_param($stmt, "iisss", $student_id, $_SESSION['user_id'], $db_date, $appointment_time, $notes);

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
$date_filter_raw = isset($_GET['date']) ? sanitize($_GET['date']) : '';

// --- MODIFIED LOGIC START ---
// Determine the date filter (No default date filter, shows all dates)
$db_date_filter = !empty($date_filter_raw) ? $date_filter_raw : '';

// Get all appointments for this counselor with student details
$query = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes, 
          u.full_name as student_name, u.email as student_email, u.department, u.course_year, u.section, u.student_number, u.id as student_id
          FROM appointments a 
          JOIN users u ON a.student_id = u.id 
          WHERE a.counselor_id = ?";

$params = [$_SESSION['user_id']];
$types = "i";

// 1. Filter by status
if (!empty($status_filter)) {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// 2. Filter by date (if provided)
if (!empty($db_date_filter)) {
    $query .= " AND a.appointment_date = ?";
    $params[] = $db_date_filter;
    $types .= "s";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$appointments = mysqli_stmt_get_result($stmt);
// --- MODIFIED LOGIC END ---

// Get students for dropdown
$students_query = "SELECT id, full_name, email FROM users WHERE role = 'student' ORDER BY full_name";
$students = mysqli_query($conn, $students_query);
$allSlots = getTimeSlots("08:00", "17:00", 30);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointment Management - Wellness Hub</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
/* === General === */
body{background-color:#f8f9fa;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;overflow-x:hidden}
.sidebar{background-color:#2b2b2b;min-height:calc(100vh - 56px);color:#fff}
.sidebar-link{color:rgba(255,255,255,.8);padding:10px 15px;display:block;text-decoration:none;transition:all .25s ease-in-out;border-radius:5px;margin-bottom:5px}
.sidebar-link:hover{color:#fff;background-color:rgba(179,1,4,.7);text-decoration:none;transform:translateX(3px)}
.sidebar-link.active{color:#fff;background-color:#b30104}
.navbar-dark.bg-dark{background-color:#1e1e1e!important}
.content{padding:20px;animation:fadeIn .5s ease-in-out}
.card{border:none;border-radius:10px;margin-bottom:20px;box-shadow:0 4px 10px rgba(0,0,0,.08);transition:all .3s ease-in-out;animation:fadeInUp .5s ease-in-out}
.card:hover{transform:translateY(-4px);box-shadow:0 6px 14px rgba(0,0,0,.12)}
.card-header.bg-primary{background-color:#b30104!important;color:#fff;font-weight:bold}
.card-header.bg-success{background-color:#008f39!important}
.card-header.bg-info{background-color:#b30104!important} /* Changed to match primary color */
.card-header.bg-warning{background-color:#d4a017!important}
.btn{transition:all .25s ease-in-out}
.btn-primary{background-color:#b30104;border:none}
.btn-primary:hover{background-color:#8a0103;transform:translateY(-2px)}
.btn-success{background-color:#008f39;border:none}
.btn-success:hover{background-color:#007531;transform:translateY(-2px)}
.btn-danger{background-color:#dc3545;border:none}
.btn-danger:hover{background-color:#b02a37;transform:translateY(-2px)}
.btn-warning{background-color:#d4a017;border:none}
.btn-warning:hover{background-color:#b88c0f;transform:translateY(-2px)}
.alert{border-left:5px solid transparent;animation:fadeIn .4s ease-in-out}
.alert-success{background-color:#e6f4ea;border-left-color:#28a745;color:#155724}
.alert-danger{background-color:#fdeaea;border-left-color:#b30104;color:#721c24}
.table-hover tbody tr:hover{background-color:#f9f9f9;transform:scale(1.01);transition:all .2s ease-in-out}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
option[disabled] {
    background-color: #f8f9fa;
    color: #ccc;
    cursor: not-allowed;
}

/* --- NEW STYLE FOR STATUS BADGES --- */
.badge-successful {
    background-color: #007bff;
    color: #fff;
}
.badge-cancelled {
    background-color: #6c757d;
    color: #fff;
}
.badge-pending {
    background-color: #ffc107;
    color: #212529;
}
.badge-accepted {
    background-color: #28a745;
    color: #fff;
}
/* ADDED STYLE FOR RESCHEDULE REQUEST */
.badge-reschedule {
    background-color: #17a2b8; /* Bootstrap 'info' color */
    color: #fff;
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
        <div class="col-md-3 col-lg-2 sidebar py-3">
            <div class="user-info mb-3 text-center">
                <div class="h5"><?php echo htmlspecialchars($user["full_name"]); ?></div>
                <div class="small"><?php echo htmlspecialchars($user["email"]); ?></div>
                <div class="badge badge-danger">Counselor</div>
            </div>
            <hr>
            <a href="dashboard.php" class="sidebar-link<?php echo ($current_page == 'dashboard.php' ? ' active' : ''); ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="user_management.php" class="sidebar-link<?php echo ($current_page == 'user_management.php' ? ' active' : ''); ?>"><i class="fas fa-users"></i> User Management</a>
            <a href="assessment_management.php" class="sidebar-link<?php echo ($current_page == 'assessment_management.php' ? ' active' : ''); ?>"><i class="fas fa-clipboard-list"></i> Assessment Tools</a>
            <a href="appointment_management.php" class="sidebar-link<?php echo ($current_page == 'appointment_management.php' ? ' active' : ''); ?>"><i class="fas fa-calendar-check"></i> Appointments</a>
            <a href="view_assessments.php" class="sidebar-link<?php echo ($current_page == 'view_assessments.php' ? ' active' : ''); ?>"><i class="fas fa-chart-bar"></i> View Assessments</a>
            <a href="counselor_schedule.php" class="sidebar-link<?php echo ($current_page == 'counselor_schedule.php' ? ' active' : ''); ?>"><i class="fas fa-calendar-alt"></i> My Schedule & Available Hours</a>
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
                                        <?php 
                                        // Reset pointer if necessary and fetch students
                                        if (mysqli_num_rows($students) > 0) {
                                            mysqli_data_seek($students, 0); 
                                        }
                                        while ($student = mysqli_fetch_assoc($students)): 
                                        ?>
                                            <option value="<?php echo $student['id']; ?>">
                                                <?php echo htmlspecialchars($student['full_name']) . ' (' . htmlspecialchars($student['email']) . ')'; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="appointment_date">Date</label>
                                    <input type="text" class="form-control" id="appointment_date" name="appointment_date" required placeholder="MM/DD/YYYY">
                                </div>
                                <div class="form-group">
                                    <label for="appointment_time">Time</label>
                                    <select class="form-control" id="appointment_time" name="appointment_time" required>
                                        <option value="">-- Select Time --</option>
                                    </select>
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
                                                <option value="">-- Show All Statuses --</option>
                                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="accepted" <?php echo $status_filter == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                                <option value="reschedule" <?php echo $status_filter == 'reschedule' ? 'selected' : ''; ?>>Reschedule Requested</option>
                                                <option value="declined" <?php echo $status_filter == 'declined' ? 'selected' : ''; ?>>Declined</option>
                                                <option value="successful" <?php echo $status_filter == 'successful' ? 'selected' : ''; ?>>Successful</option> 
                                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label>Date (Leave blank for all dates)</label>
                                            <input type="date" class="form-control" name="date" 
                                                   value="<?php echo !empty($date_filter_raw) ? htmlspecialchars($date_filter_raw) : ''; ?>">
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
                                                        <strong class="text-dark"><?php echo formatDate($appointment['appointment_date']); ?></strong><br>
                                                        <small class="text-muted"><?php echo formatTime($appointment['appointment_time']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($appointment['status'] == 'pending'): ?>
                                                            <span class="badge badge-warning">Pending</span>
                                                        <?php elseif ($appointment['status'] == 'accepted'): ?>
                                                            <span class="badge badge-success">Accepted</span>
                                                        <?php elseif ($appointment['status'] == 'reschedule'): ?>
                                                            <span class="badge badge-reschedule">Reschedule Requested</span>
                                                        <?php elseif ($appointment['status'] == 'declined'): ?>
                                                            <span class="badge badge-danger">Declined</span>
                                                        <?php elseif ($appointment['status'] == 'successful'): ?> 
                                                            <span class="badge badge-successful">Successful</span> 
                                                        <?php elseif ($appointment['status'] == 'cancelled'): ?> 
                                                            <span class="badge badge-secondary badge-cancelled">Cancelled</span> 
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($appointment['status'] == 'pending'): ?>
                                                            <a href="appointment_management.php?id=<?php echo $appointment['id']; ?>&action=accept" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check"></i> Accept
                                                            </a>
                                                            <a href="appointment_management.php?id=<?php echo $appointment['id']; ?>&action=decline" 
                                                                class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('Are you sure you want to decline this appointment request?')"> <i class="fas fa-times"></i> Decline
                                                            </a>
                                                        <?php elseif ($appointment['status'] == 'reschedule'): ?>
                                                            <a href="appointment_management.php?id=<?php echo $appointment['id']; ?>&action=accept" 
                                                                class="btn btn-sm btn-success"
                                                                onclick="return confirm('Accept this new reschedule time? This will confirm the appointment at the new date/time.')">
                                                                <i class="fas fa-check"></i> Accept Reschedule
                                                            </a>
                                                            <a href="appointment_management.php?id=<?php echo $appointment['id']; ?>&action=decline" 
                                                                class="btn btn-sm btn-danger"
                                                                onclick="return confirm('Decline this reschedule request? The appointment status will change to Declined.')">
                                                                <i class="fas fa-times"></i> Decline Reschedule
                                                            </a>
                                                        <?php elseif ($appointment['status'] == 'accepted'): ?> 
                                                            <a href="appointment_management.php?id=<?php echo $appointment['id']; ?>&action=success" 
                                                                class="btn btn-sm btn-primary" 
                                                                onclick="return confirm('Mark this appointment as Successful?')"> 
                                                                <i class="fas fa-calendar-check"></i> Successful
                                                            </a>
                                                            <a href="appointment_management.php?id=<?php echo $appointment['id']; ?>&action=cancel" 
                                                                class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('Are you sure you want to cancel this appointment?')"> <i class="fas fa-ban"></i> Cancel
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <button type="button" class="btn btn-sm btn-secondary view-student-details" 
                                                                data-id="<?php echo $appointment['id']; ?>" 
                                                                data-student-id="<?php echo $appointment['student_id']; ?>" data-name="<?php echo htmlspecialchars($appointment['student_name']); ?>"
                                                                data-email="<?php echo htmlspecialchars($appointment['student_email']); ?>"
                                                                data-department="<?php echo htmlspecialchars($appointment['department']); ?>"
                                                                data-year="<?php echo htmlspecialchars($appointment['course_year']); ?>"
                                                                data-section="<?php echo htmlspecialchars($appointment['section']); ?>"
                                                                data-student-number="<?php echo htmlspecialchars($appointment['student_number']); ?>"
                                                                data-notes="<?php echo htmlspecialchars($appointment['notes']); ?>"
                                                                data-date="<?php echo formatDate($appointment['appointment_date']); ?>"
                                                                data-time="<?php echo formatTime($appointment['appointment_time']); ?>"
                                                                data-raw-status="<?php echo htmlspecialchars($appointment['status']); ?>" 
                                                                data-status-display="<?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>">
                                                            <i class="fas fa-eye"></i> View Details
                                                        </button>

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

            <div class="modal fade" id="studentDetailsModal" tabindex="-1" role="dialog" aria-labelledby="studentDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="studentDetailsModalLabel"><i class="fas fa-user-tag"></i> Student & Appointment Details</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="modal-appointment-id" value=""> 

                            <h6>Appointment Information</h6>
                            <table class="table table-bordered table-sm mb-4">
                                <tr>
                                    <th style="width: 30%">Counselor</th>
                                    <td id="modal-counselor-name"><?php echo htmlspecialchars($user["full_name"]); ?></td>
                                </tr>
                                <tr>
                                    <th>Date & Time</th>
                                    <td><strong id="modal-appt-datetime"></strong></td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td><span class="badge" id="modal-appt-status"></span></td>
                                </tr>
                            </table>

                            <h6>Student Information</h6>
                            <table class="table table-bordered table-sm mb-4">
                                <tr>
                                    <th style="width: 30%">Full Name</th>
                                    <td id="modal-student-name"></td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td id="modal-student-email"></td>
                                </tr>
                                <tr>
                                    <th>Student Number</th>
                                    <td id="modal-student-number"></td>
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

                            <h6>Appointment Notes (Counselor's Private Notes)</h6>
                            <textarea class="form-control mb-3" id="modal-notes-textarea" rows="4" placeholder="Enter notes related to this counseling session..."></textarea>
                            <div id="notes-save-status" class="mb-3"></div>
                            
                            <a href="#" id="view-profile-btn" class="btn btn-info btn-sm mb-3">
                                <i class="fas fa-user-circle"></i> View Student Profile
                            </a>
                            <button type="button" class="btn btn-success btn-sm" id="save-notes-btn">
                                <i class="fas fa-save"></i> Save Notes
                            </button>

                        </div>
                        <div class="modal-footer">
                            <a id="download-pdf-btn" href="#" target="_blank" class="btn btn-danger mr-auto"><i class="fas fa-file-pdf"></i> Download PDF</a>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
// =========================================================================
// MODIFIED: View Details click handler
// =========================================================================
$(document).on('click', '.view-student-details', function() {
    const $this = $(this);
    const appointment_id = $this.data('id');
    const notes = $this.data('notes');
    const rawStatus = $this.data('raw-status'); // Get raw status: 'reschedule', 'accepted', etc.
    
    // Reset save status message
    $('#notes-save-status').empty();

    // 1. Set Appointment ID
    $('#modal-appointment-id').val(appointment_id);

    // 2. Appointment Details 
    const date = $this.data('date');
    const time = $this.data('time');

    $('#modal-appt-datetime').text(`${date} at ${time}`);
    
    // Status Logic Mapping
    let statusDisplayText;
    let badgeClass;
    
    switch (rawStatus) {
        case 'pending':
            statusDisplayText = 'Pending';
            badgeClass = 'badge-warning';
            break;
        case 'accepted':
            statusDisplayText = 'Accepted';
            badgeClass = 'badge-success';
            break;
        case 'reschedule':
            statusDisplayText = 'Reschedule Requested';
            badgeClass = 'badge-reschedule'; // Uses custom CSS
            break;
        case 'declined':
            statusDisplayText = 'Declined';
            badgeClass = 'badge-danger';
            break;
        case 'successful':
            statusDisplayText = 'Successful';
            badgeClass = 'badge-successful'; // Uses custom CSS
            break;
        case 'cancelled':
            statusDisplayText = 'Cancelled';
            badgeClass = 'badge-secondary badge-cancelled'; // Uses custom CSS
            break;
        default:
            statusDisplayText = 'Unknown';
            badgeClass = 'badge-light';
            break;
    }

    // Set status badge text and class
    const $statusBadge = $('#modal-appt-status');
    $statusBadge.removeClass().addClass('badge').addClass(badgeClass);
    $statusBadge.text(statusDisplayText);


    // 3. Student Details 
    $('#modal-student-name').text($this.data('name'));
    $('#modal-student-email').text($this.data('email'));
    $('#modal-student-department').text($this.data('department'));
    $('#modal-student-year').text($this.data('year'));
    $('#modal-student-section').text($this.data('section'));
    $('#modal-student-number').text($this.data('student-number'));
    
    // NEW: Set the View Profile Link
    // Assumes a student_profile.php file exists
    const student_id = $this.data('student-id');
    const profileUrl = `student_profile.php?id=${student_id}`;
    $('#view-profile-btn').attr('href', profileUrl);


    // 4. Appointment Notes
    $('#modal-notes-textarea').val(notes);

    // 5. Set Download Links (NEW IMPLEMENTATION)
    // This correctly links to the external report generation script.
    const pdfUrl = `generate_report.php?id=${appointment_id}&format=pdf`;
    $('#download-pdf-btn').attr('href', pdfUrl);
    
    $('#studentDetailsModal').modal('show');
});

// =========================================================================
// AJAX Note Saving Functionality
// =========================================================================
$('#save-notes-btn').on('click', function() {
    const btn = $(this);
    const originalText = btn.html();
    
    const appointment_id = $('#modal-appointment-id').val();
    const new_notes = $('#modal-notes-textarea').val();
    const statusContainer = $('#notes-save-status');

    // Basic validation
    if (!appointment_id) {
        statusContainer.html('<div class="alert alert-danger">Error: Missing appointment ID.</div>');
        return;
    }

    // Indicate saving process
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
    statusContainer.empty();

    $.ajax({
        url: "appointment_management.php", // POST back to the same page
        type: "POST",
        data: {
            update_notes: 1, // Flag for the PHP handler
            appointment_id: appointment_id,
            notes: new_notes
        },
        dataType: "json",
        success: function(response) {
            if (response.success) {
                // Update the button's data-notes attribute in the main table row
                // This ensures the modal opens with the newly saved note next time.
                $(`.view-student-details[data-id="${appointment_id}"]`).data('notes', new_notes);
                statusContainer.html('<div class="alert alert-success p-2">Notes saved successfully!</div>');
            } else {
                statusContainer.html('<div class="alert alert-danger p-2">' + response.message + '</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error("Error saving notes:", status, error);
            statusContainer.html('<div class="alert alert-danger p-2">An error occurred during saving.</div>');
        },
        complete: function() {
            // Restore button state after request completes
            btn.prop('disabled', false).html(originalText);
            
            // Auto-dismiss the status message after a few seconds
            setTimeout(function() {
                statusContainer.fadeOut(function() {
                    $(this).empty().show();
                });
            }, 3000);
        }
    });
});
// =========================================================================

// =========================================================================
// Time Slot Availability 
// =========================================================================
$(document).ready(function() {
    const allSlots = <?php echo json_encode($allSlots); ?>;

    flatpickr("#appointment_date", {
        dateFormat: "m/d/Y",
        minDate: "today",
        onChange: function(selectedDates, dateStr) {
            const $timeSelect = $("#appointment_time");
            $timeSelect.empty();
            $timeSelect.append('<option value="">-- Select Time --</option>');

            if (!dateStr) return;
            
            $.ajax({
                url: "get_unavailable_times.php",
                type: "GET",
                data: { date: dateStr },
                dataType: "json",
                success: function(unavailable) {
                    allSlots.forEach(slot => {
                        let display = new Date('1970-01-01T' + slot + ':00')
                                             .toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                        
                        if (unavailable.includes(slot)) {
                            $timeSelect.append('<option value="' + slot + '" disabled style="color:#ccc;">' + display + ' (Unavailable)</option>');
                        } else {
                            $timeSelect.append('<option value="' + slot + '">' + display + '</option>');
                        }
                    });
                }, 
                error: function(xhr, status, error) {
                    console.error("Error fetching unavailable times:", status, error);
                    allSlots.forEach(slot => {
                        let display = new Date('1970-01-01T' + slot + ':00')
                                             .toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                        $timeSelect.append('<option value="' + slot + '">' + display + '</option>');
                    });
                }
            });
        }
    })
});
</script>
</body>
</html>
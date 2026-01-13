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

// Handle appointment actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $appointment_id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'cancel') {
        $update_query = "UPDATE appointments SET status = 'cancelled' WHERE id = ? AND student_id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ii", $appointment_id, $_SESSION['user_id']);
        
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage("Appointment cancelled successfully.", "success");
        } else {
            setFlashMessage("Error cancelling appointment.", "danger");
        }
    }
    
    redirect("manage_appointment.php");
}

// Handle new appointment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_appointment'])) {
    $counselor_id = $_POST['counselor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $notes = sanitize($_POST['notes']);
    
    // Convert MM/DD/YYYY to YYYY-MM-DD
    $date_parts = explode('/', $appointment_date);
    if (count($date_parts) === 3) {
        $appointment_date = $date_parts[2] . '-' . $date_parts[0] . '-' . $date_parts[1];
    }

    // Validate input
    $errors = [];
    
    if (empty($counselor_id)) {
        $errors[] = "Please select a counselor.";
    }
    
    if (empty($appointment_date)) {
        $errors[] = "Please select a date.";
    } else {
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
        $insert_query = "INSERT INTO appointments (student_id, counselor_id, appointment_date, appointment_time, notes) 
                       VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "iisss", $_SESSION['user_id'], $counselor_id, $appointment_date, $appointment_time, $notes);
        
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage("Appointment scheduled successfully. Awaiting counselor confirmation.", "success");
            redirect("manage_appointment.php");
        } else {
            setFlashMessage("Error scheduling appointment.", "danger");
        }
    } else {
        $error_msg = implode("<br>", $errors);
        setFlashMessage($error_msg, "danger");
    }
}

// Get counselors for dropdown
$counselors_query = "SELECT id, full_name FROM users WHERE role = 'admin'";
$counselors = mysqli_query($conn, $counselors_query);

// Get all appointments for this student
$appointments_query = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes, 
                     u.full_name as counselor_name
                     FROM appointments a 
                     JOIN users u ON a.counselor_id = u.id 
                     WHERE a.student_id = ? 
                     ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$stmt = mysqli_prepare($conn, $appointments_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$appointments = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

    <style>
    body {
        background-color: #f8f9fa;
    }
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
        border-radius: 5px;
        margin-bottom: 5px;
        transition: 0.3s;
    }
    .sidebar-link:hover { background-color: rgba(179, 1, 4, 0.6); color: white; }
    .sidebar-link.active { background-color: #b30104; color: white; }
    .navbar-dark.bg-dark { background-color: #1e1e1e !important; }
    .content { padding: 20px; animation: fadeIn 0.4s ease-out; }
    .card { margin-bottom: 20px; border: none; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); animation: fadeUp 0.5s ease-out; }
    .card-header { background-color: #b30104 !important; color: white; font-weight: bold; }
    .btn-primary { background-color: #b30104; border: none; }
    .btn-primary:hover { background-color: #8a0103; }
    .btn-danger { background-color: #b30104; border: none; }
    .btn-danger:hover { background-color: #8a0103; }
    @keyframes fadeUp { 0% { opacity: 0; transform: translateY(20px);} 100% { opacity: 1; transform: translateY(0);} }
    @keyframes fadeIn { 0% { opacity: 0;} 100% { opacity: 1;} }
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
                
                <h2><i class="fas fa-calendar-check"></i> Manage Appointments</h2>
                <p class="lead">Schedule and manage your counseling appointments.</p>
                
                <div class="row">
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-calendar-plus"></i> Schedule New Appointment</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="form-group">
                                        <label for="counselor_id">Select Counselor</label>
                                        <select class="form-control" id="counselor_id" name="counselor_id" required>
                                            <option value="">-- Select Counselor --</option>
                                            <?php while ($counselor = mysqli_fetch_assoc($counselors)): ?>
                                                <option value="<?php echo $counselor['id']; ?>"><?php echo htmlspecialchars($counselor['full_name']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="appointment_date">Date</label>
                                        <input type="text" class="form-control" id="appointment_date" name="appointment_date" required placeholder="MM/DD/YYYY">
                                    </div>
                                    <div class="form-group">
                                        <label for="appointment_time">Time</label>
                                        <input type="time" class="form-control" id="appointment_time" name="appointment_time" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="notes">Notes (Optional)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Describe the reason for your appointment..."></textarea>
                                    </div>
                                    <button type="submit" name="schedule_appointment" class="btn btn-primary btn-block">Schedule Appointment</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Your Appointments</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (mysqli_num_rows($appointments) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Counselor</th>
                                                    <th>Date & Time</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($appointment = mysqli_fetch_assoc($appointments)): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($appointment['counselor_name']); ?></td>
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
                                                        <td>
                                                            <a href="view_appointment_details.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-info">
                                                                <i class="fas fa-eye"></i> View Details
                                                            </a>
                                                            <?php if ($appointment['status'] == 'pending' || $appointment['status'] == 'accepted'): ?>
                                                                <a href="manage_appointment.php?id=<?php echo $appointment['id']; ?>&action=cancel" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                                    <i class="fas fa-times"></i> Cancel
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="p-4 text-center">
                                        <p class="text-muted">You haven't scheduled any appointments yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    <script>
    $(function() {
        // jQuery UI Datepicker with MM/DD/YYYY format
        $("#appointment_date").datepicker({
            dateFormat: "mm/dd/yy",
            minDate: 0,         // today or future
            maxDate: "+30D",    // optional limit to 30 days
            showAnim: "fadeIn"
        });

        // Convert date format before submit (for PHP/MySQL)
        $("form").on("submit", function() {
            let dateInput = $("#appointment_date");
            let parts = dateInput.val().split("/");
            if (parts.length === 3) {
                let formatted = `${parts[2]}-${parts[0].padStart(2,'0')}-${parts[1].padStart(2,'0')}`;
                dateInput.val(formatted);
            }
        });
    });
    </script>
</body>
</html>

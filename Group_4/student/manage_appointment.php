<?php
require_once '../config.php';
require_once '../includes/functions.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isLoggedIn()) redirect("../login.php");
if (!isStudent()) redirect("../index.php");

$user = getUserDetails($_SESSION["user_id"]);

// Helper function to generate time slots (re-introduced for the reschedule modal)
if (!function_exists('getTimeSlots')) {
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
}
$all_slots = getTimeSlots("08:00", "17:00", 30); // 8:00 AM to 5:00 PM

// Fetch counselor data (re-introduced for the reschedule modal)
$counselors_query = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role = 'admin'");
$counselors_map = [];
while ($c = mysqli_fetch_assoc($counselors_query)) {
    $counselors_map[$c['id']] = $c['full_name'];
}


// --- RESCHEDULE APPOINTMENT LOGIC (POST Handler) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_appointment'])) {
    $appointment_id = (int) $_POST['appointment_id'];
    $new_date = $_POST['new_date'];
    $new_time = $_POST['new_time'];
    $reschedule_notes = sanitize($_POST['reschedule_notes']);
    $errors = [];

    // 1. Fetch original counselor ID for the appointment
    $stmt_counselor = mysqli_prepare($conn, "SELECT counselor_id FROM appointments WHERE id = ? AND student_id = ? AND status IN ('pending', 'accepted')");
    mysqli_stmt_bind_param($stmt_counselor, "ii", $appointment_id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt_counselor);
    $result_counselor = mysqli_stmt_get_result($stmt_counselor);
    $appt_data = mysqli_fetch_assoc($result_counselor);
    
    if (!$appt_data) {
        setFlashMessage("Error: Appointment not found or cannot be rescheduled.", "danger");
        redirect("manage_appointment.php");
    }
    $counselor_id = $appt_data['counselor_id'];

    // Convert date format from MM/DD/YYYY to YYYY-MM-DD for database and validation
    $date_parts = explode('/', $new_date);
    if (count($date_parts) === 3)
        $db_new_date = "{$date_parts[2]}-{$date_parts[0]}-{$date_parts[1]}";
    else
        $db_new_date = $new_date;

    if (empty($new_date)) $errors[] = "Please select a new date.";
    if (empty($new_time)) $errors[] = "Please select a new time.";
    if (empty($reschedule_notes)) $errors[] = "Please provide a reason for the reschedule.";

    // Check for future date only
    if (!empty($db_new_date)) {
        $selected_date = DateTime::createFromFormat('Y-m-d', $db_new_date);
        $today = new DateTime('today');
        if (!$selected_date) $errors[] = "Invalid date format.";
        elseif ($selected_date < $today) $errors[] = "Please select a future date.";
    }

    // 30-min gap check (Exclude the current appointment ID from the check)
    if (empty($errors)) {
        $appt_time = DateTime::createFromFormat('H:i', $new_time);
        if ($appt_time) {
            $start_window = (clone $appt_time)->modify('-30 minutes')->format('H:i:s');
            $end_window = (clone $appt_time)->modify('+30 minutes')->format('H:i:s');
            $stmt_check = mysqli_prepare($conn, "
                SELECT id FROM appointments
                WHERE counselor_id = ? AND appointment_date = ? 
                AND appointment_time BETWEEN ? AND ? 
                AND status IN ('pending', 'accepted')
                AND id != ?
            ");
            mysqli_stmt_bind_param($stmt_check, "isssi", $counselor_id, $db_new_date, $start_window, $end_window, $appointment_id);
            mysqli_stmt_execute($stmt_check);
            $result = mysqli_stmt_get_result($stmt_check);
            if (mysqli_num_rows($result) > 0)
                $errors[] = "The counselor already has another confirmed appointment within 30 minutes of the requested new time.";
        }
    }

    // Update the appointment
    if (empty($errors)) {
        // --- REVISED LOGIC FOR ROBUST NOTES LOGGING ---
        $new_datetime_log = $db_new_date . ' ' . $new_time . ':00'; // New requested time in Y-m-d H:i:s format
        $reschedule_timestamp_aud = date('Y-m-d H:i:s'); // Audit timestamp

        // Create a clear log entry to be appended to the notes field
        $reschedule_log_content = "\n\n--- RESCHEDULE REQUEST ---\n" .
             "Reason: " . $reschedule_notes . "\n" .
             "NEW TIME REQUESTED: " . $new_datetime_log . "\n" .
             "(Request submitted by student: " . $reschedule_timestamp_aud . ")";
        // --- END REVISED LOGIC ---
        
        // Use CONCAT(COALESCE(notes, ''), ?) to safely append to existing notes, even if NULL
        $sql_update = "
            UPDATE appointments
            SET appointment_date = ?, 
                appointment_time = ?, 
                notes = CONCAT(COALESCE(notes, ''), ?), 
                status = 'reschedule' 
            WHERE id = ? AND student_id = ? AND status IN ('pending', 'accepted')
        ";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "sssii", $db_new_date, $new_time, $reschedule_log_content, $appointment_id, $_SESSION['user_id']);
        
        if (mysqli_stmt_execute($stmt_update) && mysqli_stmt_affected_rows($stmt_update) > 0) {
            setFlashMessage("Reschedule request for appointment ID **{$appointment_id}** submitted successfully. Awaiting counselor confirmation for the new time.", "success");
        } else {
            setFlashMessage("Error submitting reschedule request or appointment is not eligible for rescheduling.", "danger");
        }
    } else {
        $error_msg = "Reschedule Failed:<br>" . implode("<br>", $errors);
        setFlashMessage($error_msg, "danger");
    }
    redirect("manage_appointment.php");
}


// --- CANCEL APPOINTMENT LOGIC (GET Handler) ---
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'cancel') {
    $appointment_id = (int) $_GET['id'];
    // We check for 'accepted', 'pending', or 'reschedule' status to ensure only active appointments can be cancelled.
    $stmt = mysqli_prepare($conn, "
        UPDATE appointments 
        SET status = 'cancelled' 
        WHERE id = ? AND student_id = ? AND status IN ('pending', 'accepted', 'reschedule')
    ");
    mysqli_stmt_bind_param($stmt, "ii", $appointment_id, $_SESSION['user_id']);
    
    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        setFlashMessage("Appointment cancelled successfully.", "success");
    } else {
        setFlashMessage("Error cancelling appointment or appointment already finalized/cancelled.", "danger");
    }
    redirect("manage_appointment.php");
}

// Fetch all appointments for the student
$stmt = mysqli_prepare($conn, "
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes, a.counselor_id, u.full_name AS counselor_name
    FROM appointments a
    JOIN users u ON a.counselor_id = u.id
    WHERE a.student_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
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
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css"> <style>
body { background-color: #f8f9fa; }
.sidebar { background-color: #2b2b2b; min-height: calc(100vh - 56px); color: white; }
.sidebar-link { color: rgba(255,255,255,0.8); padding: 10px 15px; display: block; text-decoration: none; border-radius: 5px; margin-bottom: 5px; transition: 0.3s; }
.sidebar-link:hover { background-color: rgba(179,1,4,0.6); color: white; }
.sidebar-link.active { background-color: #b30104; color: white; }
.navbar-dark.bg-dark { background-color: #1e1e1e !important; }
.content { padding: 20px; animation: fadeIn 0.4s ease-out; }
.card { margin-bottom: 20px; border: none; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); animation: fadeUp 0.5s ease-out; }
.card-header { background-color: #b30104 !important; color: white; font-weight: bold; }
.btn-primary, .btn-danger { background-color: #b30104; border: none; }
.btn-primary:hover, .btn-danger:hover { background-color: #8a0103; }
.btn-warning { background-color: #ffc107; border: none; color: #212529; }
.btn-warning:hover { background-color: #e0a800; }
#availability-message { font-size: 0.9rem; margin-top: 8px; }
@keyframes fadeUp {0%{opacity:0;transform:translateY(20px);}100%{opacity:1;transform:translateY(0);}}
@keyframes fadeIn {0%{opacity:0;}100%{opacity:1;}}
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
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" data-toggle="dropdown">
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
        <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="take_assessment.php" class="sidebar-link"><i class="fas fa-tasks"></i> Take Assessment</a>

        <a href="manage_appointment.php" class="sidebar-link active"><i class="fas fa-calendar-check"></i> Manage Appointments</a>
        <a href="update_information.php" class="sidebar-link"><i class="fas fa-user-edit"></i> Update Information</a>
        <a href="view_schedule.php" class="sidebar-link"><i class="fas fa-calendar-alt"></i> View Schedule</a>
    </div>

    <div class="col-md-9 col-lg-10 content">
        <?php $flash = getFlashMessage();
        if ($flash) {
            echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">'
                . $flash['message'] .
                '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
        } ?>
        
        <h2><i class="fas fa-calendar-check"></i> Manage Appointments</h2>
        <p class="lead">View and manage your scheduled counseling appointments.</p>

        <div class="row">
            <div class="col-md-12">
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
                                            <th>Notes</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($a = mysqli_fetch_assoc($appointments)): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($a['counselor_name']) ?></td>
                                            <td data-current-date="<?= $a['appointment_date'] ?>" data-current-time="<?= $a['appointment_time'] ?>">
                                                <?= formatDate($a['appointment_date']) ?><br><small><?= formatTime($a['appointment_time']) ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $status = $a['status'];
                                                $badge = [
                                                    'pending'=>'warning','accepted'=>'success','declined'=>'danger','cancelled'=>'secondary',
                                                    'successful'=>'primary',
                                                    'reschedule'=>'info' // Added new status badge for 'reschedule'
                                                ][$status] ?? 'light';
                                                ?>
                                                <span class="badge badge-<?= $badge ?>"><?= ucfirst($status) ?></span>
                                            </td>
                                            <td><?= !empty($a['notes']) ? htmlspecialchars(substr($a['notes'], 0, 50)) . (strlen($a['notes']) > 50 ? '...' : '') : 'N/A' ?></td>
                                            <td>
                                                <a href="view_appointment_details.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-info mb-1">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <?php 
                                                // Allow reschedule and cancellation only for original pending or accepted appointments
                                                if (in_array($status, ['pending','accepted'])): 
                                                ?>
                                                <button class="btn btn-sm btn-warning reschedule-btn mb-1" 
                                                                data-toggle="modal" 
                                                                data-target="#rescheduleModal"
                                                                data-id="<?= $a['id'] ?>"
                                                                data-counselor-id="<?= $a['counselor_id'] ?>"
                                                                data-counselor-name="<?= htmlspecialchars($a['counselor_name']) ?>"
                                                                data-date="<?= formatDate($a['appointment_date']) ?>"
                                                                data-time="<?= formatTime($a['appointment_time']) ?>">
                                                    <i class="fas fa-sync-alt"></i> Reschedule
                                                </button>
                                                <a href="manage_appointment.php?id=<?= $a['id'] ?>&action=cancel" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Cancel this appointment?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                                <?php elseif ($status == 'reschedule'): // Allow cancellation while waiting for reschedule confirmation ?>
                                                <span class="btn btn-sm btn-secondary mb-1 disabled">Reschedule Pending</span>
                                                <a href="manage_appointment.php?id=<?= $a['id'] ?>&action=cancel" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Cancel this appointment?')">
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
                            <div class="p-4 text-center text-muted">You have no appointments to manage.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="rescheduleModal" tabindex="-1" role="dialog" aria-labelledby="rescheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rescheduleModalLabel">Request Reschedule</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="manage_appointment.php">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="reschedule_appointment_id">
                    <input type="hidden" name="reschedule_appointment" value="1">
                    
                    <p>You are rescheduling your appointment with: <strong id="reschedule_counselor_name"></strong></p>
                    <p>Current appointment time: <strong id="reschedule_current_time"></strong></p>

                    <div class="form-group">
                        <label for="new_date">Select New Date</label>
                        <input type="text" class="form-control" id="new_date" name="new_date" required placeholder="MM/DD/YYYY">
                    </div>

                    <div id="availability-message" class="text-info"></div>

                    <div class="form-group">
                        <label for="new_time">Select New Time</label>
                        <select class="form-control" id="new_time" name="new_time" required>
                            <option value="">-- Select Time --</option>
                            <?php foreach ($all_slots as $slot): ?>
                                <option value="<?php echo $slot; ?>"><?php echo date("h:i A", strtotime($slot)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="reschedule_notes">Reason for Reschedule (Required)</label>
                        <textarea class="form-control" id="reschedule_notes" name="reschedule_notes" rows="3" required placeholder="Explain why you need to reschedule and why this new time works..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-sync-alt"></i> Request Reschedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script> <script>
$(function() {
    // Initialize datepicker for the new date input
    $("#new_date").datepicker({
        dateFormat: "mm/dd/yy",
        minDate: 0, // Restrict to current day or later
        showAnim: "fadeIn"
    });

    // Function to load availability (stub - backend logic for this is complex)
    // NOTE: This client-side function still needs a backend file (check_availability.php) 
    // to function properly in a real environment, but is kept for consistency.
    function loadAvailability() {
        // Get counselor ID from the hidden field's data attribute, which is set on button click
        let counselor = $("#reschedule_appointment_id").data('counselor-id'); 
        let date = $("#new_date").val();
        
        // Convert date from MM/DD/YYYY (datepicker) to YYYY-MM-DD for consistency in AJAX
        const dateParts = date.split('/');
        const dbDate = dateParts.length === 3 ? `${dateParts[2]}-${dateParts[0]}-${dateParts[1]}` : date;

        if (counselor && dbDate) {
            $("#availability-message").text("Checking availability...");
            
            // This AJAX call targets a hypothetical `get_unavailable_times.php` or similar endpoint
            // The `get_unavailable_times.php` from the counselor panel could be adapted for this.
            $.get("get_unavailable_times.php", { date: dbDate, counselor_id: counselor }, function(data) {
                // Assuming data is a JSON array of unavailable time slots (e.g., ["09:00", "09:30"])
                // This is where you would update the #new_time select options.
                
                // For simplicity here, we just provide a status message
                if (Array.isArray(data) && data.length > 0) {
                     $("#availability-message").html("<span class='text-danger'>*Note: There are unavailable slots on this date. Check the time dropdown.</span>");
                } else {
                     $("#availability-message").html("<span class='text-success'>Counselor should be available on this date (check time slots).</span>");
                }
            }, 'json').fail(function() {
                 $("#availability-message").html("<span class='text-danger'>*Note: Availability check service is currently unavailable. Proceed with caution.</span>");
            });

            // Re-fetch all time slots and update the select dropdown based on the date selection
            updateTimeSlots(dbDate, counselor);

        }
    }
    
    // Function to update time slots dynamically (Placeholder for a more complex implementation)
    function updateTimeSlots(date, counselorId) {
        const $timeSelect = $("#new_time");
        const allSlots = $timeSelect.data('all-slots'); // Assuming all slots are stored here or in PHP

        // Clear existing options, keep the default one
        $timeSelect.find('option:gt(0)').remove();
        
        // This is a simplified function and requires the full implementation of get_unavailable_times.php 
        // to return the correct disabled slots for the selected counselor/date.
        // For now, it just resets the slots to the default list from PHP.
        // A robust solution would involve another AJAX call here.
        const phpAllSlots = <?php echo json_encode($all_slots); ?>;
         phpAllSlots.forEach(slot => {
            let display = new Date('1970-01-01T' + slot + ':00')
                                 .toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            $timeSelect.append('<option value="' + slot + '">' + display + '</option>');
        });

    }


    $("#new_date").on("change", loadAvailability);

    // Populate modal fields when the Reschedule button is clicked
    $('.reschedule-btn').on('click', function() {
        var button = $(this);
        var appointmentId = button.data('id');
        var counselorId = button.data('counselor-id');
        var counselorName = button.data('counselor-name');
        var currentDate = button.data('date');
        var currentTime = button.data('time');
        
        // Populate hidden field with appointment ID
        $('#reschedule_appointment_id').val(appointmentId);
        // Store counselor ID on the field itself for use in loadAvailability()
        $('#reschedule_appointment_id').data('counselor-id', counselorId); 

        // Populate display fields
        $('#reschedule_counselor_name').text(counselorName);
        $('#reschedule_current_time').text(currentDate + ' at ' + currentTime);
        
        // Clear previous input/messages
        $('#new_date').val('');
        $('#new_time').val('');
        $('#reschedule_notes').val('');
        $('#availability-message').empty();
        
        // Manually trigger slot reset on modal show, but only for the time select options
        const $timeSelect = $("#new_time");
        const phpAllSlots = <?php echo json_encode($all_slots); ?>;
        $timeSelect.find('option:gt(0)').remove();
        phpAllSlots.forEach(slot => {
            let display = new Date('1970-01-01T' + slot + ':00')
                                 .toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            $timeSelect.append('<option value="' + slot + '">' + display + '</option>');
        });
    });
});
</script>
</body>
</html>
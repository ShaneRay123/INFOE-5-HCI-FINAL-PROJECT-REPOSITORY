<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Fallbacks for helper functions used in this file
if (!function_exists('formatTime')) {
    function formatTime($time) {
        // Converts a time string (like '14:30:00') into a human-readable format (like '2:30 pm')
        return date('g:i a', strtotime($time));
    }
}
if (!function_exists('formatDate')) {
    function formatDate($date) {
        // Converts a date string (like '2025-09-15') into a human-readable format (like 'Sep 15, Y')
        return date('M j, Y', strtotime($date));
    }
}

// === NEW TIME OPTION GENERATION FUNCTIONS (30-MINUTE INTERVALS) ===

/**
 * Generates <option> tags for start times (8:00 AM to 5:00 PM, 30 min intervals).
 * Defaults to 8:00 AM selected.
 */
function generateStartOptions() {
    $options = '';
    $start_time = strtotime('08:00');
    $end_time = strtotime('17:00'); 
    $interval_seconds = 1800; // 30 minutes

    for ($time = $start_time; $time <= $end_time; $time += $interval_seconds) {
        $time_24h = date('H:i', $time);
        $time_12h = date('g:i a', $time);
        // Default to 08:00 selected
        $selected = ($time_24h === '08:00') ? ' selected' : '';
        $options .= "<option value=\"$time_24h\"$selected>$time_12h</option>";
    }
    return $options;
}

/**
 * Generates <option> tags for end times (8:00 AM to 5:00 PM, 30 min intervals).
 * Defaults to 5:00 PM selected.
 */
function generateEndOptions() {
    $options = '';
    $start_time = strtotime('08:00');
    $end_time = strtotime('17:00'); 
    $interval_seconds = 1800; // 30 minutes

    for ($time = $start_time; $time <= $end_time; $time += $interval_seconds) {
        $time_24h = date('H:i', $time);
        $time_12h = date('g:i a', $time);
        // Default to 17:00 selected
        $selected = ($time_24h === '17:00') ? ' selected' : '';
        $options .= "<option value=\"$time_24h\"$selected>$time_12h</option>";
    }
    return $options;
}

// =================================================================

// Check if user is logged in
if (!isLoggedIn()) {
    redirect("../login.php");
}

// Check if user is an admin/counselor
if (!isAdmin()) {
    redirect("../index.php");
}

// Get user information and ID
$user = getUserDetails($_SESSION["user_id"]);
$counselor_id = $_SESSION["user_id"];

// Get current month and year for CALENDAR VIEW
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($month < 1 || $month > 12) { $month = date('n'); }
if ($year < 2000 || $year > 2100) { $year = date('Y'); }

// Get previous and next month
$prev_month = $month == 1 ? 12 : $month - 1;
$prev_year = $month == 1 ? $year - 1 : $year;
$next_month = $month == 12 ? 1 : $month + 1;
$next_year = $month == 12 ? $year + 1 : $year;

// Get first day of the month for calendar display
$first_day = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_week = date('N', $first_day);
$days_in_month = date('t', $first_day);
$month_name = date('F', $first_day);

// Get the timestamp for the beginning of TODAY for comparison
$today_timestamp = strtotime(date('Y-m-d'));

// --- MODAL INTERACTION LOGIC ---

// Variable to hold the specific date being managed in the modal (if applicable)
$modal_date = null;
$modal_slots = [];

// If a specific date is being requested (e.g., from the modal form submission or opening the modal via GET)
if (isset($_GET['date'])) {
    $modal_date = sanitize($_GET['date']);
    // Ensure the date is valid and relates to the current month being viewed for security/logic check (optional but good practice)
    $date_obj = date_create($modal_date);
    if ($date_obj && date('n', $date_obj->getTimestamp()) == $month && date('Y', $date_obj->getTimestamp()) == $year) {
        
        // Fetch existing slots for the date currently in the modal
        $modal_slots_query = "SELECT id, start_time, end_time 
                              FROM counselor_availability 
                              WHERE counselor_id = ? AND available_date = ? 
                              ORDER BY start_time";
        $modal_stmt = mysqli_prepare($conn, $modal_slots_query);
        mysqli_stmt_bind_param($modal_stmt, "is", $counselor_id, $modal_date);
        mysqli_stmt_execute($modal_stmt);
        $modal_slots_result = mysqli_stmt_get_result($modal_stmt);
        
        while ($row = mysqli_fetch_assoc($modal_slots_result)) {
            $modal_slots[] = $row;
        }
        mysqli_stmt_close($modal_stmt);
    } else {
        // Clear modal_date if it's invalid or outside the current view
        $modal_date = null;
    }
}


// --- START: AVAILABILITY MANAGEMENT LOGIC (POST REQUESTS) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $redirect_url = "counselor_schedule.php?month=$month&year=$year";

    // 1. Handle ADD AVAILABILITY SLOT (from the modal)
    if (isset($_POST['action']) && $_POST['action'] == 'add_slot_for_date') {
        $date = sanitize($_POST['modal_date']);
        $start_time = sanitize($_POST['start_time']);
        $end_time = sanitize($_POST['end_time']);

        // Time constraints for 8:00 AM to 5:00 PM
        $min_time_check = '08:00:00';
        $max_time_check = '17:00:00';
        
        // Check for weekend (Saturday=6 or Sunday=7)
        $day_of_week_post = date('N', strtotime($date)); 
        // Check if date is in the past
        $post_date_timestamp = strtotime($date);

        if ($day_of_week_post == 6 || $day_of_week_post == 7) {
            setFlashMessage("Cannot add scheduled hours on weekends (Saturday/Sunday). No office hours.", "danger");
        } elseif ($post_date_timestamp < $today_timestamp) {
            setFlashMessage("Cannot add scheduled hours on a past date.", "danger");
        } elseif (!empty($date) && !empty($start_time) && !empty($end_time)) {
            
            // --- NEW 30-MINUTE INTERVAL CHECK (Must be on the hour or half-hour) ---
            $start_minute = (int)date('i', strtotime($start_time));
            $end_minute = (int)date('i', strtotime($end_time));

            if (($start_minute % 30) !== 0 || ($end_minute % 30) !== 0) {
                 setFlashMessage("Start and end times must be set in 30-minute intervals (e.g., 8:00, 8:30, 9:00).", "danger");
            }
            // --- TIME RANGE CHECK ---
            elseif (strtotime($start_time) < strtotime($min_time_check) || strtotime($end_time) > strtotime($max_time_check)) {
                setFlashMessage("Available hours must be strictly within the 8:00 AM to 5:00 PM range.", "danger");
            } 
            // --- TIME ORDER CHECK ---
            elseif (strtotime($start_time) >= strtotime($end_time)) {
                setFlashMessage("Start time must be before end time.", "danger");
            } else {
                $insert_query = "INSERT INTO counselor_availability (counselor_id, available_date, start_time, end_time) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($stmt, "isss", $counselor_id, $date, $start_time, $end_time);
                
                if (mysqli_stmt_execute($stmt)) {
                    setFlashMessage("New available time slot added successfully for " . formatDate($date) . ".", "success");
                } else {
                    setFlashMessage("Error adding available time slot. Please try again. " . mysqli_error($conn), "danger");
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            setFlashMessage("All time fields are required.", "danger");
        }
        // Redirect back, ensuring the modal is reopened for the same date
        $redirect_url .= "&date=" . urlencode($date); 
        redirect($redirect_url);
    }

    // 2. Handle DELETE AVAILABILITY SLOT (from the modal)
    if (isset($_POST['action']) && $_POST['action'] == 'delete_slot') {
        $slot_id = intval($_POST['slot_id']);
        $date = sanitize($_POST['modal_date']); // Get date to reopen modal
        
        $delete_query = "DELETE FROM counselor_availability WHERE id = ? AND counselor_id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "ii", $slot_id, $counselor_id);
        
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage("Available time slot deleted successfully.", "success");
        } else {
            setFlashMessage("Error deleting available time slot.", "danger");
        }
        mysqli_stmt_close($stmt);

        // Redirect back, ensuring the modal is reopened for the same date
        $redirect_url .= "&date=" . urlencode($date);
        redirect($redirect_url);
    }
}
// --- END: AVAILABILITY MANAGEMENT LOGIC (POST REQUESTS) ---


// --- START: FETCH DATA FOR CALENDAR DISPLAY ---

// A. Fetch Booked Appointments for the current month
$appointments_query = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, u.full_name as student_name 
                      FROM appointments a 
                      JOIN users u ON a.student_id = u.id 
                      WHERE a.counselor_id = ? 
                      AND MONTH(a.appointment_date) = ? 
                      AND YEAR(a.appointment_date) = ? 
                      ORDER BY a.appointment_date, a.appointment_time";
$stmt = mysqli_prepare($conn, $appointments_query);
mysqli_stmt_bind_param($stmt, "iii", $counselor_id, $month, $year);
mysqli_stmt_execute($stmt);
$appointments_result = mysqli_stmt_get_result($stmt);

// Create array to store appointments by date
$appointments = [];
while ($row = mysqli_fetch_assoc($appointments_result)) {
    $day = date('j', strtotime($row['appointment_date']));
    if (!isset($appointments[$day])) { $appointments[$day] = []; }
    $appointments[$day][] = $row;
}
mysqli_stmt_close($stmt);


// B. Fetch Counselor Availability Slots for the current month (for calendar view)
$availability_query = "SELECT available_date, start_time, end_time 
                       FROM counselor_availability 
                       WHERE counselor_id = ? 
                       AND MONTH(available_date) = ? 
                       AND YEAR(available_date) = ? 
                       ORDER BY available_date, start_time";
$avail_stmt = mysqli_prepare($conn, $availability_query);
mysqli_stmt_bind_param($avail_stmt, "iii", $counselor_id, $month, $year);
mysqli_stmt_execute($avail_stmt);
$availability_result = mysqli_stmt_get_result($avail_stmt);

// Create array to store availability by date (for calendar view)
$availability = [];
while ($row = mysqli_fetch_assoc($availability_result)) {
    $day = date('j', strtotime($row['available_date']));
    if (!isset($availability[$day])) { $availability[$day] = []; }
    $availability[$day][] = $row;
}
mysqli_stmt_close($avail_stmt);

// --- END: FETCH DATA FOR CALENDAR DISPLAY ---

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule & Available Hours - Admin Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
    /* === Global === */
    body { background-color: #f8f9fa; }

    /* === Sidebar (Static) === */
    .sidebar { background-color: #2b2b2b; min-height: calc(100vh - 56px); color: white; }
    .sidebar-link { color: rgba(255, 255, 255, 0.8); padding: 10px 15px; display: block; text-decoration: none; transition: 0.3s; border-radius: 5px; margin-bottom: 5px; }
    .sidebar-link:hover { color: white; background-color: rgba(179, 1, 4, 0.6); text-decoration: none; }
    .sidebar-link.active { color: white; background-color: #b30104; }
    .sidebar-link i { margin-right: 10px; }

    /* === Navbar === */
    .navbar-dark.bg-dark { background-color: #1e1e1e !important; }

    /* === Content Area === */
    .content { padding: 20px; }

    /* === Card === */
    .card { margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; border-radius: 8px; }
    .card-header { background-color: #b30104; color: white; font-weight: bold; }

    /* === Calendar === */
    .calendar { width: 100%; border-collapse: collapse; animation: fadeUp 0.6s ease-out; }
    .calendar th, .calendar td { border: 1px solid #dee2e6; padding: 10px; text-align: center; height: 100px; vertical-align: top; }
    .calendar th { background-color: #b30104; color: white; cursor: default; }
    
    /* Enabled Day */
    .calendar td.calendar-day { cursor: pointer; }
    .calendar td.calendar-day:hover { background-color: rgba(179, 1, 4, 0.05); }

    /* Disabled (Blank) Day */
    .calendar td.disabled { background-color: #f1f1f1; cursor: default !important; }

    /* Weekend/No Office Day */
    .calendar td.no-office { 
        background-color: #fce7e7; /* Light red background */
        color: #b30104; 
        border: 1px dashed #e3b0b0;
    }
    
    /* Past Date */
    .calendar td.past-date {
        background-color: #e9ecef; /* Lighter gray background */
        color: #6c757d; /* Muted text color */
        cursor: default !important;
        pointer-events: none; /* Ensure no interaction */
    }


    .day-number { font-weight: bold; float: right; }
    .calendar .today { background-color: rgba(179, 1, 4, 0.1); border: 2px solid #b30104; }

    /* === Available Slot Style === */
    .availability-item {
        font-size: 0.75rem; 
        text-align: center;
        margin-bottom: 2px;
        padding: 1px 4px;
        border-radius: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        background-color: #007bff; /* Primary Blue for 'Available Slot' */
        color: white;
        font-weight: 500;
    }
    
    .modal-slots {
        max-height: 250px;
        overflow-y: auto;
    }

    /* === Appointments (Existing styles) === */
    .appointment-item {
        font-size: 0.8rem;
        text-align: left;
        margin-bottom: 5px;
        padding: 2px 5px;
        border-radius: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .bg-warning.text-dark { background-color: #ffc107 !important; color: #343a40 !important; }
    .bg-success.text-white { background-color: #008f39 !important; color: white !important; }
    .bg-primary.text-white { background-color: #007bff !important; color: white !important; }
    .bg-danger.text-white { background-color: #b30104 !important; color: white !important; }
    .bg-secondary.text-white { background-color: #6c757d !important; color: white !important; }

    /* === Buttons === */
    .btn-primary { background-color: #b30104; border: none; transition: transform 0.25s ease, box-shadow 0.25s ease; }
    .btn-primary:hover { background-color: #8a0103; transform: scale(1.05); box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
    .btn-outline-primary { border-color: #b30104; color: #b30104; transition: transform 0.25s ease, box-shadow 0.25s ease; }
    .btn-outline-primary:hover { background-color: #b30104; color: white; transform: scale(1.05); box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
    .btn-secondary { background-color: #555; border: none; }
    .btn-secondary:hover { background-color: #444; transform: scale(1.05); box-shadow: 0 4px 10px rgba(0,0,0,0.2); }

    /* === Month Navigation === */
    .month-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    </style>

</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Admin Wellness Hub</a>
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
                <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="manage_students.php" class="sidebar-link"><i class="fas fa-users"></i> Manage Students</a>
                <a href="assessment_management.php" class="sidebar-link"><i class="fas fa-cogs"></i> Manage Assessments</a>
                <a href="view_assessments.php" class="sidebar-link"><i class="fas fa-tasks"></i> View Assessments</a>
               
                <a href="counselor_schedule.php" class="sidebar-link active"><i class="fas fa-calendar-alt"></i> My Schedule & Available Hours</a>
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
                
                <h2><i class="fas fa-calendar-alt"></i> My Schedule Calendar</h2> 
                <p class="lead">Click on a working day (Mon-Fri) to manage your **Available Hours** (8:00 AM to 5:00 PM). **Past dates are unclickable.**</p>
                
                <div class="card">
                    <div class="card-body">
                        <div class="month-nav">
                            <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-chevron-left"></i> Previous Month
                            </a>
                            <h3 class="mb-0"><?php echo $month_name . " " . $year; ?></h3>
                            <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-outline-primary">
                                Next Month <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="calendar">
                                <thead>
                                    <tr>
                                        <th>Monday</th>
                                        <th>Tuesday</th>
                                        <th>Wednesday</th>
                                        <th>Thursday</th>
                                        <th>Friday</th>
                                        <th>Saturday</th>
                                        <th>Sunday</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $day_count = 1;
                                    $current_day = 1;
                                    $adj_first_day = $first_day_of_week - 1;
                                    
                                    echo "<tr>";
                                    
                                    // Print blank cells for days before the 1st of the month
                                    for ($i = 0; $i < $adj_first_day; $i++) {
                                        echo "<td class='disabled'></td>";
                                        $day_count++;
                                    }
                                    
                                    // Print days of the month
                                    while ($current_day <= $days_in_month) {
                                        if ($day_count > 7) {
                                            echo "</tr><tr>";
                                            $day_count = 1;
                                        }
                                        
                                        $full_date = date('Y-m-d', mktime(0, 0, 0, $month, $current_day, $year));
                                        $day_of_week = date('N', strtotime($full_date)); // 1=Mon, 7=Sun
                                        $is_weekend = ($day_of_week == 6 || $day_of_week == 7); // 6=Sat, 7=Sun
                                        
                                        // Check if the current day being rendered is in the past
                                        $day_timestamp = strtotime($full_date);
                                        $is_past = ($day_timestamp < $today_timestamp);


                                        $cell_classes = [];
                                        $click_handler = '';
                                        $weekend_label = '';

                                        if ($is_past) {
                                            $cell_classes[] = 'past-date disabled';
                                        } elseif ($is_weekend) {
                                            $cell_classes[] = 'no-office disabled';
                                            $weekend_label = '<div class="small mt-3 font-weight-bold text-danger">NO OFFICE</div>';
                                        } else {
                                            $cell_classes[] = 'calendar-day';
                                            $click_handler = "data-date='$full_date'";
                                        }

                                        $today_class = '';
                                        if ($current_day == date('j') && $month == date('n') && $year == date('Y')) {
                                            $today_class = 'today';
                                        }
                                        
                                        echo "<td class='$today_class " . implode(' ', $cell_classes) . "' $click_handler>";
                                        echo "<div class='day-number'>$current_day</div>";

                                        if ($is_weekend) {
                                            echo $weekend_label;
                                        } elseif (!$is_past) {
                                            // --- Display Available Hours ---
                                            if (isset($availability[$current_day])) {
                                                echo "<div class='text-center mb-1 mt-1 small font-weight-bold text-primary'>Available:</div>";
                                                foreach ($availability[$current_day] as $slot) {
                                                    echo "<div class='availability-item'>";
                                                    echo formatTime($slot['start_time']) . " - " . formatTime($slot['end_time']);
                                                    echo "</div>";
                                                }
                                                if (isset($appointments[$current_day])) {
                                                    echo "<hr class='my-1'>"; 
                                                }
                                            }

                                            // --- Display Booked Appointments ---
                                            if (isset($appointments[$current_day])) {
                                                foreach ($appointments[$current_day] as $appointment) {
                                                    $status_class = '';
                                                    switch ($appointment['status']) {
                                                        case 'pending': $status_class = 'bg-warning text-dark'; $status_text = 'Pending'; break;
                                                        case 'accepted': $status_class = 'bg-success text-white'; $status_text = 'Confirmed'; break;
                                                        case 'successful': $status_class = 'bg-primary text-white'; $status_text = 'Successful'; break;
                                                        case 'declined': $status_class = 'bg-danger text-white'; $status_text = 'Declined'; break;
                                                        case 'cancelled': $status_class = 'bg-secondary text-white'; $status_text = 'Cancelled'; break;
                                                    }
                                                    
                                                    echo "<div class='appointment-item $status_class' title='Status: " . $status_text . "'>";
                                                    echo formatTime($appointment['appointment_time']) . " - " . htmlspecialchars($appointment['student_name']); 
                                                    echo "</div>";
                                                }
                                            }
                                        }
                                        
                                        echo "</td>";
                                        
                                        $current_day++;
                                        $day_count++;
                                    }
                                    
                                    // Fill remaining cells with blank days
                                    while ($day_count <= 7) {
                                        echo "<td class='disabled'></td>";
                                        $day_count++;
                                    }
                                    
                                    echo "</tr>";
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            <h5>Legend</h5>
                            <div class="d-flex flex-wrap">
                                <div class="mr-3 mb-2">
                                    <div class="availability-item d-inline-block p-1" style="width: 100px; margin-right: 5px; background-color: #007bff;">Available</div>
                                </div>
                                <div class="mr-3 mb-2">
                                    <span style="background-color: #fce7e7; color: #b30104; padding: 2px 5px; border-radius: 3px;">No Office (Sat/Sun)</span>
                                </div>
                                <div class="mr-3 mb-2">
                                    <span style="background-color: #e9ecef; color: #6c757d; padding: 2px 5px; border-radius: 3px;">Past Date</span>
                                </div>
                                <div class="mr-3 mb-2">
                                    <span style="background-color: #ffc107; display: inline-block; width: 10px; height: 10px; border-radius: 50%;"></span> Pending Appointment
                                </div>
                                <div class="mr-3 mb-2">
                                    <span style="background-color: #008f39; display: inline-block; width: 10px; height: 10px; border-radius: 50%;"></span> Confirmed Appointment
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="manage_appointment.php" class="btn btn-secondary">
                        <i class="fas fa-clipboard-list"></i> Manage All Appointments 
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="availabilityModal" tabindex="-1" role="dialog" aria-labelledby="availabilityModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="availabilityModalLabel"><i class="fas fa-clock"></i> Manage Available Hours for <span id="modal-display-date"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    
                    <h6>Define New Available Time Slot (8:00 AM - 5:00 PM, 30-minute intervals)</h6>
                    <form id="add-slot-form" method="POST" action="counselor_schedule.php">
                        <input type="hidden" name="action" value="add_slot_for_date">
                        <input type="hidden" name="modal_date" id="modal-hidden-date" value="">
                        <input type="hidden" name="month" value="<?php echo $month; ?>">
                        <input type="hidden" name="year" value="<?php echo $year; ?>">
                        
                        <div class="form-row">
                            <div class="form-group col-5">
                                <label for="start_time" class="small">Start Time</label>
                                <select class="form-control form-control-sm" id="start_time" name="start_time" required>
                                    <?php echo generateStartOptions(); // Populates options 8:00 AM to 5:00 PM ?>
                                </select>
                            </div>
                            <div class="form-group col-5">
                                <label for="end_time" class="small">End Time</label>
                                <select class="form-control form-control-sm" id="end_time" name="end_time" required>
                                    <?php echo generateEndOptions(); // Populates options 8:00 AM to 5:00 PM ?>
                                </select>
                            </div>
                            <div class="form-group col-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <h6>Current Available Hours</h6>
                    <?php if ($modal_date && count($modal_slots) > 0): ?>
                        <div class="modal-slots">
                            <ul class="list-group">
                                <?php foreach ($modal_slots as $slot): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                        <?php echo formatTime($slot['start_time']) . " - " . formatTime($slot['end_time']); ?>
                                        <form method="POST" action="counselor_schedule.php" class="d-inline" onsubmit="return confirm('Delete this available hour?');">
                                            <input type="hidden" name="action" value="delete_slot">
                                            <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                            <input type="hidden" name="modal_date" value="<?php echo htmlspecialchars($modal_date); ?>">
                                            <input type="hidden" name="month" value="<?php echo $month; ?>">
                                            <input type="hidden" name="year" value="<?php echo $year; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php elseif ($modal_date): ?>
                        <div class="alert alert-info small text-center">
                            No available hours defined for this day. Add a new slot above.
                        </div>
                    <?php endif; ?>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // PHP logic to automatically show the modal if a date parameter is present (after form submission/redirect)
            const urlParams = new URLSearchParams(window.location.search);
            const dateFromUrl = urlParams.get('date');
            
            if (dateFromUrl) {
                // Check if the date is in the past using a JS Date object comparison
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const compareDate = new Date(dateFromUrl + 'T00:00:00');

                // Check if the URL date is in the past
                const isPastDate = compareDate < today;

                // Check if the URL date is a weekend (0=Sun, 6=Sat)
                const dayOfWeek = compareDate.getDay(); 
                const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

                if (!isPastDate && !isWeekend) {
                    // Only show modal if the date is not past and not a weekend
                    
                    // Set form fields
                    $('#modal-hidden-date').val(dateFromUrl);
                    
                    // Format the date for the modal title display
                    const options = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' };
                    $('#modal-display-date').text(compareDate.toLocaleDateString('en-US', options));
                    
                    $('#availabilityModal').modal('show');
                }
            }

            // JavaScript to handle the click event on calendar days
            // Only attach click handler to elements with 'calendar-day' class 
            $('.calendar-day').on('click', function() {
                const clickedDate = $(this).data('date'); 
                const currentMonth = <?php echo $month; ?>;
                const currentYear = <?php echo $year; ?>;

                // Re-direct to the same page with the clicked date as a GET parameter.
                window.location.href = `counselor_schedule.php?month=${currentMonth}&year=${currentYear}&date=${clickedDate}`;
            });
            
            // Explicitly prevent clicking on all cells marked as 'disabled'
            $('.calendar td.disabled').off('click');
        });
    </script>
</body>
</html>
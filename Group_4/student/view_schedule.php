<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Fallbacks for helper functions (Required for consistent time display)
if (!function_exists('formatTime')) {
    function formatTime($time) {
        // Converts a time string (like '14:30:00') into a human-readable format (like '2:30 pm')
        return date('g:i a', strtotime($time));
    }
}

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

// --- NEW LOGIC: FETCH COUNSELORS AND HANDLE SELECTION ---

$counselors = [];
// Assuming counselors are users with role = 'admin' (as used in counselor_schedule.php)
$counselor_query = "SELECT id, full_name FROM users WHERE role = 'admin' ORDER BY full_name";
$counselor_result = mysqli_query($conn, $counselor_query);
while ($row = mysqli_fetch_assoc($counselor_result)) {
    $counselors[] = $row;
}

$selected_counselor_id = null;
$selected_counselor_name = "N/A";

if (!empty($counselors)) {
    // 1. Get the counselor ID from the URL, or default to the first counselor
    $default_counselor_id = $counselors[0]['id'];
    $selected_counselor_id = isset($_GET['counselor_id']) ? intval($_GET['counselor_id']) : $default_counselor_id;
    
    // 2. Validate the selected ID against the fetched list, reset if invalid
    $valid_counselor = false;
    foreach($counselors as $c) {
        if ($c['id'] == $selected_counselor_id) {
            $valid_counselor = true;
            $selected_counselor_name = $c['full_name'];
            break;
        }
    }
    if (!$valid_counselor) {
        $selected_counselor_id = $default_counselor_id; 
        $selected_counselor_name = $counselors[0]['full_name'];
    }
}

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

// Get previous and next month, preserving the selected counselor ID
$base_url = "view_schedule.php?counselor_id=$selected_counselor_id";
$prev_month = $month == 1 ? 12 : $month - 1;
$prev_year = $month == 1 ? $year - 1 : $year;
$next_month = $month == 12 ? 1 : $month + 1;
$next_year = $month == 12 ? $year + 1 : $year;

// Get first day of the month
$first_day = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_week = date('N', $first_day);
$days_in_month = date('t', $first_day);
$month_name = date('F', $first_day);

// Get the timestamp for the beginning of TODAY for comparison
$today_timestamp = strtotime(date('Y-m-d'));


// 1. Fetch Student Appointments for the current month (existing logic)
$appointments_query = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, u.full_name as counselor_name 
                      FROM appointments a 
                      JOIN users u ON a.counselor_id = u.id 
                      WHERE a.student_id = ? 
                      AND MONTH(a.appointment_date) = ? 
                      AND YEAR(a.appointment_date) = ? 
                      ORDER BY a.appointment_date, a.appointment_time";
$stmt = mysqli_prepare($conn, $appointments_query);
mysqli_stmt_bind_param($stmt, "iii", $_SESSION["user_id"], $month, $year);
mysqli_stmt_execute($stmt);
$appointments_result = mysqli_stmt_get_result($stmt);

// Create array to store appointments by date
$appointments = [];
while ($row = mysqli_fetch_assoc($appointments_result)) {
    $day = date('j', strtotime($row['appointment_date']));
    if (!isset($appointments[$day])) {
        $appointments[$day] = [];
    }
    $appointments[$day][] = $row;
}
mysqli_stmt_close($stmt);

// 2. --- NEW LOGIC: FETCH SELECTED COUNSELOR AVAILABILITY ---

$availability = [];
if ($selected_counselor_id) {
    $availability_query = "SELECT available_date, start_time, end_time 
                           FROM counselor_availability 
                           WHERE counselor_id = ? 
                           AND MONTH(available_date) = ? 
                           AND YEAR(available_date) = ? 
                           ORDER BY available_date, start_time";
    $avail_stmt = mysqli_prepare($conn, $availability_query);
    mysqli_stmt_bind_param($avail_stmt, "iii", $selected_counselor_id, $month, $year);
    mysqli_stmt_execute($avail_stmt);
    $availability_result = mysqli_stmt_get_result($avail_stmt);

    while ($row = mysqli_fetch_assoc($availability_result)) {
        $day = date('j', strtotime($row['available_date']));
        if (!isset($availability[$day])) {
            $availability[$day] = [];
        }
        $availability[$day][] = $row;
    }
    mysqli_stmt_close($avail_stmt);
}
// --- END NEW LOGIC ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Schedule - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
    /* === Global === */
body {
    background-color: #f8f9fa;
}

/* === Sidebar (Static) === */
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

/* === Navbar === */
.navbar-dark.bg-dark {
    background-color: #1e1e1e !important;
}

/* === Content Area === */
.content {
    padding: 20px;
}

/* === Card === */
.card {
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: none;
    border-radius: 8px;
}

.card-header {
    background-color: #b30104;
    color: white;
    font-weight: bold;
}

/* === Calendar === */
.calendar {
    width: 100%;
    border-collapse: collapse;
    animation: fadeUp 0.6s ease-out;
}

.calendar th, .calendar td {
    border: 1px solid #dee2e6;
    padding: 10px;
    text-align: center;
    height: 100px;
    vertical-align: top;
}

.calendar th {
    background-color: #b30104;
    color: white;
}

.calendar td:hover {
    /* Only hover if it's not a disabled/past day, handled by JS later if needed, but keeping for general calendar feel */
    background-color: rgba(179, 1, 4, 0.05);
}

.day-number {
    font-weight: bold;
    float: right;
}

.calendar .today {
    background-color: rgba(179, 1, 4, 0.1);
    border: 2px solid #b30104;
}

/* === Availability Slot Style (NEW) === */
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


/* === Appointments (Existing styles) === */
.appointment-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}

.appointment-pending { background-color: #ffc107; }
.appointment-accepted { background-color: #008f39; }
.appointment-successful { background-color: #007bff; }
.appointment-cancelled { background-color: #6c757d; }
.appointment-declined { background-color: #b30104; }


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

/* === Buttons === */
.btn-primary {
    background-color: #b30104;
    border: none;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.btn-primary:hover {
    background-color: #8a0103;
    transform: scale(1.05);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

.btn-outline-primary {
    border-color: #b30104;
    color: #b30104;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.btn-outline-primary:hover {
    background-color: #b30104;
    color: white;
    transform: scale(1.05);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

.btn-secondary {
    background-color: #555;
    border: none;
}

.btn-secondary:hover {
    background-color: #444;
    transform: scale(1.05);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

/* === Alerts === */
.alert-success {
    background-color: #e6f4ea;
    border-left: 5px solid #008f39;
    color: #155724;
    animation: fadeIn 0.4s ease-out;
}

.alert-danger {
    background-color: #fdeaea;
    border-left: 5px solid #b30104;
    color: #721c24;
    animation: fadeIn 0.4s ease-out;
}

/* === Month Navigation === */
.month-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

/* === Animations === */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
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
                <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="take_assessment.php" class="sidebar-link"><i class="fas fa-tasks"></i> Take Assessment</a>
                
                <a href="manage_appointment.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Manage Appointments</a>
                <a href="update_information.php" class="sidebar-link"><i class="fas fa-user-edit"></i> Update Information</a>
                <a href="view_schedule.php" class="sidebar-link active"><i class="fas fa-calendar-alt"></i> View Schedule</a>
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
                
                <h2><i class="fas fa-calendar-alt"></i> Schedule & Availability Viewer</h2>
                <p class="lead">View your booked appointments and check the available hours for a counselor.</p>
                
                <div class="card">
                    <div class="card-body">
                        
                        <div class="form-group row mb-4">
                            <label for="counselor-select" class="col-sm-3 col-form-label font-weight-bold">Select Counselor's Availability:</label>
                            <div class="col-sm-9">
                                <select class="form-control" id="counselor-select" onchange="window.location.href='view_schedule.php?counselor_id=' + this.value + '&month=<?php echo $month; ?>&year=<?php echo $year; ?>'">
                                    <?php if (empty($counselors)): ?>
                                        <option>No Counselors Available</option>
                                    <?php else: ?>
                                        <?php foreach ($counselors as $counselor): ?>
                                            <option value="<?php echo $counselor['id']; ?>" <?php echo ($counselor['id'] == $selected_counselor_id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($counselor['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <small class="form-text text-muted">Showing availability for: <strong><?php echo htmlspecialchars($selected_counselor_name); ?></strong></small>
                            </div>
                        </div>
                        <div class="month-nav">
                            <a href="<?php echo $base_url . "&month=" . $prev_month . "&year=" . $prev_year; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-chevron-left"></i> Previous Month
                            </a>
                            <h3 class="mb-0"><?php echo $month_name . " " . $year; ?></h3>
                            <a href="<?php echo $base_url . "&month=" . $next_month . "&year=" . $next_year; ?>" class="btn btn-outline-primary">
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
                                    // Calculate the start day (blank cells for days from previous month)
                                    $day_count = 1;
                                    $current_day = 1;
                                    
                                    // Adjust the first day of week (1-7 where 1 is Monday) to (0-6 where 0 is Monday)
                                    $adj_first_day = $first_day_of_week - 1;
                                    
                                    echo "<tr>";
                                    
                                    // Print blank cells for days before the 1st of the month
                                    for ($i = 0; $i < $adj_first_day; $i++) {
                                        echo "<td></td>";
                                        $day_count++;
                                    }
                                    
                                    // Print days of the month
                                    while ($current_day <= $days_in_month) {
                                        // If we've reached the 8th day, start a new row
                                        if ($day_count > 7) {
                                            echo "</tr><tr>";
                                            $day_count = 1;
                                        }
                                        
                                        // Check if this day is today
                                        $today_class = '';
                                        if ($current_day == date('j') && $month == date('n') && $year == date('Y')) {
                                            $today_class = 'today';
                                        }
                                        
                                        echo "<td class='$today_class'>";
                                        echo "<div class='day-number'>$current_day</div>";

                                        // --- NEW: Display Selected Counselor Availability ---
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
                                        
                                        // Display student's appointments for this day (existing logic)
                                        if (isset($appointments[$current_day])) {
                                            foreach ($appointments[$current_day] as $appointment) {
                                                $status_class = '';
                                                $status_text = '';
                                                
                                                switch ($appointment['status']) {
                                                    case 'pending':
                                                        $status_class = 'bg-warning text-dark';
                                                        $status_text = 'Pending';
                                                        break;
                                                    case 'accepted':
                                                        $status_class = 'bg-success text-white';
                                                        $status_text = 'Confirmed';
                                                        break;
                                                    case 'successful':
                                                        $status_class = 'bg-primary text-white';
                                                        $status_text = 'Successful';
                                                        break;
                                                    case 'declined':
                                                        $status_class = 'bg-danger text-white';
                                                        $status_text = 'Declined';
                                                        break;
                                                    case 'cancelled':
                                                        $status_class = 'bg-secondary text-white';
                                                        $status_text = 'Cancelled';
                                                        break;
                                                }
                                                
                                                echo "<div class='appointment-item $status_class' title='Counselor: " . htmlspecialchars($appointment['counselor_name']) . "'>";
                                                echo formatTime($appointment['appointment_time']) . " - " . $status_text;
                                                echo "</div>";
                                            }
                                        }
                                        
                                        echo "</td>";
                                        
                                        $current_day++;
                                        $day_count++;
                                    }
                                    
                                    // Fill remaining cells with blank days
                                    while ($day_count <= 7) {
                                        echo "<td></td>";
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
                                    <div class="availability-item d-inline-block p-1" style="width: 100px; margin-right: 5px; background-color: #007bff;">Available Time</div>
                                </div>
                                <div class="mr-3 mb-2">
                                    <span class="appointment-dot appointment-pending"></span> Pending Appointment
                                </div>
                                <div class="mr-3 mb-2">
                                    <span class="appointment-dot appointment-accepted"></span> Confirmed Appointment
                                </div>
                                <div class="mr-3 mb-2">
                                    <span class="appointment-dot appointment-successful"></span> Successful Appointment
                                </div>
                                <div class="mr-3 mb-2">
                                    <span class="appointment-dot appointment-cancelled"></span> Cancelled Appointment
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="manage_appointment.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Schedule New Appointment
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary ml-2">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
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
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

// Get previous and next month
$prev_month = $month == 1 ? 12 : $month - 1;
$prev_year = $month == 1 ? $year - 1 : $year;
$next_month = $month == 12 ? 1 : $month + 1;
$next_year = $month == 12 ? $year + 1 : $year;

// Get first day of the month
$first_day = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_week = date('N', $first_day);
$days_in_month = date('t', $first_day);
$month_name = date('F', $first_day);

// Get appointments for the current month
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
        .calendar {
            width: 100%;
            border-collapse: collapse;
        }
        .calendar th, .calendar td {
            border: 1px solid #dee2e6;
            padding: 10px;
            text-align: center;
            height: 100px;
            vertical-align: top;
        }
        .calendar th {
            background-color: #343a40;
            color: white;
            height: auto;
            padding: 10px;
        }
        .day-number {
            font-weight: bold;
            float: right;
            margin-bottom: 5px;
        }
        .calendar .today {
            background-color: rgba(0, 123, 255, 0.1);
        }
        .appointment-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .appointment-pending {
            background-color: #ffc107;
        }
        .appointment-accepted {
            background-color: #28a745;
        }
        .appointment-declined {
            background-color: #dc3545;
        }
        .appointment-cancelled {
            background-color: #6c757d;
        }
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
        .month-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
                <a href="view_schedule.php" class="sidebar-link active"><i class="fas fa-calendar-alt"></i> View Schedule</a>
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
                
                <h2><i class="fas fa-calendar-alt"></i> Your Schedule</h2>
                <p class="lead">View your appointments in calendar format.</p>
                
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
                                        
                                        // Display appointments for this day
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
                                                    case 'declined':
                                                        $status_class = 'bg-danger text-white';
                                                        $status_text = 'Declined';
                                                        break;
                                                    case 'cancelled':
                                                        $status_class = 'bg-secondary text-white';
                                                        $status_text = 'Cancelled';
                                                        break;
                                                }
                                                
                                                echo "<div class='appointment-item $status_class'>";
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
                                    <span class="appointment-dot appointment-pending"></span> Pending
                                </div>
                                <div class="mr-3 mb-2">
                                    <span class="appointment-dot appointment-accepted"></span> Confirmed
                                </div>
                                <div class="mr-3 mb-2">
                                    <span class="appointment-dot appointment-declined"></span> Declined
                                </div>
                                <div class="mr-3 mb-2">
                                    <span class="appointment-dot appointment-cancelled"></span> Cancelled
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

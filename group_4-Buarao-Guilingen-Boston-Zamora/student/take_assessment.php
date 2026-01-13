<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect("../login.php");
}

// Get user details
$user = getUserDetails($_SESSION["user_id"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Assessment - Wellness Hub</title>
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
            background-color: rgba(179, 1, 4, 0.7);
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
            border: none;
            border-radius: 8px;
        }
        .question-card {
            margin-bottom: 1.5rem;
            border-left: 4px solid #b30104;
        }
        .card-header.bg-primary {
            background-color: #b30104 !important;
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

    <!-- Layout -->
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
                <a href="take_assessment.php" class="sidebar-link active"><i class="fas fa-tasks"></i> Take Assessment</a>
                <a href="view_assessment.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> View Assessments</a>
                <a href="manage_appointment.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Manage Appointments</a>
                <a href="update_information.php" class="sidebar-link"><i class="fas fa-user-edit"></i> Update Information</a>
                <a href="view_schedule.php" class="sidebar-link"><i class="fas fa-calendar-alt"></i> View Schedule</a>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-clipboard-check"></i> Student Wellness Assessment</h4>
                    </div>
                    <div class="card-body">
                        <p class="lead">Please answer the following questions honestly to help us assess your wellness and provide support where needed.</p>
                        <hr>

                        <form>
                            <!-- Question 1 -->
                            <div class="card question-card">
                                <div class="card-body">
                                    <h5 class="card-title">1. How often do you feel stressed due to academic workload?</h5>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[1]" value="Always"> <label class="form-check-label">Always</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[1]" value="Often"> <label class="form-check-label">Often</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[1]" value="Sometimes"> <label class="form-check-label">Sometimes</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[1]" value="Rarely"> <label class="form-check-label">Rarely</label></div>
                                </div>
                            </div>

                            <!-- Question 2 -->
                            <div class="card question-card">
                                <div class="card-body">
                                    <h5 class="card-title">2. How many hours of sleep do you usually get each night?</h5>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[2]" value="Less than 4 hours"> <label class="form-check-label">Less than 4 hours</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[2]" value="4–6 hours"> <label class="form-check-label">4–6 hours</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[2]" value="7–8 hours"> <label class="form-check-label">7–8 hours</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[2]" value="More than 8 hours"> <label class="form-check-label">More than 8 hours</label></div>
                                </div>
                            </div>

                            <!-- Question 3 -->
                            <div class="card question-card">
                                <div class="card-body">
                                    <h5 class="card-title">3. How would you rate your current level of physical activity?</h5>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[3]" value="Very active"> <label class="form-check-label">Very active</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[3]" value="Moderately active"> <label class="form-check-label">Moderately active</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[3]" value="Rarely active"> <label class="form-check-label">Rarely active</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[3]" value="Not active at all"> <label class="form-check-label">Not active at all</label></div>
                                </div>
                            </div>

                            <!-- Question 4 -->
                            <div class="card question-card">
                                <div class="card-body">
                                    <h5 class="card-title">4. Do you have someone to talk to when you're feeling anxious or depressed?</h5>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[4]" value="Yes, always"> <label class="form-check-label">Yes, always</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[4]" value="Sometimes"> <label class="form-check-label">Sometimes</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[4]" value="Rarely"> <label class="form-check-label">Rarely</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[4]" value="No, never"> <label class="form-check-label">No, never</label></div>
                                </div>
                            </div>

                            <!-- Question 5 -->
                            <div class="card question-card">
                                <div class="card-body">
                                    <h5 class="card-title">5. How would you describe your overall mental well-being?</h5>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[5]" value="Excellent"> <label class="form-check-label">Excellent</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[5]" value="Good"> <label class="form-check-label">Good</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[5]" value="Fair"> <label class="form-check-label">Fair</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="responses[5]" value="Poor"> <label class="form-check-label">Poor</label></div>
                                </div>
                            </div>

                            <!-- Question 6 -->
                            <div class="card question-card">
                                <div class="card-body">
                                    <h5 class="card-title">6. What do you usually do to cope with stress? (Select all that apply)</h5>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="responses[6][]" value="Exercise"> <label class="form-check-label">Exercise</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="responses[6][]" value="Talk to friends/family"> <label class="form-check-label">Talk to friends/family</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="responses[6][]" value="Watch movies or listen to music"> <label class="form-check-label">Watch movies or listen to music</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="responses[6][]" value="Sleep or rest"> <label class="form-check-label">Sleep or rest</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="responses[6][]" value="Other"> <label class="form-check-label">Other</label></div>
                                </div>
                            </div>

                            <div class="form-group text-center mt-4">
                                <button type="button" class="btn btn-primary btn-lg">Submit</button>
                                <a href="dashboard.php" class="btn btn-secondary btn-lg ml-2">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
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

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
        /* Sidebar */
        .sidebar {
            background-color: #2b2b2b; /* dark gray */
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
            background-color: rgba(179, 1, 4, 0.6); /* red hover */
        }
        .sidebar-link.active {
            color: white;
            background-color: #b30104; /* red active */
        }
        .sidebar-link i {
            margin-right: 10px;
        }

        /* Navbar */
        .navbar-dark.bg-dark {
            background-color: #1e1e1e !important; /* deeper black */
        }

        /* Content */
        .content {
            padding: 20px;
        }

        /* Cards */
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            border-radius: 8px;
        }
        .card-header.bg-primary {
            background-color: #b30104 !important; /* red */
        }
        .card-header.bg-success {
            background-color: #008f39 !important; /* green */
        }
        .card-header.bg-info {
            background-color: #008b8b !important; /* teal */
        }
        .response-card {
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
        }

        /* Buttons */
        .btn-primary {
            background-color: #b30104;
            border: none;
        }
        .btn-primary:hover {
            background-color: #920003;
        }
        .btn-secondary {
            background-color: #444;
            border: none;
        }
        .btn-secondary:hover {
            background-color: #333;
        }

        /* Badges */
        .badge-danger {
            background-color: #b30104;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
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
                            <a class="dropdown-item disabled">
                                <?php echo htmlspecialchars($user["email"]); ?>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar + Content -->
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
                <a href="appointment_management.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="view_assessments.php" class="sidebar-link active"><i class="fas fa-chart-bar"></i> View Assessments</a>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content">
                <h2><i class="fas fa-chart-bar"></i> Assessment Results</h2>
                <p class="lead">View and analyze student assessment responses.</p>
                
                <!-- Example Card -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Completed Assessments</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">No completed assessments found.</p>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

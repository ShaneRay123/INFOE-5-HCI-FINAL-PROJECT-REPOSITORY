<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Redirect based on user role if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('student/dashboard.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .jumbotron {
            background-color: #6c757d;
            color: white;
            border-radius: 0;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #343a40;
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Student Wellness Hub</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="jumbotron">
        <div class="container">
            <h1 class="display-4">Welcome to Student Wellness Hub</h1>
            <p class="lead">Online Assessment and Appointment Scheduling System</p>
        </div>
    </div>

    <div class="container">
        <?php
        $flash = getFlashMessage();
        if ($flash) {
            echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">
                    ' . $flash['message'] . '
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                  </div>';
        }
        ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>For Students</h5>
                    </div>
                    <div class="card-body">
                        <p>Access wellness resources, schedule counseling appointments, and take assessments to help monitor your well-being.</p>
                        <ul>
                            <li>Take wellness assessments</li>
                            <li>Schedule appointments with counselors</li>
                            <li>View and track your progress</li>
                            <li>Access wellness resources</li>
                        </ul>
                        <a href="register.php" class="btn btn-primary">Register Now</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>For Counselors</h5>
                    </div>
                    <div class="card-body">
                        <p>Efficiently manage student assessments, appointments, and track progress through our comprehensive system.</p>
                        <ul>
                            <li>Create and manage assessments</li>
                            <li>View student responses</li>
                            <li>Manage appointment schedules</li>
                            <li>Track student wellness metrics</li>
                        </ul>
                        <a href="login.php" class="btn btn-secondary">Counselor Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white mt-5 py-3">
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> Student Wellness Hub. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

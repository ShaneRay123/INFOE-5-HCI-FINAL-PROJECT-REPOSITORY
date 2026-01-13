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
    <title>Assessment Management - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        /* === Sidebar === */
.sidebar{background-color:#2b2b2b;min-height:calc(100vh - 56px);color:#fff}
.sidebar-link{color:rgba(255,255,255,.8);padding:10px 15px;display:block;text-decoration:none;transition:.3s;border-radius:5px;margin-bottom:5px}
.sidebar-link:hover{color:#fff;background-color:rgba(179,1,4,.6);text-decoration:none}
.sidebar-link.active{color:#fff;background-color:#b30104}
.sidebar-link i{margin-right:10px}

/* === Content === */
.content{padding:20px}

/* === Cards === */
.card{margin-bottom:20px;box-shadow:0 4px 6px rgba(0,0,0,.1);border:none;border-radius:8px}

/* === Card Headers === */
.card-header.bg-primary{background-color:#b30104!important}
.card-header.bg-success{background-color:#008f39!important}
.card-header.bg-info{background-color:#008b8b!important}
.card-header.bg-warning{background-color:#d4a017!important;color:#212529}

/* === Buttons === */
.btn-primary{background-color:#b30104;border:none}
.btn-primary:hover{background-color:#920003}
.btn-success{background-color:#008f39;border:none}
.btn-success:hover{background-color:#006f2d}
.btn-info{background-color:#008b8b;border:none}
.btn-info:hover{background-color:#006666}
.btn-warning{background-color:#d4a017;border:none}
.btn-warning:hover{background-color:#b88c0f}
.btn-danger{background-color:#b30104;border:none}
.btn-danger:hover{background-color:#920003}

/* === Badges === */
.badge-danger{background-color:#b30104}

/* === Navbar === */
.navbar-dark.bg-dark{background-color:#1e1e1e!important}

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
                            <a class="dropdown-item disabled"><?php echo htmlspecialchars($user["email"]); ?></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                <a href="assessment_management.php" class="sidebar-link active"><i class="fas fa-clipboard-list"></i> Assessment Tools</a>
                <a href="appointment_management.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="view_assessments.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> View Assessments</a>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-clipboard-list"></i> Assessment Management</h2>
                    <a href="#" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create New Assessment
                    </a>
                </div>

                <!-- Example Assessments List -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">All Assessments</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Created By</th>
                                        <th>Created On</th>
                                        <th>Assigned</th>
                                        <th>Completed</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Sample Assessment</td>
                                        <td>Admin User</td>
                                        <td>2025-09-10</td>
                                        <td><span class="badge badge-info">10 students</span></td>
                                        <td><span class="badge badge-success">6 completed</span></td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-info"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="#" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</a>
                                            <a href="#" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View Results</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Wellness Survey</td>
                                        <td>Counselor</td>
                                        <td>2025-09-01</td>
                                        <td><span class="badge badge-info">15 students</span></td>
                                        <td><span class="badge badge-success">9 completed</span></td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-info"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="#" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</a>
                                            <a href="#" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View Results</a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
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

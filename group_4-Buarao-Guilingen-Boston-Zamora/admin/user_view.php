<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once 'user_functions.php';

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

// Check if user ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage("User ID not provided.", "danger");
    redirect("user_management.php");
}

$user_id = $_GET['id'];
$view_user = getUserById($user_id);

if (!$view_user) {
    setFlashMessage("User not found.", "danger");
    redirect("user_management.php");
}

// Calculate age if birthday is set
$age = !empty($view_user['birthday']) ? calculateAge($view_user['birthday']) : 'Not specified';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - Wellness Hub</title>
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
            background-color: #dc3545;
        }
        .sidebar-link i {
            margin-right: 10px;
        }
        .content {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 1rem;
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
                    <div class="badge badge-danger">Counselor</div>
                </div>
                <hr>
                <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="user_management.php" class="sidebar-link active"><i class="fas fa-users"></i> User Management</a>
                <a href="assessment_management.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> Assessment Tools</a>
                <a href="appointment_management.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="view_assessments.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> View Assessments</a>
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
                
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="user_management.php">User Management</a></li>
                        <li class="breadcrumb-item active" aria-current="page">View User</li>
                    </ol>
                </nav>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user"></i> User Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <img src="<?php echo !empty($view_user['profile_photo']) && $view_user['profile_photo'] != 'default.jpg' ? '../uploads/' . htmlspecialchars($view_user['profile_photo']) : 'https://via.placeholder.com/150'; ?>" 
                                     alt="Profile Photo" class="profile-image img-thumbnail">
                                     
                                <h4><?php echo htmlspecialchars($view_user['full_name']); ?></h4>
                                
                                <?php if ($view_user['role'] == 'admin'): ?>
                                    <div class="badge badge-danger">Admin/Counselor</div>
                                <?php else: ?>
                                    <div class="badge badge-primary">Student</div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <a href="user_edit.php?id=<?php echo $user_id; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-edit"></i> Edit User
                                    </a>
                                    <?php if ($user_id != $_SESSION['user_id']): ?>
                                        <a href="user_management.php?action=delete&id=<?php echo $user_id; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i> Delete User
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%">Username</th>
                                        <td><?php echo htmlspecialchars($view_user['username']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Full Name</th>
                                        <td><?php echo htmlspecialchars($view_user['full_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email</th>
                                        <td><?php echo htmlspecialchars($view_user['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Student Number</th>
                                        <td><?php echo !empty($view_user["student_number"]) ? htmlspecialchars($view_user["student_number"]) : 'Not specified'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Role</th>
                                        <td><?php echo $view_user['role'] == 'admin' ? 'Admin/Counselor' : 'Student'; ?></td>
                                    </tr>
                                    <?php if ($view_user['role'] == 'student'): ?>
                                        <tr>
                                            <th>Department</th>
                                            <td><?php echo !empty($view_user['department']) ? htmlspecialchars($view_user['department']) : 'Not specified'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Course Year</th>
                                            <td><?php echo !empty($view_user['course_year']) ? htmlspecialchars($view_user['course_year']) : 'Not specified'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Section</th>
                                            <td><?php echo !empty($view_user['section']) ? htmlspecialchars($view_user['section']) : 'Not specified'; ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Birthday</th>
                                        <td><?php echo !empty($view_user['birthday']) ? formatDate($view_user['birthday']) : 'Not specified'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Age</th>
                                        <td><?php echo $age; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Address</th>
                                        <td><?php echo !empty($view_user['address']) ? htmlspecialchars($view_user['address']) : 'Not specified'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Account Created</th>
                                        <td><?php echo formatDate($view_user['created_at']); ?></td>
                                    </tr>
                                </table>
                                
                                <?php if ($view_user['role'] == 'student'): ?>
                                    <div class="mt-4">
                                        <h5>Quick Actions</h5>
                                        <a href="appointment_management.php?student_id=<?php echo $user_id; ?>" class="btn btn-success">
                                            <i class="fas fa-calendar-check"></i> View Appointments
                                        </a>
                                        <a href="view_assessments.php?student_id=<?php echo $user_id; ?>" class="btn btn-info">
                                            <i class="fas fa-clipboard-list"></i> View Assessments
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="user_management.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to User List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

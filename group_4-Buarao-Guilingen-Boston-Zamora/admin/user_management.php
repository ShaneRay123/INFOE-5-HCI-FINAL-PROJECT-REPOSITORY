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

// Handle delete user action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Don't allow deleting own account
    if ($user_id == $_SESSION['user_id']) {
        setFlashMessage("You cannot delete your own account.", "danger");
        redirect("user_management.php");
    }
    
    $delete_query = "DELETE FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage("User deleted successfully.", "success");
    } else {
        setFlashMessage("Error deleting user. There may be related records.", "danger");
    }
    redirect("user_management.php");
}

// Get all users for display
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$query = "SELECT * FROM users WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
}

if (!empty($role_filter)) {
    $query .= " AND role = ?";
}
$query .= " ORDER BY id DESC";

$stmt = mysqli_prepare($conn, $query);

if (!empty($search) && !empty($role_filter)) {
    mysqli_stmt_bind_param($stmt, "ssss", $search_param, $search_param, $search_param, $role_filter);
} elseif (!empty($search)) {
    mysqli_stmt_bind_param($stmt, "sss", $search_param, $search_param, $search_param);
} elseif (!empty($role_filter)) {
    mysqli_stmt_bind_param($stmt, "s", $role_filter);
}

mysqli_stmt_execute($stmt);
$users_result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Wellness Hub</title>
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
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users"></i> User Management</h2>
                    <a href="user_add.php" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> Add New User
                    </a>
                </div>
                
                <!-- Filter and Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="user_management.php" method="get" class="row">
                            <div class="col-md-5 mb-2">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="search" placeholder="Search by username, name or email" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <select class="form-control" name="role">
                                    <option value="">All Roles</option>
                                    <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin/Counselor</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <button type="submit" class="btn btn-primary mr-2">Filter</button>
                                <a href="user_management.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">User List</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Student Number</th>
                                        <th>Department</th>
                                        <th>Year</th>
                                        <th>Section</th>
                                        <th>Role</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($users_result) > 0): ?>
                                        <?php while ($user_row = mysqli_fetch_assoc($users_result)): ?>
                                            <tr>
                                                <td><?php echo $user_row['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user_row['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user_row['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user_row['email']); ?></td>
                                                <td><?php echo !empty($user_row['student_number']) ? htmlspecialchars($user_row['student_number']) : '<em>Not set</em>'; ?></td>
                                                <td><?php echo !empty($user_row['department']) ? htmlspecialchars($user_row['department']) : '<em>Not set</em>'; ?></td>
                                                <td><?php echo !empty($user_row['course_year']) ? htmlspecialchars($user_row['course_year']) : '<em>Not set</em>'; ?></td>
                                                <td><?php echo !empty($user_row['section']) ? htmlspecialchars($user_row['section']) : '<em>Not set</em>'; ?></td>
                                                <td>
                                                    <?php if ($user_row['role'] == 'admin'): ?>
                                                        <span class="badge badge-danger">Admin/Counselor</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-primary">Student</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($user_row['created_at']); ?></td>
                                                <td>
                                                    <a href="user_edit.php?id=<?php echo $user_row['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <?php if ($user_row['id'] != $_SESSION['user_id']): ?>
                                                        <a href="user_management.php?action=delete&id=<?php echo $user_row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="user_view.php?id=<?php echo $user_row['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="11" class="text-center py-4">No users found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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

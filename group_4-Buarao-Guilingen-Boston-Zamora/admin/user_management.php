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

    // Start transaction to ensure all deletions succeed together
    mysqli_begin_transaction($conn);

    try {
        // 1. Delete responses for student's assessments
        $stmt = $conn->prepare("
            DELETE r FROM responses r
            INNER JOIN student_assessments sa ON r.student_assessment_id = sa.id
            WHERE sa.student_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // 2. Delete student assessments
        $stmt = $conn->prepare("DELETE FROM student_assessments WHERE student_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // 3. Delete appointments where user is student or counselor
        $stmt = $conn->prepare("DELETE FROM appointments WHERE student_id = ? OR counselor_id = ?");
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $stmt->close();

        // 4. Finally, delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            setFlashMessage("User deleted successfully along with all related records.", "success");
        } else {
            throw new Exception("Failed to delete user.");
        }
        $stmt->close();

        // Commit transaction
        mysqli_commit($conn);

    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        setFlashMessage("Error deleting user: " . $e->getMessage(), "danger");
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
    /* === General Styles === */
    body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
    .sidebar { background-color: #2b2b2b; min-height: calc(100vh - 56px); color: white; }
    .sidebar-link { color: rgba(255,255,255,0.85); padding: 10px 15px; display:block; text-decoration:none; border-radius:5px; transition:0.25s; }
    .sidebar-link:hover { color:white; background-color: rgba(179,1,4,0.7); transform:translateX(3px); }
    .sidebar-link.active { color:white; background-color:#b30104; }
    .sidebar-link i { margin-right:10px; }
    .navbar-dark.bg-dark { background-color: #1e1e1e !important; }
    .content { padding:20px; animation: fadeIn 0.5s ease-in-out; }
    .card { border:none; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1); transition:0.3s; margin-bottom:20px; }
    .card:hover { transform:translateY(-4px); box-shadow:0 8px 16px rgba(0,0,0,0.15); }
    .card-header.bg-primary { background-color:#b30104 !important; color:white; font-weight:bold; }
    .table thead th { background-color:#f5f5f5; border-bottom:2px solid #b30104; }
    .btn-primary { background-color:#b30104; border:none; }
    .btn-primary:hover { background-color:#8a0103; transform:translateY(-2px); }
    .btn-danger { background-color:#dc3545; border:none; }
    .btn-danger:hover { background-color:#b02a37; transform:translateY(-2px); }
    .btn-success { background-color:#28a745; border:none; }
    .btn-success:hover { background-color:#218838; transform:translateY(-2px); }
    .btn-secondary { background-color:#6c757d; border:none; }
    .btn-secondary:hover { background-color:#5a6268; transform:translateY(-2px); }
    .alert-success { background-color:#e6f4ea; border-left:5px solid #28a745; color:#155724; animation: fadeIn 0.4s; }
    .alert-danger { background-color:#fdeaea; border-left:5px solid #b30104; color:#721c24; animation: fadeIn 0.4s; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }
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
                    echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">'
                        . $flash['message'] .
                        '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
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

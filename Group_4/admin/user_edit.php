<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once 'user_functions.php';

// Check if user is logged in and admin
if (!isLoggedIn()) redirect("../login.php");
if (!isAdmin()) redirect("../index.php");

$user = getUserDetails($_SESSION["user_id"]);

// Get user ID
if (!isset($_GET['id'])) redirect("user_management.php");

$user_id = $_GET['id'];
$edit_user = getUserById($user_id);

if (!$edit_user) redirect("user_management.php");

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $student_number = $_POST['student_number'] ?? '';
    $department = $_POST['department'] ?? '';
    $course_year = $_POST['course_year'] ?? '';
    $section = $_POST['section'] ?? '';
    $birthday = $_POST['birthday'] ?? null;
    $address = $_POST['address'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE users SET full_name=?, email=?, role=?, student_number=?, department=?, course_year=?, section=?, birthday=?, address=?, password=? WHERE id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssssssi", $full_name, $email, $role, $student_number, $department, $course_year, $section, $birthday, $address, $hashed_password, $user_id);
    } else {
        $query = "UPDATE users SET full_name=?, email=?, role=?, student_number=?, department=?, course_year=?, section=?, birthday=?, address=? WHERE id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssssi", $full_name, $email, $role, $student_number, $department, $course_year, $section, $birthday, $address, $user_id);
    }

    if ($stmt->execute()) {
        header("Location: user_management.php");
        exit;
    } else {
        echo "<div class='alert alert-danger text-center'>Error updating user: " . $stmt->error . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit User - Wellness Hub</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
<style>
    body { 
    background-color: #f8f9fa; 
    animation: fadeIn 0.5s ease-in-out; 
}

/* Sidebar */
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
    transition: background-color 0.3s, color 0.3s;
    border-radius: 5px;
    margin-bottom: 5px;
}
.sidebar-link:hover {
    color: white;
    background-color: rgba(179, 1, 4, 0.6);
}
.sidebar-link.active {
    color: white;
    background-color: #b30104;
}
.sidebar-link i {
    margin-right: 10px;
}

/* Navbar */
.navbar-dark.bg-dark {
    background-color: #1e1e1e !important;
}

/* Card */
.card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    opacity: 0;
    transform: translateY(15px);
    animation: fadeSlideUp 0.6s ease forwards;
}
.card-header.bg-info {
    background-color: #b30104 !important;
}

/* Buttons */
.btn-info {
    background-color: #b30104;
    border: none;
    transition: background-color 0.3s ease;
}
.btn-info:hover {
    background-color: #8c0103;
}
.btn-secondary {
    background-color: #444;
    border: none;
    transition: background-color 0.3s ease;
}
.btn-secondary:hover {
    background-color: #333;
}

/* Badge */
.badge-danger {
    background-color: #b30104;
}

/* Alert fade-in */
.alert {
    animation: fadeIn 0.4s ease;
}

/* Content fade */
.content {
    animation: fadeIn 0.5s ease;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}

</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <a class="navbar-brand" href="dashboard.php">Student Wellness Hub - Admin</a>
    <div class="ml-auto dropdown">
        <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" data-toggle="dropdown">
            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user['username']); ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
            <a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid">
<div class="row">
    <div class="col-md-3 col-lg-2 sidebar py-3">
        <div class="user-info text-center mb-3">
            <div class="h5"><?php echo htmlspecialchars($user['full_name']); ?></div>
            <div class="small"><?php echo htmlspecialchars($user['email']); ?></div>
            <div class="badge badge-danger">Counselor</div>
        </div>
        <hr>
        <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="user_management.php" class="sidebar-link active"><i class="fas fa-users"></i> User Management</a>
        <a href="assessment_management.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> Assessment Tools</a>
        <a href="appointment_management.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Appointments</a>
        <a href="view_assessments.php" class="sidebar-link"><i class="fas fa-chart-bar"></i> View Assessments</a>
         <a href="counselor_schedule.php" class="sidebar-link active"><i class="fas fa-calendar-alt"></i> My Schedule & Available Hours</a>
    </div>

    <div class="col-md-9 col-lg-10 p-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="user_management.php">User Management</a></li>
                <li class="breadcrumb-item active">Edit User</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-header bg-info text-white">
                <h5><i class="fas fa-user-edit"></i> Edit User: <?php echo htmlspecialchars($edit_user['username']); ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_user['username']); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select class="form-control" name="role" id="role">
                            <option value="student" <?php if ($edit_user['role']=='student') echo 'selected'; ?>>Student</option>
                            <option value="admin" <?php if ($edit_user['role']=='admin') echo 'selected'; ?>>Admin/Counselor</option>
                        </select>
                    </div>

                    <div id="student_fields" style="<?php echo $edit_user['role']=='student' ? '' : 'display:none;'; ?>">
                        <div class="form-group">
                            <label>Student Number</label>
                            <input type="text" class="form-control" name="student_number" value="<?php echo htmlspecialchars($edit_user['student_number']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Department</label>
                            <select class="form-control" name="department">
                                <option value="">Select Department</option>
                                <option value="College of Arts and Sciences" <?php if($edit_user['department']=='College of Arts and Sciences') echo 'selected'; ?>>College of Arts and Sciences</option>
                                <option value="College of Education" <?php if($edit_user['department']=='College of Education') echo 'selected'; ?>>College of Education</option>
                                <option value="College of Engineering" <?php if($edit_user['department']=='College of Engineering') echo 'selected'; ?>>College of Engineering</option>
                                <option value="College of Nursing" <?php if($edit_user['department']=='College of Nursing') echo 'selected'; ?>>College of Nursing</option>
                                <option value="College of Computer Studies" <?php if($edit_user['department']=='College of Computer Studies') echo 'selected'; ?>>College of Computer Studies</option>
                                <option value="College of Business Administration" <?php if($edit_user['department']=='College of Business Administration') echo 'selected'; ?>>College of Business Administration</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Course Year</label>
                            <select class="form-control" name="course_year">
                                <option value="">Select Year Level</option>
                                <option value="1st Year" <?php if($edit_user['course_year']=='1st Year') echo 'selected'; ?>>1st Year</option>
                                <option value="2nd Year" <?php if($edit_user['course_year']=='2nd Year') echo 'selected'; ?>>2nd Year</option>
                                <option value="3rd Year" <?php if($edit_user['course_year']=='3rd Year') echo 'selected'; ?>>3rd Year</option>
                                <option value="4th Year" <?php if($edit_user['course_year']=='4th Year') echo 'selected'; ?>>4th Year</option>
                                <option value="Graduate Student" <?php if($edit_user['course_year']=='Graduate Student') echo 'selected'; ?>>Graduate Student</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Section</label>
                            <input type="text" class="form-control" name="section" value="<?php echo htmlspecialchars($edit_user['section']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Birthday</label>
                            <input type="date" class="form-control" name="birthday" value="<?php echo htmlspecialchars($edit_user['birthday']); ?>">
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <textarea class="form-control" name="address"><?php echo htmlspecialchars($edit_user['address']); ?></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" class="form-control" name="new_password">
                        <small class="text-muted">Leave blank to keep current password.</small>
                    </div>

                    <button type="submit" class="btn btn-info">Update User</button>
                    <a href="user_management.php" class="btn btn-secondary ml-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
$('#role').on('change', function() {
    if ($(this).val() === 'student') {
        $('#student_fields').show();
    } else {
        $('#student_fields').hide();
    }
});
</script>
</body>
</html>

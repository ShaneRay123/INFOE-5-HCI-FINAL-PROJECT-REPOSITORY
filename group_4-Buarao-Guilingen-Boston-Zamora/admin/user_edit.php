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
$edit_user = getUserById($user_id);

if (!$edit_user) {
    setFlashMessage("User not found.", "danger");
    redirect("user_management.php");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $role = sanitize($_POST['role']);
    $student_number = sanitize($_POST['student_number'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $course_year = sanitize($_POST['course_year'] ?? '');
    $section = sanitize($_POST['section'] ?? '');
    $birthday = !empty($_POST["birthday"]) ? $_POST["birthday"] : null;
    $address = sanitize($_POST['address'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    
    // Validate input
    $errors = validateUserInput([
        'id' => $user_id,
        'full_name' => $full_name,
        'email' => $email,
        'new_password' => $new_password
    ], true);
    
    // If no errors, update user
    if (empty($errors)) {
        if (!empty($new_password)) {
            // Update with new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET full_name = ?, email = ?, role = ?, student_number = ?, department = ?, 
                            course_year = ?, section = ?, birthday = ?, address = ?, password = ? 
                            WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "ssssssssssi", 
                $full_name, $email, $role, $student_number, $department, $course_year, 
                $section, $birthday, $address, $hashed_password, $user_id
            );
        } else {
            // Update without changing password
            $update_query = "UPDATE users SET full_name = ?, email = ?, role = ?, student_number = ?, department = ?, 
                            course_year = ?, section = ?, birthday = ?, address = ? 
                            WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "sssssssssi", 
                $full_name, $email, $role, $student_number, $department, $course_year, 
                $section, $birthday, $address, $user_id
            );
        }
        
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage("User updated successfully.", "success");
            redirect("user_management.php");
        } else {
            setFlashMessage("Error updating user: " . mysqli_error($conn), "danger");
        }
    } else {
        $error_msg = implode("<br>", $errors);
        setFlashMessage($error_msg, "danger");
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
                
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="user_management.php">User Management</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit User</li>
                    </ol>
                </nav>
                
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-user-edit"></i> Edit User: <?php echo htmlspecialchars($edit_user['username']); ?></h5>
                    </div>
                    <div class="card-body">
                        <form action="user_edit.php?id=<?php echo $user_id; ?>" method="post">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_user['username']); ?>" disabled>
                                <small class="form-text text-muted">Username cannot be changed.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="full_name">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="student_number">Student Number</label>
                                <input type="text" class="form-control" id="student_number" name="student_number" value="<?php echo htmlspecialchars($edit_user['student_number'] ?? ''); ?>" placeholder="e.g. 2022-2960-A">
                                <small class="form-text text-muted">Student ID number (for students only)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="role">Role <span class="text-danger">*</span></label>
                                <select class="form-control" id="role" name="role" required>
                                    <option value="student" <?php echo $edit_user['role'] == 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="admin" <?php echo $edit_user['role'] == 'admin' ? 'selected' : ''; ?>>Admin/Counselor</option>
                                </select>
                            </div>
                            
                            <div id="student_fields" <?php echo $edit_user['role'] != 'student' ? 'style="display: none;"' : ''; ?>>
                                <div class="form-group">
                                    <label for="department">Department</label>
                                    <select class="form-control" id="department" name="department">
                                        <option value="" <?php echo empty($edit_user['department']) ? 'selected' : ''; ?>>Select Department</option>
                                        <option value="College of Arts and Sciences" <?php echo ($edit_user['department'] == 'College of Arts and Sciences') ? 'selected' : ''; ?>>College of Arts and Sciences</option>
                                        <option value="College of Education" <?php echo ($edit_user['department'] == 'College of Education') ? 'selected' : ''; ?>>College of Education</option>
                                        <option value="College of Engineering" <?php echo ($edit_user['department'] == 'College of Engineering') ? 'selected' : ''; ?>>College of Engineering</option>
                                        <option value="College of Nursing" <?php echo ($edit_user['department'] == 'College of Nursing') ? 'selected' : ''; ?>>College of Nursing</option>
                                        <option value="College of Computer Studies" <?php echo ($edit_user['department'] == 'College of Computer Studies') ? 'selected' : ''; ?>>College of Computer Studies</option>
                                        <option value="College of Business Administration" <?php echo ($edit_user['department'] == 'College of Business Administration') ? 'selected' : ''; ?>>College of Business Administration</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="course_year">Course Year</label>
                                    <select class="form-control" id="course_year" name="course_year">
                                        <option value="" <?php echo empty($edit_user['course_year']) ? 'selected' : ''; ?>>Select Year Level</option>
                                        <option value="1st Year" <?php echo ($edit_user['course_year'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                        <option value="2nd Year" <?php echo ($edit_user['course_year'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                        <option value="3rd Year" <?php echo ($edit_user['course_year'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                        <option value="4th Year" <?php echo ($edit_user['course_year'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                        <option value="5th Year" <?php echo ($edit_user['course_year'] == '5th Year') ? 'selected' : ''; ?>>5th Year</option>
                                        <option value="Graduate Student" <?php echo ($edit_user['course_year'] == 'Graduate Student') ? 'selected' : ''; ?>>Graduate Student</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="section">Section</label>
                                    <input type="text" class="form-control" id="section" name="section" value="<?php echo htmlspecialchars($edit_user['section']); ?>">
                                    <small class="form-text text-muted">Leave blank if not applicable.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="birthday">Birthday</label>
                                    <input type="date" class="form-control" id="birthday" name="birthday" value="<?php echo htmlspecialchars($edit_user['birthday']); ?>">
                                </div>
                                up">
                            <div class="form-group">
                                <label for="new_password">New Password</label> htmlspecialchars($edit_user['address']); ?></textarea>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <small class="form-text text-muted">Leave blank to keep current password.</small>
                            </div>
                            
                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-info">Update User</button>_password">
                                <a href="user_management.php" class="btn btn-secondary ml-2">Cancel</a>mall class="form-text text-muted">Leave blank to keep current password.</small>
                            </div>iv>
                        </form>  
                    </div>      <div class="form-group mt-4">
                </div>              <button type="submit" class="btn btn-info">Update User</button>
            </div>                  <a href="user_management.php" class="btn btn-secondary ml-2">Cancel</a>
        </div>                  </div>
    </div>                        </form>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>v>
    <script>
        // Toggle student fields based on role selection
        $(document).ready(function() {query-3.5.1.slim.min.js"></script>
            $('#role').change(function() {er.js@1.16.1/dist/umd/popper.min.js"></script>
                if ($(this).val() === 'student') {com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
                    $('#student_fields').show();
                } else {election
                    $('#student_fields').hide();t).ready(function() {
                }#role').change(function() {
            });     if ($(this).val() === 'student') {
        });       $('#student_fields').show();
    </script>         } else {
</body>             $('#student_fields').hide();
</html>                }

            });
        });
    </script>
</body>
</html>

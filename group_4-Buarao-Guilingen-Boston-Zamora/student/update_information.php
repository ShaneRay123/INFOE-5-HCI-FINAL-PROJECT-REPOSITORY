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

// Define variables and initialize with user's current data
$full_name = $user['full_name'];
$email = $user['email'];
$student_number = isset($user['student_number']) ? $user['student_number'] : '';
$department = isset($user['department']) ? $user['department'] : '';
$course_year = isset($user['course_year']) ? $user['course_year'] : '';
$section = isset($user['section']) ? $user['section'] : '';
$birthday = isset($user['birthday']) ? $user['birthday'] : '';
$address = isset($user['address']) ? $user['address'] : '';
$password = "";
$new_password = "";
$confirm_password = "";

// Define error variables
$full_name_err = $email_err = $student_number_err = $department_err = $course_year_err = $section_err = $password_err = $new_password_err = $confirm_password_err = $photo_err = "";

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate full name
    if (empty(trim($_POST["full_name"]))) {
        $full_name_err = "Please enter your full name.";
    } else {
        $full_name = trim($_POST["full_name"]);
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Invalid email format.";
    } else {
        // Check if email is already taken by another user
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $param_email, $param_id);
        $param_email = trim($_POST["email"]);
        $param_id = $_SESSION["user_id"];
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $email_err = "This email is already taken.";
        } else {
            $email = trim($_POST["email"]);
        }
    }
    
    // Validate student number
    if (empty(trim($_POST["student_number"]))) {
        $student_number_err = "Please enter your student number.";
    } else {
        $student_number = trim($_POST["student_number"]);
    }
    
    // Validate department, course year, section, birthday, and address
    $department = trim($_POST["department"]);
    $course_year = trim($_POST["course_year"]);
    $section = trim($_POST["section"]);
    $birthday = !empty($_POST["birthday"]) ? $_POST["birthday"] : null;
    $address = trim($_POST["address"]);

    // Check if password is provided (only validate if user wants to change password)
    if (!empty(trim($_POST["password"]))) {
        $password = trim($_POST["password"]);
        
        // Verify current password
        if (!password_verify($password, $user['password'])) {
            $password_err = "Current password is incorrect.";
        } else {
            // Validate new password
            if (empty(trim($_POST["new_password"]))) {
                $new_password_err = "Please enter a new password.";
            } elseif (strlen(trim($_POST["new_password"])) < 6) {
                $new_password_err = "Password must have at least 6 characters.";
            } else {
                $new_password = trim($_POST["new_password"]);
            }
            
            // Validate confirm password
            if (empty(trim($_POST["confirm_password"]))) {
                $confirm_password_err = "Please confirm your password.";
            } else {
                $confirm_password = trim($_POST["confirm_password"]);
                if (empty($new_password_err) && ($new_password != $confirm_password)) {
                    $confirm_password_err = "Passwords did not match.";
                }
            }
        }
    }
    
    // Handle profile photo upload
    $profile_photo = $user['profile_photo']; // Default to current photo
    $photo_err = "";

    if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg']; // Restrict to JPG files only
        $filename = $_FILES['profile_photo']['name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Check extension
        if(in_array($file_ext, $allowed)) {
            $new_filename = uniqid('photo_') . '.' . $file_ext;
            $upload_dir = '../uploads/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $new_filename;
            
            // Upload file
            if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                // If successful, update profile_photo variable
                $profile_photo = $new_filename;
            } else {
                $photo_err = "Failed to upload image.";
            }
        } else {
            $photo_err = "Only JPG files are allowed.";
        }
    }
    
    // Check for errors before updating the database
    if (empty($full_name_err) && empty($email_err) && empty($password_err) && empty($new_password_err) && empty($confirm_password_err) && empty($photo_err)) {
        
        // If password is changed
        if (!empty($new_password)) {
            $sql = "UPDATE users SET full_name = ?, email = ?, student_number = ?, department = ?, course_year = ?, section = ?, profile_photo = ?, birthday = ?, address = ?, password = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssssssi", $param_full_name, $param_email, $param_student_number, $param_department, $param_course_year, $param_section, $param_profile_photo, $param_birthday, $param_address, $param_password, $param_id);
            $param_full_name = $full_name;
            $param_email = $email;
            $param_student_number = $student_number;
            $param_department = $department;
            $param_course_year = $course_year;
            $param_section = $section;
            $param_profile_photo = $profile_photo;
            $param_birthday = $birthday;
            $param_address = $address;
            $param_password = password_hash($new_password, PASSWORD_DEFAULT);
            $param_id = $_SESSION["user_id"];
        } else {
            // Only update profile info without changing password
            $sql = "UPDATE users SET full_name = ?, email = ?, student_number = ?, department = ?, course_year = ?, section = ?, profile_photo = ?, birthday = ?, address = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssssi", $param_full_name, $param_email, $param_student_number, $param_department, $param_course_year, $param_section, $param_profile_photo, $param_birthday, $param_address, $param_id);
            $param_full_name = $full_name;
            $param_email = $email;
            $param_student_number = $student_number;
            $param_department = $department;
            $param_course_year = $course_year;
            $param_section = $section;
            $param_profile_photo = $profile_photo;
            $param_birthday = $birthday;
            $param_address = $address;
            $param_id = $_SESSION["user_id"];
        }
        
        // Execute the statement
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage("Your profile has been updated successfully!", "success");
            redirect("view_profile.php");
        } else {
            setFlashMessage("Oops! Something went wrong. Please try again later.", "danger");
        }
        
        // Close statement
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Information - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
    /* Global */
body {
    background-color: #f8f9fa;
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
    transition: 0.3s;
    border-radius: 5px;
    margin-bottom: 5px;
}

.sidebar-link:hover {
    color: white;
    background-color: rgba(179, 1, 4, 0.6);
    text-decoration: none;
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

/* Card Styling */
.card {
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: none;
    border-radius: 8px;
    animation: fadeUp 0.5s ease-out;
}

.card-header {
    background-color: #b30104 !important;
    color: white;
    font-weight: bold;
}

/* Buttons */
.btn-primary {
    background-color: #b30104;
    border: none;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.btn-primary:hover {
    background-color: #8a0103;
    transform: scale(1.05);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

.btn-outline-primary {
    color: #b30104;
    border-color: #b30104;
    transition: 0.25s;
}

.btn-outline-primary:hover {
    background-color: #b30104;
    color: white;
    transform: scale(1.05);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

.btn-danger {
    background-color: #b30104;
    border: none;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.btn-danger:hover {
    background-color: #8a0103;
    transform: scale(1.05);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

/* Alerts */
.alert-success {
    background-color: #e6f4ea;
    border-left: 5px solid #28a745;
    color: #155724;
    animation: fadeIn 0.4s ease-out;
}

.alert-danger {
    background-color: #fdeaea;
    border-left: 5px solid #b30104;
    color: #721c24;
    animation: fadeIn 0.4s ease-out;
}

/* Table Header */
.table thead th {
    background-color: #f5f5f5;
    border-bottom: 2px solid #b30104;
}

/* Modal header */
.modal-header {
    background-color: #b30104;
    color: white;
}

/* Misc */
.content {
    padding: 20px;
    animation: fadeIn 0.4s ease-out;
}

/* Profile image hover animation */
.profile-photo-container img {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.profile-photo-container img:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
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
                <a href="update_information.php" class="sidebar-link active"><i class="fas fa-user-edit"></i> Update Information</a>
                <a href="view_schedule.php" class="sidebar-link"><i class="fas fa-calendar-alt"></i> View Schedule</a>
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
                
                <h2><i class="fas fa-user-edit"></i> Update Your Information</h2>
                <p class="lead">Use this form to update your profile information and change your password.</p>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                            <div class="row mb-4">
                                <div class="col-md-4 text-center">
                                    <div class="profile-photo-container mb-2">
                                        <img id="profile-preview" src="<?php echo !empty($user['profile_photo']) && $user['profile_photo'] != 'default.jpg' ? '../uploads/' . htmlspecialchars($user['profile_photo']) : 'https://via.placeholder.com/150'; ?>" alt="Profile Photo" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                                    </div>
                                    <div class="form-group">
                                        <label for="profile_photo" class="btn btn-outline-primary btn-sm">Choose Photo</label>
                                        <input type="file" name="profile_photo" id="profile_photo" class="d-none" accept=".jpg,.jpeg" onchange="previewImage(this)">
                                        <div id="file-name-display" class="small text-muted mt-1"></div>
                                        <?php if(!empty($photo_err)): ?>
                                            <div class="text-danger small"><?php echo $photo_err; ?></div>
                                        <?php endif; ?>
                                        <div class="small text-muted mt-1">Only JPG files are allowed</div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user["username"]); ?>" disabled>
                                        <small class="form-text text-muted">Username cannot be changed.</small>
                                    </div>
                                    <div class="form-group">
                                        <label>Full Name</label>
                                        <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($full_name); ?>">
                                        <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                                    </div>
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>">
                                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Student Number</label>
                                        <input type="text" name="student_number" class="form-control" value="<?php echo htmlspecialchars($student_number); ?>" placeholder="e.g. 2022-2960-A">
                                        <small class="form-text text-muted">Your student ID number (e.g., 2022-2960-A)</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Department</label>
                                        <select name="department" class="form-control">
                                            <option value="" <?php echo empty($department) ? 'selected' : ''; ?>>Select Department</option>
                                            <option value="College of Computer Studies" <?php echo ($department == 'College of Computer Studies') ? 'selected' : ''; ?>>College of Computer Studies</option>
                                            <option value="College of Business Administration" <?php echo ($department == 'College of Business Administration') ? 'selected' : ''; ?>>College of Business Administration</option>
                                            <option value="College of Engineering" <?php echo ($department == 'College of Engineering') ? 'selected' : ''; ?>>College of Engineering</option>
                                            <option value="College of Arts and Sciences" <?php echo ($department == 'College of Arts and Sciences') ? 'selected' : ''; ?>>College of Arts and Sciences</option>
                                            <option value="College of Education" <?php echo ($department == 'College of Education') ? 'selected' : ''; ?>>College of Education</option>
                                            <option value="College of Nursing" <?php echo ($department == 'College of Nursing') ? 'selected' : ''; ?>>College of Nursing</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Course Year</label>
                                        <select name="course_year" class="form-control">
                                            <option value="" <?php echo empty($course_year) ? 'selected' : ''; ?>>Select Year Level</option>
                                            <option value="1st Year" <?php echo ($course_year == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                            <option value="2nd Year" <?php echo ($course_year == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                            <option value="3rd Year" <?php echo ($course_year == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                            <option value="4th Year" <?php echo ($course_year == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                            <option value="Graduate Student" <?php echo ($course_year == 'Graduate Student') ? 'selected' : ''; ?>>Graduate Student</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Section field below Course Year -->
                                    <div class="form-group">
                                        <label>Section</label>
                                        <input type="text" name="section" class="form-control" value="<?php echo htmlspecialchars($section); ?>" placeholder="e.g. BSIFO 3-A">
                                        <small class="form-text text-muted">Enter your class section (e.g., BSIFO 3-A)</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Birthday</label>
                                        <input type="date" name="birthday" class="form-control" value="<?php echo htmlspecialchars($birthday); ?>">
                                        <small class="form-text text-muted">Your date of birth</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Address</label>
                                        <textarea name="address" class="form-control" rows="3" placeholder="Enter your complete address"><?php echo htmlspecialchars($address); ?></textarea>
                                        <small class="form-text text-muted">Your current residential address</small>
                                    </div>
                                    
                                    <hr>
                                    <h5 class="mb-3">Change Password (Optional)</h5>
                                    
                                    <div class="form-group">
                                        <label>Current Password</label>
                                        <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                                        <small class="form-text text-muted">Leave blank if you don't want to change your password.</small>
                                    </div>
                                    <div class="form-group">
                                        <label>New Password</label>
                                        <input type="password" name="new_password" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>">
                                        <span class="invalid-feedback"><?php echo $new_password_err; ?></span>
                                    </div>
                                    <div class="form-group">
                                        <label>Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                                        <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            
            reader.onload = function(e) {
                document.getElementById('profile-preview').src = e.target.result;
            }
            
            reader.readAsDataURL(input.files[0]);
            document.getElementById('file-name-display').textContent = 'Selected: ' + input.files[0].name;
        }
    }
    </script>
</body>
</html>
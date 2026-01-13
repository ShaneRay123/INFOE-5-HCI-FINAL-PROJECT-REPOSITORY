<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('student/dashboard.php');
    }
}

// Define variables and initialize with empty values
$username = $password = $confirm_password = $full_name = $email = $student_number = $department = $course_year = $section = $birthday = $address = "";
$username_err = $password_err = $confirm_password_err = $full_name_err = $email_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                /* store result */
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
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
        $email = trim($_POST["email"]);
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Get optional fields
    $student_number = sanitize($_POST["student_number"] ?? "");
    $department = sanitize($_POST["department"] ?? "");
    $course_year = sanitize($_POST["course_year"] ?? "");
    $section = sanitize($_POST["section"] ?? "");
    $birthday = !empty($_POST["birthday"]) ? $_POST["birthday"] : null;
    $address = sanitize($_POST["address"] ?? "");
    
    // Check input errors before inserting in database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($full_name_err) && empty($email_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (username, password, full_name, email, student_number, department, course_year, section, birthday, address, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'student')";
         
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssssssssss", $param_username, $param_password, $param_full_name, $param_email, $param_student_number, $param_department, $param_course_year, $param_section, $param_birthday, $param_address);
            
            // Set parameters
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_full_name = $full_name;
            $param_email = $email;
            $param_student_number = $student_number;
            $param_department = $department;
            $param_course_year = $course_year;
            $param_section = $section;
            $param_birthday = $birthday;
            $param_address = $address;
            
            // Execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Redirect to login page
                setFlashMessage("Registration successful! You can now log in.", "success");
                redirect("login.php");
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Student Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
                    <li class="nav-item active">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="register-container">
            <h2 class="text-center mb-4">Register</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                    <span class="invalid-feedback"><?php echo $username_err; ?></span>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $full_name; ?>">
                    <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                    <span class="invalid-feedback"><?php echo $email_err; ?></span>
                </div>
                
                <div class="form-group">
                    <label>Student Number (Optional)</label>
                    <input type="text" name="student_number" class="form-control" placeholder="e.g. 2022-2960-A" value="<?php echo $student_number; ?>">
                    <small class="form-text text-muted">Enter your student ID number (e.g., 2022-2960-A)</small>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                    <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                </div>
                <div class="form-group">
                    <label>Department (Optional)</label>
                    <select name="department" class="form-control">
                        <option value="">Select Department</option>
                        <option value="College of Computer Studies">College of Computer Studies</option>
                        <option value="College of Business Administration">College of Business Administration</option>
                        <option value="College of Engineering">College of Engineering</option>
                        <option value="College of Arts and Sciences">College of Arts and Sciences</option>
                        <option value="College of Education">College of Education</option>
                        <option value="College of Nursing">College of Nursing</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year Level (Optional)</label>
                    <select name="course_year" class="form-control">
                        <option value="">Select Year Level</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                        <option value="5th Year">5th Year</option>
                        <option value="Graduate Student">Graduate Student</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="section">Section (Optional)</label>
                    <input type="text" name="section" class="form-control" placeholder="e.g. BSIFO 3-A">
                    <small class="form-text text-muted">Enter your class section (e.g., BSIFO 3-A)</small>
                </div>
                <div class="form-group">
                    <label for="birthday">Birthday (Optional)</label>
                    <input type="date" name="birthday" class="form-control">
                </div>
                <div class="form-group">
                    <label for="address">Address (Optional)</label>
                    <textarea name="address" class="form-control" rows="3" placeholder="Enter your complete address"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Register</button>
                    <button type="reset" class="btn btn-secondary btn-block">Reset</button>
                </div>
                <p class="text-center">Already have an account? <a href="login.php">Login here</a>.</p>
            </form>
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

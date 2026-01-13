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
$username = $password = $confirm_password = $full_name = $email = "";
$username_err = $password_err = $confirm_password_err = $full_name_err = $email_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            $param_username = trim($_POST["username"]);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            }
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
    
    // Insert to database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($full_name_err) && empty($email_err)) {
        $sql = "INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'student')";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssss", $param_username, $param_password, $param_full_name, $param_email);
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_full_name = $full_name;
            $param_email = $email;
            if (mysqli_stmt_execute($stmt)) {
                setFlashMessage("Registration successful! You can now log in.", "success");
                redirect("login.php");
            }
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
        background-image: url('ASAT.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed;
        overflow-x: hidden;
    }
    .overlay {
        background: rgba(0, 0, 0, 0.6);
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    .register-container {
        position: relative;
        z-index: 1;
        max-width: 500px;
        margin: 60px auto;
        padding: 30px 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(255, 4, 4, 0.75);
        text-align: center;
        opacity: 0;
        transform: translateY(25px);
        animation: fadeSlideIn 0.6s ease-out forwards;
    }
    #logo {
        max-width: 100px;
        height: auto;
        margin-bottom: 15px;
        opacity: 0;
        transform: scale(0.9);
        animation: logoPop 0.5s ease-out 0.3s forwards;
    }
    .btn {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }

    /* Keyframes */
    @keyframes fadeSlideIn {
        from {opacity: 0; transform: translateY(25px);}
        to {opacity: 1; transform: translateY(0);}
    }
    @keyframes logoPop {
        from {opacity: 0; transform: scale(0.9);}
        to {opacity: 1; transform: scale(1);}
    }
</style>
</head>
<body>
<div class="overlay"></div>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">Student Wellness Hub</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                <li class="nav-item active"><a class="nav-link" href="register.php">Register</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="register-container">
        <img src="ua1 (1).png" alt="Logo" id="logo">
        <h2 class="mb-4">Register</h2>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group text-left">
                <label>Username</label>
                <input type="text" name="username" class="form-control">
            </div>
            <div class="form-group text-left">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control">
            </div>
            <div class="form-group text-left">
                <label>Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group text-left">
                <label>Password</label>
                <input type="password" name="password" class="form-control">
            </div>
            <div class="form-group text-left">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control">
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

<script>
    // Quick fade-in for the entire page
    document.body.style.opacity = 0;
    window.addEventListener("load", () => {
        document.body.style.transition = "opacity 0.4s ease-in";
        document.body.style.opacity = 1;
    });
</script>
</body>
</html>

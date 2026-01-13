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

$full_name = $email = $password = $confirm_password = $admin_code = "";
$full_name_err = $email_err = $password_err = $confirm_password_err = $admin_code_err = "";

$SECURE_ADMIN_CODE = "SWHub2025"; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate full name
    if (empty(trim($_POST["full_name"]))) {
        $full_name_err = "Please enter full name.";
    } else {
        $full_name = trim($_POST["full_name"]);
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Invalid email format.";
    } else {
        $email = trim($_POST["email"]);
        $check_email = "SELECT id FROM users WHERE email = ?";
        if ($stmt = mysqli_prepare($conn, $check_email)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $email_err = "Email is already registered.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 8) {
        $password_err = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $_POST["password"]) || !preg_match('/[0-9]/', $_POST["password"])) {
        $password_err = "Password must include at least one uppercase letter and one number.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if ($password != $confirm_password) {
            $confirm_password_err = "Passwords do not match.";
        }
    }

    // Validate admin code
    if (empty(trim($_POST["admin_code"]))) {
        $admin_code_err = "Please enter the admin access code.";
    } elseif (trim($_POST["admin_code"]) !== $SECURE_ADMIN_CODE) {
        $admin_code_err = "Invalid admin access code.";
    } else {
        $admin_code = trim($_POST["admin_code"]);
    }

    // Insert to DB
    if (empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($admin_code_err) && empty($full_name_err)) {
        $sql = "INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'admin')";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            $username = explode('@', $email)[0]; // auto-create username from email
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            mysqli_stmt_bind_param($stmt, "ssss", $username, $hashed_password, $full_name, $email);
            if (mysqli_stmt_execute($stmt)) {
                setFlashMessage("✅ Counselor account created successfully!", "success");
                redirect("login.php");
            } else {
                setFlashMessage("Something went wrong, please try again.", "danger");
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
<title>Counselor Registration - Student Wellness Hub</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
body {
    background-image: url('ASAT.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
}
    
.overlay {
    background: rgba(0, 0, 0, 0.6);
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
}
.register-container {
    position: relative;
    z-index: 1;
    max-width: 500px;
    margin: 70px auto;
    padding: 30px;
    background: white;
    border-radius: 10px;
    /* Changed box-shadow color */
    box-shadow: 0 4px 15px rgba(179, 1, 4, 0.8);
    /* Initial state for animation */
    opacity: 0;
    transform: translateY(25px);
    animation: fadeSlideIn 0.8s ease-out forwards;
}

/* Custom button and text color for the new red shade */
.btn-custom-red {
    background-color: #b30104;
    border-color: #b30104;
    color: white;
}
.btn-custom-red:hover {
    background-color: #8c0103;
    border-color: #8c0103;
    color: white;
}
.text-custom-red {
    color: #b30104 !important;
}

/* Added Button Animation */
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

/* Applied logoPop to the navbar brand */
.navbar-brand {
    opacity: 0; /* Initial state for animation */
    animation: logoPop 0.5s ease-out 0.2s forwards; /* 0.2s delay */
}
</style>
</head>
<body>
<div class="overlay"></div>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">Student Wellness Hub</a>
        <div class="ml-auto">
            <a href="login.php" class="btn btn-outline-light btn-sm">Login</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="register-container">
        <h3 class="mb-3 text-custom-red">Counselor Registration</h3>
        <form method="POST">
            <div class="form-group text-left">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($full_name) ?>">
                <small class="text-danger"><?= $full_name_err ?></small>
            </div>

            <div class="form-group text-left">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>">
                <small class="text-danger"><?= $email_err ?></small>
            </div>

            <div class="form-group text-left">
                <label>Password</label>
                <input type="password" name="password" class="form-control">
                <small class="text-danger"><?= $password_err ?></small>
            </div>

            <div class="form-group text-left">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control">
                <small class="text-danger"><?= $confirm_password_err ?></small>
            </div>

            <div class="form-group text-left">
                <label>Counselor Access Code 🔒</label>
                <input type="password" name="admin_code" class="form-control">
                <small class="text-danger"><?= $admin_code_err ?></small>
            </div>

            <button type="submit" class="btn btn-custom-red btn-block">Register Counselor</button>
            <p class="mt-3 text-center">Already a Counselor? <a class="text-custom-red" href="login.php">Login here</a></p>
        </form>
    </div>
</div>
</body>
</html>
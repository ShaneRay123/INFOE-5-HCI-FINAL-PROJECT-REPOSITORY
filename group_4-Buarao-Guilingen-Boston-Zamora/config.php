<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'syu');

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Check connection
if (!$conn) {
    die("ERROR: Could not connect to MySQL. " . mysqli_connect_error());
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if (mysqli_query($conn, $sql)) {
    // Select the database
    mysqli_select_db($conn, DB_NAME);
    
    // Create users table if not exists
    $users_table = "CREATE TABLE IF NOT EXISTS users (
        id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        student_number VARCHAR(50),
        department VARCHAR(100),
        course_year VARCHAR(50),
        section VARCHAR(50),
        profile_photo VARCHAR(255) DEFAULT 'default.jpg',
        birthday DATE,
        address TEXT,
        role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    mysqli_query($conn, $users_table);
    
    // Check if the section column exists, if not add it
    $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'section'");
    if (mysqli_num_rows($check_columns) == 0) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN section VARCHAR(50) AFTER course_year");
    }
    
    // Check if the profile_photo column exists, if not add it
    $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'profile_photo'");
    if (mysqli_num_rows($check_columns) == 0) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT 'default.jpg' AFTER section");
    }
    
    // Check if the birthday column exists, if not add it
    $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'birthday'");
    if (mysqli_num_rows($check_columns) == 0) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN birthday DATE AFTER profile_photo");
    }
    
    // Check if the address column exists, if not add it
    $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'address'");
    if (mysqli_num_rows($check_columns) == 0) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN address TEXT AFTER birthday");
    }
    
    // Check if the student_number column exists, if not add it
    $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'student_number'");
    if (mysqli_num_rows($check_columns) == 0) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN student_number VARCHAR(50) AFTER email");
    }
    
    // Create assessments table
    $assessments_table = "CREATE TABLE IF NOT EXISTS assessments (
        id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        created_by INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )";
    mysqli_query($conn, $assessments_table);
    
    // Create questions table
    $questions_table = "CREATE TABLE IF NOT EXISTS questions (
        id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
        assessment_id INT,
        question_text TEXT NOT NULL,
        question_type ENUM('multiple_choice', 'text', 'scale') NOT NULL,
        options TEXT,
        FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
    )";
    mysqli_query($conn, $questions_table);
    
    // Create student_assessments table
    $student_assessments = "CREATE TABLE IF NOT EXISTS student_assessments (
        id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
        student_id INT,
        assessment_id INT,
        status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
        submitted_at DATETIME,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (assessment_id) REFERENCES assessments(id)
    )";
    mysqli_query($conn, $student_assessments);
    
    // Create appointments table
    $appointments = "CREATE TABLE IF NOT EXISTS appointments (
        id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
        student_id INT,
        counselor_id INT,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        status ENUM('pending', 'accepted', 'declined', 'cancelled') DEFAULT 'pending',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (counselor_id) REFERENCES users(id)
    )";
    mysqli_query($conn, $appointments);
    
    // Create responses table
    $responses = "CREATE TABLE IF NOT EXISTS responses (
        id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
        student_assessment_id INT,
        question_id INT,
        response_text TEXT,
        FOREIGN KEY (student_assessment_id) REFERENCES student_assessments(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES questions(id)
    )";
    mysqli_query($conn, $responses);
    
    // Check if admin user exists, if not create one
    $admin_check = "SELECT id FROM users WHERE email = 'admin@gmail.com' OR username = 'admin'";
    $admin_result = mysqli_query($conn, $admin_check);
    
    if (mysqli_num_rows($admin_result) == 0) {
        // Create default admin account
        $admin_username = "admin";
        $admin_password = password_hash("admin123", PASSWORD_DEFAULT);
        $admin_fullname = "System Administrator";
        $admin_email = "admin@gmail.com";
        $admin_role = "admin";
        
        $admin_insert = "INSERT INTO users (username, password, full_name, email, role) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $admin_insert);
        mysqli_stmt_bind_param($stmt, "sssss", $admin_username, $admin_password, $admin_fullname, $admin_email, $admin_role);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
} else {
    echo "Error creating database: " . mysqli_error($conn);
}

// Global connection variable
$GLOBALS['conn'] = $conn;

// Session start
if(!isset($_SESSION)) {
    session_start();
}
?>

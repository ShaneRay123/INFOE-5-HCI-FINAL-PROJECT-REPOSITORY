<?php
/**
 * Common functions for user management
 */

/**
 * Get user details by ID
 * 
 * @param int $user_id User ID
 * @return array|null User data or null if not found
 */
function getUserById($user_id) {
    global $conn;
    
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

/**
 * Calculate age from birthday
 * 
 * @param string $birthday Birthday in Y-m-d format
 * @return string Age in years
 */
function calculateAge($birthday) {
    if (empty($birthday)) return "Not specified";
    
    $birthDate = new DateTime($birthday);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y . ' years';
}

/**
 * Validate user input
 * 
 * @param array $data User input data
 * @param bool $isEdit Whether this is an edit operation
 * @return array Errors array
 */
function validateUserInput($data, $isEdit = false) {
    global $conn;
    $errors = [];
    
    // Validate username (only for new users)
    if (!$isEdit) {
        if (empty($data['username'])) {
            $errors[] = "Username is required.";
        } else {
            // Check if username exists
            $check_query = "SELECT id FROM users WHERE username = ?";
            $stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($stmt, "s", $data['username']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $errors[] = "Username already exists.";
            }
        }
    }
    
    // Validate full name
    if (empty($data['full_name'])) {
        $errors[] = "Full name is required.";
    }
    
    // Validate email
    if (empty($data['email'])) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email is already taken by another user
        $query = "SELECT id FROM users WHERE email = ?";
        if ($isEdit) {
            $query .= " AND id != ?";
        }
        
        $stmt = mysqli_prepare($conn, $query);
        
        if ($isEdit) {
            mysqli_stmt_bind_param($stmt, "si", $data['email'], $data['id']);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $data['email']);
        }
        
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Email already exists.";
        }
    }
    
    // Validate password (only for new users or if changing password)
    if (!$isEdit || !empty($data['new_password'])) {
        $password = $isEdit ? $data['new_password'] : $data['password'];
        
        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must have at least 6 characters.";
        }
    }
    
    return $errors;
}

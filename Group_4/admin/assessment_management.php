<?php
// === START: Error Reporting (Temporary for Debugging) ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// === END: Error Reporting ===

require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect("../login.php");
}

// Check if user is an admin (Assuming 'admin' covers the 'Counselor' role shown in the UI)
if (!isAdmin()) {
    redirect("../index.php");
}

// Get user information
$user = getUserDetails($_SESSION["user_id"]);

// Define current page for dynamic sidebar highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// --- Helper function for dynamic bind_param (if needed for older PHP, assuming environment supports) ---
// Note: This is a placeholder/fallback and may not be strictly necessary depending on the PHP version
if (!function_exists('ref_values')) {
    function ref_values($arr){
        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            $refs = array();
            foreach($arr as $key => $value)
                $refs[$key] = &$arr[$key];
            return $refs;
        }
        return $arr;
    }
}
// --- END Helper function ---


// Handle assessment actions (add, edit, delete)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Add new assessment
    if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $questions = isset($_POST['questions']) ? $_POST['questions'] : [];
        $question_types = isset($_POST['question_types']) ? $_POST['question_types'] : [];
        // The 'options' array now holds either MC options (imploded) or Section Description
        $options = isset($_POST['options']) ? $_POST['options'] : []; 
        
        // Validate input
        $errors = [];
        
        if (empty($title)) {
            $errors[] = "Assessment title is required.";
        }
        
        if (count($questions) === 0) {
            $errors[] = "At least one question or section header is required.";
        }
        
        // Validate Multiple Choice options and Question Text
        foreach ($question_types as $i => $type) {
            if (empty($questions[$i]) || trim($questions[$i]) == '') {
                 $errors[] = ($type == 'section_header' ? 'Section Header' : 'Question') . " #" . ($i + 1) . " requires text.";
            }

            if ($type == 'multiple_choice' && (empty($options[$i]) || trim($options[$i]) == '')) {
                 $errors[] = "Multiple Choice question #" . ($i + 1) . " requires options.";
            }
        }

        // If no errors, add assessment
        if (empty($errors)) {
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Insert assessment
                $insert_assessment = "INSERT INTO assessments (title, description, created_by) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_assessment);
                mysqli_stmt_bind_param($stmt, "ssi", $title, $description, $_SESSION['user_id']);
                mysqli_stmt_execute($stmt);
                
                $assessment_id = mysqli_insert_id($conn);
                
                // Insert questions
                for ($i = 0; $i < count($questions); $i++) {
                    $question_text = sanitize($questions[$i]);
                    $question_type = sanitize($question_types[$i]);
                    $question_options = null;
                    
                    if ($question_type == 'multiple_choice' && isset($options[$i]) && !empty($options[$i])) {
                        // Explode and trim options, then JSON encode
                        $raw_options = explode("\n", $options[$i]);
                        $clean_options = array_values(array_filter(array_map('trim', $raw_options))); // Clean up empty lines
                        
                        if (!empty($clean_options)) {
                           $question_options = json_encode($clean_options);
                        }
                    } elseif ($question_type == 'section_header' && isset($options[$i])) {
                        // For section headers, the 'options' field stores the description text
                        $question_options = sanitize($options[$i]);
                    }
                    
                    // Note: question_text is also used for section headers
                    $insert_question = "INSERT INTO questions (assessment_id, question_text, question_type, options) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $insert_question);
                    // Use NULL for $question_options if it's not set for DB to accept it
                    mysqli_stmt_bind_param($stmt, "isss", $assessment_id, $question_text, $question_type, $question_options);
                    mysqli_stmt_execute($stmt);
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                // --- START: NEW AUTOMATIC ASSIGNMENT ---
                $assigned_count = 0;
                $students_query = "SELECT id FROM users WHERE role = 'student'";
                $students_result = mysqli_query($conn, $students_query);

                if (mysqli_num_rows($students_result) > 0) {
                    $insert_student_assessment = "INSERT INTO student_assessments (student_id, assessment_id) VALUES (?, ?)";
                    $stmt_insert = mysqli_prepare($conn, $insert_student_assessment);
                    
                    // Also prepare statement for checking existence
                    $check_query = "SELECT id FROM student_assessments WHERE student_id = ? AND assessment_id = ?";
                    $stmt_check = mysqli_prepare($conn, $check_query);
                    
                    while ($student_row = mysqli_fetch_assoc($students_result)) {
                        $student_id = (int)$student_row['id'];
                        
                        // Check for existing assignment
                        mysqli_stmt_bind_param($stmt_check, "ii", $student_id, $assessment_id);
                        mysqli_stmt_execute($stmt_check);
                        $check_result = mysqli_stmt_get_result($stmt_check);
                        
                        if (mysqli_num_rows($check_result) == 0) {
                            // Insert new assignment
                            mysqli_stmt_bind_param($stmt_insert, "ii", $student_id, $assessment_id);
                            mysqli_stmt_execute($stmt_insert);
                            $assigned_count++;
                        }
                    }
                    mysqli_stmt_close($stmt_insert);
                    mysqli_stmt_close($stmt_check);
                }
                // --- END: NEW AUTOMATIC ASSIGNMENT ---

                setFlashMessage("Assessment created and automatically assigned to **$assigned_count** students.", "success");
                redirect("assessment_management.php");
                
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                setFlashMessage("Error creating assessment: " . $e->getMessage(), "danger");
            }
        } else {
            // Retain POST data for user experience
            $error_msg = implode("<br>", $errors);
            setFlashMessage($error_msg, "danger");
        }
    }
    
    // Edit assessment
    if ($action == 'edit' && isset($_GET['id'])) {
        $assessment_id = (int) $_GET['id'];
        
        // Get assessment details for edit form
        $edit_query = "SELECT * FROM assessments WHERE id = ?";
        $stmt = mysqli_prepare($conn, $edit_query);
        mysqli_stmt_bind_param($stmt, "i", $assessment_id);
        mysqli_stmt_execute($stmt);
        $edit_result = mysqli_stmt_get_result($stmt);
        $edit_assessment = mysqli_fetch_assoc($edit_result);
        
        if (!$edit_assessment) {
            setFlashMessage("Assessment not found.", "danger");
            redirect("assessment_management.php");
        }
        
        // Get questions
        $questions_query = "SELECT * FROM questions WHERE assessment_id = ? ORDER BY id ASC"; // Order by ID is important for consistency
        $stmt = mysqli_prepare($conn, $questions_query);
        mysqli_stmt_bind_param($stmt, "i", $assessment_id);
        mysqli_stmt_execute($stmt);
        $questions_result = mysqli_stmt_get_result($stmt);
        $questions = [];
        while ($question = mysqli_fetch_assoc($questions_result)) {
            $questions[] = $question;
        }
        
        // Handle form submission for edit
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            
            // Question handling inputs (names match the 'add' logic/template for reusability)
            $submitted_question_ids = isset($_POST['question_id']) ? $_POST['question_id'] : [];
            $submitted_questions = isset($_POST['questions']) ? $_POST['questions'] : [];
            $submitted_question_types = isset($_POST['question_types']) ? $_POST['question_types'] : [];
            $submitted_options = isset($_POST['options']) ? $_POST['options'] : [];
            
            // Validate input
            $errors = [];
            
            if (empty($title)) {
                $errors[] = "Assessment title is required.";
            }
            
            if (count($submitted_questions) === 0) {
                $errors[] = "At least one question or section header is required.";
            }
            
            // Validate Multiple Choice options for ALL submitted questions
            foreach ($submitted_question_types as $i => $type) {
                // Validate Question Text for all types, including Section Header
                if (empty($submitted_questions[$i]) || trim($submitted_questions[$i]) == '') {
                     $errors[] = ($type == 'section_header' ? 'Section Header' : 'Question') . " #" . ($i + 1) . " requires text.";
                }
                
                if ($type == 'multiple_choice' && (empty($submitted_options[$i]) || trim($submitted_options[$i]) == '')) {
                     // Since keys are reset on POST, we rely on the sequential index $i
                     $errors[] = "Multiple Choice question #" . ($i + 1) . " requires options.";
                }
            }

            // If no errors, update assessment and questions
            if (empty($errors)) {
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // 1. Update assessment title/description
                    $update_query = "UPDATE assessments SET title = ?, description = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($stmt, "ssi", $title, $description, $assessment_id);
                    mysqli_stmt_execute($stmt);

                    // 2. Identify and Delete removed questions
                    $current_q_ids_query = "SELECT id FROM questions WHERE assessment_id = ?";
                    $stmt = mysqli_prepare($conn, $current_q_ids_query);
                    mysqli_stmt_bind_param($stmt, "i", $assessment_id);
                    mysqli_stmt_execute($stmt);
                    $current_q_ids_result = mysqli_stmt_get_result($stmt);
                    $current_q_ids = [];
                    while($row = mysqli_fetch_assoc($current_q_ids_result)) {
                        $current_q_ids[] = (int) $row['id'];
                    }
                    
                    // Filter submitted IDs: only existing questions have IDs > 0
                    $submitted_existing_q_ids = array_filter(array_map('intval', $submitted_question_ids));

                    // IDs to delete: those in the DB but NOT submitted by the form
                    $q_ids_to_delete = array_diff($current_q_ids, $submitted_existing_q_ids);
                    
                    if (!empty($q_ids_to_delete)) {
                        $placeholders = implode(',', array_fill(0, count($q_ids_to_delete), '?'));
                        $delete_q_query = "DELETE FROM questions WHERE id IN ($placeholders) AND assessment_id = ?";
                        $stmt = mysqli_prepare($conn, $delete_q_query);
                        
                        // Bind parameters: 'i' for each ID + one 'i' for assessment_id
                        $bind_types = str_repeat('i', count($q_ids_to_delete)) . 'i';
                        $bind_values = array_merge($q_ids_to_delete, [$assessment_id]);
                        
                        // Use ref_values for dynamic bind_param if needed, otherwise use splat
                         if (version_compare(PHP_VERSION, '5.6.0') >= 0) {
                            call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt, $bind_types), ref_values($bind_values)));
                        } else {
                            call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt, $bind_types), ref_values($bind_values)));
                        }
                        
                        mysqli_stmt_execute($stmt);
                    }

                    // 3. Update or Insert remaining questions
                    for ($i = 0; $i < count($submitted_questions); $i++) {
                        $question_id = (int)$submitted_question_ids[$i]; // 0 for new questions
                        $question_text = sanitize($submitted_questions[$i]);
                        $question_type = sanitize($submitted_question_types[$i]);
                        $question_options = null;
                        
                        $raw_options_text = $submitted_options[$i] ?? ''; // Used for both options and description

                        if ($question_type == 'multiple_choice') {
                             if (!empty($raw_options_text)) {
                                $raw_options = explode("\n", $raw_options_text);
                                $clean_options = array_values(array_filter(array_map('trim', $raw_options)));
                                
                                if (!empty($clean_options)) {
                                   $question_options = json_encode($clean_options);
                                }
                            }
                        } elseif ($question_type == 'section_header') {
                            // For section headers, save the description text directly
                            $question_options = sanitize($raw_options_text);
                        }
                        // For scale, or others, $question_options remains null

                        
                        if ($question_id > 0) {
                            // Update existing question
                            $update_q = "UPDATE questions SET question_text = ?, question_type = ?, options = ? WHERE id = ? AND assessment_id = ?";
                            $stmt = mysqli_prepare($conn, $update_q);
                            mysqli_stmt_bind_param($stmt, "sssii", $question_text, $question_type, $question_options, $question_id, $assessment_id);
                            mysqli_stmt_execute($stmt);
                        } else {
                            // Insert new question
                            $insert_q = "INSERT INTO questions (assessment_id, question_text, question_type, options) VALUES (?, ?, ?, ?)";
                            $stmt = mysqli_prepare($conn, $insert_q);
                            mysqli_stmt_bind_param($stmt, "isss", $assessment_id, $question_text, $question_type, $question_options);
                            mysqli_stmt_execute($stmt);
                        }
                    }
                    
                    // Commit transaction
                    mysqli_commit($conn);
                    
                    // --- START: NEW AUTOMATIC ASSIGNMENT (Keep this) ---
                    $assigned_count = 0;
                    $students_query = "SELECT id FROM users WHERE role = 'student'";
                    $students_result = mysqli_query($conn, $students_query);

                    if (mysqli_num_rows($students_result) > 0) {
                        $insert_student_assessment = "INSERT INTO student_assessments (student_id, assessment_id) VALUES (?, ?)";
                        $stmt_insert = mysqli_prepare($conn, $insert_student_assessment);
                        
                        // Also prepare statement for checking existence
                        $check_query = "SELECT id FROM student_assessments WHERE student_id = ? AND assessment_id = ?";
                        $stmt_check = mysqli_prepare($conn, $check_query);
                        
                        while ($student_row = mysqli_fetch_assoc($students_result)) {
                            $student_id = (int)$student_row['id'];
                            
                            // Check for existing assignment
                            mysqli_stmt_bind_param($stmt_check, "ii", $student_id, $assessment_id);
                            mysqli_stmt_execute($stmt_check);
                            $check_result = mysqli_stmt_get_result($stmt_check);
                            
                            if (mysqli_num_rows($check_result) == 0) {
                                // Insert new assignment
                                mysqli_stmt_bind_param($stmt_insert, "ii", $student_id, $assessment_id);
                                mysqli_stmt_execute($stmt_insert);
                                $assigned_count++;
                            }
                        }
                        mysqli_stmt_close($stmt_insert);
                        mysqli_stmt_close($stmt_check);
                    }
                    // --- END: NEW AUTOMATIC ASSIGNMENT ---

                    setFlashMessage("Assessment and questions updated successfully. Assigned to **$assigned_count** new students.", "success");
                    redirect("assessment_management.php");
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    mysqli_rollback($conn);
                    setFlashMessage("Error updating assessment: " . $e->getMessage(), "danger");
                }
            } else {
                // Retain POST data for user experience
                $error_msg = implode("<br>", $errors);
                setFlashMessage($error_msg, "danger");
                
                // Re-construct $questions array from POST data to repopulate the form
                $questions = [];
                for ($i = 0; $i < count($submitted_questions); $i++) {
                    $options_data = $submitted_options[$i] ?? '';
                    $question_type = $submitted_question_types[$i];
                    $json_options = null;
                    
                    if ($question_type == 'multiple_choice' && !empty($options_data)) {
                        $clean_options = array_values(array_filter(array_map('trim', explode("\n", $options_data))));
                        $json_options = json_encode($clean_options);
                    } elseif ($question_type == 'section_header') {
                         // Retain description text directly
                         $json_options = $options_data; 
                    }
                    
                    $questions[] = [
                        'id' => $submitted_question_ids[$i],
                        'question_text' => $submitted_questions[$i],
                        'question_type' => $question_type,
                        'options' => $json_options
                    ];
                }
                
                // Keep assessment title/description for sticky form
                $edit_assessment['title'] = $title;
                $edit_assessment['description'] = $description;
            }
        }
    }
    
    // Delete assessment
    if ($action == 'delete' && isset($_GET['id'])) {
        $assessment_id = (int) $_GET['id'];

        mysqli_begin_transaction($conn);
        try {
            // Delete from student_assessments first (child table)
            $delete_student_assessments = "DELETE FROM student_assessments WHERE assessment_id = ?";
            $stmt = mysqli_prepare($conn, $delete_student_assessments);
            mysqli_stmt_bind_param($stmt, "i", $assessment_id);
            mysqli_stmt_execute($stmt);

            // Delete from questions (another child table)
            $delete_questions = "DELETE FROM questions WHERE assessment_id = ?";
            $stmt = mysqli_prepare($conn, $delete_questions);
            mysqli_stmt_bind_param($stmt, "i", $assessment_id);
            mysqli_stmt_execute($stmt);

            // Finally delete the main assessment (parent)
            $delete_assessment = "DELETE FROM assessments WHERE id = ?";
            $stmt = mysqli_prepare($conn, $delete_assessment);
            mysqli_stmt_bind_param($stmt, "i", $assessment_id);
            mysqli_stmt_execute($stmt);

            mysqli_commit($conn);
            setFlashMessage("Assessment deleted successfully.", "success");
        } catch (Exception $e) {
            mysqli_rollback($conn);
            setFlashMessage("Error deleting assessment: " . $e->getMessage(), "danger");
        }

        redirect("assessment_management.php");
    }
}

// Get all assessments - MODIFIED to only show assessments created by the logged-in user
$assessments_query = "SELECT a.*, u.full_name as creator_name, 
                     (SELECT COUNT(*) FROM student_assessments WHERE assessment_id = a.id) as assigned_count,
                     (SELECT COUNT(*) FROM student_assessments WHERE assessment_id = a.id AND status = 'completed') as completed_count
                     FROM assessments a
                     JOIN users u ON a.created_by = u.id
                     WHERE a.created_by = " . (int)$_SESSION['user_id'] . " 
                     ORDER BY a.created_at DESC";
$assessments_result = mysqli_query($conn, $assessments_query);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Management - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
/* =========================
    LAYOUT & THEME COLORS
    ========================= */
body {
    background-color: #f8f9fa;
}

/* Navbar (static, no animation) */
.navbar-dark.bg-dark {
    background-color: #1e1e1e !important; /* deeper black */
}

/* Sidebar (static, no animation) */
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
    transition: 0.3s ease;
    border-radius: 4px;
}
.sidebar-link:hover {
    color: white;
    background-color: #b30104; /* red hover */
    text-decoration: none;
}
.sidebar-link.active {
    color: white;
    background-color: #b30104;
}
.sidebar-link i {
    margin-right: 10px;
}

/* =========================
    CONTENT & COMPONENTS
    ========================= */
.content {
    padding: 20px;
}

/* Card (static) */
.card {
    margin-bottom: 20px;
    border: none;
    box-shadow: 0 4px 6px rgba(0,0,0,0.15);
    border-radius: 10px;
}

/* Card header (red theme) */
.card-header {
    /* Changed from bg-primary in template for consistency with theme */
    background-color: #b30104 !important; 
    color: white !important;
}

/* Alert (static) */
.alert {
    margin-top: 10px;
}

/* Question container styling */
.question-container {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    position: relative;
    background: #fff;
    /* Default question border */
    border-left: 5px solid #007bff; 
}

/* MODIFIED Styling for the Section Header type to make it look like a section box */
.question-container.section-header-style {
    border: 1px solid #ced4da; /* A subtle box outline */
    border-left: 5px solid #28a745; /* Strong green accent */
    background-color: #e9ecef; /* Light gray background to separate it */
    padding: 10px 15px;
    margin-top: 25px; /* Extra space above to clearly start a new section */
    box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* Soft shadow for depth */
}

/* Make the section title stand out */
.question-container.section-header-style .question-text-label {
    font-size: 1.1rem; 
    font-weight: bold;
    color: #28a745; 
}

/* Ensure options container is hidden by default for non-MC */
.options-container {
    display: none; 
} 
/* =========================
    ANIMATIONS & EFFECTS
    ========================= */

/* Smooth fade-in for content and cards */
.content, .card, .alert {
    animation: fadeIn 0.5s ease-in-out;
}

/* Slight hover lift for cards */
.card:hover {
    transform: translateY(-4px);
    transition: transform 0.4s ease, box-shadow 0.4s ease;
    box-shadow: 0 6px 10px rgba(0,0,0,0.25);
}

/* Alerts fade in softly */
.alert {
    transition: opacity 0.6s ease;
}

/* Keyframes for fade-in animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
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
            <div class="col-md-3 col-lg-2 sidebar py-3">
                <div class="user-info mb-3 text-center">
                    <div class="h5"><?php echo htmlspecialchars($user["full_name"]); ?></div>
                    <div class="small"><?php echo htmlspecialchars($user["email"]); ?></div>
                    <div class="badge badge-danger">Counselor</div>
                </div>
                <hr>
                <a href="dashboard.php" class="sidebar-link<?php echo ($current_page == 'dashboard.php' ? ' active' : ''); ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="user_management.php" class="sidebar-link<?php echo ($current_page == 'user_management.php' ? ' active' : ''); ?>"><i class="fas fa-users"></i> User Management</a>
                <a href="assessment_management.php" class="sidebar-link<?php echo ($current_page == 'assessment_management.php' ? ' active' : ''); ?>"><i class="fas fa-clipboard-list"></i> Assessment Tools</a>
                <a href="appointment_management.php" class="sidebar-link<?php echo ($current_page == 'appointment_management.php' ? ' active' : ''); ?>"><i class="fas fa-calendar-check"></i> My Schedule & Available Hours</a>
                <a href="view_assessments.php" class="sidebar-link<?php echo ($current_page == 'view_assessments.php' ? ' active' : ''); ?>"><i class="fas fa-chart-bar"></i> View Assessments</a>
            </div>
            
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
                
                <?php if (isset($action) && ($action == 'add' || $action == 'edit')): ?>
                    <div class="card">
                        <div class="card-header text-white"> 
                            <h5 class="mb-0">
                                <?php echo $action == 'add' ? '<i class="fas fa-plus"></i> Create New Assessment' : '<i class="fas fa-edit"></i> Edit Assessment'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo $action == 'add' ? 'assessment_management.php?action=add' : 'assessment_management.php?action=edit&id=' . (isset($assessment_id) ? $assessment_id : ''); ?>">
                                <div class="form-group">
                                    <label for="title">Assessment Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required
                                                value="<?php echo isset($edit_assessment) ? htmlspecialchars($edit_assessment['title']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($edit_assessment) ? htmlspecialchars($edit_assessment['description']) : ''; ?></textarea>
                                </div>
                                
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0">Assessment Sections and Questions <span class="badge badge-light" id="question-count"><?php echo isset($questions) ? count($questions) : 0; ?></span></h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="questions-container">
                                            <?php 
                                            // --- PHP Question Rendering for Edit Mode ---
                                            if (isset($questions) && !empty($questions)) {
                                                foreach ($questions as $q) {
                                                    $q_id = (int)$q['id'];
                                                    $q_text = htmlspecialchars($q['question_text']); // Section Title or Question Text
                                                    $q_type = htmlspecialchars($q['question_type']);
                                                    
                                                    $q_options_text = ''; // This will hold either MC options (imploded) or Section Description
                                                    $is_mc = ($q_type == 'multiple_choice');
                                                    $is_section = ($q_type == 'section_header');
                                                    $container_class = $is_section ? 'section-header-style' : '';

                                                    if ($is_mc && !empty($q['options'])) {
                                                        $options_array = json_decode($q['options'], true);
                                                        if (is_array($options_array)) {
                                                            $q_options_text = htmlspecialchars(implode("\n", $options_array));
                                                        }
                                                    } elseif ($is_section && !empty($q['options'])) {
                                                        // For section_header, options holds the description text
                                                        $q_options_text = htmlspecialchars($q['options']); 
                                                    }
                                            ?>
                                            <div class="question-container <?php echo $container_class; ?>" data-question-id="<?php echo $q_id; ?>">
                                                <button type="button" class="btn btn-sm btn-danger remove-question">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <input type="hidden" name="question_id[]" value="<?php echo $q_id; ?>">
                                                
                                                <div class="form-group question-text-group"> 
                                                    <label class="question-text-label">Question Text</label> 
                                                    <textarea class="form-control question-text" name="questions[]" required rows="2"><?php echo $q_text; ?></textarea>
                                                </div>
                                                
                                                <div class="form-group question-type-group">
                                                    <label>Type</label>
                                                    <select class="form-control question-type" name="question_types[]" required> 
                                                        <option value="text" <?php echo $q_type == 'text' ? 'selected' : ''; ?>>Text Answer</option>
                                                        <option value="multiple_choice" <?php echo $q_type == 'multiple_choice' ? 'selected' : ''; ?>>Multiple Choice</option>
                                                        <option value="scale" <?php echo $q_type == 'scale' ? 'selected' : ''; ?>>Scale (0-3)</option>
                                                        <option value="section_header" <?php echo $q_type == 'section_header' ? 'selected' : ''; ?>>Section Header/Category</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group options-container" style="<?php echo ($is_mc || $is_section) ? '' : 'display: none;'; ?>"> 
                                                    <label class="options-label"><?php echo $is_section ? 'Section Description (optional)' : 'Options (One per line)'; ?></label>
                                                    <textarea class="form-control options-textarea" name="options[]" rows="4" 
                                                        placeholder="<?php echo $is_section ? 'Enter a brief description for this section' : 'Enter each option on a new line'; ?>"
                                                        <?php echo $is_mc ? 'required' : ''; ?>><?php echo $q_options_text; ?></textarea>
                                                    <small class="form-text text-muted options-help-text" style="<?php echo $is_section ? 'display: none;' : ''; ?>">For multiple choice questions, enter each option on a new line</small>
                                                </div>
                                            </div>
                                            <?php 
                                                }
                                            } 
                                            // --- END PHP Question Rendering for Edit Mode ---
                                            ?>
                                        </div>
                                        <button type="button" class="btn btn-secondary btn-block mb-2" id="add-section">
                                            <i class="fas fa-layer-group"></i> Add Section Header (e.g., Test 1)
                                        </button>
                                        <button type="button" class="btn btn-success btn-block" id="add-question">
                                            <i class="fas fa-plus"></i> Add Question
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $action == 'add' ? 'Create Assessment' : 'Update Assessment'; ?>
                                    </button>
                                    <a href="assessment_management.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-clipboard-list"></i> Assessment Management</h2>
                        <a href="assessment_management.php?action=add" class="btn btn-success">
                            <i class="fas fa-plus"></i> Create New Assessment
                        </a>
                    </div>
                    
                    <div class="card">
                           <div class="card-header text-white">
                                <h5 class="mb-0">All Assessments</h5>
                            </div>
                        <div class="card-body p-0">
                            <?php if (mysqli_num_rows($assessments_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Title</th>
                                                <th>Created By</th>
                                                <th>Created On</th>
                                                <th>Assigned</th>
                                                <th>Completed</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($assessment = mysqli_fetch_assoc($assessments_result)): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($assessment['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($assessment['creator_name']); ?></td>
                                                    <td><?php echo formatDate($assessment['created_at']); ?></td>
                                                    <td>
                                                        <span class="badge badge-info"><?php echo $assessment['assigned_count']; ?> students</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-success"><?php echo $assessment['completed_count']; ?> completed</span>
                                                    </td>
                                                    <td>
                                                        <a href="assessment_management.php?action=edit&id=<?php echo $assessment['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="assessment_management.php?action=delete&id=<?php echo $assessment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this assessment? This will also delete all responses associated with it.')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                        <a href="view_assessments.php?assessment_id=<?php echo $assessment['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-eye"></i> View Results
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center">
                                    <p class="text-muted">No assessments found.</p>
                                    <a href="assessment_management.php?action=add" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Create Your First Assessment
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="question-template" style="display: none;">
        <div class="question-container">
            <button type="button" class="btn btn-sm btn-danger remove-question">
                <i class="fas fa-times"></i>
            </button>
            <input type="hidden" name="question_id[]" value="0"> 
            
            <div class="form-group question-text-group">
                <label class="question-text-label">Question Text</label>
                <textarea class="form-control question-text" name="questions[]" required rows="2"></textarea>
            </div>
            
            <div class="form-group question-type-group">
                <label>Type</label>
                <select class="form-control question-type" name="question_types[]" required> 
                    <option value="text">Text Answer</option>
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="scale">Scale (0-3)</option>
                    <option value="section_header">Section Header/Category</option>
                </select>
            </div>
            
            <div class="form-group options-container"> 
                <label class="options-label">Options (One per line)</label>
                <textarea class="form-control options-textarea" name="options[]" rows="4" placeholder="Enter each option on a new line"></textarea>
                <small class="form-text text-muted options-help-text">For multiple choice questions, enter each option on a new line</small>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        /**
         * Function to update the question count, attach event handlers, and apply numbering.
         */
        function updateQuestionDisplay() {
            const $containers = $('#questions-container').children('.question-container');
            const count = $containers.length;
            $('#question-count').text(count);
            
            let question_num = 0;
            let section_num = 0;

            // 1. Apply Dynamic Numbering based on Type and Order
            $containers.each(function(index) {
                const $container = $(this);
                const $typeSelect = $container.find('.question-type');
                const $textLabel = $container.find('.question-text-label');
                const $optionsContainer = $container.find('.options-container');
                const $optionsLabel = $container.find('.options-label');
                const $optionsTextarea = $container.find('.options-textarea');
                const $optionsHelpText = $container.find('.options-help-text');
                const $typeGroup = $container.find('.question-type-group');
                const $questionTextarea = $container.find('.question-text');
                
                const type = $typeSelect.val();
                
                // --- Reset to default states ---
                $container.removeClass('section-header-style');
                $typeGroup.show();
                $optionsContainer.hide();
                $optionsTextarea.prop('required', false);
                $questionTextarea.prop('required', true); // Default to required

                if (type === 'section_header') {
                    section_num++;
                    question_num = 0; // Reset question count for the new section
                    
                    // --- Section Styling and Visibility ---
                    $container.addClass('section-header-style');
                    $typeGroup.hide(); // Hide the type selector
                    $optionsContainer.show(); // Show description field
                    
                    // Update labels/placeholders for Section
                    $textLabel.text(`Section ${section_num}: Section Title (required)`);
                    $optionsLabel.text('Section Description (optional)');
                    $optionsTextarea.attr('placeholder', 'Enter a brief description for this section');
                    $optionsHelpText.hide(); // Hide helper text
                    
                } else {
                    question_num++;
                    
                    // --- Question Styling and Visibility ---
                    $textLabel.text(`Q. ${question_num}: Question Text`);
                    $optionsTextarea.attr('placeholder', 'Enter each option on a new line');
                    
                    if (type === 'multiple_choice') {
                         $optionsContainer.show();
                         $optionsLabel.text('Options (One per line)');
                         $optionsHelpText.show();
                         $optionsTextarea.prop('required', true);
                    } else {
                         // Text or Scale - Hide Options
                         $optionsContainer.hide();
                         // We do NOT clear .options-textarea here, only on type change (handled below)
                         $optionsTextarea.prop('required', false);
                    }
                }
            });


            // 2. Re-attach general event handlers
            
            // Re-attach Remove handler
            $('.remove-question').off('click').on('click', function() {
                $(this).closest('.question-container').remove();
                updateQuestionDisplay(); // Update numbering after removal
            });
            
            // Re-attach Type Change handler (must call updateQuestionDisplay to re-number)
            $('.question-type').off('change').on('change', function() {
                const newType = $(this).val();
                const $container = $(this).closest('.question-container');
                
                // Clear options/description content unless the old type was 'multiple_choice' or 'section_header' 
                // and the new type is the other of those two (to preserve data when switching between them)
                if (newType !== 'multiple_choice' && newType !== 'section_header') {
                     $container.find('.options-textarea').val(''); 
                }
                
                // Recalculate numbering and styling for ALL questions (global change)
                updateQuestionDisplay(); 
            });
        }
        
        $(document).ready(function() {
            
            // Auto-add the first question if in add mode and no questions exist
            <?php if (isset($action) && $action == 'add'): ?>
                if ($('#questions-container').children('.question-container').length === 0) {
                    // Start with a Section Header by default for a new assessment structure
                    $('#add-section').click(); 
                    // Then add a question
                    $('#add-question').click();
                }
            <?php endif; ?>
            
            // Add Question Button handler
            $('#add-question').click(function() {
                const template = $('#question-template').html();
                const newQuestion = $(template);
                
                // Set default question type to text for a new question
                newQuestion.find('.question-type').val('text');
                newQuestion.find('input[name="question_id[]"]').val(0);
                
                $('#questions-container').append(newQuestion);
                updateQuestionDisplay();
            });

            // Add Section Header Button handler
            $('#add-section').click(function() {
                const template = $('#question-template').html();
                const newSection = $(template);
                
                // Set the type to section_header
                newSection.find('.question-type').val('section_header');
                newSection.find('input[name="question_id[]"]').val(0);
                
                $('#questions-container').append(newSection);
                updateQuestionDisplay();
            });
            
            // Initial call to set up event handlers (important for edit mode to activate change listeners and numbering)
            updateQuestionDisplay();
        });
    </script>
</body>
</html>
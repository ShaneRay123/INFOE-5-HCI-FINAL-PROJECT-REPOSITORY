<?php
require_once '../config.php';
require_once '../includes/functions.php';

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

// Handle assessment actions (add, edit, delete)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Add new assessment
    if ($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $questions = isset($_POST['questions']) ? $_POST['questions'] : [];
        $question_types = isset($_POST['question_types']) ? $_POST['question_types'] : [];
        $options = isset($_POST['options']) ? $_POST['options'] : [];
        $selected_students = isset($_POST['selected_students']) ? $_POST['selected_students'] : [];
        
        // Validate input
        $errors = [];
        
        if (empty($title)) {
            $errors[] = "Assessment title is required.";
        }
        
        if (count($questions) === 0) {
            $errors[] = "At least one question is required.";
        }
        
        if (count($selected_students) === 0) {
            $errors[] = "At least one student must be selected.";
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
                        $question_options = json_encode(array_map('trim', explode("\n", $options[$i])));
                    }
                    
                    $insert_question = "INSERT INTO questions (assessment_id, question_text, question_type, options) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $insert_question);
                    mysqli_stmt_bind_param($stmt, "isss", $assessment_id, $question_text, $question_type, $question_options);
                    mysqli_stmt_execute($stmt);
                }
                
                // Assign assessment to selected students
                foreach ($selected_students as $student_id) {
                    $insert_student_assessment = "INSERT INTO student_assessments (student_id, assessment_id) VALUES (?, ?)";
                    $stmt = mysqli_prepare($conn, $insert_student_assessment);
                    mysqli_stmt_bind_param($stmt, "ii", $student_id, $assessment_id);
                    mysqli_stmt_execute($stmt);
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                setFlashMessage("Assessment created and assigned successfully.", "success");
                redirect("assessment_management.php");
                
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                setFlashMessage("Error creating assessment: " . $e->getMessage(), "danger");
            }
        } else {
            $error_msg = implode("<br>", $errors);
            setFlashMessage($error_msg, "danger");
        }
    }
    
    // Edit assessment
    if ($action == 'edit' && isset($_GET['id'])) {
        $assessment_id = $_GET['id'];
        
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
        $questions_query = "SELECT * FROM questions WHERE assessment_id = ?";
        $stmt = mysqli_prepare($conn, $questions_query);
        mysqli_stmt_bind_param($stmt, "i", $assessment_id);
        mysqli_stmt_execute($stmt);
        $questions_result = mysqli_stmt_get_result($stmt);
        $questions = [];
        while ($question = mysqli_fetch_assoc($questions_result)) {
            $questions[] = $question;
        }
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            
            // Validate input
            $errors = [];
            
            if (empty($title)) {
                $errors[] = "Assessment title is required.";
            }
            
            // If no errors, update assessment
            if (empty($errors)) {
                $update_query = "UPDATE assessments SET title = ?, description = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "ssi", $title, $description, $assessment_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    setFlashMessage("Assessment updated successfully.", "success");
                    redirect("assessment_management.php");
                } else {
                    setFlashMessage("Error updating assessment.", "danger");
                }
            } else {
                $error_msg = implode("<br>", $errors);
                setFlashMessage($error_msg, "danger");
            }
        }
    }
    
    // Delete assessment
    if ($action == 'delete' && isset($_GET['id'])) {
        $assessment_id = $_GET['id'];
        
        $delete_query = "DELETE FROM assessments WHERE id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $assessment_id);
        
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage("Assessment deleted successfully.", "success");
        } else {
            setFlashMessage("Error deleting assessment.", "danger");
        }
        
        redirect("assessment_management.php");
    }
}

// Get all assessments
$assessments_query = "SELECT a.*, u.full_name as creator_name, 
                    (SELECT COUNT(*) FROM student_assessments WHERE assessment_id = a.id) as assigned_count,
                    (SELECT COUNT(*) FROM student_assessments WHERE assessment_id = a.id AND status = 'completed') as completed_count
                    FROM assessments a
                    JOIN users u ON a.created_by = u.id
                    ORDER BY a.created_at DESC";
$assessments_result = mysqli_query($conn, $assessments_query);

// Get students for select dropdown
$students_query = "SELECT id, full_name, username FROM users WHERE role = 'student' ORDER BY full_name";
$students_result = mysqli_query($conn, $students_query);
$students = [];
while ($student = mysqli_fetch_assoc($students_result)) {
    $students[] = $student;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Management - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.0.0/dist/select2-bootstrap4.min.css">
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
        .question-container {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        .remove-question {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .options-container {
            display: none;
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
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar py-3">
                <div class="user-info mb-3 text-center">
                    <div class="h5"><?php echo htmlspecialchars($user["full_name"]); ?></div>
                    <div class="small"><?php echo htmlspecialchars($user["email"]); ?></div>
                    <div class="badge badge-danger">Counselor</div>
                </div>
                <hr>
                <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="user_management.php" class="sidebar-link"><i class="fas fa-users"></i> User Management</a>
                <a href="assessment_management.php" class="sidebar-link active"><i class="fas fa-clipboard-list"></i> Assessment Tools</a>
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
                
                <?php if (isset($action) && ($action == 'add' || $action == 'edit')): ?>
                    <!-- Create/Edit Assessment Form -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <?php echo $action == 'add' ? '<i class="fas fa-plus"></i> Create New Assessment' : '<i class="fas fa-edit"></i> Edit Assessment'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo $action == 'add' ? 'assessment_management.php?action=add' : 'assessment_management.php?action=edit&id=' . $assessment_id; ?>">
                                <div class="form-group">
                                    <label for="title">Assessment Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required
                                           value="<?php echo isset($edit_assessment) ? htmlspecialchars($edit_assessment['title']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($edit_assessment) ? htmlspecialchars($edit_assessment['description']) : ''; ?></textarea>
                                </div>
                                
                                <?php if ($action == 'add'): ?>
                                    <div class="card mb-4">
                                        <div class="card-header bg-info text-white">
                                            <h5 class="mb-0">Questions</h5>
                                        </div>
                                        <div class="card-body">
                                            <div id="questions-container">
                                                <!-- Questions will be added here dynamically -->
                                            </div>
                                            <button type="button" class="btn btn-success btn-block" id="add-question">
                                                <i class="fas fa-plus"></i> Add Question
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-4">
                                        <div class="card-header bg-warning text-dark">
                                            <h5 class="mb-0">Assign to Students</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label for="selected_students">Select Students</label>
                                                <select class="form-control select2" id="selected_students" name="selected_students[]" multiple required>
                                                    <?php foreach ($students as $student): ?>
                                                        <option value="<?php echo $student['id']; ?>">
                                                            <?php echo htmlspecialchars($student['full_name']) . ' (' . htmlspecialchars($student['username']) . ')'; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="form-text text-muted">Hold Ctrl (or Cmd) to select multiple students or select all using the checkbox above</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
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
                    <!-- Assessments List -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-clipboard-list"></i> Assessment Management</h2>
                        <a href="assessment_management.php?action=add" class="btn btn-success">
                            <i class="fas fa-plus"></i> Create New Assessment
                        </a>
                    </div>
                    
                    <div class="card">
                        <div class="card-header bg-primary text-white">
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
    
    <!-- Question Template (hidden) -->
    <div id="question-template" style="display: none;">
        <div class="question-container">
            <button type="button" class="btn btn-sm btn-danger remove-question">
                <i class="fas fa-times"></i>
            </button>
            <div class="form-group">
                <label>Question Text</label>
                <textarea class="form-control question-text" name="questions[]" required rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Question Type</label>
                <select class="form-control question-type" name="question_types[]" required>
                    <option value="text">Text Answer</option>
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="scale">Scale (1-10)</option>
                </select>
            </div>
            <div class="form-group options-container">
                <label>Options (One per line)</label>
                <textarea class="form-control" name="options[]" rows="4" placeholder="Enter each option on a new line"></textarea>
                <small class="form-text text-muted">For multiple choice questions, enter each option on a new line</small>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap4',
                placeholder: 'Select students to assign',
                allowClear: true
            });
            
            // Add Question Button
            $('#add-question').click(function() {
                const template = $('#question-template').html();
                $('#questions-container').append(template);
                
                // Attach event handlers to the new question
                attachQuestionEventHandlers();
            });
            
            // Add the first question automatically if in add mode
            <?php if ($action == 'add'): ?>
            $('#add-question').click();
            <?php endif; ?>
            
            // Function to attach event handlers to questions
            function attachQuestionEventHandlers() {
                // Remove question
                $('.remove-question').off('click').on('click', function() {
                    $(this).closest('.question-container').remove();
                });
                
                // Show/hide options based on question type
                $('.question-type').off('change').on('change', function() {
                    const optionsContainer = $(this).closest('.question-container').find('.options-container');
                    if ($(this).val() === 'multiple_choice') {
                        optionsContainer.show();
                    } else {
                        optionsContainer.hide();
                    }
                });
            }
        });
    </script>
</body>
</html>

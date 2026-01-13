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
$user_id = $_SESSION["user_id"];

// Check if an assessment ID is provided
if (isset($_GET['id'])) {
    $assessment_id = (int) $_GET['id'];
    
    // Check if this student assessment exists and is pending, AND fetch counselor name
    $check_query = "SELECT sa.id, a.title, a.description, u.full_name AS counselor_name 
                    FROM student_assessments sa 
                    JOIN assessments a ON sa.assessment_id = a.id 
                    JOIN users u ON a.created_by = u.id 
                    WHERE sa.id = ? AND sa.student_id = ? AND sa.status = 'pending'";
    
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "ii", $assessment_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        // If it's a POST request, we still redirect. If it's a GET, we redirect with a message.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            setFlashMessage("Assessment not found or already completed.", "danger");
        }
        redirect("dashboard.php");
    }
    
    $assessment = mysqli_fetch_assoc($result);
    
    // Get questions for this assessment
    $questions_query = "SELECT q.id, q.question_text, q.question_type, q.options 
                        FROM questions q 
                        JOIN assessments a ON q.assessment_id = a.id 
                        JOIN student_assessments sa ON sa.assessment_id = a.id 
                        WHERE sa.id = ?
                        ORDER BY q.id ASC"; // Ensure questions are ordered correctly
    $stmt = mysqli_prepare($conn, $questions_query);
    mysqli_stmt_bind_param($stmt, "i", $assessment_id);
    mysqli_stmt_execute($stmt);
    $questions_result = mysqli_stmt_get_result($stmt);
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessment'])) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update student_assessment status
            $update_query = "UPDATE student_assessments SET status = 'completed', submitted_at = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "i", $assessment_id);
            mysqli_stmt_execute($stmt);
            
            // Save responses
            foreach ($_POST['responses'] as $question_id => $response) {
                // Ignore responses for question_id 0 (which would be invalid, but as a safeguard)
                if ((int)$question_id === 0) {
                    continue;
                }
                
                // If a checkbox/multiple-select field was used (though not in current rendering), handle it as an array
                if (is_array($response)) {
                    $response = implode(", ", $response);
                } else {
                    // Sanitize all responses before saving (assuming sanitize function is in functions.php)
                    $response = sanitize($response); 
                }
                
                $insert_response = "INSERT INTO responses (student_assessment_id, question_id, response_text) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_response);
                mysqli_stmt_bind_param($stmt, "iis", $assessment_id, $question_id, $response);
                mysqli_stmt_execute($stmt);
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            setFlashMessage("Assessment submitted successfully! Your counselor will review your responses shortly.", "success");
            redirect("view_assessment.php");
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            setFlashMessage("Error submitting assessment. Please try again. (" . $e->getMessage() . ")", "danger");
        }
    }
    
    // Handle assessment cancellation via GET action
    if (isset($_GET['action']) && $_GET['action'] == 'cancel') {
        // $assessment_id is already set from $_GET['id'] at line 29
        
        // Only allow cancellation if status is 'pending' and belongs to the user
        $cancel_query = "UPDATE student_assessments SET status = 'cancelled' WHERE id = ? AND student_id = ? AND status = 'pending'";
        $stmt = mysqli_prepare($conn, $cancel_query);
        mysqli_stmt_bind_param($stmt, "ii", $assessment_id, $user_id);
        
        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
            setFlashMessage("Assessment has been **cancelled** successfully.", "success");
        } else {
            setFlashMessage("Error cancelling assessment. It may have already been completed or cancelled.", "danger");
        }
        
        redirect("take_assessment.php"); // Redirect back to the list view
    }
    
} else {
    // No specific assessment ID, show list of available assessments
    $assessments_query = "SELECT sa.id, a.title, a.description, sa.status, sa.submitted_at, u.full_name AS counselor_name 
                          FROM student_assessments sa 
                          JOIN assessments a ON sa.assessment_id = a.id 
                          JOIN users u ON a.created_by = u.id 
                          WHERE sa.student_id = ? 
                          ORDER BY sa.status ASC, sa.submitted_at DESC";
    
    $stmt = mysqli_prepare($conn, $assessments_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $assessments = mysqli_stmt_get_result($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Assessment - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
    /* Sidebar and Navbar */
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
        transition: background-color 0.3s ease, color 0.3s ease;
        border-radius: 4px;
    }
    .sidebar-link:hover {
        color: white;
        background-color: #b30104;
        text-decoration: none;
    }
    .sidebar-link.active {
        color: white;
        background-color: #b30104;
    }
    .sidebar-link i {
        margin-right: 10px;
    }
    .navbar.navbar-dark.bg-dark {
        background-color: #1e1e1e !important;
    }

    /* Content Area */
    .content {
        padding: 20px;
        opacity: 0;
        animation: fadeIn 0.5s ease-in-out forwards;
    }

    /* Cards */
    .card {
        margin-bottom: 20px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        opacity: 0;
        transform: translateY(10px);
        animation: fadeInUp 0.6s ease forwards;
    }
    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        transition: transform 0.4s ease, box-shadow 0.4s ease;
    }

    /* Question Cards Accent */
    .question-card {
        margin-bottom: 1.5rem;
        border-left: 4px solid #b30104;
    }

    /* Scale Styling - Custom appearance for list-group radio buttons */
    .list-group-item label {
        cursor: pointer;
        margin-bottom: 0;
    }
    .list-group-item:hover {
        background-color: #f8f9fa;
    }
    .list-group-item input[type="radio"]:checked + strong {
        color: #b30104 !important; /* Highlight text when selected */
    }

    /* Alerts */
    .alert {
        opacity: 0;
        animation: fadeIn 0.4s ease-in-out forwards;
    }

    /* Card Header */
    .card-header {
        /* This is the primary color for the system, overriding Bootstrap's bg-primary */
        background-color: #b30104 !important;
        color: #fff !important;
    }
    
    .border-info {
        border-color: #17a2b8 !important; /* Custom color for section header */
    }

    /* Keyframes for smooth fade-in */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
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
            <div class="col-md-3 col-lg-2 sidebar py-3">
                <div class="user-info mb-3 text-center">
                    <div class="h5"><?php echo htmlspecialchars($user["full_name"]); ?></div>
                    <div class="small"><?php echo htmlspecialchars($user["email"]); ?></div>
                </div>
                <hr>
                <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="take_assessment.php" class="sidebar-link active"><i class="fas fa-tasks"></i> Take Assessment</a>
    
                <a href="manage_appointment.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Manage Appointments</a>
                <a href="update_information.php" class="sidebar-link"><i class="fas fa-user-edit"></i> Update Information</a>
                <a href="view_schedule.php" class="sidebar-link"><i class="fas fa-calendar-alt"></i> View Schedule</a>
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
                
                <?php if (isset($assessment)): ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4><i class="fas fa-clipboard-check"></i> <?php echo htmlspecialchars($assessment['title']); ?></h4>
                        </div>
                        <div class="card-body">
                            <p class="lead"><?php echo htmlspecialchars($assessment['description']); ?></p>
                            <p class="text-muted">Assigned by: <strong><?php echo htmlspecialchars($assessment['counselor_name']); ?></strong></p>
                            <hr>
                            
                            <form method="post" action="">
                                <?php 
                                $question_number = 1;
                                mysqli_data_seek($questions_result, 0); // Reset pointer for loop
                                while ($question = mysqli_fetch_assoc($questions_result)): 
                                    
                                    // Handle Section Header/Category
                                    if ($question['question_type'] == 'section_header'): ?>
                                        <div class="card bg-light mb-4 shadow-sm border-info">
                                            <div class="card-body">
                                                <h4 class="mb-1 text-info">
                                                    <i class="fas fa-layer-group mr-2"></i> <?php echo htmlspecialchars($question['question_text']); ?>
                                                </h4>
                                                <?php 
                                                // Display the 'options' field as the section description
                                                if (!empty($question['options'])): ?>
                                                    <p class="text-secondary small mt-2 mb-0"><?php echo nl2br(htmlspecialchars($question['options'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php 
                                        // FIX: Reset the question number for the next actual question
                                        $question_number = 1; 
                                        continue; // Skip the rest of the loop for section headers
                                    endif; 
                                    ?>
                                
                                <div class="card question-card">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <span class="text-muted mr-2"><?php echo $question_number; ?>.</span> 
                                            <?php echo htmlspecialchars($question['question_text']); ?>
                                        </h5>
                                        
                                        <?php if ($question['question_type'] == 'multiple_choice'): ?>
                                            <?php 
                                            $options = json_decode($question['options'], true); 
                                            // Check if options decoded successfully and is an array
                                            if (is_array($options) && !empty($options)):
                                                foreach ($options as $option): 
                                                ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="responses[<?php echo $question['id']; ?>]" value="<?php echo htmlspecialchars($option); ?>" required>
                                                    <label class="form-check-label"><?php echo htmlspecialchars($option); ?></label>
                                                </div>
                                                <?php 
                                                endforeach;
                                            else: ?>
                                                <div class="alert alert-warning mt-2" role="alert">
                                                    <i class="fas fa-exclamation-triangle"></i> Warning: Missing or invalid options data for this Multiple Choice question.
                                                </div>
                                            <?php endif; // End check for array options ?>
                                            
                                        <?php elseif ($question['question_type'] == 'text'): ?>
                                            <div class="form-group">
                                                <textarea class="form-control" name="responses[<?php echo $question['id']; ?>]" rows="3" required placeholder="Enter your response here..."></textarea>
                                            </div>
                                            
                                        <?php elseif ($question['question_type'] == 'scale'): ?>
                                            <div class="form-group">
                                                <p class="small text-muted font-weight-bold">Select the best option:</p>
                                                <div class="list-group">
                                                <?php 
                                                $scale_options = [
                                                    '0' => '0 - Not at all',
                                                    '1' => '1 - Several days',
                                                    '2' => '2 - More than half the days',
                                                    '3' => '3 - Nearly every day'
                                                ];
                                                foreach ($scale_options as $value => $label): ?>
                                                    <label class="list-group-item list-group-item-action d-flex align-items-center mb-1 rounded">
                                                        <input class="form-check-input mr-3" type="radio" 
                                                            name="responses[<?php echo $question['id']; ?>]" 
                                                            value="<?php echo htmlspecialchars($value); ?>" 
                                                            required>
                                                        <strong class="mr-2 text-info"><?php echo htmlspecialchars($value); ?>:</strong> 
                                                        <span><?php echo htmlspecialchars(substr($label, 4)); ?></span> </label>
                                                <?php endforeach; ?>
                                                </div>
                                            </div>
                                            
                                        <?php else: ?>
                                            <div class="alert alert-danger mt-2" role="alert">
                                                <i class="fas fa-bug"></i> Error: Question type '<?php echo htmlspecialchars($question['question_type']); ?>' is not supported.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php 
                                // Increment the question number only if it was a real question (not a section header)
                                $question_number++;
                                endwhile; 
                                ?>
                                
                                <div class="form-group text-center mt-4">
                                    <button type="submit" name="submit_assessment" class="btn btn-primary btn-lg">Submit Assessment</button>
                                    <a href="dashboard.php" class="btn btn-secondary btn-lg ml-2">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <h2><i class="fas fa-tasks"></i> Assessments</h2>
                    <p class="lead">Below are all the assessments assigned to you.</p>
                    
                    <?php if (mysqli_num_rows($assessments) > 0): ?>
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Your Assessments</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group">
                                    <?php while ($row = mysqli_fetch_assoc($assessments)): ?>
                                        <div class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><?php echo htmlspecialchars($row['title']); ?></h5>
                                                <?php if ($row['status'] == 'pending'): ?>
                                                    <span class="badge badge-warning">Pending</span>
                                                <?php elseif ($row['status'] == 'completed'): ?>
                                                    <span class="badge badge-success">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Cancelled</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($row['description']); ?></p>
                                            
                                            <small class="text-info">Counselor: <strong><?php echo htmlspecialchars($row['counselor_name']); ?></strong></small>
                                            
                                            <div class="mt-2">
                                                <?php if ($row['status'] == 'pending'): ?>
                                                    <a href="take_assessment.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit"></i> Take Assessment
                                                    </a>
                                                    <a href="view_assessment_details.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </a>
                                                    <a href="take_assessment.php?id=<?php echo $row['id']; ?>&action=cancel" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this assessment?')">
                                                        <i class="fas fa-times-circle"></i> Cancel
                                                    </a>
                                                <?php elseif ($row['status'] == 'completed'): ?>
                                                    <a href="view_assessment.php?id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-eye"></i> View Responses
                                                    </a>
                                                    <a href="view_assessment_details.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-file-alt"></i> View Details
                                                    </a>
                                                    <small class="text-muted ml-2">Submitted on: <?php echo formatDate($row['submitted_at']); ?></small>
                                                <?php elseif ($row['status'] == 'cancelled'): ?>
                                                    <a href="view_assessment_details.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-file-alt"></i> View Details
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You don't have any assessments assigned to you yet.
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
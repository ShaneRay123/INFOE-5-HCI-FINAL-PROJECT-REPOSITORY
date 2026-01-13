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

// Check if an assessment ID is provided
if (isset($_GET['id'])) {
    $assessment_id = $_GET['id'];
    
    // Check if this student assessment exists and is pending
    $check_query = "SELECT sa.id, a.title, a.description 
                 FROM student_assessments sa 
                 JOIN assessments a ON sa.assessment_id = a.id 
                 WHERE sa.id = ? AND sa.student_id = ? AND sa.status = 'pending'";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "ii", $assessment_id, $_SESSION["user_id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        setFlashMessage("Assessment not found or already completed.", "danger");
        redirect("dashboard.php");
    }
    
    $assessment = mysqli_fetch_assoc($result);
    
    // Get questions for this assessment
    $questions_query = "SELECT q.id, q.question_text, q.question_type, q.options 
                     FROM questions q 
                     JOIN assessments a ON q.assessment_id = a.id 
                     JOIN student_assessments sa ON sa.assessment_id = a.id 
                     WHERE sa.id = ?";
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
                if (is_array($response)) {
                    $response = implode(", ", $response);
                }
                
                $insert_response = "INSERT INTO responses (student_assessment_id, question_id, response_text) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_response);
                mysqli_stmt_bind_param($stmt, "iis", $assessment_id, $question_id, $response);
                mysqli_stmt_execute($stmt);
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            setFlashMessage("Assessment submitted successfully!", "success");
            redirect("view_assessment.php");
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            setFlashMessage("Error submitting assessment. Please try again.", "danger");
        }
    }
    
} else {
    // No specific assessment, show list of available assessments
    $assessments_query = "SELECT sa.id, a.title, a.description, sa.status, sa.submitted_at 
                       FROM student_assessments sa 
                       JOIN assessments a ON sa.assessment_id = a.id 
                       WHERE sa.student_id = ? 
                       ORDER BY sa.status ASC, sa.submitted_at DESC";
    $stmt = mysqli_prepare($conn, $assessments_query);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
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
            background-color: #007bff;
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
        .question-card {
            margin-bottom: 1.5rem;
            border-left: 4px solid #007bff;
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
                <a href="take_assessment.php" class="sidebar-link active"><i class="fas fa-tasks"></i> Take Assessment</a>
                <a href="view_assessment.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> View Assessments</a>
                <a href="manage_appointment.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Manage Appointments</a>
                <a href="update_information.php" class="sidebar-link"><i class="fas fa-user-edit"></i> Update Information</a>
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
                
                <?php if (isset($assessment)): ?>
                    <!-- Assessment form -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4><i class="fas fa-clipboard-check"></i> <?php echo htmlspecialchars($assessment['title']); ?></h4>
                        </div>
                        <div class="card-body">
                            <p class="lead"><?php echo htmlspecialchars($assessment['description']); ?></p>
                            <hr>
                            
                            <form method="post" action="">
                                <?php 
                                $question_number = 1;
                                while ($question = mysqli_fetch_assoc($questions_result)): 
                                ?>
                                <div class="card question-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Question <?php echo $question_number; ?>: <?php echo htmlspecialchars($question['question_text']); ?></h5>
                                        
                                        <?php if ($question['question_type'] == 'multiple_choice'): ?>
                                            <?php $options = json_decode($question['options'], true); ?>
                                            <?php foreach ($options as $option): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="responses[<?php echo $question['id']; ?>]" value="<?php echo htmlspecialchars($option); ?>" required>
                                                <label class="form-check-label"><?php echo htmlspecialchars($option); ?></label>
                                            </div>
                                            <?php endforeach; ?>
                                            
                                        <?php elseif ($question['question_type'] == 'text'): ?>
                                            <div class="form-group">
                                                <textarea class="form-control" name="responses[<?php echo $question['id']; ?>]" rows="3" required></textarea>
                                            </div>
                                            
                                        <?php elseif ($question['question_type'] == 'scale'): ?>
                                            <div class="form-group">
                                                <div class="d-flex justify-content-between">
                                                    <span>1 (Strongly Disagree)</span>
                                                    <span>10 (Strongly Agree)</span>
                                                </div>
                                                <input type="range" class="custom-range" min="1" max="10" name="responses[<?php echo $question['id']; ?>]" required>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php 
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
                    <!-- List of assessments -->
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
                                            
                                            <div class="mt-2">
                                                <?php if ($row['status'] == 'pending'): ?>
                                                    <a href="take_assessment.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit"></i> Take Assessment
                                                    </a>
                                                    <a href="view_assessment_details.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </a>
                                                    <a href="take_assessment.php?id=<?php echo $row['id']; ?>&action=cancel" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this assessment?')">Cancel</a>
                                                <?php elseif ($row['status'] == 'completed'): ?>
                                                    <a href="view_assessment.php?id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-eye"></i> View Responses
                                                    </a>
                                                    <a href="view_assessment_details.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-file-alt"></i> View Details
                                                    </a>
                                                    <small class="text-muted ml-2">Submitted on: <?php echo formatDate($row['submitted_at']); ?></small>
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

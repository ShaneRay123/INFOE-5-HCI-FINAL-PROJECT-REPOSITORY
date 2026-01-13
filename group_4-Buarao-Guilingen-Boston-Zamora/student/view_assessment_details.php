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

// Check if assessment ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage("Assessment ID not provided.", "danger");
    redirect("dashboard.php");
}

$assessment_id = $_GET['id'];

// Get assessment details
$assessment_query = "SELECT sa.id, a.title, a.description, sa.status, sa.submitted_at, 
                    u.full_name as created_by_name
                    FROM student_assessments sa 
                    JOIN assessments a ON sa.assessment_id = a.id 
                    JOIN users u ON a.created_by = u.id
                    WHERE sa.id = ? AND sa.student_id = ?";
$stmt = mysqli_prepare($conn, $assessment_query);
mysqli_stmt_bind_param($stmt, "ii", $assessment_id, $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    setFlashMessage("Assessment not found.", "danger");
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
$questions = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Details - Wellness Hub</title>
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
            margin-bottom: 1rem;
            border-left: 4px solid #17a2b8;
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
                <a href="view_assessment.php" class="sidebar-link active"><i class="fas fa-clipboard-list"></i> View Assessments</a>
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
                
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h4><i class="fas fa-clipboard-list"></i> Assessment Details</h4>
                    </div>
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($assessment['title']); ?></h3>
                        
                        <div class="row mt-3 mb-4">
                            <div class="col-md-6">
                                <p><strong>Created by:</strong> <?php echo htmlspecialchars($assessment['created_by_name']); ?></p>
                                <p><strong>Status:</strong> 
                                    <?php if ($assessment['status'] == 'pending'): ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php elseif ($assessment['status'] == 'completed'): ?>
                                        <span class="badge badge-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Cancelled</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Submission Date:</strong> 
                                    <?php echo $assessment['submitted_at'] ? formatDate($assessment['submitted_at']) : 'Not submitted yet'; ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5>Description</h5>
                                <p><?php echo nl2br(htmlspecialchars($assessment['description'])); ?></p>
                            </div>
                        </div>
                        
                        <h5 class="mb-3">Questions</h5>
                        
                        <?php 
                        $question_number = 1;
                        while ($question = mysqli_fetch_assoc($questions)): 
                        ?>
                            <div class="card question-card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Question <?php echo $question_number; ?>: <?php echo htmlspecialchars($question['question_text']); ?></h6>
                                    
                                    <p class="card-text text-muted">
                                        <strong>Type:</strong> 
                                        <?php 
                                        if ($question['question_type'] == 'multiple_choice') {
                                            echo 'Multiple Choice';
                                        } elseif ($question['question_type'] == 'text') {
                                            echo 'Text Response';
                                        } elseif ($question['question_type'] == 'scale') {
                                            echo 'Scale (1-10)';
                                        }
                                        ?>
                                    </p>
                                    
                                    <?php if ($question['question_type'] == 'multiple_choice' && !empty($question['options'])): ?>
                                        <div class="mt-2">
                                            <strong>Options:</strong>
                                            <ul>
                                                <?php 
                                                $options = json_decode($question['options'], true);
                                                if (is_array($options)) {
                                                    foreach ($options as $option) {
                                                        echo '<li>' . htmlspecialchars($option) . '</li>';
                                                    }
                                                }
                                                ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php 
                        $question_number++;
                        endwhile; 
                        ?>
                        
                        <div class="text-center mt-4">
                            <?php if ($assessment['status'] == 'pending'): ?>
                                <a href="take_assessment.php?id=<?php echo $assessment_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Take Assessment
                                </a>
                            <?php elseif ($assessment['status'] == 'completed'): ?>
                                <a href="view_assessment.php?id=<?php echo $assessment_id; ?>" class="btn btn-success">
                                    <i class="fas fa-eye"></i> View Responses
                                </a>
                            <?php endif; ?>
                            <a href="dashboard.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

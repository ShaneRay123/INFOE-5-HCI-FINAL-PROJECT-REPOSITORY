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

// Check if specific assessment ID is provided
if (isset($_GET['id'])) {
    $assessment_id = $_GET['id'];
    
    // Get assessment details
    $assessment_query = "SELECT sa.id, a.title, a.description, sa.status, sa.submitted_at 
                      FROM student_assessments sa 
                      JOIN assessments a ON sa.assessment_id = a.id 
                      WHERE sa.id = ? AND sa.student_id = ?";
    $stmt = mysqli_prepare($conn, $assessment_query);
    mysqli_stmt_bind_param($stmt, "ii", $assessment_id, $_SESSION["user_id"]);
    mysqli_stmt_execute($stmt);
    $assessment_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($assessment_result) == 0) {
        setFlashMessage("Assessment not found.", "danger");
        redirect("view_assessment.php");
    }
    
    $assessment = mysqli_fetch_assoc($assessment_result);
    
    // Get responses for this assessment
    $responses_query = "SELECT q.question_text, q.question_type, r.response_text 
                     FROM responses r 
                     JOIN questions q ON r.question_id = q.id 
                     WHERE r.student_assessment_id = ?";
    $stmt = mysqli_prepare($conn, $responses_query);
    mysqli_stmt_bind_param($stmt, "i", $assessment_id);
    mysqli_stmt_execute($stmt);
    $responses = mysqli_stmt_get_result($stmt);
    
} else {
    // Get all completed assessments
    $assessments_query = "SELECT sa.id, a.title, sa.status, sa.submitted_at 
                       FROM student_assessments sa 
                       JOIN assessments a ON sa.assessment_id = a.id 
                       WHERE sa.student_id = ? AND sa.status = 'completed' 
                       ORDER BY sa.submitted_at DESC";
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
    <title>View Assessments - Wellness Hub</title>
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
        .response-card {
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
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
                
                <?php if (isset($assessment)): ?>
                    <!-- Assessment details view -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4><i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars($assessment['title']); ?></h4>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <p class="lead"><?php echo htmlspecialchars($assessment['description']); ?></p>
                                <div class="d-flex justify-content-between">
                                    <span><strong>Status:</strong> 
                                        <span class="badge badge-success">Completed</span>
                                    </span>
                                    <span><strong>Submitted on:</strong> <?php echo formatDate($assessment['submitted_at']); ?></span>
                                </div>
                            </div>
                            
                            <h5 class="mb-3">Your Responses</h5>
                            
                            <?php 
                            $question_number = 1;
                            while ($response = mysqli_fetch_assoc($responses)): 
                            ?>
                            <div class="card response-card">
                                <div class="card-body">
                                    <h6 class="card-title">Question <?php echo $question_number; ?>: <?php echo htmlspecialchars($response['question_text']); ?></h6>
                                    
                                    <div class="mt-2">
                                        <strong>Your Answer:</strong>
                                        <?php if ($response['question_type'] == 'scale'): ?>
                                            <div class="progress mt-2">
                                                <?php 
                                                $value = intval($response['response_text']);
                                                $percentage = ($value / 10) * 100;
                                                ?>
                                                <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $value; ?>" aria-valuemin="0" aria-valuemax="10">
                                                    <?php echo $value; ?>/10
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($response['response_text'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php 
                            $question_number++;
                            endwhile; 
                            ?>
                            
                            <div class="text-center mt-4">
                                <a href="view_assessment.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Assessments</a>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Assessment list view -->
                    <h2><i class="fas fa-clipboard-list"></i> Completed Assessments</h2>
                    <p class="lead">View your completed assessment history.</p>
                    
                    <?php if (mysqli_num_rows($assessments) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Assessment Title</th>
                                        <th>Completion Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($assessments)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td><?php echo formatDate($row['submitted_at']); ?></td>
                                            <td>
                                                <a href="view_assessment.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You haven't completed any assessments yet.
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                        <a href="take_assessment.php" class="btn btn-primary"><i class="fas fa-tasks"></i> Take an Assessment</a>
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

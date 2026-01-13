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

// Handle viewing specific assessment results
if (isset($_GET['id'])) {
    $student_assessment_id = $_GET['id'];
    
    // Get assessment details
    $assessment_query = "SELECT sa.id, a.title, a.description, u.full_name as student_name, u.department, u.course_year, u.section, sa.submitted_at 
                      FROM student_assessments sa 
                      JOIN assessments a ON sa.assessment_id = a.id 
                      JOIN users u ON sa.student_id = u.id 
                      WHERE sa.id = ? AND sa.status = 'completed'";
    $stmt = mysqli_prepare($conn, $assessment_query);
    mysqli_stmt_bind_param($stmt, "i", $student_assessment_id);
    mysqli_stmt_execute($stmt);
    $assessment_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($assessment_result) == 0) {
        setFlashMessage("Assessment not found or not completed.", "danger");
        redirect("view_assessments.php");
    }
    
    $assessment = mysqli_fetch_assoc($assessment_result);
    
    // Get responses for this assessment
    $responses_query = "SELECT q.question_text, q.question_type, r.response_text 
                     FROM responses r 
                     JOIN questions q ON r.question_id = q.id 
                     WHERE r.student_assessment_id = ?";
    $stmt = mysqli_prepare($conn, $responses_query);
    mysqli_stmt_bind_param($stmt, "i", $student_assessment_id);
    mysqli_stmt_execute($stmt);
    $responses = mysqli_stmt_get_result($stmt);
    
} else if (isset($_GET['assessment_id'])) {
    // View all responses for a specific assessment
    $assessment_id = $_GET['assessment_id'];
    
    // Get assessment details
    $assessment_query = "SELECT * FROM assessments WHERE id = ?";
    $stmt = mysqli_prepare($conn, $assessment_query);
    mysqli_stmt_bind_param($stmt, "i", $assessment_id);
    mysqli_stmt_execute($stmt);
    $assessment_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($assessment_result) == 0) {
        setFlashMessage("Assessment not found.", "danger");
        redirect("view_assessments.php");
    }
    
    $assessment_info = mysqli_fetch_assoc($assessment_result);
    
    // Get all completed responses for this assessment
    $responses_query = "SELECT sa.id, u.full_name as student_name, sa.submitted_at 
                      FROM student_assessments sa 
                      JOIN users u ON sa.student_id = u.id 
                      WHERE sa.assessment_id = ? AND sa.status = 'completed' 
                      ORDER BY sa.submitted_at DESC";
    $stmt = mysqli_prepare($conn, $responses_query);
    mysqli_stmt_bind_param($stmt, "i", $assessment_id);
    mysqli_stmt_execute($stmt);
    $responses_list = mysqli_stmt_get_result($stmt);
    
} else {
    // Default view - show all completed assessments
    $student_filter = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    $date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';
    
    // Build query with filters
    $query = "SELECT sa.id, a.title, u.full_name as student_name, sa.submitted_at 
             FROM student_assessments sa 
             JOIN assessments a ON sa.assessment_id = a.id 
             JOIN users u ON sa.student_id = u.id 
             WHERE sa.status = 'completed'";
    
    $params = [];
    $types = "";
    
    if ($student_filter > 0) {
        $query .= " AND sa.student_id = ?";
        $params[] = $student_filter;
        $types .= "i";
    }
    
    if (!empty($date_filter)) {
        $query .= " AND DATE(sa.submitted_at) = ?";
        $params[] = $date_filter;
        $types .= "s";
    }
    
    $query .= " ORDER BY sa.submitted_at DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $completed_assessments = mysqli_stmt_get_result($stmt);
    
    // Get all students for filter dropdown
    $students_query = "SELECT id, full_name FROM users WHERE role = 'student' ORDER BY full_name";
    $students_result = mysqli_query($conn, $students_query);
    $students = [];
    while ($student = mysqli_fetch_assoc($students_result)) {
        $students[] = $student;
    }
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
        .response-card {
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
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
                <a href="assessment_management.php" class="sidebar-link"><i class="fas fa-clipboard-list"></i> Assessment Tools</a>
                <a href="appointment_management.php" class="sidebar-link"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="view_assessments.php" class="sidebar-link active"><i class="fas fa-chart-bar"></i> View Assessments</a>
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
                    <!-- Single Assessment View -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4>
                                <i class="fas fa-clipboard-list"></i> 
                                <?php echo htmlspecialchars($assessment['title']); ?> - 
                                <?php echo htmlspecialchars($assessment['student_name']); ?>
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <?php if (!empty($assessment['description'])): ?>
                                    <p class="lead"><?php echo htmlspecialchars($assessment['description']); ?></p>
                                    <hr>
                                <?php endif; ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Student:</strong> <?php echo htmlspecialchars($assessment['student_name']); ?></p>
                                        <p><strong>Department:</strong> <?php echo !empty($assessment['department']) ? htmlspecialchars($assessment['department']) : '<em>Not specified</em>'; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Course Year:</strong> <?php echo !empty($assessment['course_year']) ? htmlspecialchars($assessment['course_year']) : '<em>Not specified</em>'; ?></p>
                                        <p><strong>Section:</strong> <?php echo !empty($assessment['section']) ? htmlspecialchars($assessment['section']) : '<em>Not specified</em>'; ?></p>
                                        <p><strong>Submitted on:</strong> <?php echo formatDate($assessment['submitted_at']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="mb-3">Responses</h5>
                            
                            <?php 
                            $question_number = 1;
                            while ($response = mysqli_fetch_assoc($responses)): 
                            ?>
                            <div class="card response-card">
                                <div class="card-body">
                                    <h6 class="card-title">Question <?php echo $question_number; ?>: <?php echo htmlspecialchars($response['question_text']); ?></h6>
                                    
                                    <div class="mt-2">
                                        <strong>Response:</strong>
                                        <?php if ($response['question_type'] == 'scale'): ?>
                                            <div class="progress mt-2">
                                                <?php 
                                                $value = intval($response['response_text']);
                                                $percentage = ($value / 10) * 100;
                                                
                                                // Determine color based on value
                                                $color = 'bg-success';
                                                if ($value <= 3) {
                                                    $color = 'bg-danger';
                                                } elseif ($value <= 6) {
                                                    $color = 'bg-warning';
                                                }
                                                ?>
                                                <div class="progress-bar <?php echo $color; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $value; ?>" aria-valuemin="0" aria-valuemax="10">
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
                                <a href="view_assessments.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to All Assessments</a>
                            </div>
                        </div>
                    </div>
                
                <?php elseif (isset($assessment_info)): ?>
                    <!-- Assessment Results List -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4><i class="fas fa-clipboard-list"></i> Results for: <?php echo htmlspecialchars($assessment_info['title']); ?></h4>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <p><?php echo htmlspecialchars($assessment_info['description']); ?></p>
                            </div>
                            
                            <?php if (mysqli_num_rows($responses_list) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Student</th>
                                                <th>Submitted On</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = mysqli_fetch_assoc($responses_list)): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                                    <td><?php echo formatDate($row['submitted_at']); ?></td>
                                                    <td>
                                                        <a href="view_assessments.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-eye"></i> View Responses
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No completed assessments found.
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <a href="view_assessments.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to All Assessments</a>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- All Assessments List -->
                    <h2><i class="fas fa-chart-bar"></i> Assessment Results</h2>
                    <p class="lead">View and analyze student assessment responses.</p>
                    
                    <!-- Filter Card -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form action="" method="get" class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label>Filter by Student</label>
                                        <select class="form-control" name="student_id">
                                            <option value="">All Students</option>
                                            <?php foreach ($students as $student): ?>
                                                <option value="<?php echo $student['id']; ?>" <?php echo $student_filter == $student['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label>Filter by Date</label>
                                        <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-group mb-0 w-100">
                                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Completed Assessments</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (mysqli_num_rows($completed_assessments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Assessment</th>
                                                <th>Student</th>
                                                <th>Submitted On</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = mysqli_fetch_assoc($completed_assessments)): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                                    <td><?php echo formatDate($row['submitted_at']); ?></td>
                                                    <td>
                                                        <a href="view_assessments.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-eye"></i> View Responses
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="p-4 text-center">
                                    <p class="text-muted">No completed assessments found with the current filters.</p>
                                    <?php if (!empty($student_filter) || !empty($date_filter)): ?>
                                        <a href="view_assessments.php" class="btn btn-outline-secondary">Clear Filters</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
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

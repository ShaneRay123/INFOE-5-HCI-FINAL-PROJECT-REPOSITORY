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

// ------------------------------------------------------------------
// 1. Get assessment details
// ------------------------------------------------------------------
$assessment_query = "SELECT sa.id, a.title, a.description, sa.status, sa.submitted_at, 
                     u.full_name as created_by_name
                     FROM student_assessments sa 
                     JOIN assessments a ON sa.assessment_id = a.id 
                     JOIN users u ON a.created_by = u.id
                     WHERE sa.id = ? AND sa.student_id = ?";
$stmt_assessment = mysqli_prepare($conn, $assessment_query);
mysqli_stmt_bind_param($stmt_assessment, "ii", $assessment_id, $_SESSION["user_id"]);
mysqli_stmt_execute($stmt_assessment);
$result_assessment = mysqli_stmt_get_result($stmt_assessment);

if (mysqli_num_rows($result_assessment) == 0) {
    setFlashMessage("Assessment not found.", "danger");
    redirect("dashboard.php");
}

$assessment = mysqli_fetch_assoc($result_assessment);
mysqli_free_result($result_assessment);
mysqli_stmt_close($stmt_assessment);

// ------------------------------------------------------------------
// 2. Fetch Student Responses if assessment is completed
// (Assumes a 'responses' table with columns 'student_assessment_id' and 'question_id')
// ------------------------------------------------------------------
$student_responses = [];
if ($assessment['status'] == 'completed') {
    $responses_query = "SELECT question_id, response_text
                         FROM responses
                         WHERE student_assessment_id = ?";
    $stmt_responses = mysqli_prepare($conn, $responses_query);
    // Note: $assessment_id is student_assessments.id
    mysqli_stmt_bind_param($stmt_responses, "i", $assessment_id);
    mysqli_stmt_execute($stmt_responses);
    $responses_result = mysqli_stmt_get_result($stmt_responses);

    while ($response = mysqli_fetch_assoc($responses_result)) {
        // Store response with question_id as key for easy lookup
        $student_responses[$response['question_id']] = $response['response_text'];
    }
    mysqli_free_result($responses_result);
    mysqli_stmt_close($stmt_responses);
}


// ------------------------------------------------------------------
// 3. Get questions and sections for this assessment
// ------------------------------------------------------------------
$questions_query = "SELECT q.id, q.question_text, q.question_type, q.options 
                     FROM questions q 
                     JOIN assessments a ON q.assessment_id = a.id 
                     JOIN student_assessments sa ON sa.assessment_id = a.id 
                     WHERE sa.id = ?";
$stmt_questions = mysqli_prepare($conn, $questions_query);
mysqli_stmt_bind_param($stmt_questions, "i", $assessment_id);
mysqli_stmt_execute($stmt_questions);
$questions_result = mysqli_stmt_get_result($stmt_questions);

// Fetch all questions/sections into an array and close the statement
$questions = mysqli_fetch_all($questions_result, MYSQLI_ASSOC);
mysqli_free_result($questions_result);
mysqli_stmt_close($stmt_questions);

// ------------------------------------------------------------------
// 4. Define scale labels for 'scale' question type (0-3)
// ------------------------------------------------------------------
$scale_labels = [
    '0' => 'Not at all',
    '1' => 'Several days',
    '2' => 'More than half the days',
    '3' => 'Nearly every day',
];
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
/* ================= Sidebar ================= */
.sidebar{background-color:#2b2b2b;min-height:calc(100vh - 56px);color:#fff;}
.sidebar-link{color:rgba(255,255,255,0.8);padding:10px 15px;display:block;text-decoration:none;transition:background-color .3s ease,color .3s ease;border-radius:4px;}
.sidebar-link:hover{color:#fff;background-color:#b30104;text-decoration:none;}
.sidebar-link.active{color:#fff;background-color:#b30104;}
.sidebar-link i{margin-right:10px;}

/* ================= Navbar ================= */
.navbar.navbar-dark.bg-dark{background-color:#1e1e1e!important;}

/* ================= Content ================= */
.content{padding:20px;opacity:0;animation:fadeIn .5s ease-in-out forwards;}

/* ================= Cards ================= */
.card{margin-bottom:20px;box-shadow:0 4px 6px rgba(0,0,0,.1);opacity:0;transform:translateY(10px);animation:fadeInUp .6s ease forwards;}
.card:hover{transform:translateY(-3px);box-shadow:0 6px 12px rgba(0,0,0,.15);transition:transform .4s ease,box-shadow .4s ease;}
.card-header{background-color:#b30104!important;color:#fff!important;}

/* ================= Alerts ================= */
.alert{opacity:0;animation:fadeIn .4s ease-in-out forwards;}

/* ================= Question Cards ================= */
.question-card{margin-bottom:1rem;border-left:4px solid #b30104;}
.response-box { border-color: #28a745 !important; }

/* ================= Animations ================= */
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
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
                <a href="take_assessment.php" class="sidebar-link"><i class="fas fa-tasks"></i> Take Assessment</a>
        
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
                        
                        <h5 class="mb-3">Assessment Content</h5>
                        
                        <?php 
                        $question_number = 1;
                        foreach ($questions as $question): 
                            $question_id = $question['id'];
                            $response_text = $student_responses[$question_id] ?? null;

                            // Check if it's a Section Header
                            if ($question['question_type'] == 'section_header'):
                        ?>
                            <div class="mt-4 mb-2 p-2 border-bottom border-primary" style="background-color: #f8f9fa;">
                                <h5 class="text-danger mb-0"><i class="fas fa-bookmark mr-2"></i> <?php echo htmlspecialchars($question['question_text']); ?></h5>
                            </div>
                        <?php 
                            // Continue to the next item, do not render a question card or increment question number
                            continue;
                            endif;
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
                                            // MODIFIED: Changed scale to 0-3
                                            echo 'Scale (0-3)';
                                        }
                                        ?>
                                        
                                        <?php if ($question['question_type'] == 'scale'): ?>
                                            <div class="small mt-1 p-2 bg-light border rounded">
                                                **Scale Labels:** 0 - Not at all | 1 - Several days | 2 - More than half the days | 3 - Nearly every day
                                            </div>
                                        <?php endif; ?>
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

                                    <?php if ($assessment['status'] == 'completed'): ?>
                                        <div class="mt-3">
                                            <h6><i class="fas fa-reply text-success"></i> Your Response</h6>
                                            <?php if ($response_text !== null): ?>
                                                <?php if ($question['question_type'] == 'multiple_choice'): ?>
                                                    <span class="badge badge-success p-2"><?php echo htmlspecialchars($response_text); ?></span>
                                                <?php elseif ($question['question_type'] == 'scale'): ?>
                                                    <?php
                                                        // MODIFIED: Map numeric response to scale label
                                                        $numeric_response = (string) $response_text;
                                                        $label = $scale_labels[$numeric_response] ?? 'Invalid Response / Out of Scale (0-3)';
                                                    ?>
                                                    <span class="badge badge-info p-2">
                                                        **<?php echo htmlspecialchars($numeric_response); ?>** (<?php echo htmlspecialchars($label); ?>)
                                                    </span>
                                                <?php else: // text type ?>
                                                    <div class="border p-3 bg-light rounded response-box">
                                                        <?php echo nl2br(htmlspecialchars($response_text)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p class="text-danger">No response recorded for this question.</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php 
                        $question_number++;
                        endforeach; 
                        ?>
                        
                        <div class="text-center mt-4">
                            <?php if ($assessment['status'] == 'pending'): ?>
                                <a href="take_assessment.php?id=<?php echo $assessment_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Take Assessment
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
<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Fallback for formatDate (if not in functions.php)
if (!function_exists('formatDate')) {
    /**
     * Formats a MySQL datetime string into a readable format.
     * @param string $datetime
     * @return string
     */
    function formatDate($datetime) {
        // Example format: September 15, 2025 9:30 am
        return date('F j, Y g:i a', strtotime($datetime));
    }
}

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
$full_name = htmlspecialchars($user["full_name"]);
$username = htmlspecialchars($user["username"]);
$email = htmlspecialchars($user["email"]);


// Check if an assessment ID is provided
if (!isset($_GET['id'])) {
    // FIX: If no ID is provided (e.g., from the sidebar link), redirect to the assessment list page.
    redirect("take_assessment.php"); 
}

$student_assessment_id = (int) $_GET['id'];

// 1. Fetch student assessment details.
$assessment_query = "SELECT sa.id, a.title, a.description, sa.status, sa.submitted_at, u.full_name AS counselor_name, a.id AS assessment_template_id
                     FROM student_assessments sa
                     JOIN assessments a ON sa.assessment_id = a.id
                     JOIN users u ON a.created_by = u.id
                     WHERE sa.id = ? AND sa.student_id = ? AND sa.status IN ('completed', 'cancelled', 'pending')";

$stmt = mysqli_prepare($conn, $assessment_query);
mysqli_stmt_bind_param($stmt, "ii", $student_assessment_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    setFlashMessage("Assessment not found or does not belong to you.", "danger");
    redirect("take_assessment.php");
}

$assessment_data = mysqli_fetch_assoc($result);
$assessment_template_id = $assessment_data['assessment_template_id'];

// 2. Fetch all questions for this assessment template
$questions_query = "SELECT q.id, q.question_text, q.question_type, q.options
                    FROM questions q
                    WHERE q.assessment_id = ?
                    ORDER BY q.id ASC";

$stmt = mysqli_prepare($conn, $questions_query);
mysqli_stmt_bind_param($stmt, "i", $assessment_template_id);
mysqli_stmt_execute($stmt);
$questions_result = mysqli_stmt_get_result($stmt);

// 3. Fetch all responses for this specific student assessment
// Responses only exist if the status is 'completed'
$responses = [];
if ($assessment_data['status'] === 'completed') {
    $responses_query = "SELECT question_id, response_text
                        FROM responses
                        WHERE student_assessment_id = ?";
    $stmt = mysqli_prepare($conn, $responses_query);
    mysqli_stmt_bind_param($stmt, "i", $student_assessment_id);
    mysqli_stmt_execute($stmt);
    $responses_result = mysqli_stmt_get_result($stmt);

    // Map responses to an array keyed by question_id for easy lookup
    while ($response = mysqli_fetch_assoc($responses_result)) {
        $responses[$response['question_id']] = $response['response_text'];
    }
}

// Merge questions and responses for rendering
$questions_with_responses = [];
while ($question = mysqli_fetch_assoc($questions_result)) {
    $question_id = $question['id'];
    // Assign response or a default message
    $question['response'] = $responses[$question_id] ?? 'No response provided for this question.';
    $questions_with_responses[] = $question;
}

// Determine if there are responses to display
$has_responses = $assessment_data['status'] === 'completed' && !empty($questions_with_responses);


/**
 * Renders the student's response based on the question type.
 * This function encapsulates the display logic for different question formats.
 * * @param array $question Array containing question details and the 'response' text.
 * @return string The HTML formatted response.
 */
function renderResponse($question) {
    $response = htmlspecialchars($question['response']);

    // Handle scale type response display
    if ($question['question_type'] == 'scale') {
        // --- MODIFICATION START ---
        // Define the new scale labels (0-3)
        $scale_labels = [
            '0' => 'Not at all',
            '1' => 'Several days',
            '2' => 'More than half the days',
            '3' => 'Nearly every day',
        ];
        
        // Find the corresponding text label for the numerical response
        $response_label = $scale_labels[$response] ?? 'N/A'; // N/A if response is invalid

        return '<span class="h4 text-primary font-weight-bold">' . $response . ' (' . htmlspecialchars($response_label) . ')</span> / 3' .
                '<p class="small text-muted mb-0 mt-1">' . 
                '0 (Not at all), 1 (Several days), 2 (More than half the days), 3 (Nearly every day)' .
                '</p>';
        // --- MODIFICATION END ---
    }

    // Handle multiple choice response display
    if ($question['question_type'] == 'multiple_choice' && !empty($question['options'])) {
        $options = json_decode($question['options'], true);
        $html = '<ul class="list-unstyled mb-0">';
        if (is_array($options)) {
            foreach ($options as $option) {
                $option_html = htmlspecialchars($option);
                
                // Highlight the selected option
                if ($option_html === $response) {
                    $html .= '<li><i class="fas fa-dot-circle text-primary mr-2"></i><strong>' . $option_html . '</strong></li>';
                } else {
                    // Show unselected option for context
                    $html .= '<li><i class="far fa-circle text-muted mr-2"></i>' . $option_html . '</li>';
                }
            }
        } else {
            // Fallback for failed JSON decode
             $html .= '<li><i class="fas fa-check text-success mr-2"></i>' . $response . ' (Selected)</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    // Handle text/textarea and other types (default)
    return nl2br($response);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Assessment - Wellness Hub</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        /* Reusing and adapting styles from take_assessment.php for a consistent look */
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
        /* Highlight the 'View Assessments' link when on this page */
        .sidebar-link.active-view-assessment {
             color: white;
             background-color: #b30104;
        }
        .navbar.navbar-dark.bg-dark {
            background-color: #1e1e1e !important;
        }
        .content {
            padding: 20px;
        }
        .card-header {
            /* Primary color */
            background-color: #b30104 !important;
            color: #fff !important;
        }
        .response-card {
            /* Highlight box for the student's response */
            border-left: 4px solid #007bff; /* Use Bootstrap primary/blue for response highlight */
            margin-top: 10px;
            padding: 10px;
            background-color: #e9ecef; /* Light gray background for the response area */
            border-radius: 4px;
        }
        .section-header-card {
            margin-bottom: 1.5rem;
            border-left: 4px solid #17a2b8; /* Info color for section header */
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .card {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
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
                            <i class="fas fa-user-circle"></i> <?php echo $username; ?>
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
                    <div class="h5"><?php echo $full_name; ?></div>
                    <div class="small"><?php echo $email; ?></div>
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
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-clipboard-list"></i> Assessment: <?php echo htmlspecialchars($assessment_data['title']); ?></h4>
                    </div>
                    <div class="card-body">
                        <p class="lead"><?php echo htmlspecialchars($assessment_data['description']); ?></p>
                        <p class="text-muted">Assigned by: <strong><?php echo htmlspecialchars($assessment_data['counselor_name']); ?></strong></p>
                        <p class="mb-0">
                            Status: 
                            <?php if ($assessment_data['status'] == 'completed'): ?>
                                <span class="badge badge-success">Completed</span>
                                <small class="text-muted ml-2">Submitted on: <?php echo formatDate($assessment_data['submitted_at']); ?></small>
                            <?php elseif ($assessment_data['status'] == 'cancelled'): ?>
                                <span class="badge badge-danger">Cancelled</span>
                            <?php elseif ($assessment_data['status'] == 'pending'): ?>
                                <span class="badge badge-warning">Pending</span>
                                <a href="take_assessment.php?id=<?php echo $student_assessment_id; ?>" class="btn btn-warning btn-sm ml-2"><i class="fas fa-edit"></i> Resume Assessment</a>
                            <?php endif; ?>
                        </p>
                        
                        <hr>
                        
                        <?php if ($has_responses): ?>
                            <h5 class="mt-4 mb-3 text-primary"><i class="fas fa-check-circle"></i> Your Submitted Responses</h5>
                            
                            <?php
                            $question_number = 1;
                            foreach ($questions_with_responses as $question):
                                
                                // Handle Section Header/Category
                                if ($question['question_type'] == 'section_header'): ?>
                                    <div class="card bg-light mb-4 shadow-sm section-header-card">
                                        <div class="card-body">
                                            <h4 class="mb-0 text-info">
                                                <i class="fas fa-layer-group mr-2"></i> <?php echo htmlspecialchars($question['question_text']); ?>
                                            </h4>
                                        </div>
                                    </div>
                                <?php
                                    continue; // Skip the rest of the loop for section headers
                                endif;
                                ?>
                                
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <span class="text-muted mr-2"><?php echo $question_number; ?>.</span>
                                            <strong><?php echo htmlspecialchars($question['question_text']); ?></strong>
                                        </h6>
                                        
                                        <div class="response-card">
                                            <small class="text-primary font-weight-bold">Your Response:</small>
                                            <p class="card-text mb-0 mt-1">
                                                <?php echo renderResponse($question); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                            <?php
                            $question_number++;
                            endforeach;
                            ?>
                            
                        <?php elseif ($assessment_data['status'] == 'pending'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> This assessment is still **pending**. Responses will be visible here once you submit the assessment.
                                <a href="take_assessment.php?id=<?php echo $student_assessment_id; ?>" class="alert-link ml-2">Click here to continue the assessment.</a>
                            </div>
                        <?php else: // Status is cancelled ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> This assessment was **cancelled** by you, so there are no recorded responses to display.
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="take_assessment.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Assessments List</a>
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
<?php
// NOTE: Assuming config.php contains the database connection $conn
// NOTE: Assuming includes/functions.php contains isLoggedIn, isAdmin, redirect, getUserDetails, 
//       setFlashMessage, getFlashMessage, sanitize, and formatDate, along with all the helper functions
//       like calculateMultipleChoiceOutcome with the corrected SBQ-R logic.
require_once '../config.php';
require_once '../includes/functions.php';
// NOTE: You must include a PDF generation library here, e.g.:
require_once '../vendor/autoload.php'; // <--- Assumes Dompdf is installed here

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

// ADDED: Define current page for dynamic sidebar highlighting
$current_page = basename($_SERVER['PHP_SELF']);


// --- START: REPORT GENERATION HELPER FUNCTIONS (MODIFIED) ---
// NOTE: All helper functions (formatDate, getScaleLabel, getSeverityLabel, etc.) 
// have been removed from this file, as they are now correctly defined in 
// '../includes/functions.php' as per the previous solution.

use Dompdf\Dompdf; // Assuming Dompdf is loaded via autoload.php

/**
 * Generates the PDF content using the Dompdf library.
 * @param array $assessment Assessment and student details.
 * @param array $responses_data Array of question/response pairs.
 * @param mysqli $conn Database connection object.
 * @return string Binary PDF content.
 */
if (!function_exists('generateStudentAssessmentReportPdf')) {
    function generateStudentAssessmentReportPdf($assessment, $responses_data, $conn) {
        
        $current_section_name = 'Initial Assessment Section';
        $section_score = 0;
        $report_content = '';

        // Get overall MC outcome for report header (Uses the function from functions.php)
        $mc_outcome_data = calculateMultipleChoiceOutcome($conn, $assessment['id']);
        // Determine text for PDF header
        $mc_outcome_text = $mc_outcome_data['total_score'] >= 7 ? 'Positive' : 'Negative';


        // Build the HTML content for the PDF report
        $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 10pt; }
                    h1 { color: #b30104; border-bottom: 2px solid #b30104; padding-bottom: 5px; }
                    .details { margin-bottom: 15px; border: 1px solid #ccc; padding: 10px; background-color: #f9f9f9; }
                    .details p { margin: 5px 0; }
                    .question { font-weight: bold; margin-top: 15px; font-size: 12pt; color: #2b2b2b; }
                    .response { margin-left: 20px; padding: 5px; border-left: 3px solid #008f39; margin-top: 5px; }
                    .section-header { font-size: 14pt; color: #17a2b8; margin-top: 25px; border-bottom: 1px solid #17a2b8; padding-bottom: 3px; }
                    /* ADDED: Summary Box Style */
                    .summary-box { border: 1px solid #008f39; padding: 10px; margin-top: 10px; background-color: #e6ffe6; font-size: 11pt; }
                    .mc-summary-box { border: 1px solid #17a2b8; padding: 10px; margin-top: 10px; background-color: #f0f8ff; font-size: 11pt; }
                </style>
            </head>
            <body>
                <h1>Assessment Report: ' . htmlspecialchars($assessment['title']) . '</h1>
                <div class="details">
                    <p><strong>Student:</strong> ' . htmlspecialchars($assessment['student_name']) . '</p>
                    <p><strong>Submitted:</strong> ' . formatDate($assessment['submitted_at']) . '</p>
                    <p><strong>Department/Course:</strong> ' . htmlspecialchars($assessment['department']) . ' / ' . htmlspecialchars($assessment['course_year']) . '</p>
                    <p><strong>Overall Multiple-Choice Score:</strong> ' . $mc_outcome_data['total_score'] . ' (Outcome: ' . $mc_outcome_text . ')</p>
                </div>';

        $question_number = 1;
        foreach ($responses_data as $response) {
            
            // 1. Handle score aggregation for 'scale' questions
            if (isset($response['question_type']) && $response['question_type'] == 'scale') {
                // Ensure the response is treated as an integer for summation
                $section_score += intval($response['response_text']); 
            }

            // 2. Handle Section Header
            if (isset($response['question_type']) && $response['question_type'] == 'section_header') {
                 // Display summary for previous section
                 if ($question_number > 1 || $section_score > 0) {
                     $severity = getSeverityLabelText($section_score);
                     $report_content .= '<div class="summary-box"><strong>Scale Summary: ' . htmlspecialchars($current_section_name) . '</strong><br>Total Scale Score: ' . $section_score . '<br>Severity Level: ' . $severity . '</div>';
                 }
                 
                 // Display new section header
                 $report_content .= '<div class="section-header">' . htmlspecialchars($response['question_text']) . '</div>';
                 
                 // Reset variables
                 $current_section_name = $response['question_text'];
                 $section_score = 0;
                 $question_number = 1;
                 continue; 
            }
            
            // 3. Display individual question/response
            $report_content .= '<div class="question">' . $question_number . '. ' . htmlspecialchars($response['question_text']) . '</div>';
            
            $responseText = '';
            if (isset($response['question_type']) && $response['question_type'] == 'scale') {
                // MODIFIED: Use getScaleLabel for PDF
                $label = getScaleLabel($response['response_text']);
                $responseText = 'Scale Value: ' . $label . ' (' . htmlspecialchars($response['response_text']) . '/3)';
            } else {
                $responseText = nl2br(htmlspecialchars($response['response_text']));
            }
            
            $report_content .= '<div class="response">Response: ' . $responseText . '</div>';
            $question_number++;
        }
        
        // 4. Display summary for the FINAL section (Scale)
        if ($section_score > 0 || $question_number > 1) {
            $severity = getSeverityLabelText($section_score);
            $report_content .= '<div class="summary-box"><strong>Scale Summary: ' . htmlspecialchars($current_section_name) . '</strong><br>Total Scale Score: ' . $section_score . '<br>Severity Level: ' . $severity . '</div>';
        }

        // 5. Display Overall Multiple-Choice Outcome in the requested format
        $mc_score = $mc_outcome_data['total_score'];
        $mc_severity = ($mc_score >= 7) ? 'Risk Indicated' : 'No Indication';
        
        $report_content .= '
        <div class="mc-summary-box">
            <strong>Scale Summary: SBQ-R SUICIDE BEHAVIORS QUESTIONNAIRE-REVISED</strong><br>
            Total Scale Score: ' . $mc_score . '<br>
            Severity Level: ' . $mc_severity . '
        </div>';
        
        $html .= $report_content;
        $html .= '</body></html>';

        // Use Dompdf to generate the PDF
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output(); // Return PDF content (binary)
    }
}

/**
 * Generates the DOCX content (HTML with DOC headers).
 * @param array $assessment Assessment and student details.
 * @param array $responses_data Array of question/response pairs.
 * @param mysqli $conn Database connection object.
 * @return string HTML content to be downloaded as a DOC file.
 */
if (!function_exists('generateStudentAssessmentReportDocx')) {
    function generateStudentAssessmentReportDocx($assessment, $responses_data, $conn) {
        
        $current_section_name = 'Initial Assessment Section';
        $section_score = 0;
        $report_content = '';

        // Get overall MC outcome for report header (Uses the function from functions.php)
        $mc_outcome_data = calculateMultipleChoiceOutcome($conn, $assessment['id']);
        $mc_outcome_text = $mc_outcome_data['total_score'] >= 7 ? 'Positive' : 'Negative';

        // Build the HTML content for the DOCX report
        // Note: Using standard HTML structure for DOCX download
        $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <title>Assessment Report</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    h1 { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
                    .details p { margin: 5px 0; }
                    .question { font-weight: bold; margin-top: 20px; font-size: 14pt; color: #2b2b2b; }
                    .response { margin-left: 20px; padding: 10px; border: 1px solid #ccc; background-color: #f7f7f7; }
                    .section-header { font-size: 16pt; color: #17a2b8; margin-top: 25px; border-bottom: 2px solid #17a2b8; padding-bottom: 5px; }
                    /* ADDED: Summary Box Style */
                    .summary-box { border: 1px solid #008f39; padding: 10px; margin-top: 10px; background-color: #e6ffe6; font-size: 12pt; }
                    .mc-summary-box { border: 1px solid #17a2b8; padding: 10px; margin-top: 10px; background-color: #f0f8ff; font-size: 12pt; }
                </style>
            </head>
            <body>
                <h1>Assessment Report: ' . htmlspecialchars($assessment['title']) . '</h1>
                <div class="details">
                    <p><strong>Student:</strong> ' . htmlspecialchars($assessment['student_name']) . '</p>
                    <p><strong>Submitted:</strong> ' . formatDate($assessment['submitted_at']) . '</p>
                    <p><strong>Department/Course:</strong> ' . htmlspecialchars($assessment['department']) . ' / ' . htmlspecialchars($assessment['course_year']) . '</p>
                    <p><strong>Overall Multiple-Choice Score:</strong> ' . $mc_outcome_data['total_score'] . ' (Outcome: ' . $mc_outcome_text . ')</p>
                </div>
                <h2>Student Responses</h2>';

        $question_number = 1;
        foreach ($responses_data as $response) {
            
            // 1. Handle score aggregation for 'scale' questions
            if (isset($response['question_type']) && $response['question_type'] == 'scale') {
                $section_score += intval($response['response_text']); 
            }

            // 2. Handle Section Header
            if (isset($response['question_type']) && $response['question_type'] == 'section_header') {
                 // Display summary for previous section
                 if ($question_number > 1 || $section_score > 0) {
                     $severity = getSeverityLabelText($section_score);
                     $report_content .= '<div class="summary-box"><strong>Scale Summary: ' . htmlspecialchars($current_section_name) . '</strong><br>Total Scale Score: ' . $section_score . '<br>Severity Level: ' . $severity . '</div>';
                 }
                 
                 // Display new section header
                 $report_content .= '<div class="section-header">' . htmlspecialchars($response['question_text']) . '</div>';
                 
                 // Reset variables
                 $current_section_name = $response['question_text'];
                 $section_score = 0;
                 $question_number = 1;
                 continue; 
            }
            
            // 3. Display individual question/response
            $report_content .= '<div class="question">' . $question_number . '. ' . htmlspecialchars($response['question_text']) . '</div>';
            
            $responseText = '';
            if (isset($response['question_type']) && $response['question_type'] == 'scale') {
                // MODIFIED: Use getScaleLabel for DOCX
                $label = getScaleLabel($response['response_text']);
                $responseText = 'Scale Value: ' . $label . ' (' . htmlspecialchars($response['response_text']) . '/3)';
            } else {
                $responseText = nl2br(htmlspecialchars($response['response_text']));
            }

            $report_content .= '<div class="response">' . $responseText . '</div>';
            $question_number++;
        }

        // 4. Display summary for the FINAL section (Scale)
        if ($section_score > 0 || $question_number > 1) {
            $severity = getSeverityLabelText($section_score);
            $report_content .= '<div class="summary-box"><strong>Scale Summary: ' . htmlspecialchars($current_section_name) . '</strong><br>Total Scale Score: ' . $section_score . '<br>Severity Level: ' . $severity . '</div>';
        }
        
        // 5. Display Overall Multiple-Choice Outcome in the requested format
        $mc_score = $mc_outcome_data['total_score'];
        $mc_severity = ($mc_score >= 7) ? 'Risk Indicated' : 'No Indication';

        $report_content .= '
        <div class="mc-summary-box">
            <strong>Scale Summary: SBQ-R SUICIDE BEHAVIORS QUESTIONNAIRE-REVISED</strong><br>
            Total Scale Score: ' . $mc_score . '<br>
            Severity Level: ' . $mc_severity . '
        </div>';
        
        $html .= $report_content;
        $html .= '</body></html>';

        return $html; // Return HTML content
    }
}
// --- END: REPORT GENERATION HELPER FUNCTIONS (MODIFIED) ---


// --- START: REPORT DOWNLOAD LOGIC ---
if (isset($_GET['id']) && isset($_GET['download'])) {
    $student_assessment_id = $_GET['id'];
    $download_type = $_GET['download'];

    // 1. Fetch data required for the report
    
    // 1a. Fetch basic assessment data
    $assessment_query = "SELECT sa.id, a.title, a.description, u.full_name as student_name, u.department, u.course_year, u.section, sa.submitted_at, a.id as assessment_template_id
                             FROM student_assessments sa 
                             JOIN assessments a ON sa.assessment_id = a.id 
                             JOIN users u ON sa.student_id = u.id 
                             WHERE sa.id = ? AND sa.status = 'completed'";
    $stmt = mysqli_prepare($conn, $assessment_query);
    mysqli_stmt_bind_param($stmt, "i", $student_assessment_id);
    mysqli_stmt_execute($stmt);
    $assessment_result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($assessment_result) == 0) {
        die("Assessment not found or not completed.");
    }
    $assessment = mysqli_fetch_assoc($assessment_result);
    $assessment_template_id = $assessment['assessment_template_id'];

    // 1b. Fetch ALL questions for the template
    $questions_query = "SELECT id, question_text, question_type, options FROM questions WHERE assessment_id = ? ORDER BY id ASC";
    $stmt = mysqli_prepare($conn, $questions_query);
    mysqli_stmt_bind_param($stmt, "i", $assessment_template_id);
    mysqli_stmt_execute($stmt);
    $questions_result = mysqli_stmt_get_result($stmt);
    $questions = mysqli_fetch_all($questions_result, MYSQLI_ASSOC);
    
    // 1c. Fetch responses for the specific student assessment
    $responses_map = [];
    $responses_query = "SELECT question_id, response_text FROM responses WHERE student_assessment_id = ?";
    $stmt = mysqli_prepare($conn, $responses_query);
    mysqli_stmt_bind_param($stmt, "i", $student_assessment_id);
    mysqli_stmt_execute($stmt);
    $responses_result = mysqli_stmt_get_result($stmt);
    while ($response = mysqli_fetch_assoc($responses_result)) {
        $responses_map[$response['question_id']] = $response['response_text'];
    }

    // 1d. Merge questions and responses for report generation (create $responses_data)
    $responses_data = [];
    foreach ($questions as $question) {
        $data = [
            'question_text' => $question['question_text'],
            'question_type' => $question['question_type'],
            'response_text' => $responses_map[$question['id']] ?? 'N/A' // Use N/A for missing responses/headers
        ];
        $responses_data[] = $data;
    }


    $filename_base = "Assessment_Report_" . str_replace(" ", "_", $assessment['student_name']) . "_" . date('Ymd');
    $content = '';
    $filename = '';
    $mime_type = '';


    if ($download_type === 'pdf') {
        $content = generateStudentAssessmentReportPdf($assessment, $responses_data, $conn); // MODIFIED: Pass $conn
        $filename = $filename_base . ".pdf";
        $mime_type = 'application/pdf';
        
    } elseif ($download_type === 'docx') {
        $content = generateStudentAssessmentReportDocx($assessment, $responses_data, $conn); // MODIFIED: Pass $conn
        $filename = $filename_base . ".doc";
        $mime_type = 'application/vnd.ms-word';
    }

    if (!empty($content)) {
        // Send HTTP Headers for Download
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        ini_set('zlib.output_compression','0');
        
        // Output the content and stop execution
        echo $content;
        exit; 
    } else {
        die("Invalid download type or empty content.");
    }
}
// --- END: REPORT DOWNLOAD LOGIC ---


// Handle viewing specific assessment results (MODIFIED DATA FETCHING)
if (isset($_GET['id'])) {
    $student_assessment_id = $_GET['id'];
    
    // Get assessment details
    $assessment_query = "SELECT sa.id, a.title, a.description, u.full_name as student_name, u.department, u.course_year, u.section, sa.submitted_at, a.id as assessment_template_id 
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
    $assessment_template_id = $assessment['assessment_template_id']; // Use the fetched template ID
    
    // --- START: NEW DATA MERGING FOR DISPLAY ---
    
    // 1. Fetch ALL questions for this assessment template (including section_header)
    $questions_query = "SELECT q.id, q.question_text, q.question_type, q.options
                        FROM questions q
                        WHERE q.assessment_id = ?
                        ORDER BY q.id ASC";

    $stmt = mysqli_prepare($conn, $questions_query);
    mysqli_stmt_bind_param($stmt, "i", $assessment_template_id);
    mysqli_stmt_execute($stmt);
    $questions_result = mysqli_stmt_get_result($stmt);

    // 2. Fetch all responses for this specific student assessment
    $responses_map = [];
    $responses_query = "SELECT question_id, response_text
                        FROM responses
                        WHERE student_assessment_id = ?";
    $stmt = mysqli_prepare($conn, $responses_query);
    mysqli_stmt_bind_param($stmt, "i", $student_assessment_id);
    mysqli_stmt_execute($stmt);
    $responses_result = mysqli_stmt_get_result($stmt);

    // Map responses to an array keyed by question_id for easy lookup
    while ($response = mysqli_fetch_assoc($responses_result)) {
        $responses_map[$response['question_id']] = $response['response_text'];
    }

    // 3. Merge questions and responses for rendering
    $questions_with_responses = [];
    while ($question = mysqli_fetch_assoc($questions_result)) {
        $question_id = $question['id'];
        // Assign response or a default message
        $question['response'] = $responses_map[$question_id] ?? 'N/A'; // Headers will have 'N/A'
        $questions_with_responses[] = $question;
    }
    
    // --- END: NEW DATA MERGING FOR DISPLAY ---
    
    // Unset old $responses object which is no longer needed
    unset($responses);
    
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
        // Use mysqli_stmt_bind_param dynamically
        $bind_names[] = $stmt;
        $bind_names[] = &$types;
        for ($i=0; $i<count($params); $i++) {
            $bind_name = 'param'.$i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array('mysqli_stmt_bind_param',$bind_names);
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
/* ===============================
ADMIN THEME — WELLNESS HUB
=============================== */

/* Sidebar */
.sidebar {
    background-color: #2b2b2b;
    min-height: calc(100vh - 56px);
    color: #fff;
}
.sidebar-link {
    color: rgba(255, 255, 255, 0.8);
    padding: 10px 15px;
    display: block;
    text-decoration: none;
    border-radius: 4px;
    transition: background-color 0.3s, color 0.3s;
}
.sidebar-link:hover {
    color: #fff;
    background-color: #b30104;
    text-decoration: none;
}
.sidebar-link.active {
    background-color: #b30104;
    color: #fff;
}
.sidebar-link i {
    margin-right: 10px;
}

/* Navbar */
.navbar-dark.bg-dark {
    background-color: #1e1e1e !important;
}

/* Content Layout */
.content {
    padding: 20px;
    animation: fadeIn 0.5s ease-in-out;
}

/* Cards */
.card {
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: none;
    transition: transform 0.4s ease, box-shadow 0.4s ease;
}
.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 14px rgba(0,0,0,0.25);
}

/* Card Headers */
.card-header {
    background-color: #b30104; /* red theme for headers */
    color: #fff;
    font-weight: 500;
}

/* Alerts */
.alert {
    animation: fadeIn 0.6s ease;
    border-radius: 6px;
}

/* Buttons (Primary = #008f39) */
.btn-primary {
    background-color: #008f39 !important;
    border-color: #008f39 !important;
}
.btn-primary:hover, 
.btn-primary:focus {
    background-color: #00732e !important;
    border-color: #00732e !important;
}

/* Custom Download Button Colors */
.btn-pdf {
    background-color: #dc3545 !important; /* Standard Danger Red */
    border-color: #dc3545 !important;
    color: #fff !important;
}
.btn-pdf:hover {
    background-color: #c82333 !important;
    border-color: #c82333 !important;
}
.btn-word {
    background-color: #007bff !important; /* Standard Info Blue */
    border-color: #007bff !important;
    color: #fff !important;
}
.btn-word:hover {
    background-color: #0056b3 !important;
    border-color: #0056b3 !important;
}


/* Response Cards */
.response-card {
    margin-bottom: 1rem;
    border-left: 4px solid #008f39; /* green accent */
}

/* ADDED: Section Header Card style for Admin View */
.section-header-card {
    margin-bottom: 1.5rem;
    border-left: 4px solid #17a2b8; /* Info color for section header */
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    background-color: #f8f9fa; /* Light background */
}

/* Fade-in animation */
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
                <a href="appointment_management.php" class="sidebar-link<?php echo ($current_page == 'appointment_management.php' ? ' active' : ''); ?>"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="view_assessments.php" class="sidebar-link<?php echo ($current_page == 'view_assessments.php' ? ' active' : ''); ?>"><i class="fas fa-chart-bar"></i> View Assessments</a>
                <a href="counselor_schedule.php" class="sidebar-link<?php echo ($current_page == 'counselor_schedule.php' ? ' active' : ''); ?>"><i class="fas fa-calendar-alt"></i> My Schedule & Available Hours</a>
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
                            
                            <div class="mb-4 text-right">
                                <a href="view_assessments.php?id=<?php echo $assessment['id']; ?>&download=pdf" class="btn btn-pdf">
                                    <i class="fas fa-file-pdf"></i> Download Report (PDF)
                                </a>
                                <a href="view_assessments.php?id=<?php echo $assessment['id']; ?>&download=docx" class="btn btn-word">
                                    <i class="fas fa-file-word"></i> Download Report (Word)
                                </a>
                            </div>
                            <hr>
                            
                            <h5 class="mb-3">Responses</h5>
                            
                            <?php 
                            // Helper function to display the section summary in HTML
                            // This function relies on getSeverityLabel, which is now sourced from functions.php
                            function displaySectionSummary($sectionName, $score) {
                                // Only display summary if there was at least one scored question in the section
                                if ($score > 0) {
                                    $severityHtml = getSeverityLabel($score);
                                    echo '
                                    <div class="card mb-4 shadow-sm border-success">
                                        <div class="card-body bg-light">
                                            <h5 class="mb-0 text-success"><i class="fas fa-chart-line mr-2"></i> Scale Summary: ' . htmlspecialchars($sectionName) . '</h5>
                                            <p class="mt-2 mb-0"><strong>Total Scale Score:</strong> <span class="badge badge-secondary">' . $score . '</span></p>
                                            <p class="mb-0"><strong>Severity Level:</strong> ' . $severityHtml . '</p>
                                        </div>
                                    </div>';
                                }
                            }
                            
                            $question_number = 1;
                            $section_score = 0; // Initialize score for the current section
                            $current_section_name = 'Initial Assessment Section'; // Default name for the first section
                            
                            // Iterate over the combined array for display (NEW LOGIC)
                            foreach ($questions_with_responses as $response): 
                                
                                // 1. Handle score aggregation for 'scale' questions
                                if ($response['question_type'] == 'scale') {
                                    // The scale response is the numeric value (0-3)
                                    // Use intval() to ensure the string response is treated as a number
                                    $section_score += intval($response['response']); 
                                }

                                // 2. Handle Section Header/Category
                                if ($response['question_type'] == 'section_header'): 
                                    
                                    // a. Display the summary for the PREVIOUS section
                                    // Only display if we had any scored questions in the previous segment
                                    if ($question_number > 1 || $section_score > 0) {
                                        displaySectionSummary($current_section_name, $section_score);
                                    }
                                    
                                    // b. Display the new section header
                                    ?>
                                    <div class="card bg-light mb-4 shadow-sm section-header-card">
                                        <div class="card-body">
                                            <h4 class="mb-0 text-info">
                                                <i class="fas fa-layer-group mr-2"></i> <?php echo htmlspecialchars($response['question_text']); ?>
                                            </h4>
                                        </div>
                                    </div>
                                    <?php
                                    
                                    // c. Reset variables for the new section
                                    $current_section_name = $response['question_text']; // Set the new section name
                                    $section_score = 0; // Reset the score
                                    $question_number = 1; 
                                    continue; // Skip the rest for section headers
                                endif;
                                
                            ?>
                            <div class="card response-card">
                                <div class="card-body">
                                    <h6 class="card-title">Question <?php echo $question_number; ?>: <?php echo htmlspecialchars($response['question_text']); ?></h6>
                                    
                                    <div class="mt-2">
                                        <strong>Response:</strong>
                                        <?php if ($response['question_type'] == 'scale'): ?>
                                            <div class="progress mt-2">
                                                <?php 
                                                $value = intval($response['response']); 
                                                $percentage = ($value / 3) * 100;
                                                
                                                // Determine color based on value
                                                $color = 'bg-success'; 
                                                if ($value >= 3) {
                                                    $color = 'bg-danger'; 
                                                } elseif ($value == 2) {
                                                    $color = 'bg-warning'; 
                                                }
                                                
                                                // Determine Label (using getScaleLabel from functions.php)
                                                $label = getScaleLabel($value);
                                                ?>
                                                <div class="progress-bar <?php echo $color; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $value; ?>" aria-valuemin="0" aria-valuemax="3">
                                                    <?php echo $label; ?> (<?php echo $value; ?>/3)
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($response['response'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php 
                            $question_number++;
                            endforeach; 
                            
                            // 3. Display the summary for the FINAL section (Scale)
                            displaySectionSummary($current_section_name, $section_score);
                            ?>
                            
                            <?php 
                            // --- START: MODIFIED SBQ-R OUTCOME TO MATCH REQUESTED FORMAT ---
                            $mc_outcome_data = calculateMultipleChoiceOutcome($conn, $assessment['id']);
                            $mc_score = $mc_outcome_data['total_score'];
                            $mc_severity = '';
                            $mc_severity_class = 'border-info'; 
                            $mc_severity_text = 'text-info'; 
                            
                            if ($mc_score >= 7) {
                                $mc_severity = 'Risk Indicated';
                                $mc_severity_class = 'border-danger'; // Red for risk
                                $mc_severity_text = 'text-danger';
                            } else {
                                $mc_severity = 'No Indication'; 
                                $mc_severity_class = 'border-success'; // Green for no risk
                                $mc_severity_text = 'text-success';
                            }

                            $mc_title = 'SBQ-R SUICIDE BEHAVIORS QUESTIONNAIRE-REVISED'; 
                            ?>

                            <div class="card mb-4 shadow-sm <?php echo $mc_severity_class; ?>">
                                <div class="card-body bg-light">
                                    <h5 class="mb-0 <?php echo $mc_severity_text; ?>">
                                        <i class="fas fa-chart-line mr-2"></i> Scale Summary: <?php echo htmlspecialchars($mc_title); ?>
                                    </h5>
                                    <p class="mt-2 mb-0"><strong>Total Scale Score:</strong> <span class="badge badge-secondary"><?php echo $mc_score; ?></span></p>
                                    <p class="mb-0"><strong>Severity Level:</strong> <?php echo htmlspecialchars($mc_severity); ?></p>
                                    <small class="text-muted d-block mt-1">Screening Result: <?php echo $mc_outcome_data['outcome_html']; ?> (7+ Positive, 6- Negative)</small>
                                </div>
                            </div>
                            <?php 
                            // --- END: MODIFIED SBQ-R OUTCOME ---
                            ?>
                            
                            <div class="text-center mt-4">
                                <a href="view_assessments.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to All Assessments</a>
                            </div>
                        </div>
                    </div>
                
                <?php elseif (isset($assessment_info)): ?>
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
                                                        <a href="view_assessments.php?id=<?php echo $row['id']; ?>&download=pdf" class="btn btn-pdf btn-sm" title="Download PDF Report">
                                                            <i class="fas fa-file-pdf"></i>
                                                        </a>
                                                        <a href="view_assessments.php?id=<?php echo $row['id']; ?>&download=docx" class="btn btn-word btn-sm" title="Download Word Document">
                                                            <i class="fas fa-file-word"></i>
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
                    <h2><i class="fas fa-chart-bar"></i> Assessment Results</h2>
                    <p class="lead">View and analyze student assessment responses.</p>
                    
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
                        <div class="card-header text-white" style="background-color: #b30104;">
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
                                                <th>Overall Severity</th>
                                                <th>SBQ-R</th> <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = mysqli_fetch_assoc($completed_assessments)): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                                    <td><?php echo formatDate($row['submitted_at']); ?></td>
                                                    
                                                    <?php 
                                                        // Calculate overall scale score and severity
                                                        $overall_score_data = calculateTotalScoreAndSeverity($conn, $row['id']); 
                                                        // Calculate overall MC outcome
                                                        $mc_outcome_data = calculateMultipleChoiceOutcome($conn, $row['id']);
                                                    ?>
                                                    <td class="text-center">
                                                        <?php echo $overall_score_data['severity_html']; ?> 
                                                        <br><small class="text-muted">(Score: <?php echo $overall_score_data['total_score']; ?>)</small>
                                                    </td>
                                                    
                                                    <td class="text-center">
                                                        <?php echo $mc_outcome_data['outcome_html']; ?> 
                                                    </td>
                                                    
                                                    <td>
                                                        <a href="view_assessments.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-eye"></i> View Responses
                                                        </a>
                                                        <a href="view_assessments.php?id=<?php echo $row['id']; ?>&download=pdf" class="btn btn-pdf btn-sm" title="Download PDF Report">
                                                            <i class="fas fa-file-pdf"></i>
                                                        </a>
                                                        <a href="view_assessments.php?id=<?php echo $row['id']; ?>&download=docx" class="btn btn-word btn-sm" title="Download Word Document">
                                                            <i class="fas fa-file-word"></i>
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
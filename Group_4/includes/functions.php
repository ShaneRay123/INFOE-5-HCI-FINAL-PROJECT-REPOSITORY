<?php
// Note: Dompdf and PhpWord classes and related logic are removed, as they should be
// included and initialized only in the scripts that require reporting functionality (e.g., view_assessments.php).

// --- START: ADDED FOR REPORTING FUNCTIONALITY ---
// This line is required to use the Dompdf class from the Composer autoloader
use Dompdf\Dompdf;
// --- END: ADDED FOR REPORTING FUNCTIONALITY ---

// Global variables declaration
global $conn;

// =========================================================================
// --- CORE UTILITY FUNCTIONS ---
// =========================================================================

/**
 * Checks if a user is currently logged in.
 * @return bool True if a session user ID exists, false otherwise.
 */
function isLoggedIn() {
	return isset($_SESSION['user_id']);
}

/**
 * Redirects the user to a specified URL and terminates script execution.
 * @param string $url The URL to redirect to.
 */
function redirect($url) {
	header("Location: $url");
	exit;
}

/**
 * Checks if the logged-in user is an administrator or counselor.
 * @return bool True if the user role is 'admin' or 'counselor'.
 */
function isAdmin() {
	return isset($_SESSION['role']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'counselor');
}

/**
 * Checks if the logged-in user is a student.
 * @return bool True if the user role is 'student';
 */
function isStudent() {
	return isset($_SESSION['role']) && $_SESSION['role'] == 'student';
}

/**
 * Sanitize input data to prevent XSS and SQL injection.
 * NOTE: Assumes $conn is available globally (defined in config.php).
 * @param string $data The input string to sanitize.
 * @return string The sanitized string.
 */
function sanitize($data) {
	global $conn;
	if (!isset($conn)) {
		// Fallback if connection is not available (e.g., in unit tests or early execution)
		return htmlspecialchars(trim($data));
	}
	// Using mysqli_real_escape_string requires a live connection ($conn) to be available
	return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

/**
 * Sets a flash message in the session for temporary display.
 * @param string $message The message content.
 * @param string $type The message type (e.g., 'info', 'success', 'danger').
 */
function setFlashMessage($message, $type = 'info') {
	$_SESSION['flash_message'] = $message;
	$_SESSION['flash_type'] = $type;
}

/**
 * Retrieves and clears the flash message from the session.
 * @return array|null An array containing 'message' and 'type', or null if none exists.
 */
function getFlashMessage() {
	if (isset($_SESSION['flash_message'])) {
		$message = $_SESSION['flash_message'];
		$type = $_SESSION['flash_type'];
		unset($_SESSION['flash_message']);
		unset($_SESSION['flash_type']);
		return ['message' => $message, 'type' => $type];
	}
	return null;
}

/**
 * Fetches user details from the database by user ID.
 * @param int $user_id The ID of the user.
 * @return array|null The user's row data or null.
 */
function getUserDetails($user_id) {
	global $conn;
	// Check if connection is available before attempting database operations
	if (!isset($conn)) {
		return null;	
	}
	$sql = "SELECT * FROM users WHERE id = ?";
	$stmt = mysqli_prepare($conn, $sql);
	mysqli_stmt_bind_param($stmt, "i", $user_id);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	
	if ($row = mysqli_fetch_assoc($result)) {
		return $row;
	}
	
	return null;
}

/**
 * Logs a security-relevant event to the security_log table.
 * @param mysqli $conn The database connection object.
 * @param string $eventType The type of event (e.g., 'LOGIN_FAILURE', 'PASSWORD_CHANGE_SUCCESS').
 * @param int|null $userId The ID of the user involved. Pass null if the user is not authenticated.
 * @param string|null $description Optional descriptive text.
 * @param string|null $usernameAttempted The username used during the attempt (crucial for failures).
 */
function logSecurityEvent($conn, $eventType, $userId = null, $description = null, $usernameAttempted = null) {
	if (!$conn) {
		error_log("Attempted to log security event but database connection is unavailable.");
		return;
	}
	
	// Determine the IP address
	$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
	
	// Initial fields and types
	$query_fields = ["`event_type`", "`description`", "`ip_address`", "`username_attempted`"];
	$query_placeholders = ["?", "?", "?", "?"];
	$bind_types = "ssss";
	$bind_values = [(string)$eventType, $description ?? '', $ipAddress, $usernameAttempted ?? ''];

	// If user ID is known, add it to the query
	if ($userId !== null && (int)$userId > 0) {
		array_unshift($query_fields, "`user_id`");
		array_unshift($query_placeholders, "?");
		$bind_types = "i" . $bind_types;
		array_unshift($bind_values, (int)$userId);
	}
	
	$query = "INSERT INTO security_log (" . implode(", ", $query_fields) . ") VALUES (" . implode(", ", $query_placeholders) . ")";

	if ($stmt = mysqli_prepare($conn, $query)) {
		// Dynamically bind parameters using the procedural style
		mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_values);

		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	} else {
		error_log("Security log statement preparation failed: " . mysqli_error($conn));
	}
}


/**
 * Formats a SQL datetime string into a readable date and time format for reports.
 * @param string $datetime The datetime string (e.g., YYYY-MM-DD HH:MM:SS).
 * @return string The formatted date and time string (e.g., "September 15, 2025 at 10:30 AM").
 */
function formatDate($datetime) {
	if (empty($datetime)) {
		return 'N/A';
	}
	// Check if the input is a date (YYYY-MM-DD) or datetime
	if (strlen($datetime) <= 10 || (strtotime($datetime) !== false && date('H:i:s', strtotime($datetime)) == '00:00:00')) {
		return date("F j, Y", strtotime($datetime));
	}
	return date("F j, Y \a\t g:i A", strtotime($datetime));	
}

/**
 * Formats a SQL time string for display.
 * @param string $time The time string (e.g., HH:MM:SS).
 * @return string The formatted time string (e.g., "10:30 AM").
 */
function formatTime($time) {
	if (empty($time) || $time === '00:00:00') return 'N/A';
	return date("g:i A", strtotime($time));
}


// =========================================================================
// --- REPORT GENERATION FUNCTIONS ---
// =========================================================================

/**
 * Retrieves the Base64 encoded string for the University Logo.
 * NOTE: You MUST update this function with the actual Base64 string of your image
 * ('ua1 (1).jpg') for the logo to render reliably in Dompdf/PDF reports.
 * * You can generate this by running a small script like:
 * $data = file_get_contents('ua1 (1).jpg');
 * echo 'data:image/jpeg;base64,' . base64_encode($data);	
 * * @return string The Base64 data URI of the logo.
 */
function getLogoBase64() {
	// === START: REPLACE THIS WITH YOUR ACTUAL BASE64 STRING ===
	// If you cannot generate the Base64 string, set this to an empty string.
	$logo_base64 = "";	

	// Fallback/Attempt to read the file locally if Base64 is empty.
	// This is unreliable for Dompdf but works for standard web pages/DOCX.
	if (empty($logo_base64) && file_exists(__DIR__ . '/ua1 (1).jpg')) {
		$data = file_get_contents(__DIR__ . '/ua1 (1).jpg');
		// Assuming the file is a JPEG. Adjust 'image/jpeg' if needed (e.g., 'image/png').
		$logo_base64 = 'data:image/jpeg;base64,' . base64_encode($data);
	}
	// === END: REPLACE THIS WITH YOUR ACTUAL BASE64 STRING ===
	
	return $logo_base64;
}

/**
 * Generates the HTML for the University Header, often used in reports.
 * @return string The HTML code for the university header.
 */
function getUniversityHeaderHtml() {
	$logo_base64 = getLogoBase64();
	
	$logo_html = '';
	// Use an 80px width for the logo
	$logo_style = 'width: 80px; height: auto;';

	if (!empty($logo_base64)) {
		// Use Base64 string for maximum compatibility (especially with Dompdf)
		$logo_html = '<img src="' . $logo_base64 . '" alt="University Logo" style="' . $logo_style . '">';
	} else {
		// Fallback to relative path
		$logo_html = '<img src="ua1 (1).jpg" alt="University Logo" style="' . $logo_style . '">';
	}
	
	// This implementation correctly centers the logo and text block as a single unit.
	$header_html = '
	<div style="margin-bottom: 20px; text-align: center;">
		<div style="margin-bottom: 5px;">' . $logo_html . '</div>

		<div>
			<p style="margin: 0; font-size: 10pt; color: #555;">Republic of the Philippines</p>
			<h1 style="color: #000; font-size: 10pt; margin: 5px 0 0 0;"><strong>UNIVERSITY OF ANTIQUE</strong></h1>
			<p style="margin: 0; font-size: 10pt; color: #555;"><strong>Sibalóm, Antique</strong></p>
		</div>
		<hr style="border: 1px solid #b30104; width: 80%; margin: 10px auto 10px auto; display: block; border-style: solid none none;">
	</div>';
	
	return $header_html;
}

/**
 * ADDED: Helper function for scale labels (0-3 scale).
 * @param int $value The scale value (0, 1, 2, or 3).
 * @return string The corresponding text label.
 */
function getScaleLabel($value) {
	$value = intval($value);
	switch ($value) {
		case 0: return 'Not at all';
		case 1: return 'Several days';
		case 2: return 'More than half the days';
		case 3: return 'Nearly every day';
		default: return 'Invalid Scale Value';
	}
}

// =========================================================================
// --- START: SCORING AND OUTCOME FUNCTIONS ---
// =========================================================================

/**
 * ADDED: Helper function for scale score severity (for display/HTML).
 * @param int $score The total scale score.
 * @return string The HTML badge for severity.
 */
function getSeverityLabel($score) {
	$score = intval($score);
	if ($score >= 15) {
		return '<span class="badge badge-danger">Severe</span>';
	} elseif ($score >= 10) {
		return '<span class="badge badge-warning">Moderate</span>';
	} elseif ($score >= 5) {
		return '<span class="badge badge-info">Mild</span>';
	} elseif ($score >= 1) {
		return '<span class="badge badge-success">Minimal</span>';
	} else {
		return '<span class="badge badge-secondary">No Indication</span>';
	}
}

/**
 * ADDED: Helper function for scale score severity (for plain text/report use).
 * @param int $score The total scale score.
 * @return string The plain text severity label.
 */
function getSeverityLabelText($score) {
	$score = intval($score);
	if ($score >= 15) {
		return 'Severe';
	} elseif ($score >= 10) {
		return 'Moderate';
	} elseif ($score >= 5) {
		return 'Mild';
	} elseif ($score >= 1) {
		return 'Minimal';
	} else {
		return 'No Indication';
	}
}

/**
 * ADDED: Helper function to calculate total scale score and return severity label for the entire assessment.
 * @param mysqli $conn The database connection.
 * @param int $student_assessment_id The ID of the student assessment.
 * @return array The total score and its severity in HTML format.
 */
function calculateTotalScoreAndSeverity($conn, $student_assessment_id) {
	global $conn;
	// Check if connection is available
	if (!isset($conn)) return ['total_score' => 0, 'severity_html' => '<span class="badge badge-secondary">N/A</span>'];	
	
	// Query to sum up all responses from 'scale' questions for a given student_assessment
	$score_query = "
		SELECT SUM(CAST(r.response_text AS UNSIGNED)) AS total_score
		FROM responses r
		JOIN questions q ON r.question_id = q.id
		WHERE r.student_assessment_id = ? AND q.question_type = 'scale'";

	$stmt = mysqli_prepare($conn, $score_query);
	mysqli_stmt_bind_param($stmt, "i", $student_assessment_id);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	$data = mysqli_fetch_assoc($result);
	$total_score = intval($data['total_score']);
	
	// Use existing severity function to get HTML badge
	$severity_html = getSeverityLabel($total_score);	
	
	return [
		'total_score' => $total_score,
		'severity_html' => $severity_html
	];
}

/**
 * MODIFIED: Correctly implements the Multiple Choice (SBQ-R) logic (7+ Positive/Red, 6- Negative/Green).
 * @param mysqli $conn The database connection.
 * @param int $student_assessment_id The ID of the student assessment.
 * @return array The total score and its outcome in HTML format.
 */
function calculateMultipleChoiceOutcome($conn, $student_assessment_id) {
	global $conn;
	if (!isset($conn)) return ['total_score' => 0, 'outcome_html' => '<span class="badge badge-secondary">N/A</span>'];

	// Assumption: Multiple-choice questions that need to be summed have their numerical score (e.g., 0 or 1) stored in response_text
	$score_query = "
		SELECT SUM(CAST(r.response_text AS UNSIGNED)) AS total_mc_score
		FROM responses r
		JOIN questions q ON r.question_id = q.id
		WHERE r.student_assessment_id = ? AND q.question_type = 'multiple_choice'"; // Target 'multiple_choice' type

	$stmt = mysqli_prepare($conn, $score_query);
	mysqli_stmt_bind_param($stmt, "i", $student_assessment_id);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	$data = mysqli_fetch_assoc($result);
	$total_mc_score = intval($data['total_mc_score']);
	
	$outcome = '';
	if ($total_mc_score >= 7) {
		// Positive 7 above is RED (badge-danger)
		$outcome = '<span class="badge badge-danger">Positive (' . $total_mc_score . ')</span>';
	} else { // 6 or below
		// Negative 6 below is GREEN (badge-success)
		$outcome = '<span class="badge badge-success">Negative (' . $total_mc_score . ')</span>';
	}
	
	return [
		'total_score' => $total_mc_score,
		'outcome_html' => $outcome
	];
}

/**
 * ADDED: Helper function to calculate total multiple-choice score and return the SBQ-R outcome label in plain text.
 * @param mysqli $conn The database connection.
 * @param int $student_assessment_id The ID of the student assessment.
 * @return array The total score and its outcome in plain text.
 */
function calculateMultipleChoiceOutcomeText($conn, $student_assessment_id) {
	global $conn;
	if (!isset($conn)) return ['total_score' => 0, 'outcome_text' => 'N/A'];
	
	// Re-run or reuse the logic to get the total score
	$score_query = "
		SELECT SUM(CAST(r.response_text AS UNSIGNED)) AS total_mc_score
		FROM responses r
		JOIN questions q ON r.question_id = q.id
		WHERE r.student_assessment_id = ? AND q.question_type = 'multiple_choice'";

	$stmt = mysqli_prepare($conn, $score_query);
	mysqli_stmt_bind_param($stmt, "i", $student_assessment_id);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	$data = mysqli_fetch_assoc($result);
	$total_mc_score = intval($data['total_mc_score']);
	
	$outcome_text = $total_mc_score >= 7 ? 'Positive' : 'Negative';
	
	return [
		'total_score' => $total_mc_score,
		'outcome_text' => $outcome_text
	];
}

// =========================================================================
// --- END: SCORING AND OUTCOME FUNCTIONS ---
// =========================================================================


/**
 * MODIFIED: Helper function to generate the reusable HTML structure of the report content.
 * **Includes a dedicated, color-coded summary box for SBQ-R using the requested format.**
 */
function buildReportHtml($assessment, $responses_data) {
	// 0. Get the header HTML and prepend it
	$university_header = getUniversityHeaderHtml();
	
	// NOTE: We need the database connection here to call the outcome function
	global $conn;
	$mc_outcome_data = calculateMultipleChoiceOutcomeText($conn, $assessment['id']);	

	// --- START: SBQ-R Styling Calculation (7+ RED, 6- GREEN) ---
	$mc_total_score = $mc_outcome_data['total_score'];
	$mc_outcome_text = $mc_outcome_data['outcome_text']; // Either 'Positive' or 'Negative'

	// 1. Calculate the requested Severity Level text
	$mc_severity_level = ($mc_total_score >= 7) ? 'Risk Indicated' : 'No Indication';

	// Determine color based on score (7+ Risk Indicated/Red, 6- No Indication/Green)
	$mc_color_bg = ($mc_total_score >= 7) ? '#dc3545' : '#008f39'; // Red for Positive/Risk, Green for Negative/No Indication
	$mc_color_text = '#ffffff'; // White text
	$mc_title_bg = ($mc_total_score >= 7) ? '#f8d7da' : '#d4edda'; // Light background for the summary box
	// --- END: SBQ-R Styling Calculation ---

	// Basic inline CSS for predictable styling across Dompdf/Word.
	$html = '<!DOCTYPE html>
	<html>
	<head>
		<title>Assessment Report: ' . htmlspecialchars($assessment['student_name']) . '</title>
		<style>
			body { font-family: sans-serif; margin: 20px; font-size: 11pt; }	
			h1, h2, h3 { color: #b30104; }
			h1 { font-size: 18pt; }	
			h2 { font-size: 14pt; margin-top: 10px; }	
			h3 { font-size: 14pt; margin-top: 15px; }	
			p { margin: 0 0 5px 0; font-size: 10pt; }	
			.header-info { border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; background-color: #f9f9f9; }	
			.header-info p { margin: 3px 0; }	
			.response-block { border: 1px solid #008f39; padding: 8px; margin-bottom: 10px; border-left: 5px solid #008f39; page-break-inside: avoid; }	
			.question-title { font-weight: bold; color: #1e1e1e; margin-bottom: 3px; font-size: 10pt; }	
			.response-text { margin-top: 3px; padding-left: 8px; white-space: pre-wrap; font-size: 9pt; }	
			
			/* Scale Summary Box Style (for Scale sections) */
			.summary-box {
				border: 1px solid #b30104; /* Primary red accent */
				padding: 10px;
				margin: 15px 0;
				background-color: #ffeaea; /* Light red/pink background */
				page-break-inside: avoid;
				font-size: 10pt;
				line-height: 1.6;
			}
			.summary-box strong {
				color: #b30104;
				font-size: 11pt;
			}

			/* Scale bar styling for report generation */
			.progress-bar-scale {
				background-color: #e9ecef; height: 16px; border-radius: 4px; overflow: hidden; width: 100%;	
			}
			.progress-bar-scale div {
				height: 100%; text-align: center; color: white; line-height: 16px; font-size: 9pt; font-weight: bold;	
				transition: width 0.6s ease;
			}
			.bg-danger { background-color: #dc3545; }
			.bg-warning { background-color: #ffc107; }
			.bg-success { background-color: #008f39; }
		</style>
	</head>
	<body>
		' . $university_header . '
		<div style="text-align: right; font-size: 9pt; color: #666;">Report Generated on: ' . date("F j, Y") . '</div>
		<h1>Student Assessment Report</h1>
		
		<h2 style="color: #008f39;">' . htmlspecialchars($assessment['title']) . '</h2>
		<p style="font-style: italic; margin-bottom: 10px;">' . htmlspecialchars($assessment['description']) . '</p>
		
		<div class="header-info">
			<h3>Student Details</h3>
			<p><strong>Name:</strong> ' . htmlspecialchars($assessment['student_name']) . '</p>
			<p><strong>Department:</strong> ' . htmlspecialchars($assessment['department']) . '</p>
			<p><strong>Course/Year:</strong> ' . htmlspecialchars($assessment['course_year']) . '</p>
			<p><strong>Section:</strong> ' . htmlspecialchars($assessment['section']) . '</p>
			<p><strong>Submitted On:</strong> ' . formatDate($assessment['submitted_at']) . '</p>
		</div>

		<div style="
			border: 2px solid ' . $mc_color_bg . ';
			padding: 10px;
			margin: 15px 0;
			background-color: ' . $mc_title_bg . ';
			font-size: 10pt;
			page-break-inside: avoid;
			color: #1e1e1e;
		">
			<p style="margin: 0; font-size: 11pt;">
				<strong>Scale Summary: SBQ-R SUICIDE BEHAVIORS QUESTIONNAIRE-REVISED</strong>
			</p>
			<p style="margin: 5px 0 0 0;">
				<strong>Total Scale Score:</strong> ' . $mc_total_score . '
			</p>
			<p style="margin: 3px 0 0 0; color: ' . $mc_color_bg . '; font-weight: bold;">
				Severity Level: ' . $mc_severity_level . '
			</p>
		</div>
		<h3 style="margin-top: 30px;">Assessment Responses</h3>';

	// --- START: LOGIC FOR SECTION SCORING AND SUMMARY ---
	$question_number = 1;
	$current_section_name = 'Introduction'; // Default initial section name before the first header
	$section_score = 0;
	// --- END: LOGIC FOR SECTION SCORING AND SUMMARY ---

	foreach ($responses_data as $response) {
		
		// 1. Check for SECTION HEADER (Triggers previous section summary)
		if ($response['question_type'] == 'section_header') {
			
			// A. Display summary for PREVIOUS section if it had scale questions
			// Only display if it's not the very first thing (question_number > 1)	
			// OR if a score was accumulated
			if ($question_number > 1 || $section_score > 0) {
				$severity = getSeverityLabelText($section_score); // Get plain text severity
				$html .= '<div class="summary-box">
					<strong>Scale Summary: ' . htmlspecialchars($current_section_name) . '</strong><br>
					Total Scale Score: ' . $section_score . '<br>
					Severity Level: ' . $severity . '
				</div>';
			}
			
			// B. Update section state for the NEW section
			$current_section_name = $response['question_text']; // New section name
			$section_score = 0; // Reset score for the new section
			$question_number = 1; // Reset question numbering for the new section

			// C. Render section header in the report
			$html .= '<h4 style="color: #555; background-color: #f0f0f0; padding: 5px 10px; margin-top: 25px; border-left: 5px solid #b30104; page-break-after: avoid;">' . htmlspecialchars($current_section_name) . '</h4>';

			continue;
		}
		
		// 2. Handle Scale Question Scoring (Accumulate score)
		if ($response['question_type'] == 'scale') {
			$value = intval($response['response_text']);
			$section_score += $value; // Accumulate score
		}
		
		// 3. Render the question/response block
		$html .= '<div class="response-block">
			<p class="question-title">Question ' . $question_number . ': ' . htmlspecialchars($response['question_text']) . '</p>';

		if ($response['question_type'] == 'scale') {
			$value = intval($response['response_text']);
			// MODIFIED: Base percentage on a maximum of 3
			$percentage = ($value / 3) * 100;	
			
			// MODIFIED: Determine color based on new 0-3 scale logic
			$color = 'bg-success'; // Low (0-1)
			if ($value >= 3) {
				$color = 'bg-danger'; // High (3)
			} elseif ($value == 2) {
				$color = 'bg-warning'; // Medium (2)
			}
			
			// ADDED: Get the label
			$label = getScaleLabel($value);

			// MODIFIED: Update text and scale max (3)
			// Note: The background-color is replicated here because Dompdf struggles with class names for background colors.
			$html .= '<p style="margin-top: 10px; font-size: 10pt;"><strong>Response (Scale 0-3):</strong> ' . $label . '</p>
				<div class="progress-bar-scale">
					<div style="width: ' . $percentage . '%; ' .	
						'background-color: ' . ($color == 'bg-danger' ? '#dc3545' : ($color == 'bg-warning' ? '#ffc107' : '#008f39')) . ';">' .	
						$label . ' (' . $value . '/3)</div>
				</div>';
		} else {
			// This renders responses for 'multiple_choice', 'text', and other types
			$html .= '<p style="margin-top: 10px; font-size: 10pt;"><strong>Response:</strong></p>
				<div class="response-text" style="background-color: #eee; padding: 10px; border-radius: 3px;">' . nl2br(htmlspecialchars($response['response_text'])) . '</div>';
		}

		$html .= '</div>';
		$question_number++;
	}
	
	// 4. Display summary for the FINAL scale section
	// Only display if a score was accumulated for the final section
	if ($section_score > 0 || $question_number > 1) {	
		$severity = getSeverityLabelText($section_score);
		$html .= '<div class="summary-box">
			<strong>Scale Summary: ' . htmlspecialchars($current_section_name) . '</strong><br>
			Total Scale Score: ' . $section_score . '<br>
			Severity Level: ' . $severity . '
		</div>';
	}

	// --- START: ADDED SIGNATURE LINE AT BOTTOM LEFT ---
	$signature_html = '
	<div style="margin-top: 50px; text-align: left; width: 50%; page-break-before: auto;">
		<div style="width: 250px;">
			<hr style="border: 1px solid black; margin-bottom: 2px;">
			<p style="text-align: center; font-size: 9pt;">Admin/Counselor Signature Over Printed Name</p>
		</div>
	</div>';
	
	$html .= $signature_html;
	// --- END: ADDED SIGNATURE LINE AT BOTTOM LEFT ---

	$html .= '</body></html>';
	return $html;
}

// -------------------------------------------------------------------------

/**
 * Generates the PDF report using Dompdf and returns the raw binary content.
 * NOTE: The calling script (view_assessments.php) MUST include ../vendor/autoload.php
 * BEFORE including this file for the Dompdf class to be available.
 */
function generateStudentAssessmentReportPdf($assessment, $responses_data) {
	// 1. Get the HTML content
	$html = buildReportHtml($assessment, $responses_data);

	// 2. Initialize Dompdf
	$dompdf = new Dompdf();
	$dompdf->loadHtml($html);

	// (Optional) Configure paper size and orientation
	$dompdf->setPaper('A4', 'portrait');

	// 3. Render the HTML as PDF
	$dompdf->render();

	// 4. Return the raw PDF binary content
	return $dompdf->output();	
}

// -------------------------------------------------------------------------

/**
 * Generates an HTML string formatted for Microsoft Word and returns it.
 */
function generateStudentAssessmentReportDocx($assessment, $responses_data) {
	// 1. Get the core HTML content
	$html = buildReportHtml($assessment, $responses_data);
	
	// 2. Add Microsoft Office specific formatting (optional but recommended)
	// This tells Word to treat the file as a proper document.
	$word_html = '
	<html xmlns:o="urn:schemas-microsoft-com:office:office"
	xmlns:w="urn:schemas-microsoft-com:office:word"
	xmlns="http://www.w3.org/TR/REC-html40">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<style>
			@page {	
				mso-page-border-surround: none;
				mso-page-border-shadow: none;
				margin: 1in; /* Set margins for print */
			}
		</style>
		</head>
	<body>' . $html . '</body>
	</html>';

	return $word_html;
}
?>
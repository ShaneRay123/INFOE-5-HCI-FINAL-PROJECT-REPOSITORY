<?php
// Note: This script assumes you have run 'composer require dompdf/dompdf phpoffice/phpword'
// and that '../vendor/autoload.php' exists and includes the necessary classes.

require_once '../config.php';
// IMPORTANT: We no longer need to include functions.php as its necessary contents are moved here.
// require_once '../includes/functions.php'; 

// IMPORTANT: Include the Composer Autoloader
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc; // For text alignment
use PhpOffice\PhpWord\SimpleType\JcTable; // For table alignment

// =========================================================================
// 1. UTILITY FUNCTIONS (Moved from includes/functions.php)
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
 * Fetches user details from the database by user ID.
 * @param int $user_id The ID of the user.
 * @return array|null The user's row data or null.
 */
function getUserDetails($user_id) {
    global $conn;
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
 * Formats a SQL datetime string into a readable date format.
 * @param string $datetime The datetime string (e.g., YYYY-MM-DD HH:MM:SS).
 * @return string The formatted date string.
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
 * @return string The formatted time string.
 */
function formatTime($time) {
    if (empty($time) || $time === '00:00:00') return 'N/A';
    return date("g:i A", strtotime($time));
}

// =========================================================================
// 2. LOGO BASE64 SETUP (Crucial Fix for 500 Errors on PDF/DOCX)
// =========================================================================

// This variable will hold the Base64 image data URI.
$logo_base64 = '';
// FIX: Changed relative path from '../ua1 (1).jpg' to 'ua1 (1).jpg' 
// because the file is confirmed to be in the same directory (admin/).
$logo_path = 'ua1 (1).jpg'; 

if (file_exists($logo_path)) {
    $mime = '';
    
    // Fallback for restricted hosting where mime_content_type might be disabled
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($logo_path);
    } else {
        $extension = pathinfo($logo_path, PATHINFO_EXTENSION);
        if (strtolower($extension) === 'jpg' || strtolower($extension) === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif (strtolower($extension) === 'png') {
            $mime = 'image/png';
        }
        // Default to jpeg if still empty, assuming ua1 (1).jpg is a JPEG
        if (empty($mime)) $mime = 'image/jpeg'; 
    }

    $data = file_get_contents($logo_path);
    if ($data !== false) {
        $logo_base64 = 'data:' . $mime . ';base64,' . base64_encode($data);
    }
} else {
    // Log error if logo is missing, but allow script to continue
    error_log("Logo file not found at: " . $logo_path);
}

// =========================================================================
// 3. HTML GENERATION FUNCTION (Moved from includes/functions.php)
// =========================================================================

/**
 * Generates the HTML content used for PDF/DOCX generation of an Appointment Report.
 *
 * @param array $data The appointment data array (pre-sanitized).
 * @param string $logoBase64 The Base64 Data URI of the logo image.
 * @return string The generated HTML.
*/
function generateAppointmentReportHtml($data, $logoBase64) {
    // --- Styles for the PDF ---
    $styles = '
        body { font-family: Arial, sans-serif; line-height: 1.4; margin: 0; padding: 0; font-size: 9pt; }
        .container { width: 95%; margin: 10px auto; } 
        h1, h2 { color: #333; margin-bottom: 5px; } 
        h1 { font-size: 16pt; color: #B30104; text-align: center; padding-bottom: 3px; margin-top: 5px; } 
        h2 { font-size: 12pt; color: #008f39; margin-top: 10px; border-bottom: 1px solid #eee; padding-bottom: 2px; } 
        h3 { color: #333; font-size: 11pt; margin-top: 5px; margin-bottom: 3px; } 

        /* Custom Header Styles */
        .header-container { width: 100%; margin-bottom: 0px; }
        .logo-container { float: left; width: 15%; text-align: left; } 
        .title-container { float: right; width: 85%; text-align: center; } 
        .clearfix::after { content: ""; clear: both; display: table; }
        
        /* Table Styles */
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 9pt; }
        th, td { padding: 5px; border: 1px solid #ddd; text-align: left; } 
        th { background-color: #f2f2f2; width: 30%; }
        
        /* Notes and Footer Styles */
        .notes { border: 1px solid #ccc; padding: 10px; background-color: #f9f9f9; white-space: pre-wrap; font-size: 9pt; }
        .notes-block { margin-bottom: 10px; }
        .footer { margin-top: 20px; font-size: 0.7em; text-align: center; color: #666; }

        /* Signature Styles - FINALIZED FOR LEFT-ALIGNED GENERIC TEXT, NOT BOLD */
        .signature-block { 
            width: 50%;
            margin-top: 30px; 
            text-align: left;
        }
        .signature-line {
            border-bottom: 1px solid #000; 
            display: block;
            padding-bottom: 2px;
            margin-bottom: 2px; /* Space between line and text */
            width: 100%;
        }
        .signature-text { /* New style for the combined text */
            font-size: 9pt; /* Not bold */
            display: block;
            margin-top: 0;
            text-transform: none; /* Ensure no unwanted capitalization */
        }
    ';

    // --- Header HTML (Matching the requested layout) ---
    $headerHtml = '
        <div class="header-container clearfix">
            <div class="logo-container">
                <img src="' . $logoBase64 . '" style="width: 75px; height: 67px; display: block; margin: 0 auto;">
            </div>
            <div class="title-container">
                <p style="font-size: 9pt; margin: 0; padding: 0;">Republic of the Philippines</p>
                <p style="font-size: 9pt; margin: 0; padding: 0;">University of Antique</p>
                <p style="font-size: 10pt; margin: 0; padding: 0;">Sibalóm, Antique</p>
            </div>
        </div>
        <hr style="border: none; border-top: 3px solid #b30104; margin-top: 5px; margin-bottom: 5px;">
        <h1>Counseling Session Report (ID: ' . $data['id'] . ')</h1>
    ';


    // --- Signature Block HTML (The modification) ---
    $signatureHtml = '
        <div class="signature-block">
            <div class="signature-line"></div>
            <span class="signature-text">Admin/Counselor Signature Over Printed Name</span>
        </div>
    ';
    
    // --- Body HTML ---
    $bodyHtml = '
        <h2>Personnel & Logistical Details</h2>
        <table>
            <tr><th>Counselor Name</th><td>' . $data['counselor_name'] . '</td></tr>
            <tr><th>Appointment Date</th><td>' . $data['date'] . '</td></tr>
            <tr><th>Appointment Time</th><td>' . $data['time'] . '</td></tr>
            <tr><th>Status</th><td>' . $data['status'] . '</td></tr>
            <tr><th>Total Sessions Attended (Including this one)</th><td><strong>' . $data['total_sessions'] . '</strong></td></tr>
        </table>

        <h2>Student Information</h2>
        <table>
            <tr><th>Student Name</th><td>' . $data['student_name'] . '</td></tr>
            <tr><th>Student Number</th><td>' . $data['student_number'] . '</td></tr>
            <tr><th>Email</th><td>' . $data['student_email'] . '</td></tr>
            <tr><th>Department</th><td>' . $data['department'] . '</td></tr>
            <tr><th>Course Year</th><td>' . $data['course_year'] . '</td></tr>
            <tr><th>Section</th><td>' . $data['section'] . '</td></tr>
        </table>

        <h2>Counseling Process & Outcomes</h2>
        <div class="notes-block">
            <h3>Session Notes (Goals, Remarks, and Follow-up)</h3>
            <div class="notes">' . nl2br($data['session_notes']) . '</div>
        </div>
        
        ' . $signatureHtml . '
    ';


    // --- Footer HTML ---
    $footerHtml = '
        <div class="footer">
            Report generated by Wellness Hub Reporting System on ' . $data['report_timestamp'] . '.
        </div>
    ';
    
    // Final HTML structure
    return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>Appointment Report ' . $data['id'] . '</title>
            <style>' . $styles . '</style>
        </head>
        <body>
            <div class="container">
                ' . $headerHtml . '
                ' . $bodyHtml . '
                ' . $footerHtml . '
            </div>
        </body>
        </html>
    ';
}

// =========================================================================
// 4. MAIN SCRIPT LOGIC
// =========================================================================

if (!isLoggedIn() || !isAdmin()) {
    // Redirect unauthenticated or non-admin users
    redirect("../login.php");
}

if (!isset($_GET['id']) || !isset($_GET['format'])) {
    die("Missing appointment ID or format.");
}

$appointment_id = (int)$_GET['id'];
$format = strtolower($_GET['format']);
$counselor_user = getUserDetails($_SESSION["user_id"]);
$counselor_id = $_SESSION['user_id'];

// =========================================================================
// 5. Fetch Data
// =========================================================================
$query = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes, a.student_id, 
          u.full_name as student_name, u.email as student_email, u.department, u.course_year, u.section, u.student_number
          FROM appointments a 
          JOIN users u ON a.student_id = u.id 
          WHERE a.id = ? AND a.counselor_id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $appointment_id, $counselor_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    die("Appointment not found or you do not have permission to view this record.");
}

// --- Fetch Total Sessions Attended ---
$student_id = $data['student_id'];

// Count successful/attended sessions for this student with this counselor
$count_query = "SELECT COUNT(id) AS session_count FROM appointments 
                WHERE student_id = ? 
                AND counselor_id = ?
                AND status IN ('successful', 'completed')
                AND id <= ?"; // Count all successful sessions up to this one

$stmt_count = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($stmt_count, "iii", $student_id, $counselor_id, $appointment_id);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$session_count_row = mysqli_fetch_assoc($result_count);
// This will be the session number (1st, 2nd, 3rd, etc.)
$total_sessions = $session_count_row['session_count']; 
// --------------------------------------------------


// Prepare data structure (using htmlspecialchars is crucial here as this data will be outputted)
$report_data = [
    'id' => $appointment_id,
    'counselor_name' => htmlspecialchars($counselor_user['full_name']),
    'total_sessions' => $total_sessions, // Passed the calculated session count
    'student_name' => htmlspecialchars($data['student_name']),
    'student_email' => htmlspecialchars($data['student_email']),
    'student_number' => htmlspecialchars($data['student_number']),
    'department' => htmlspecialchars($data['department']),
    'course_year' => htmlspecialchars($data['course_year']),
    'section' => htmlspecialchars($data['section']),
    'date' => formatDate($data['appointment_date']),
    'time' => formatTime($data['appointment_time']),
    'status' => htmlspecialchars(ucfirst($data['status'])),
    
    // Using a single field for all notes, as per database structure
    'session_notes' => htmlspecialchars($data['notes']), 

    'report_timestamp' => date("F j, Y, g:i a"),
];

$filename = "Appointment_Report_" . $appointment_id . "_" . $data['student_name'];

// =========================================================================
// 6. Report Generation
// =========================================================================

if ($format === 'pdf') {
    // PDF Generation using Dompdf
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); 
    
    $dompdf = new Dompdf($options);
    
    $html = generateAppointmentReportHtml($report_data, $logo_base64); 
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Output PDF file to browser
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    echo $dompdf->output();
    exit;

} elseif ($format === 'docx') {
    // DOCX Generation using PhpWord
    $phpWord = new PhpWord();
    
    // Define style defaults
    $phpWord->addTitleStyle(1, ['size' => 14, 'bold' => true, 'color' => 'B30104']);
    $phpWord->addTitleStyle(2, ['size' => 12, 'bold' => true, 'color' => '333333']);
    $phpWord->addTitleStyle(3, ['size' => 10, 'bold' => true, 'color' => '008f39']);
    
    $noteStyle = ['spaceBefore' => 100, 'spaceAfter' => 100]; 
    
    // Signature styles (Updated to match image: generic text, not bold, single line)
    $phpWord->addParagraphStyle('SignatureLineStyle', ['spacing' => 0, 'spaceBefore' => 200, 'alignment' => Jc::LEFT]); 
    $phpWord->addParagraphStyle('SignatureTextStyle', ['spacing' => 0, 'spaceBefore' => 0, 'alignment' => Jc::LEFT]); // New style for the text
    
    $section = $phpWord->addSection();
    
    // Set minimal page margins
    $section->getStyle()->setMarginLeft(Converter::cmToTwip(1.5));
    $section->getStyle()->setMarginRight(Converter::cmToTwip(1.5));
    $section->getStyle()->setMarginTop(Converter::cmToTwip(1.5));
    $section->getStyle()->setMarginBottom(Converter::cmToTwip(1.5));

    // ---------------------------------------------------------------------
    // HEADER BLOCK FOR DOCX (Manual PHPWord Implementation)
    // ---------------------------------------------------------------------
    $headerTable = $section->addTable([
        'width' => 10000, 
        'unit' => Converter::isTenthsOfAMm, 
        'alignment' => JcTable::CENTER,
        'cellMarginLeft' => 50,
        'cellMarginTop' => 0,
        'cellMarginBottom' => 0
    ]);
    
    $headerTable->addRow();

    // Cell 1: Logo (Width 20%)
    $logoCell = $headerTable->addCell(2000, ['valign' => 'center']);
    
    if (!empty($logo_base64)) {
        $base64_parts = explode(',', $logo_base64, 2);
        $pure_base64 = count($base64_parts) > 1 ? end($base64_parts) : $logo_base64;
        $image_data = base64_decode($pure_base64);

        $logoCell->addImage(
            null,
            [
                'width' => 70,
                'height' => 63, 
                'alignment' => Jc::LEFT,
                'imageString' => $image_data 
            ]
        );
    }

    // Cell 2: University Title (Width 80%)
    $titleCell = $headerTable->addCell(8000, ['valign' => 'center']);
    
    $titleCell->addText(
        'Republic of the Philippines', 
        ['size' => 9, 'color' => '333333'], 
        ['align' => Jc::CENTER, 'spaceAfter' => 0]
    );

    $titleCell->addText(
        'University of Antique', 
        ['size' => 9, 'bold' => true, 'color' => '333333'], 
        ['align' => Jc::CENTER, 'spaceAfter' => 0]
    );
    $titleCell->addText(
        'Sibalóm, Antique', 
        ['size' => 10, 'color' => '333333'], 
        ['align' => Jc::CENTER, 'spaceAfter' => 0]
    );
    
    // Red Line Separator 
    $lineTable = $section->addTable([
        'width' => 10000, 
        'unit' => Converter::isTenthsOfAMm, 
        'borderBottomSize' => 18, 
        'borderBottomColor' => 'B30104', 
        'cellMarginBottom' => 50
    ]);
    $lineTable->addRow(1);
    $lineTable->addCell(10000)->addText(''); 

    
    // 1. Report Title 
    $section->addTitle('Counseling Session Report (ID: ' . $appointment_id . ')', 1);
    $section->addTextBreak(0);

    // 2. Personnel & Logistical Details
    $section->addTitle('Personnel & Logistical Details', 2);
    $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'width' => 10000, 'unit' => Converter::isTenthsOfAMm, 'cellMargin' => 50]);
    
    // Rows
    $table->addRow();
    $table->addCell(2500, ['bgColor' => 'F2F2F2'])->addText('Counselor Name', ['bold' => true, 'size' => 9]);
    $table->addCell(7500)->addText($report_data['counselor_name'], ['size' => 9]);

    $table->addRow();
    $table->addCell(2500, ['bgColor' => 'F2F2F2'])->addText('Appointment Date', ['bold' => true, 'size' => 9]);
    $table->addCell(7500)->addText($report_data['date'], ['size' => 9]);
    
    $table->addRow();
    $table->addCell(2500, ['bgColor' => 'F2F2F2'])->addText('Appointment Time', ['bold' => true, 'size' => 9]);
    $table->addCell(7500)->addText($report_data['time'], ['size' => 9]);
    
    $table->addRow();
    $table->addCell(2500, ['bgColor' => 'F2F2F2'])->addText('Status', ['bold' => true, 'size' => 9]);
    $table->addCell(7500)->addText($report_data['status'], ['size' => 9]);

    $table->addRow();
    $table->addCell(2500, ['bgColor' => 'F2F2F2'])->addText('Total Sessions Attended (Including this one)', ['bold' => true, 'size' => 9]);
    $table->addCell(7500)->addText($report_data['total_sessions'], ['bold' => true, 'size' => 9]);
    $section->addTextBreak(0);


    // 3. Student Information
    $section->addTitle('Student Information', 2);
    $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'width' => 10000, 'unit' => Converter::isTenthsOfAMm, 'cellMargin' => 50]);
    
    // Rows
    $table->addRow();
    $table->addCell(2500, ['bgColor' => 'F2F2F2'])->addText('Student Name', ['bold' => true, 'size' => 9]);
    $table->addCell(7500)->addText($report_data['student_name'], ['size' => 9]);
    
    $table->addRow();
    $table->addCell(2500, ['bgColor' => 'F2F2F2'])->addText('Student Number', ['bold' => true, 'size' => 9]);
    $table->addCell(7500)->addText($report_data['student_number'], ['size' => 9]);

    $table->addRow();
    $table->addCell(2500, ['bgColor' => 'F2F2F2'])->addText('Email', ['bold' => true, 'size' => 9]);
    $table->addCell(7500)->addText($report_data['student_email'], ['size' => 9]);

    $table->addRow();
    $table->addCell(2500, ['bgColor' => 'F2F2F2'])->addText('Department', ['bold' => true, 'size' => 9]);
    $table->addCell(7500)->addText($report_data['department'], ['size' => 9]);
    
    $table->addRow();
    $table->addCell(2500, ['bgColor' => 'F2F2F2'])->addText('Course Year', ['bold' => true, 'size' => 9]);
    $table->addCell(7500)->addText($report_data['course_year'], ['size' => 9]);
    
    $table->addRow();
    $table->addCell(2500, ['bgColor' => 'F2F2F2'])->addText('Section', ['bold' => true, 'size' => 9]);
    $table->addCell(7500)->addText($report_data['section'], ['size' => 9]);
    $section->addTextBreak(0);


    // 4. Counseling Process & Outcomes
    $section->addTitle('Counseling Process & Outcomes', 2);
    
    // Session Notes
    $section->addTitle('Session Notes (Goals, Remarks, and Follow-up)', 3);
    $section->addText(trim($report_data['session_notes']), ['size' => 9], $noteStyle);

    // 5. Signature Block (FINALIZED LEFT-ALIGNED with generic text, not bold)
    $section->addTextBreak(1);

    // Signature Line Placeholder
    $section->addText(
        '__________________________________', 
        null, 
        'SignatureLineStyle'
    );
    
    // Generic Signature Text (not bold)
    $section->addText(
        'Admin/Counselor Signature Over Printed Name', 
        ['size' => 9], // Not bold
        'SignatureTextStyle'
    );
    
    // 6. Footer
    $section->addTextBreak(1);
    $section->addText('Report generated by Wellness Hub Reporting System on ' . $report_data['report_timestamp'] . '.', 
                     ['size' => 7, 'color' => '666666'], 
                     ['alignment' => Jc::CENTER]);


    // Save the DOCX file
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment;filename="' . $filename . '.docx"');
    header('Cache-Control: max-age=0');
    
    $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save('php://output');
    exit;

} else {
    die("Invalid file format specified.");
}
?>
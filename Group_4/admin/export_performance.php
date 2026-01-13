<?php
// Define the base path for files outside the admin folder for includes
$base_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR;

// Include the Composer autoloader
require $base_dir . 'vendor/autoload.php';
// Assuming config and functions are one directory level up from the current admin subdirectory
require_once $base_dir . 'config.php';
require_once $base_dir . 'includes/functions.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// --- 1. Security Check and Data Loading ---
if (!isLoggedIn() || !isAdmin()) {
    exit("Unauthorized access.");
}

$user_id = $_SESSION["user_id"];
$user = getUserDetails($user_id);


// --- 2. METRICS CALCULATION (UNCHANGED) ---

// Function to fetch appointment counts by status and period
function getAppointmentCount($conn, $counselor_id, $status, $period = 'monthly') {
    $count = 0;
    $date_clause = '';

    switch ($period) {
        case 'daily':
            $date_clause = "AND DATE(appointment_date) = CURDATE()";
            break;
        case 'weekly':
            $date_clause = "AND YEARWEEK(appointment_date) = YEARWEEK(CURDATE())";
            break;
        case 'monthly':
            $date_clause = "AND MONTH(appointment_date) = MONTH(CURDATE()) AND YEAR(appointment_date) = YEAR(CURDATE())";
            break;
        case 'total':
            $date_clause = ""; // All-time total
            break;
    }

    $query = "SELECT COUNT(id) as count FROM appointments WHERE counselor_id = ? AND status = ? $date_clause";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "is", $counselor_id, $status);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = mysqli_fetch_assoc($result)['count'];
        mysqli_stmt_close($stmt);
    }
    return $count;
}

// Monthly Appointments
$app_monthly_pending = getAppointmentCount($conn, $user_id, 'pending', 'monthly');
$app_monthly_accepted = getAppointmentCount($conn, $user_id, 'accepted', 'monthly');
$app_monthly_successful = getAppointmentCount($conn, $user_id, 'successful', 'monthly');
$app_monthly_cancelled = getAppointmentCount($conn, $user_id, 'cancelled', 'monthly');
$app_total_monthly = $app_monthly_pending + $app_monthly_accepted + $app_monthly_successful + $app_monthly_cancelled;

// Total Appointments (All Time)
$app_total_accepted = getAppointmentCount($conn, $user_id, 'accepted', 'total');
$app_total_successful = getAppointmentCount($conn, $user_id, 'successful', 'total');
$app_total_cancelled = getAppointmentCount($conn, $user_id, 'cancelled', 'total');
$app_total_all_time = $app_total_accepted + $app_total_successful + $app_total_cancelled + getAppointmentCount($conn, $user_id, 'pending', 'total');


// Function to fetch assessment counts by submission status and period (Filtered by assessments CREATED by the counselor)
function getAssessmentCount($conn, $counselor_id, $status, $period = 'monthly') {
    $count = 0;
    $date_clause = '';

    switch ($period) {
        case 'daily':
            $date_clause = "AND DATE(sa.submitted_at) = CURDATE()";
            break;
        case 'weekly':
            $date_clause = "AND YEARWEEK(sa.submitted_at) = YEARWEEK(CURDATE())";
            break;
        case 'monthly':
            $date_clause = "AND MONTH(sa.submitted_at) = MONTH(CURDATE()) AND YEAR(sa.submitted_at) = YEAR(CURDATE())";
            break;
        case 'total_created':
            // Count total unique assessments created by the counselor (not submissions)
            $query = "SELECT COUNT(id) as count FROM assessments WHERE created_by = ?";
            if ($stmt = mysqli_prepare($conn, $query)) {
                mysqli_stmt_bind_param($stmt, "i", $counselor_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $count = mysqli_fetch_assoc($result)['count'];
                mysqli_stmt_close($stmt);
            }
            return $count;
        case 'total_submissions':
            // Total assignments (submissions) made by students from this counselor's assessments
            $query = "SELECT COUNT(sa.id) as count FROM student_assessments sa JOIN assessments a ON sa.assessment_id = a.id WHERE a.created_by = ?";
            if ($stmt = mysqli_prepare($conn, $query)) {
                mysqli_stmt_bind_param($stmt, "i", $counselor_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $count = mysqli_fetch_assoc($result)['count'];
                mysqli_stmt_close($stmt);
            }
            return $count;
    }

    $query = "SELECT COUNT(sa.id) as count 
              FROM student_assessments sa
              JOIN assessments a ON sa.assessment_id = a.id
              WHERE a.created_by = ? AND sa.status = ? $date_clause";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "is", $counselor_id, $status);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = mysqli_fetch_assoc($result)['count'];
        mysqli_stmt_close($stmt);
    }
    return $count;
}

// Monthly Assessments
$ass_monthly_completed = getAssessmentCount($conn, $user_id, 'completed', 'monthly');
$ass_monthly_pending = getAssessmentCount($conn, $user_id, 'pending', 'monthly'); 
$ass_total_monthly = $ass_monthly_completed;

// Total Assessments (All Time)
$ass_total_created = getAssessmentCount($conn, $user_id, '', 'total_created');
$ass_total_submissions = getAssessmentCount($conn, $user_id, '', 'total_submissions');
$ass_total_completed = getAssessmentCount($conn, $user_id, 'completed', 'total');
$ass_total_pending = getAssessmentCount($conn, $user_id, 'pending', 'total');

// Completion Rate Calculation
$completion_rate = ($ass_total_submissions > 0) 
                     ? round(($ass_total_completed / $ass_total_submissions) * 100, 1) 
                     : 0;

// Success Rate Calculation (Successful Appointments / Total Completed Appointments)
$app_success_rate = ($app_total_successful + $app_total_cancelled > 0) 
                     ? round(($app_total_successful / ($app_total_successful + $app_total_cancelled)) * 100, 1) 
                     : 0;
// --- END METRICS CALCULATION ---


// --- 3. Determine Output Format and Generate ---

$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'pdf';
$filename = 'Counselor_Performance_Report_' . $user["username"] . '_' . date('Ymd');

// Define Logo Paths and Styles 
$logo_filename = 'ua1 (1).jpg';
$logo_filepath = __DIR__ . DIRECTORY_SEPARATOR . $logo_filename; 
$logo_width_html = '110px';
$logo_width_docx = 320; 

// Base64 encode the image for reliable embedding in Dompdf
$logo_data = file_get_contents($logo_filepath);
$logo_base64 = 'data:image/jpeg;base64,' . base64_encode($logo_data);

// --- HTML Content for the Report (For PDF/Dompdf) ---
$html_content = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 10pt; color: #333; }
        .main-header { 
            background-color: white; 
            color: #333; 
            padding: 10px 0;
            margin-bottom: 20px; 
            border-bottom: 1px solid #ccc;
            display: table; 
            width: 100%;
        }
        .logo-cell {
            display: table-cell;
            width: 15%; 
            vertical-align: middle;
            padding-left: 20px; 
        }
        .text-cell {
            display: table-cell;
            width: 85%; 
            vertical-align: middle;
            text-align: center;
        }
        .header-logo {
            width: " . $logo_width_html . ";
            height: auto;
            display: block;
        }
        .header-text h1 { 
            font-size: 16pt; 
            margin: 0; 
            color: #333; 
            padding-right: 110px;
        }
        .header-text p { 
            font-size: 11pt; 
            margin: 5; 
            padding-right: 110px;
        }
        
        .report-title-block {
            text-align: center;
            margin-bottom: 20px;
        }
        .report-title-block h2 {
            font-size: 18pt;
            color: #b30104;
            margin: 0;
            border: none;
            padding: 0;
        }
        .report-title-block p {
             font-size: 10pt;
             margin: 5px 0 0 0;
        }

        /* --- Body Styles --- */
        h2 { border-bottom: 2px solid #ccc; padding-bottom: 5px; margin-top: 25px; color: #b30104; }
        .summary-box { border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; display: inline-block; width: 48%; margin-right: 1%; }
        .detail-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .detail-table th, .detail-table td { border: 1px solid #ccc; padding: 8px 10px; text-align: left; }
        .detail-table th { background-color: #f2f2f2; font-weight: bold; }
        .bg-light { background-color: #eee !important; font-weight: bold; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; color: white; font-size: 9pt; }
        .badge-success { background-color: #28a745; }
        .badge-warning { background-color: #ffc107; color: black; }
        .badge-info { background-color: #17a2b8; }
        .badge-danger { background-color: #dc3545; }
        
        /* Signature Block Style */
        .signature-block {
            width: 250px;
            margin-top: 50px;
            float: left; /* Aligns to bottom left */
            text-align: center;
            font-size: 10pt;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            height: 1px; /* Ensure line is visible */
        }
        /* Page Numbering in the footer area for PDF */
        @page {
            margin-bottom: 50px; 
        }
        .page-number-footer {
            position: fixed;
            bottom: 5px;
            right: 15px;
            font-size: 8pt;
            color: #333;
        }
    </style>
</head>
<body>
    <div class='main-header'>
        <div class='logo-cell'>
            <img src='" . $logo_base64 . "' alt='University of Antique Logo' class='header-logo'>
        </div>
        <div class='text-cell'>
            <div class='header-text'>
             <p>Republic of the Philippines</p>
                <p>University of Antique</p>
                <p>Sibalóm, Antique</p>
            </div>
        </div>
    </div>
    
    <div class='report-title-block'>
        <h2>COUNSELOR PERFORMANCE REPORT</h2>
        <p>Generated for: <strong>" . htmlspecialchars($user["full_name"]) . "</strong> (ID: " . $user_id . ") on " . date('Y-m-d H:i:s') . "</p>
    </div>
    
    <h2>Assessment Performance Summary</h2>
    <div class='summary-box'>
        <p><strong>Total Assessments Created:</strong> " . $ass_total_created . "</p>
        <p><strong>Total Submissions (All Time):</strong> " . $ass_total_submissions . "</p>
    </div>
    <div class='summary-box'>
        <p><strong>Assessments Completed (This Month):</strong> " . $ass_monthly_completed . "</p>
        <p><strong>Overall Completion Rate:</strong> <span class='badge badge-success'>" . $completion_rate . "%</span></p>
    </div>

    <h2>Appointment Performance Summary</h2>
    <div class='summary-box'>
        <p><strong>Total Appointments (All Time):</strong> " . $app_total_all_time . "</p>
        <p><strong>Successful Appointments (This Month):</strong> " . $app_monthly_successful . "</p>
    </div>
    <div class='summary-box'>
        <p><strong>Cancelled Appointments (All Time):</strong> " . $app_total_cancelled . "</p>
        <p><strong>Appointment Success Rate:</strong> <span class='badge badge-info'>" . $app_success_rate . "%</span></p>
    </div>

    <h2>Detailed Monthly Metrics (" . date('F Y') . ")</h2>
    <table class='detail-table'>
        <thead>
            <tr>
                <th>Metric</th>
                <th>Assessments</th>
                <th>Appointments</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Completed / Successful</strong></td>
                <td><span class='badge badge-success'>" . $ass_monthly_completed . "</span></td>
                <td><span class='badge badge-success'>" . $app_monthly_successful . "</span></td>
            </tr>
            <tr>
                <td><strong>Pending / Accepted</strong></td>
                <td><span class='badge badge-warning'>" . $ass_monthly_pending . "</span></td>
                <td><span class='badge badge-info'>" . $app_monthly_accepted . "</span></td>
            </tr>
            <tr>
                <td><strong>Pending Requests</strong></td>
                <td>N/A</td>
                <td><span class='badge badge-warning'>" . $app_monthly_pending . "</span></td>
            </tr>
            <tr>
                <td><strong>Cancelled / Declined</strong></td>
                <td>N/A</td>
                <td><span class='badge badge-danger'>" . $app_monthly_cancelled . "</span></td>
            </tr>
            <tr>
                <td class='bg-light'>TOTAL MONTHLY ACTIVITY</td>
                <td class='bg-light'>" . $ass_total_monthly . "</td>
                <td class='bg-light'>" . $app_total_monthly . "</td>
            </tr>
        </tbody>
    </table>
    
    <div class='signature-block'>
        <br><br><br>
        <p class='signature-line'></p>
        <p>Admin/Counselor Signature Over Printed Name</p>
    </div>
    <div class='page-number-footer'>
        <script type='text/php'>echo \$PAGE_NUM . ' / ' . \$PAGE_COUNT;</script>
    </div>

</body>
</html>";


// --- PDF GENERATION (Dompdf) ---
if ($format == 'pdf') {
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false); 
    
    $dompdf = new Dompdf($options);
    
    $dompdf->loadHtml($html_content);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Output the generated PDF
    $dompdf->stream($filename . ".pdf", array("Attachment" => true));
    
// --- DOCX (WORD) GENERATION (PhpWord) ---
} elseif ($format == 'docx') {
    $phpWord = new PhpWord();
    
    // Set default styles for better appearance
    $phpWord->setDefaultFontName('Calibri');
    $phpWord->setDefaultFontSize(11);

    $section = $phpWord->addSection();
    
    // --- DOCX HEADER IMPLEMENTATION ---
    $doc_header = $section->addHeader();
    
    // 1. Create a table to align the logo left and text center
    $doc_header_tableStyle = ['width' => 10000, 'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP, 'cellMargin' => 0];
    $doc_header_cellStyle = ['valign' => \PhpOffice\PhpWord\SimpleType\VerticalJc::CENTER]; 
    
    $doc_header->addTable($doc_header_tableStyle, ['spaceAfter' => 0]);
    $table = $doc_header->getTables()[0];
    
    $table->addRow();
    
    // Left Cell: Logo (approx 20% width)
    $logo_cell = $table->addCell(2500, $doc_header_cellStyle); 
    $logo_cell->addImage(
        $logo_filepath,
        array(
            'width' => $logo_width_docx, 
            'height' => $logo_width_docx,
            'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 
            'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.7) 
        )
    );
    
    // Right Cell: Institution Name (remaining width for text)
    $text_cell = $table->addCell(7500, $doc_header_cellStyle); 
    
    // Center alignment style for text within the cell (No custom indentation applied here)
    $center_text_style = ['spaceAfter' => 0, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]; 

    // Institution Name (Bold)
    $text_cell->addText(
        'University of Antique', 
        ['bold' => true, 'size' => 16, 'color' => '333333'], 
        $center_text_style
    );
    
    // Location
    $text_cell->addText(
        'Sibalóm, Antique', 
        ['size' => 11, 'color' => '333333'], 
        $center_text_style
    );
    
    // 2. Add the separator line outside the table
    $doc_header->addShape(
        'line', 
        array(
            'width' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(16), 
            'height' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0),
        ), 
        array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'color' => '000000', 'spaceBefore' => 100)
    );
    
    // 3. Report Title and Counselor Details (Centered)
    $center_style = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
    $doc_header->addText('COUNSELOR PERFORMANCE REPORT', ['bold' => true, 'size' => 14, 'color' => 'B30104'], $center_style);
    $doc_header->addText('Generated for: ' . htmlspecialchars($user["full_name"]) . ' on ' . date('Y-m-d H:i:s'), ['size' => 9, 'color' => '333333'], $center_style);
    $doc_header->addTextBreak(1); 
    
    // --- END DOCX HEADER CONTENT ---
    
    // 4. Add Page Number to the Footer
    $footer = $section->addFooter();
    $footer->addPreserveText(
        'Page {PAGE} of {NUMPAGES}', 
        null, 
        array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT, 'color' => '333333')
    );


    // --- Document Body (starts here in DOCX) ---

    // 2. Assessment Summary
    $section->addTitle('Assessment Performance Summary', 2);
    $section->addText('Total Assessments Created: ' . $ass_total_created);
    $section->addText('Total Submissions (All Time): ' . $ass_total_submissions);
    $section->addText('Assessments Completed (This Month): ' . $ass_monthly_completed);
    $section->addText('Overall Completion Rate: ' . $completion_rate . '%', ['bold' => true, 'color' => '00AA00']);
    
    // 3. Appointment Summary
    $section->addTitle('Appointment Performance Summary', 2);
    $section->addText('Total Appointments (All Time): ' . $app_total_all_time);
    $section->addText('Successful Appointments (This Month): ' . $app_monthly_successful);
    $section->addText('Cancelled Appointments (All Time): ' . $app_total_cancelled);
    $section->addText('Appointment Success Rate: ' . $app_success_rate . '%', ['bold' => true, 'color' => '0077FF']);

    // 4. Detailed Monthly Metrics Table
    $section->addTitle('Detailed Monthly Metrics (' . date('F Y') . ')', 2);
    
    $tableStyle = array('borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 80);
    $fontStyle = array('bold' => true, 'size' => 10);
    $cellStyle = array('bgColor' => 'F2F2F2');
    
    $phpWord->addTableStyle('MetricsTable', $tableStyle);
    $table = $section->addTable('MetricsTable');
    
    // Table Header
    $table->addRow();
    $table->addCell(4000, $cellStyle)->addText('Metric', $fontStyle);
    $table->addCell(3000, $cellStyle)->addText('Assessments', $fontStyle);
    $table->addCell(3000, $cellStyle)->addText('Appointments', $fontStyle);

    // Data Rows
    $table->addRow();
    $table->addCell(4000)->addText('Completed / Successful');
    $table->addCell(3000)->addText($ass_monthly_completed);
    $table->addCell(3000)->addText($app_monthly_successful);
    
    $table->addRow();
    $table->addCell(4000)->addText('Pending / Accepted');
    $table->addCell(3000)->addText($ass_monthly_pending);
    $table->addCell(3000)->addText($app_monthly_accepted);

    $table->addRow();
    $table->addCell(4000)->addText('Pending Requests');
    $table->addCell(3000)->addText('N/A');
    $table->addCell(3000)->addText($app_monthly_pending);

    $table->addRow();
    $table->addCell(4000)->addText('Cancelled / Declined');
    $table->addCell(3000)->addText('N/A');
    $table->addCell(3000)->addText($app_monthly_cancelled);
    
    // Total Row
    $table->addRow();
    $table->addCell(4000, $cellStyle)->addText('TOTAL MONTHLY ACTIVITY', $fontStyle);
    $table->addCell(3000, $cellStyle)->addText($ass_total_monthly, $fontStyle);
    $table->addCell(3000, $cellStyle)->addText($app_total_monthly, $fontStyle);
    
    // --- UPDATED SIGNATURE BLOCK FOR DOCX (PHPWORD) ---
    $section->addTextBreak(3); // Add some vertical space
    
    $signatureTableStyle = ['width' => 4000, 'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP, 'cellMargin' => 0, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT];
    $section->addTable($signatureTableStyle);
    $sigTable = $section->getTables()[count($section->getTables()) - 1];
    
    $sigTable->addRow();
    $sigCell = $sigTable->addCell(4000, ['borderBottomSize' => 10, 'borderBottomColor' => '000000', 'vAlign' => \PhpOffice\PhpWord\SimpleType\VerticalJc::CENTER]);
    $sigCell->addText('   ', ['size' => 10]); // Spacer for the line
    
    $sigTable->addRow();
    $sigTable->addCell(4000)->addText(
        'Admin/Counselor Signature Over Printed Name', // CHANGED TEXT HERE
        ['size' => 9], 
        ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
    );
    // --- END SIGNATURE BLOCK ---


    // Save the file
    $objWriter = IOFactory::createWriter($phpWord, 'Word2007');

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment;filename="' . $filename . '.docx"');
    header('Cache-Control: max-age=0');
    
    $objWriter->save('php://output');

} else {
    exit("Invalid format specified.");
}
?>
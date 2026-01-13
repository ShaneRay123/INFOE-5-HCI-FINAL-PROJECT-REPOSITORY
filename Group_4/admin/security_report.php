<?php
session_start();

// Load Dompdf via Composer's autoloader
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Start output buffering to capture all HTML generated below
ob_start();

// Assuming these files exist and contain necessary functions and configuration
require_once '../config.php';
require_once '../includes/functions.php';

// 1. Authorization Check: Only logged-in Admins/Counselors can view this report
if (!isLoggedIn() || !isAdmin()) {
    error_log("Unauthorized access attempt to security_report.php from user ID: " . ($_SESSION["user_id"] ?? 'N/A'));
    redirect("../index.php");
}

$user = getUserDetails($_SESSION["user_id"]);
$conn = $GLOBALS['conn'] ?? null;

// =========================================================================
// 0. Define Logo and Path for Header (CORRECTED)
// =========================================================================
// Define Logo Paths and Styles
$logo_filename = 'ua1 (1).jpg';
// FIX: Using __DIR__ ensures the script looks in the current directory (admin/)
$logo_filepath = __DIR__ . DIRECTORY_SEPARATOR . $logo_filename; 
$logo_width_html = '110px';

// Base64 encode the image for reliable embedding in Dompdf (PDF)
if (file_exists($logo_filepath)) {
    $logo_data = file_get_contents($logo_filepath);
    $logo_base64 = 'data:image/jpeg;base64,' . base64_encode($logo_data);
} else {
    // Fallback if logo file is not found
    error_log("Logo file not found at: " . $logo_filepath);
    $logo_base64 = '';
}


// =========================================================================
// 2. Fetch Security Metrics
// =========================================================================

// --- A. User Role Counts (This remains All Time) ---
$role_counts = [];
$admin_counselor_count = 0;
$student_count = 0;
$total_users = 0;

if ($conn) {
    $role_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
    $role_result = mysqli_query($conn, $role_query);
    if ($role_result) {
        while ($row = mysqli_fetch_assoc($role_result)) {
            $role_counts[$row['role']] = $row['count'];
        }
        $admin_counselor_count = $role_counts['admin'] ?? 0;
        $student_count = $role_counts['student'] ?? 0;
        $total_users = $admin_counselor_count + $student_count;
    }
}

// --- C. Security Event Counts from security_log (LAST 7 DAYS - Existing Users Only) ---
$login_success_count = 0;
$login_failure_count = 0;

if ($conn) {
    // 1. Successful Logins Count (Filtered to existing users via INNER JOIN)
    $success_count_query = "SELECT COUNT(*) as count FROM security_log sl
                            INNER JOIN users u ON sl.user_id = u.id 
                            WHERE sl.event_type = 'LOGIN_SUCCESS'
                            AND sl.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $success_count_result = mysqli_query($conn, $success_count_query);
    if ($success_count_result) {
        $login_success_count = mysqli_fetch_assoc($success_count_result)['count'];
    }

    // 2. Failed Logins Count (Filtered to attempts against existing user emails via INNER JOIN)
    $failure_count_query = "SELECT COUNT(*) as count FROM security_log sl
                            INNER JOIN users u ON sl.username_attempted = u.email
                            WHERE sl.event_type = 'LOGIN_FAILURE'
                            AND sl.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $failure_count_result = mysqli_query($conn, $failure_count_query);
    if ($failure_count_result) {
        $login_failure_count = mysqli_fetch_assoc($failure_count_result)['count'];
    }
    
    // Total Security Events Logged (Last 7 Days) will now be the sum of these filtered counts.
    $total_security_events_weekly = $login_success_count + $login_failure_count;
}

// --- D. Detailed Log Fetches (Top 10 of Last 7 Days, using INNER JOIN) ---
$successful_logins = [];
$failed_logins = [];

if ($conn) {
    // Successful Logins Query (Top 10 of Last 7 Days, using INNER JOIN to exclude deleted users)
    $success_sql = "SELECT sl.timestamp, u.email, sl.ip_address, u.full_name 
                    FROM security_log sl
                    INNER JOIN users u ON sl.user_id = u.id 
                    WHERE sl.event_type = 'LOGIN_SUCCESS'
                    AND sl.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY sl.timestamp DESC
                    LIMIT 10";
    $success_result = mysqli_query($conn, $success_sql);
    if ($success_result) {
        while ($row = mysqli_fetch_assoc($success_result)) {
            $successful_logins[] = $row;
        }
    }

    // Failed Logins Query (Top 10 of Last 7 Days, using INNER JOIN to only include attempts against existing users)
    $failure_sql = "SELECT sl.timestamp, sl.username_attempted, sl.ip_address, sl.description, u.full_name 
                    FROM security_log sl
                    INNER JOIN users u ON sl.username_attempted = u.email
                    WHERE sl.event_type = 'LOGIN_FAILURE'
                    AND sl.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY sl.timestamp DESC
                    LIMIT 10";
    $failure_result = mysqli_query($conn, $failure_sql);
    if ($failure_result) {
        while ($row = mysqli_fetch_assoc($failure_result)) {
            $failed_logins[] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security & Compliance Report - Wellness Hub</title>
    <style>
        /* Styles optimized for PDF rendering and maximized space */
        /* MAXIMIZATION CHANGE 1: Reduced body padding (margins) */
        body { font-family: sans-serif; background-color: #ffffff; padding: 10px; font-size: 10pt; }
        .container { max-width: 900px; margin: auto; }
        
        /* --- HEADER STYLES (MATCHING UA LOGO/TEXT LAYOUT) --- */
        .main-header { 
            background-color: white; 
            color: #333; 
            padding: 5px 0; /* Reduced padding */
            margin-bottom: 10px; /* Reduced margin */
            border-bottom: 1px solid #ccc;
            display: table; 
            width: 100%;
        }
        .logo-cell {
            display: table-cell;
            width: 15%; 
            vertical-align: middle;
            padding-left: 10px; /* Reduced padding */
        }
        .text-cell {
            display: table-cell;
            width: 85%; 
            vertical-align: middle;
            text-align: center;
        }
        .header-logo {
            width: <?php echo $logo_width_html; ?>;
            height: auto;
            display: block;
        }
        .header-text h1 { 
            font-size: 11pt; 
            margin: 0; 
            color: #333; 
            padding-right: 110px; 
            font-weight: bold;
        }
        .header-text p { 
            font-size: 11pt; 
            margin: 5; 
            padding-right: 110px; 
            font-weight: bold;
        }
        /* --- END HEADER STYLES --- */

        /* --- REPORT TITLE STYLES --- */
        .report-title-block { 
            background-color: transparent; 
            color: #333; 
            padding: 5px 0; /* Reduced padding */
            margin-bottom: 15px; /* Reduced margin */
            text-align: center; 
        }
        .report-title { 
            font-size: 18pt; 
            margin-bottom: 5px; 
            color: #b30104; 
            margin: 0;
            padding: 0;
            font-weight: bold;
        }
        .report-date { 
            font-size: 0.8em; 
            opacity: 0.8; 
            margin: 0;
        }
        /* --- END REPORT TITLE STYLES --- */

        /* Replacement for Bootstrap Card/Badge styles */
        /* MAXIMIZATION CHANGE 2: Reduced card margin and padding */
        .card { border: 1px solid #ccc; border-radius: 5px; margin-bottom: 10px; page-break-inside: avoid; }
        .card-header { font-weight: bold; padding: 8px 10px; border-bottom: 1px solid #ccc; } /* Reduced padding */
        .card-body { padding: 10px; } /* Reduced padding */
        .h4 { font-size: 14pt; margin: 0; }
        .h5 { font-size: 12pt; margin-top: 0; margin-bottom: 8px; }
        
        .bg-info { background-color: #17a2b8; color: white; }
        .bg-secondary { background-color: #6c757d; color: white; }
        .bg-success { background-color: #28a745; color: white; }
        .bg-danger { background-color: #dc3545; color: white; }
        
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; color: white; font-size: 9pt; font-weight: bold; }
        .badge-success { background-color: #28a745; }
        .badge-danger { background-color: #dc3545; }
        .badge-primary { background-color: #007bff; }
        .badge-info { background-color: #17a2b8; }
        
        /* MAXIMIZATION CHANGE 3: Reduced font size and padding for log tables */
        .log-table { width: 100%; border-collapse: collapse; }
        .log-table th, .log-table td { 
            font-size: 0.75em; /* Further reduced font size */
            padding: 5px; /* Reduced cell padding */
            border: 1px solid #ddd; 
            text-align: left; 
        }
        .log-table th { background-color: #f2f2f2; font-weight: bold; }
        .log-table tbody tr:nth-child(even) { background-color: #f9f9f9; } /* Striping */
        
        /* SIGNATURE BLOCK STYLES - Adjusted positioning for smaller margins */
        .signature-block {
            width: 300px;
            margin-top: 30px; /* Reduced margin */
            text-align: center;
            font-size: 10pt;
            position: absolute;
            bottom: 20px; /* Adjusted bottom position */
            left: 10px; /* Adjusted left position */
        }
        .signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            height: 1px;
        }
    </style>
</head>
<body>

<div class='main-header'>
    <div class='logo-cell'>
        <?php if ($logo_base64): ?>
            <img src='<?php echo $logo_base64; ?>' alt='University of Antique Logo' class='header-logo'>
        <?php endif; ?>
    </div>
    <div class='text-cell'>
        <div class='header-text'>
            <h1>Republic of the Philippines</h1>
             <p>University of Antique</p>
            <p>Sibalóm, Antique</p>
        </div>
    </div>
</div>

<div class="report-title-block">
    <h1 class="report-title"> Security & Compliance Report</h1>
    <p class="report-date">Generated by: <?php echo htmlspecialchars($user["full_name"]); ?> (<?php echo htmlspecialchars($user["username"]); ?>)</p>
    <p class="report-date">Date: <?php echo date("F j, Y, g:i a T"); ?></p>
</div>


<div class="container">
    <div class="card my-4">
        <div class="card-header h4 bg-info text-white">
             System Overview Metrics (All Time)
        </div>
        <div class="card-body">
            <h5 class="card-title mb-3">User Account Statistics</h5>
            
            <p class="mb-1">
                <strong>Total Registered Users:</strong> 
                <span class="badge badge-primary"><?php echo $total_users; ?></span>
            </p>
            <p class="mb-1">
                <strong>Active Student Accounts:</strong> 
                <span class="badge badge-success"><?php echo $student_count; ?></span>
            </p>
            <p class="mb-0">
                <strong>Admin/Counselor Accounts:</strong> 
                <span class="badge badge-danger"><?php echo $admin_counselor_count; ?></span>
            </p>
        </div>
    </div>
    
    <div class="card my-4">
        <div class="card-header h4 bg-secondary text-white">
             Authentication Activity Summary (Last 7 Days)
        </div>
        <div class="card-body">
            <h5 class="card-title mb-3">Authentication Events (Last 7 Days - Existing Users Only)</h5>
            
            <p class="mb-1">
                <strong>Successful Logins:</strong> 
                <span class="badge badge-success"><?php echo $login_success_count; ?></span>
            </p>
            <p class="mb-3">
                <strong>Failed Login Attempts:</strong> 
                <span class="badge badge-danger"><?php echo $login_failure_count; ?></span>
            </p>

            <hr style="border-top: 1px solid #ccc;">
            <p class="mb-0"><strong>Total Security Events Logged (Last 7 Days - Existing Users Only):</strong> <span class="badge badge-primary"><?php echo $total_security_events_weekly; ?></span></p>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-header h4 bg-success text-white">
             Top 10 Successful Logins (Last 7 Days - Existing Users Only)
        </div>
        <div class="card-body">
            <?php if (!empty($successful_logins)): ?>
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Timestamp</th>
                        <th style="width: 35%;">User (Email)</th>
                        <th style="width: 20%;">Full Name</th>
                        <th style="width: 20%;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($successful_logins as $log): ?>
                    <tr>
                        <td><?php echo formatDate($log['timestamp']); ?></td>
                        <td><?php echo htmlspecialchars($log['email']); ?></td>
                        <td><?php echo htmlspecialchars($log['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No successful login events found for existing users in the log for the last 7 days.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="card my-4">
        <div class="card-header h4 bg-danger text-white">
             Top 10 Failed Login Attempts (Last 7 Days - Against Existing Users Only)
        </div>
        <div class="card-body">
            <?php if (!empty($failed_logins)): ?>
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Timestamp</th>
                        <th style="width: 25%;">User Attempted</th>
                        <th style="width: 20%;">Full Name</th>
                        <th style="width: 20%;">IP Address</th>
                        <th style="width: 15%;">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($failed_logins as $log): ?>
                    <tr>
                        <td><?php echo formatDate($log['timestamp']); ?></td>
                        <td><?php echo htmlspecialchars($log['username_attempted']); ?></td>
                        <td><?php echo htmlspecialchars($log['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No failed login attempts found against existing users in the log for the last 7 days.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="signature-block">
    <br><br><br>
    <p class="signature-line"></p>
    <p>Admin/Counselor Signature Over Printed Name</p>
</div>
</body>
</html>
<?php
// 3. Stop buffering and get the content
$html = ob_get_clean();

// 4. Dompdf initialization and configuration
$options = new Options();
// Keep isRemoteEnabled true to ensure the base64 image loads correctly
$options->set('isRemoteEnabled', true); 
$options->set('defaultFont', 'sans-serif'); 
$dompdf = new Dompdf($options);

// 5. Load HTML to Dompdf
$dompdf->loadHtml($html);

// 6. (Optional) Set paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// 7. Render the HTML as PDF
$dompdf->render();

// 8. Output the generated PDF to browser (download)
$filename = 'Security_Report_' . date('Ymd') . '_' . $user["username"] . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]); // true forces download

exit;
?>
<?php
require_once '../config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode([]);
    exit;
}

$date = isset($_GET['date']) ? $_GET['date'] : '';
if (!$date) {
    echo json_encode([]);
    exit;
}

$dateObj = DateTime::createFromFormat('m/d/Y', $date);
if (!$dateObj) {
    echo json_encode([]);
    exit;
}

$db_date = $dateObj->format('Y-m-d');

// Fetch appointments for this counselor on this date
$query = "SELECT appointment_time FROM appointments 
          WHERE counselor_id = ? AND appointment_date = ? AND status IN ('accepted','pending')";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "is", $_SESSION['user_id'], $db_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$unavailable = [];
while ($row = mysqli_fetch_assoc($result)) {
    $unavailable[] = date('H:i', strtotime($row['appointment_time']));
}

echo json_encode($unavailable);

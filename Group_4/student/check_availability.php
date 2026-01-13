<?php
require_once '../config.php';
require_once '../includes/functions.php';

if (isset($_GET['counselor_id'], $_GET['date'])) {
    $counselor_id = (int) $_GET['counselor_id'];
    $date_parts = explode('/', $_GET['date']);
    if (count($date_parts) === 3)
        $date = "{$date_parts[2]}-{$date_parts[0]}-{$date_parts[1]}";
    else
        $date = $_GET['date'];

    $stmt = mysqli_prepare($conn, "
        SELECT appointment_time FROM appointments
        WHERE counselor_id = ? AND appointment_date = ? AND status IN ('pending', 'accepted')
        ORDER BY appointment_time
    ");
    mysqli_stmt_bind_param($stmt, "is", $counselor_id, $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        echo "<strong>Unavailable times:</strong><br>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo formatTime($row['appointment_time']) . "<br>";
        }
    } else {
        echo "<span class='text-success'>All times are available for this date.</span>";
    }
}
?>

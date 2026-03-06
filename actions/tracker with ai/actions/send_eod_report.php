<?php
require_once '../includes/functions.php';
requireLogin();

// This script acts as a bridge. It takes the date range from the reports page
// and passes it as URL parameters to the email composition page.

$date_from = $_POST['date_from'] ?? date('Y-m-d');
$time_from = $_POST['time_from'] ?? '00:00:00';
$date_to = $_POST['date_to'] ?? date('Y-m-d');
$time_to = $_POST['time_to'] ?? '23:59:59';
// For admin reports, a target_user_id might be passed
$user_id = $_POST['target_user_id'] ?? $_SESSION['user_id'];

$queryParams = http_build_query([
    'date_from' => $date_from,
    'time_from' => $time_from,
    'date_to' => $date_to,
    'time_to' => $time_to,
    'user_id' => $user_id
]);

// Redirect to the page where the user will compose and send the email.
header("Location: ../compose_email.php?" . $queryParams);
exit();
?>
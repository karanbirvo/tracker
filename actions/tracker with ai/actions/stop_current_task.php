<?php
require_once '../includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];

if (stopCurrentTask($userId, $pdo)) {
    $_SESSION['message'] = "Current task stopped.";
    $_SESSION['message_type'] = "info";
} else {
    $_SESSION['message'] = "No active task to stop.";
    $_SESSION['message_type'] = "warning";
}

header("Location: ../index.php");
exit();
?>
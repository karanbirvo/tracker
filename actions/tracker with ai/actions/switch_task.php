<?php
require_once '../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_name'])) {
    $taskName = trim($_POST['task_name']);
    $userId = $_SESSION['user_id'];

    if (!empty($taskName)) {
        startNewTask($userId, $taskName, $pdo); // This function already handles stopping current task
        $_SESSION['message'] = "Switched to task '".htmlspecialchars($taskName)."'.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Invalid task switch request.";
        $_SESSION['message_type'] = "danger";
    }
}
header("Location: ../index.php");
exit();
?>
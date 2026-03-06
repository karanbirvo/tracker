<?php
require_once '../includes/functions.php'; // This will include db.php as well
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_name'])) {
    $taskName = trim($_POST['task_name']);
    $userId = $_SESSION['user_id'];

    if (!empty($taskName)) {
        startNewTask($userId, $taskName, $pdo);
        $_SESSION['message'] = "Task '".htmlspecialchars($taskName)."' started.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Task name cannot be empty.";
        $_SESSION['message_type'] = "danger";
    }
}
header("Location: ../index.php");
exit();
?>
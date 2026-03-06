<?php
require_once '../includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];
$activeTask = getActiveTask($userId, $pdo);

if ($activeTask) {
    // 1. Save the details of the currently active task to the session
    $_SESSION['paused_task'] = [
        'project_id' => $activeTask['project_id'],
        'task_name' => $activeTask['task_name'],
        'description' => $activeTask['description']
    ];

    // 2. Stop the timer
    stopCurrentTask($userId, $pdo);

    $_SESSION['message'] = "Task '".htmlspecialchars($activeTask['task_name'])."' paused.";
    $_SESSION['message_type'] = "warning";
} else {
    $_SESSION['message'] = "No active task to pause.";
    $_SESSION['message_type'] = "info";
}

header("Location: ../index.php");
exit();
?>
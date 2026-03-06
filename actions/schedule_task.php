<!-- actions/schedule_task.php -->
<?php
require_once '../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $taskName = trim($_POST['task_name'] ?? '');
    $projectId = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $description = trim($_POST['description'] ?? '');

    if (!empty($taskName)) {
        $stmt = $pdo->prepare("INSERT INTO scheduled_tasks (user_id, project_id, task_name, description, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$userId, $projectId, $taskName, $description]);
        
        $_SESSION['message'] = "Task scheduled successfully.";
        $_SESSION['message_type'] = "info";
    } else {
        $_SESSION['message'] = "Task name required to schedule.";
        $_SESSION['message_type'] = "warning";
    }
}
header("Location: ../index.php");
exit();
?>
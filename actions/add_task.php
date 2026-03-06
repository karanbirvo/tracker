<?php
require_once '../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $taskName = trim($_POST['task_name'] ?? '');
    $projectId = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $description = trim($_POST['description'] ?? '');
    $actionType = $_POST['action_type'] ?? 'tracker';
    $scheduledId = $_POST['scheduled_id'] ?? null;
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

    if (!empty($taskName)) {
        
        // Clear paused state, since we are starting/scheduling something new
        unset($_SESSION['paused_task']);

        // --- CORRECTED LOGIC ---
        // 1. SCHEDULE TASK
        if ($actionType === 'schedule') {
            $stmt = $pdo->prepare("INSERT INTO scheduled_tasks (user_id, project_id, task_name, description, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$userId, $projectId, $taskName, $description]);
            $_SESSION['message'] = "Task scheduled successfully.";
            $_SESSION['message_type'] = "info";
        }
        
        // 2. ON TRACKER or WITHOUT TRACKER
        else {
            // Stop any previously running timer
            stopCurrentTask($userId, $pdo);

            // ON TRACKER (Green) -> time_entries table
            if ($actionType === 'tracker') {
                $stmt = $pdo->prepare("INSERT INTO time_entries (user_id, project_id, task_name, description, start_time, entry_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $projectId, $taskName, $description, $now, $today]);
                $_SESSION['message'] = "Tracker started.";
                $_SESSION['message_type'] = "success";
            }
            // WITHOUT TRACKER (Red) -> manual_entries table
            elseif ($actionType === 'manual') {
                $stmt = $pdo->prepare("INSERT INTO manual_entries (user_id, project_id, task_name, description, start_time, entry_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $projectId, $taskName, $description, $now, $today]);
                $_SESSION['message'] = "Manual Timer started.";
                $_SESSION['message_type'] = "warning";
            }
        }

        // Cleanup: If this task came from the scheduled list, mark it as completed
        if ($scheduledId && $actionType !== 'schedule') {
            $pdo->prepare("UPDATE scheduled_tasks SET status = 'completed' WHERE id = ? AND user_id = ?")->execute([$scheduledId, $userId]);
        }

        // Persist Project for next time
        $_SESSION['last_selected_project'] = $projectId;

    } else {
        $_SESSION['message'] = "Task name is required.";
        $_SESSION['message_type'] = "danger";
    }
}
header("Location: ../index.php");
exit();
?>
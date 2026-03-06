<?php
require_once '../includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];
$projectId = (int)($_GET['id'] ?? 0);

if ($projectId > 0) {
    // Change status from 'Archived' back to 'Active'
    $sql = "UPDATE projects SET status = 'Active' WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$projectId, $userId]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['message'] = "Project has been restored.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Project not found.";
        $_SESSION['message_type'] = "warning";
    }
}

// Redirect back to the archived tab
header("Location: ../projects.php?tab=archived");
exit();
?>
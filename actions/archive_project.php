<?php
require_once '../includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];
$projectId = (int)($_GET['id'] ?? 0);

if ($projectId > 0) {
    // We only change the status, we don't delete it
    $sql = "UPDATE projects SET status = 'Archived' WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$projectId, $userId]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['message'] = "Project has been archived.";
        $_SESSION['message_type'] = "info";
    } else {
        $_SESSION['message'] = "Project not found or already archived.";
        $_SESSION['message_type'] = "warning";
    }
}

header("Location: ../projects.php");
exit();
?>
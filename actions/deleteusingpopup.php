<?php
require_once '../includes/functions.php';
requireLogin();

// 1. Permission Check
if (!hasPermission('perm_delete')) {
    $_SESSION['message'] = "Access Denied: You do not have permission to delete entries.";
    $_SESSION['message_type'] = "danger";
    header("Location: ../admin_reports.php"); 
    exit();
}

// ... (Rest of file remains same, see below for full context if needed)
$redirect_user_id = $_POST['user_id_selected'] ?? '';
$redirect_date_from = $_POST['date_from'] ?? date('Y-m-01');
$redirect_date_to = $_POST['date_to'] ?? date('Y-m-d');
$redirect_url = "../index.php?user_id_selected=$redirect_user_id&date_from=$redirect_date_from&date_to=$redirect_date_to";

// 3. Perform Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entry_id'])) {
    $entryId = (int)$_POST['entry_id'];
    
    // Check Entry Type to determine Table
    $type = $_POST['entry_type'] ?? 'tracker';
    $tableName = ($type === 'manual') ? 'manual_entries' : 'time_entries';

    try {
        $stmt = $pdo->prepare("DELETE FROM $tableName WHERE id = :id");
        $stmt->bindParam(':id', $entryId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                $_SESSION['message'] = "Entry deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Entry ID $entryId not found in $tableName.";
                $_SESSION['message_type'] = "warning";
            }
        } else {
            $_SESSION['message'] = "Could not delete entry.";
            $_SESSION['message_type'] = "danger";
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "Database Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
} else {
    $_SESSION['message'] = "Invalid request.";
    $_SESSION['message_type'] = "danger";
}
header("Location: " . $redirect_url);
exit();
?>
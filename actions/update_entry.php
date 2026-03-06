<?php
require_once '../includes/functions.php';
requireLogin();

if (!hasPermission('perm_edit')) {
    $_SESSION['message'] = "Access Denied."; $_SESSION['message_type'] = "danger";
    header("Location: ../index.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entryId = $_POST['entry_id'];
    $currentType = $_POST['current_type'];
    $newType = $_POST['entry_type'];
    
    // Data from form
    $data = [
        'task_name' => trim($_POST['task_name']),
        'description' => trim($_POST['description']),
        'project_id' => !empty($_POST['project_id']) ? $_POST['project_id'] : null,
        'entry_date' => $_POST['entry_date'],
        'start_time' => $_POST['entry_date'] . ' ' . $_POST['start_time'],
        'end_time' => !empty($_POST['end_time']) ? $_POST['entry_date'] . ' ' . $_POST['end_time'] : null,
    ];

    try {
        $pdo->beginTransaction();

        if ($newType === $currentType) {
            $table = ($newType === 'manual') ? 'manual_entries' : 'time_entries';
            $sql = "UPDATE $table SET task_name=?, description=?, project_id=?, start_time=?, end_time=?, entry_date=? WHERE id=?";
            $pdo->prepare($sql)->execute([$data['task_name'], $data['description'], $data['project_id'], $data['start_time'], $data['end_time'], $data['entry_date'], $entryId]);
        } else {
            $oldTable = ($currentType === 'manual') ? 'manual_entries' : 'time_entries';
            $newTable = ($newType === 'manual') ? 'manual_entries' : 'time_entries';
            
            // Get user_id before deleting
            $stmt = $pdo->prepare("SELECT user_id FROM $oldTable WHERE id=?");
            $stmt->execute([$entryId]);
            $userId = $stmt->fetchColumn();

            // Insert into new table
            $sql_ins = "INSERT INTO $newTable (user_id, project_id, task_name, description, start_time, end_time, entry_date) VALUES (?,?,?,?,?,?,?)";
            $pdo->prepare($sql_ins)->execute([$userId, $data['project_id'], $data['task_name'], $data['description'], $data['start_time'], $data['end_time'], $data['entry_date']]);

            // Delete from old table
            $pdo->prepare("DELETE FROM $oldTable WHERE id=?")->execute([$entryId]);
        }

        $pdo->commit();
        $_SESSION['message'] = "Entry updated successfully.";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error updating entry: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}
// Redirect back to the last visited page or a default
$redirectTo = $_SERVER['HTTP_REFERER'] ?? '../index.php';
header("Location: $redirectTo");
exit();
?>
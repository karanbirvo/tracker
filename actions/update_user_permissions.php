<?php
require_once '../includes/functions.php';
requireLogin();

// Security Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Access Denied");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = (int)$_POST['user_id'];
    
    // PROTECT SUPER ADMIN (ID 1)
    if ($targetUserId === 1) {
        $_SESSION['message'] = "Cannot modify the Project Owner.";
        $_SESSION['message_type'] = "warning";
        header("Location: ../admin_users.php");
        exit();
    }

    // 1. Update Role
    $role = $_POST['user_role'];
    
    // 2. Get Permissions (Checkbox logic: if not set, it's 0)
    $pDelete = isset($_POST['perm_delete']) ? 1 : 0;
    $pEdit   = isset($_POST['perm_edit']) ? 1 : 0;
    $pAi     = isset($_POST['perm_ai']) ? 1 : 0;
    $pEmail  = isset($_POST['perm_email']) ? 1 : 0;
    $pReports= isset($_POST['perm_reports']) ? 1 : 0;
    $pViewAll= isset($_POST['perm_view_all']) ? 1 : 0;

    try {
        $sql = "UPDATE users SET 
                user_role = ?, 
                perm_delete = ?, 
                perm_edit = ?, 
                perm_ai = ?, 
                perm_email = ?, 
                perm_reports = ?, 
                perm_view_all = ? 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$role, $pDelete, $pEdit, $pAi, $pEmail, $pReports, $pViewAll, $targetUserId]);

        $_SESSION['message'] = "User permissions updated successfully.";
        $_SESSION['message_type'] = "success";

    } catch (PDOException $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

header("Location: ../admin_users.php");
exit();
?>
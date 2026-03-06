<?php
require_once '../includes/header.php'; // To initialize session, db, functions
requireLogin();

// Admin Access Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access Denied: You do not have administrative privileges to delete entries.";
    $_SESSION['message_type'] = "danger";
    header("Location: ../index.php"); // Redirect to a safe page
    exit();
}

// Default redirect parameters (in case some are missing from POST)
$redirect_user_id = isset($_POST['user_id_selected']) ? $_POST['user_id_selected'] : '';
$redirect_date_from = isset($_POST['date_from']) ? $_POST['date_from'] : date('Y-m-01');
$redirect_date_to = isset($_POST['date_to']) ? $_POST['date_to'] : date('Y-m-d');

$redirect_url = "../admin_reports.php";
$redirect_params = [];
if (!empty($redirect_user_id)) $redirect_params['user_id_selected'] = $redirect_user_id;
if (!empty($redirect_date_from)) $redirect_params['date_from'] = $redirect_date_from;
if (!empty($redirect_date_to)) $redirect_params['date_to'] = $redirect_date_to;

if (!empty($redirect_params)) {
    $redirect_url .= "?" . http_build_query($redirect_params);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entry_id']) && is_numeric($_POST['entry_id'])) {
    $entryIdToDelete = (int)$_POST['entry_id'];

    try {
        // Optional: You might want to fetch the entry first to log details before deleting
        // $stmtFetch = $pdo->prepare("SELECT * FROM time_entries WHERE id = ?");
        // $stmtFetch->execute([$entryIdToDelete]);
        // $entryToDeleteDetails = $stmtFetch->fetch();
        // if ($entryToDeleteDetails) { /* Log details */ }


        $stmt = $pdo->prepare("DELETE FROM time_entries WHERE id = :id");
        $stmt->bindParam(':id', $entryIdToDelete, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                $_SESSION['message'] = "Time entry ID: " . htmlspecialchars($entryIdToDelete) . " has been successfully deleted.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Time entry ID: " . htmlspecialchars($entryIdToDelete) . " was not found or already deleted.";
                $_SESSION['message_type'] = "warning";
            }
        } else {
            $_SESSION['message'] = "Error: Could not delete time entry ID: " . htmlspecialchars($entryIdToDelete) . ".";
            $_SESSION['message_type'] = "danger";
        }
    } catch (PDOException $e) {
        error_log("Error deleting time entry ID " . $entryIdToDelete . ": " . $e->getMessage());
        $_SESSION['message'] = "Database error: Could not delete the time entry. Please check server logs.";
        $_SESSION['message_type'] = "danger";
    }
} else {
    $_SESSION['message'] = "Invalid request for deleting entry.";
    $_SESSION['message_type'] = "danger";
}

header("Location: " . $redirect_url);
exit();
?>
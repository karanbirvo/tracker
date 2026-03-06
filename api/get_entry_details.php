<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn() || !hasPermission('perm_edit')) {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

$id = $_GET['id'] ?? 0;
$type = $_GET['type'] ?? 'tracker';
$tableName = ($type === 'manual') ? 'manual_entries' : 'time_entries';

try {
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE id = ?");
    $stmt->execute([$id]);
    $entry = $stmt->fetch();

    if ($entry) {
        echo json_encode(['success' => true, 'data' => $entry]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Entry not found.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>
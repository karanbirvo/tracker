<?php
// 1. SILENCE ERRORS & CLEAN BUFFER
error_reporting(0);
ini_set('display_errors', 0);

// 2. Start Session Safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Connect DB
require_once '../includes/db.php';

// 4. Send JSON Header
header('Content-Type: application/json');

// 5. Auth Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$userId = $_SESSION['user_id'];
$projectId = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int)$_GET['project_id'] : null;

try {
    // --- UPDATED SQL with UNION ---
    // This query fetches distinct task names from BOTH tables for the selected project
    
    // Base part of the query
    $tracker_sql = "SELECT DISTINCT task_name FROM time_entries WHERE user_id = :uid1";
    $manual_sql = "SELECT DISTINCT task_name FROM manual_entries WHERE user_id = :uid2";
    
    // Parameters start with user IDs
    $params = ['uid1' => $userId, 'uid2' => $userId];
    
    // Add project filter if a project is selected
    if ($projectId) {
        $tracker_sql .= " AND project_id = :pid1";
        $manual_sql .= " AND project_id = :pid2";
        $params['pid1'] = $projectId;
        $params['pid2'] = $projectId;
    }

    // Combine the queries with UNION and apply final ordering and limit
    $final_sql = "($tracker_sql) UNION ($manual_sql) LIMIT 50";

    $stmt = $pdo->prepare($final_sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($tasks);

} catch (Exception $e) {
    // On error, return an empty array to prevent breaking the frontend
    echo json_encode([]);
}
?>
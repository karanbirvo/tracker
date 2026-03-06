<?php
// Ensure db.php is included, which starts session
if (empty($pdo)) {
    require_once __DIR__ . '/db.php';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    global $pdo;
    if (!isset($_SESSION['user_id'], $_SESSION['session_token'])) { logoutAndRedirect(); }
    $stmt = $pdo->prepare("SELECT session_token FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['session_token'] !== $_SESSION['session_token']) { logoutAndRedirect(); }
}

function logoutAndRedirect() {
    session_unset(); session_destroy(); header("Location: /login.php"); exit();
}

function stopCurrentTask($userId, $pdo) {
    $now = date('Y-m-d H:i:s');
    $stmt1 = $pdo->prepare("UPDATE time_entries SET end_time = :now WHERE user_id = :user_id AND end_time IS NULL");
    $stmt1->execute(['now' => $now, 'user_id' => $userId]);
    $stmt2 = $pdo->prepare("UPDATE manual_entries SET end_time = :now WHERE user_id = :user_id AND end_time IS NULL");
    $stmt2->execute(['now' => $now, 'user_id' => $userId]);
}

function getUserProjects($userId, $pdo) {
    $stmt = $pdo->prepare("SELECT id, project_name, client_name FROM projects WHERE user_id = ? AND status = 'Active' ORDER BY project_name ASC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getTodaysEntries($userId, $pdo) {
    $today = date('Y-m-d');
    $sql = "(SELECT id, task_name, description, start_time, end_time, 'tracker' as type, project_id FROM time_entries WHERE user_id = :uid1 AND entry_date = :today1) UNION ALL (SELECT id, task_name, description, start_time, end_time, 'manual' as type, project_id FROM manual_entries WHERE user_id = :uid2 AND entry_date = :today2) ORDER BY start_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uid1'=>$userId, 'today1'=>$today, 'uid2'=>$userId, 'today2'=>$today]);
    $entries = $stmt->fetchAll();
    // Join projects in PHP to avoid SQL collation issues
    foreach ($entries as $key => $entry) {
        if ($entry['project_id']) {
            $projStmt = $pdo->prepare("SELECT project_name FROM projects WHERE id = ?");
            $projStmt->execute([$entry['project_id']]);
            $entries[$key]['project_name'] = $projStmt->fetchColumn();
        } else {
            $entries[$key]['project_name'] = null;
        }
    }
    return $entries;
}

function getActiveTask($userId, $pdo) {
    $sql1 = "SELECT te.*, p.project_name, 'tracker' as type FROM time_entries te LEFT JOIN projects p ON te.project_id = p.id WHERE te.user_id = :user_id AND te.end_time IS NULL LIMIT 1";
    $stmt = $pdo->prepare($sql1); $stmt->execute(['user_id' => $userId]);
    if ($res = $stmt->fetch()) return $res;
    $sql2 = "SELECT me.*, p.project_name, 'manual' as type FROM manual_entries me LEFT JOIN projects p ON me.project_id = p.id WHERE me.user_id = :user_id AND me.end_time IS NULL LIMIT 1";
    $stmt = $pdo->prepare($sql2); $stmt->execute(['user_id' => $userId]);
    return $stmt->fetch();
}

function formatDuration($startTime, $endTime = null) {
    if ($endTime === null) $endTime = new DateTime(); else $endTime = new DateTime($endTime);
    $startTime = new DateTime($startTime);
    $interval = $startTime->diff($endTime);
    $duration = "";
    if ($interval->h > 0) $duration .= $interval->h . "h ";
    if ($interval->i > 0) $duration .= $interval->i . "m ";
    if ($interval->s > 0) $duration .= $interval->s . "s";
    return empty($duration) ? "0s" : trim($duration);
}

// Encryption Helpers
function encrypt_data($data) {
    if (empty($data)) return '';
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($data, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);
    return ($encrypted === false) ? false : base64_encode($iv . $encrypted);
}

function decrypt_data($encrypted_data) {
    if (empty($encrypted_data)) return '';
    $decoded_data = base64_decode($encrypted_data);
    if ($decoded_data === false) return false;
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = substr($decoded_data, 0, $iv_length);
    $ciphertext = substr($decoded_data, $iv_length);
    if (strlen($iv) < $iv_length || empty($ciphertext)) return false;
    return openssl_decrypt($ciphertext, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);
}


// --- PERMISSION CHECKER FUNCTION (This was the missing piece) ---
function hasPermission($permName) {
    global $pdo;
    
    // Super Admin (ID 1) always has all permissions
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) return true;

    // Check for specific permission flag in the database
    if (isset($_SESSION['user_id'])) {
        // Ensure the column name is safe
        $allowedPerms = ['perm_delete', 'perm_edit', 'perm_ai', 'perm_email', 'perm_reports', 'perm_view_all'];
        if (!in_array($permName, $allowedPerms)) {
            return false;
        }
        
        $stmt = $pdo->prepare("SELECT `$permName` FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return (bool) $stmt->fetchColumn();
    }
    
    return false;
}
?>
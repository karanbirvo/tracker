<?php
// Ensure db.php is included, which starts session and sets timezone
if (empty($pdo)) {
    require_once __DIR__ . '/db.php';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    global $pdo;

    if (!isset($_SESSION['user_id'], $_SESSION['session_token'])) {
        logoutAndRedirect();
    }

    $stmt = $pdo->prepare("SELECT session_token FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['session_token'] !== $_SESSION['session_token']) {
        logoutAndRedirect();
    }
}

function logoutAndRedirect() {
    session_unset();
    session_destroy();
    header("Location: /login.php");
    exit();
}


function stopCurrentTask($userId, $pdo) {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE time_entries SET end_time = :now WHERE user_id = :user_id AND end_time IS NULL");
    $stmt->execute(['now' => $now, 'user_id' => $userId]);
    return $stmt->rowCount() > 0;
}

function startNewTask($userId, $taskName, $pdo) {
    stopCurrentTask($userId, $pdo);

    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("INSERT INTO time_entries (user_id, task_name, start_time, entry_date) VALUES (:user_id, :task_name, :start_time, :entry_date)");
    $stmt->execute([
        'user_id' => $userId,
        'task_name' => $taskName,
        'start_time' => $now,
        'entry_date' => $today
    ]);
    return $pdo->lastInsertId();
}

function getTodaysEntries($userId, $pdo) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM time_entries WHERE user_id = :user_id AND entry_date = :today ORDER BY start_time ASC");
    $stmt->execute(['user_id' => $userId, 'today' => $today]);
    return $stmt->fetchAll();
}

function getActiveTask($userId, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM time_entries WHERE user_id = :user_id AND end_time IS NULL ORDER BY start_time DESC LIMIT 1");
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetch();
}

function formatDuration($startTime, $endTime = null) {
    if ($endTime === null) {
        $endTime = new DateTime();
    } else {
        $endTime = new DateTime($endTime);
    }
    $startTime = new DateTime($startTime);
    $interval = $startTime->diff($endTime);

    $duration = "";
    if ($interval->h > 0) $duration .= $interval->h . "h ";
    if ($interval->i > 0) $duration .= $interval->i . "m ";
    if ($interval->s > 0) $duration .= $interval->s . "s";
    if (empty($duration)) $duration = "0s";
    
    return trim($duration);
}

function getUniqueTaskNamesForToday($userId, $pdo) {
    $today = date('Y-m-d');
    $staticTasks = ['Login', 'in', 'out', 'Break', 'Lunch', 'Breakfast', 'Dinner', 'Bio Break', 'Fun Friday', 'logout'];
    $placeholders = implode(',', array_fill(0, count($staticTasks), '?'));

    $sql = "SELECT DISTINCT task_name 
            FROM time_entries 
            WHERE user_id = ? AND entry_date = ? 
            AND task_name NOT IN ($placeholders)
            ORDER BY MIN(start_time) DESC";

    $params = array_merge([$userId, $today], $staticTasks);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ... (after your existing functions)

/**
 * Encrypts data using the key and cipher defined in db.php.
 *
 * @param string $data The plaintext data to encrypt.
 * @return string|false The encrypted and base64-encoded data, or false on failure.
 */
function encrypt_data($data) {
    if (empty($data)) {
        return '';
    }
    // Generate a random Initialization Vector (IV)
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = openssl_random_pseudo_bytes($iv_length);

    // Encrypt the data
    $encrypted = openssl_encrypt($data, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);

    if ($encrypted === false) {
        return false; // Encryption failed
    }

    // Prepend the IV to the encrypted data, then Base64 encode for safe DB storage
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypts data using the key and cipher defined in db.php.
 *
 * @param string $encrypted_data The base64-encoded string containing the IV and ciphertext.
 * @return string|false The original plaintext data, or false on failure.
 */
function decrypt_data($encrypted_data) {
    if (empty($encrypted_data)) {
        return '';
    }
    // Decode from Base64
    $decoded_data = base64_decode($encrypted_data);
    if ($decoded_data === false) {
        return false; // Base64 decode failed
    }

    // Extract the IV from the beginning of the decoded data
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = substr($decoded_data, 0, $iv_length);
    $ciphertext = substr($decoded_data, $iv_length);

    if (strlen($iv) < $iv_length || empty($ciphertext)) {
        return false; // Data is malformed
    }
    
    // Decrypt the data
    $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);
    
    return $decrypted; // Returns false on failure
}

?>

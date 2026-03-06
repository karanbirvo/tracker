<?php
require_once 'includes/functions.php';
requireLogin();

// Only admin (user ID 1) can access this page
if ($_SESSION['user_id'] != 1) {
    echo "<p style='color: red; font-weight: bold;'>Access Denied: Only administrators can manage API keys.</p>";
    echo "<a href='index.php'>Back to Dashboard</a>";
    exit();
}

$message = '';
$messageType = ''; // 'success' or 'error'

// Handle API Key Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_key') {
    $keyName = $_POST['key_name'] ?? '';
    $keyValue = $_POST['key_value'] ?? '';
    
    // Validate input
    if (empty($keyName) || empty($keyValue)) {
        $message = 'Error: Both API Key Name and Value are required.';
        $messageType = 'error';
    } else {
        // Store the key securely (encrypted)
        if (setApiKey($keyName, $keyValue)) {
            $message = "✓ API Key '$keyName' has been saved securely.";
            $messageType = 'success';
        } else {
            $message = "Error: Failed to save API key. Please try again.";
            $messageType = 'error';
        }
    }
}

// Retrieve existing Gemini API key (masked for security)
$geminiKeyExists = false;
try {
    $stmt = $pdo->prepare("SELECT key_value FROM api_keys WHERE key_name = ?");
    $stmt->execute(['gemini']);
    $result = $stmt->fetch();
    if ($result && !empty($result['key_value'])) {
        $geminiKeyExists = true;
    }
} catch (PDOException $e) {
    // Query failed
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys Management</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; font-size: 14px; margin-bottom: 20px; }
        .alert { padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 13px; box-sizing: border-box; }
        input[type="text"]:focus, textarea:focus { outline: none; border-color: #4CAF50; box-shadow: 0 0 5px rgba(76, 175, 80, 0.3); }
        .button-group { display: flex; gap: 10px; margin-top: 25px; }
        button { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; transition: 0.3s; }
        .btn-save { background: #4CAF50; color: white; }
        .btn-save:hover { background: #45a049; }
        .btn-back { background: #ccc; color: #333; }
        .btn-back:hover { background: #bbb; }
        .info-box { background: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px; border-radius: 4px; margin-bottom: 20px; color: #1565c0; }
        .key-status { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .key-status.exists { background: #c8e6c9; color: #2e7d32; }
        .key-status.missing { background: #ffecb3; color: #f57f17; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 API Keys Management</h1>
        <p class="subtitle">Securely store sensitive API keys (encrypted in database)</p>
        
        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <strong>ℹ️ Security Notice:</strong> All API keys are encrypted using AES-256-CBC before storage. Never share your actual API keys in plain text.
        </div>
        
        <!-- API Key Status -->
        <div class="key-status <?php echo $geminiKeyExists ? 'exists' : 'missing'; ?>">
            <strong>Gemini API Key Status:</strong>
            <?php echo $geminiKeyExists ? '✓ Key is stored' : '⚠ No key stored yet'; ?>
        </div>
        
        <!-- Update Gemini API Key Form -->
        <form method="POST">
            <input type="hidden" name="action" value="update_key">
            
            <div class="form-group">
                <label for="key_name">API Key Name:</label>
                <input type="text" id="key_name" name="key_name" value="gemini" readonly title="API key identifier">
            </div>
            
            <div class="form-group">
                <label for="key_value">Gemini API Key:</label>
                <textarea id="key_value" name="key_value" rows="3" placeholder="Paste your Gemini API key here..." required></textarea>
                <small style="color: #666; display: block; margin-top: 5px;">Get your API key from: <a href="https://ai.google.dev/" target="_blank">https://ai.google.dev/</a></small>
            </div>
            
            <div class="button-group">
                <button type="submit" class="btn-save">💾 Save API Key</button>
                <a href="index.php"><button type="button" class="btn-back">← Back</button></a>
            </div>
        </form>
        
        <hr style="margin-top: 30px; color: #eee;">
        
        <div style="font-size: 12px; color: #999; margin-top: 20px;">
            <p><strong>How it works:</strong></p>
            <ul>
                <li>Your API key is encrypted with: <code>aes-256-cbc</code></li>
                <li>Encryption key: The one defined in your <code>db.php</code></li>
                <li>Stored in database table: <code>api_keys</code></li>
                <li>Retrieved and decrypted automatically when needed</li>
            </ul>
        </div>
    </div>
</body>
</html>

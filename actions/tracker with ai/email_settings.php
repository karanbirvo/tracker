<?php
require_once 'includes/header.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Handle form submission to save/update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE users SET 
        smtp_host = :smtp_host, 
        smtp_port = :smtp_port, 
        smtp_username = :smtp_username, 
        smtp_password = :smtp_password, 
        default_recipients = :default_recipients 
        WHERE id = :user_id");
    
    // Encrypt each piece of data before storing it in the database
    $stmt->execute([
        'smtp_host' => encrypt_data(trim($_POST['smtp_host'])),
        'smtp_port' => encrypt_data(trim($_POST['smtp_port'])),
        'smtp_username' => encrypt_data(trim($_POST['smtp_username'])),
        // Only update the password if a new one is typed in. Otherwise, keep the old one.
        'smtp_password' => !empty($_POST['smtp_password']) ? encrypt_data($_POST['smtp_password']) : $_POST['current_password_encrypted'],
        'default_recipients' => encrypt_data(trim($_POST['default_recipients'])),
        'user_id' => $userId
    ]);

    $_SESSION['message'] = "Email settings saved successfully.";
    $_SESSION['message_type'] = "success";
    header("Location: email_settings.php");
    exit();
}

// Fetch current settings (they are encrypted in the DB)
$stmt = $pdo->prepare("SELECT smtp_host, smtp_port, smtp_username, smtp_password, default_recipients FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userSettings = $stmt->fetch();

// Decrypt data for display in the form
$decrypted_host = decrypt_data($userSettings['smtp_host'] ?? '');
$decrypted_port = decrypt_data($userSettings['smtp_port'] ?? '');
$decrypted_username = decrypt_data($userSettings['smtp_username'] ?? '');
$decrypted_recipients = decrypt_data($userSettings['default_recipients'] ?? '');
// For security, we never show the password, just an indicator if it's set.
$password_is_set = !empty($userSettings['smtp_password']);
?>

<h1><i class="fas fa-envelope-cog"></i> Email & SMTP Settings</h1>
<p>Configure your outgoing email server (SMTP) to send EOD reports. This information is stored securely and is required for the "Send EOD" feature.</p>

<div class="card" style="max-width: 700px; padding: 25px; margin-top: 20px;">
    <form action="email_settings.php" method="POST">
        <h2>SMTP Configuration</h2>
        <div class="form-group">
            <label for="smtp_host">SMTP Host:</label>
            <input type="text" name="smtp_host" id="smtp_host" class="form-control" value="<?= htmlspecialchars($decrypted_host) ?>" placeholder="e.g., smtp.office365.com or smtp.gmail.com" required>
        </div>
        <div class="form-group">
            <label for="smtp_port">SMTP Port:</label>
            <input type="number" name="smtp_port" id="smtp_port" class="form-control" value="<?= htmlspecialchars($decrypted_port) ?>" placeholder="e.g., 587 (TLS) or 465 (SSL)" required>
        </div>
        <div class="form-group">
            <label for="smtp_username">SMTP Username (Your Full Email Address):</label>
            <input type="email" name="smtp_username" id="smtp_username" class="form-control" value="<?= htmlspecialchars($decrypted_username) ?>" required>
        </div>
        <div class="form-group">
            <label for="smtp_password">SMTP Password:</label>
            <input type="password" name="smtp_password" id="smtp_password" class="form-control" placeholder="<?= $password_is_set ? 'Enter new password to change' : 'Enter your email password' ?>">
            <small>For security, your password is never shown. Leave this field blank to keep your current saved password.</small>
            <!-- This hidden field holds the current encrypted password. -->
            <input type="hidden" name="current_password_encrypted" value="<?= htmlspecialchars($userSettings['smtp_password'] ?? '') ?>">
        </div>

        <h2 style="margin-top: 30px;">Default Recipients</h2>
        <div class="form-group">
            <label for="default_recipients">Default "To" Addresses:</label>
            <textarea name="default_recipients" id="default_recipients" class="form-control" rows="3" placeholder="manager@example.com, supervisor@example.com"><?= htmlspecialchars($decrypted_recipients) ?></textarea>
             <small>Enter email addresses separated by a comma.</small>
        </div>
        
        <div style="margin-top: 25px;">
            <button type="submit" class="button button-primary"><i class="fas fa-save"></i> Save Settings</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
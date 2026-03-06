<?php
require_once 'includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We encrypt all the new data before saving
    $stmt = $pdo->prepare("UPDATE users SET 
        smtp_host = :smtp_host, 
        smtp_port = :smtp_port, 
        smtp_username = :smtp_username, 
        smtp_password = :smtp_password, 
        default_recipients = :default_recipients,
        email_sender_name = :sender_name,
        email_reply_to = :reply_to
        WHERE id = :user_id");
    
    $stmt->execute([
        'smtp_host' => encrypt_data(trim($_POST['smtp_host'])),
        'smtp_port' => encrypt_data(trim($_POST['smtp_port'])),
        'smtp_username' => encrypt_data(trim($_POST['smtp_username'])),
        'smtp_password' => !empty($_POST['smtp_password']) ? encrypt_data($_POST['smtp_password']) : $_POST['current_password_encrypted'],
        'default_recipients' => encrypt_data(trim($_POST['default_recipients'])),
        'sender_name' => encrypt_data(trim($_POST['email_sender_name'])),
        'reply_to' => encrypt_data(trim($_POST['email_reply_to'])),
        'user_id' => $userId
    ]);

    $_SESSION['message'] = "Email settings saved successfully.";
    $_SESSION['message_type'] = "success";
    header("Location: email_settings.php");
    exit();
}

// Fetch and Decrypt Current Settings
$stmt = $pdo->prepare("SELECT smtp_host, smtp_port, smtp_username, smtp_password, default_recipients, email_sender_name, email_reply_to FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userSettings = $stmt->fetch();

$host = decrypt_data($userSettings['smtp_host'] ?? '');
$port = decrypt_data($userSettings['smtp_port'] ?? '');
$username = decrypt_data($userSettings['smtp_username'] ?? '');
$recipients = decrypt_data($userSettings['default_recipients'] ?? '');
$sender_name = decrypt_data($userSettings['email_sender_name'] ?? '');
$reply_to = decrypt_data($userSettings['email_reply_to'] ?? '');
$password_is_set = !empty($userSettings['smtp_password']);

require_once 'includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-cogs text-primary me-2"></i>Email Settings</h2>
            <p class="text-muted m-0 small">Configure your outgoing mail server and identity.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="email_settings.php" method="POST">
                
                <!-- 1. Sender Identity -->
                <h5 class="fw-bold mb-3 border-bottom pb-2">Sender Identity</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">YOUR SENDER NAME</label>
                        <input type="text" name="email_sender_name" class="form-control" value="<?= htmlspecialchars($sender_name) ?>" placeholder="e.g., John Doe from Company">
                        <div class="form-text">This is the name recipients will see.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">"REPLY-TO" EMAIL ADDRESS</label>
                        <input type="email" name="email_reply_to" class="form-control" value="<?= htmlspecialchars($reply_to) ?>" placeholder="e.g., your.manager@example.com">
                        <div class="form-text">Replies will go to this address. If blank, uses "From" address.</div>
                    </div>
                </div>

                <!-- 2. SMTP Server Configuration -->
                <h5 class="fw-bold mb-3 border-bottom pb-2">SMTP Server</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">SMTP HOST</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($host) ?>" required placeholder="e.g., smtp.gmail.com">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">SMTP PORT</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($port) ?>" required placeholder="e.g., 587">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">SMTP USERNAME ("FROM" ADDRESS)</label>
                        <input type="email" name="smtp_username" class="form-control" value="<?= htmlspecialchars($username) ?>" required placeholder="your.email@example.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">SMTP PASSWORD</label>
                        <input type="password" name="smtp_password" class="form-control" placeholder="<?= $password_is_set ? 'Enter new password to change' : 'Enter password' ?>">
                        <div class="form-text">Leave blank to keep your current saved password.</div>
                        <input type="hidden" name="current_password_encrypted" value="<?= htmlspecialchars($userSettings['smtp_password'] ?? '') ?>">
                    </div>
                </div>

                <!-- 3. Default Recipients -->
                <h5 class="fw-bold mb-3 border-bottom pb-2">Defaults</h5>
                <div>
                    <label class="form-label small fw-bold text-muted">DEFAULT "TO" ADDRESSES (COMMA-SEPARATED)</label>
                    <textarea name="default_recipients" class="form-control" rows="2" placeholder="manager@example.com, team@example.com"><?= htmlspecialchars($recipients) ?></textarea>
                </div>

                <!-- Save Button -->
                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="fas fa-save me-2"></i>Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
body { background-color: #f8f9fa; }
.form-control:focus, .form-select:focus {
    box-shadow: 0 0 0 0.2rem rgba(45, 90, 149, 0.15);
    border-color: #2D5A95;
}
</style>

<?php require_once 'includes/footer.php'; ?>
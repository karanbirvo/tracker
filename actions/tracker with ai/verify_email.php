<?php
require_once 'includes/header.php'; // For DB connection, session, functions

$message = "";
$message_type = "info"; // Default

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    // Find user by token
    $stmt = $pdo->prepare("SELECT id, username, is_email_verified FROM users WHERE email_verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['is_email_verified']) {
            $message = "This email address has already been verified. You can now login.";
            $message_type = "info";
        } else {
            // Mark email as verified and clear the token
            $updateStmt = $pdo->prepare("UPDATE users SET is_email_verified = TRUE, email_verification_token = NULL WHERE id = ?");
            if ($updateStmt->execute([$user['id']])) {
                $message = "Email successfully verified for " . htmlspecialchars($user['username']) . "! You can now log in.";
                $message_type = "success";
            } else {
                $message = "Error updating your account. Please try again or contact support.";
                $message_type = "danger";
            }
        }
    } else {
        $message = "Invalid or expired verification token. Please register again or contact support if you believe this is an error.";
        $message_type = "danger";
    }
} else {
    $message = "No verification token provided. Please use the link sent to your email.";
    $message_type = "warning";
}

// Set session message to display on login page (or a dedicated message page)
$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;

// Redirect to login page to show the message
header("Location: login.php");
exit();

/*
// --- Alternative: Display message directly on this page ---
?>
<h1>Email Verification</h1>
<div class="card" style="padding:20px; max-width: 600px; margin: 20px auto;">
    <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php if ($message_type === 'success' || ($user && $user['is_email_verified'])): ?>
        <p style="text-align:center;"><a href="login.php" class="button button-primary">Proceed to Login</a></p>
    <?php elseif ($message_type === 'danger' || $message_type === 'warning'): ?>
         <p style="text-align:center;"><a href="register.php" class="button">Register Again</a></p>
    <?php endif; ?>
</div>
<?php
// require_once 'includes/footer.php';
// --- End Alternative ---
*/
?>
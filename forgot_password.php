<?php
// --- PHP LOGIC (No changes needed here) ---
require_once 'includes/functions.php';
$stage = 1; $username = ''; $security_question = ''; $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username'])) {
        $username = trim($_POST['username']);
        $stmt = $pdo->prepare("SELECT username, security_question FROM users WHERE username = ? OR email = ? OR mobile_number = ?");
        $stmt->execute([$username, $username, $username]);
        $user = $stmt->fetch();
        if ($user && !empty($user['security_question'])) { $stage = 2; $security_question = $user['security_question']; $username = $user['username']; }
        else { $errors[] = "Account not found or no security question is set."; }
    }
    elseif (isset($_POST['security_answer'])) {
        $username = trim($_POST['username_hidden']); $answer = trim($_POST['security_answer']);
        $stmt = $pdo->prepare("SELECT id, security_answer_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($answer, $user['security_answer_hash'])) {
            $token = bin2hex(random_bytes(32)); $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
            $updateStmt->execute([$token, $expires, $user['id']]);
            header("Location: reset_password.php?token=" . $token); exit();
        } else {
            $errors[] = "The security answer is incorrect.";
            $stmt = $pdo->prepare("SELECT security_question FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $security_question = $stmt->fetchColumn(); $stage = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - VO TimeTracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* --- Using the same CSS as the login page for consistency --- */
    :root { --dark-navy: #0b1a2e; --light-navy: #112240; --accent-green: #64ffda; --light-grey: #ccd6f6; --mid-grey: #8892b0; }
    @keyframes slideInLeft { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    body.auth-page { font-family: 'Poppins', sans-serif; color: var(--light-grey); overflow: hidden; }    /*background-color: var(--dark-navy); */
    .auth-wrapper { display: flex; width: 100vw; height: 100vh; align-items: center; justify-content: center; padding: 20px; }
    .auth-container { display: flex; width: 100%; max-width: 950px; height: 650px; background-color: var(--light-navy); border-radius: 15px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); overflow: hidden; }
    .branding-panel { width: 45%; background: linear-gradient(160deg, #0d2c44, var(--light-navy)); padding: 50px; display: flex; flex-direction: column; justify-content: center; animation: slideInLeft 0.8s cubic-bezier(0.25, 1, 0.5, 1); text-align: center; }
    .branding-panel .logo-icon { font-size: 4rem; margin-bottom: 20px; color: var(--accent-green); }
    .branding-panel h2 { font-size: 2.5rem; font-weight: 600; margin: 0; color: #fff; }
    .branding-panel p { font-size: 1.1rem; color: var(--mid-grey); line-height: 1.6; margin-top: 15px; }
    .form-panel { width: 55%; padding: 50px; display: flex; flex-direction: column; justify-content: center; animation: slideInRight 0.8s cubic-bezier(0.25, 1, 0.5, 1); }
    .form-panel h1 { font-weight: 600; margin-bottom: 30px; }
    .input-group { position: relative; margin-bottom: 35px; }
    .input-group input { width: 100%; padding: 10px 0; background: transparent; border: none; border-bottom: 2px solid var(--mid-grey); color: #fff; font-size: 1rem; }
    .input-group input:focus { outline: none; border-bottom-color: var(--accent-green); }
    .input-group label { position: absolute; top: 10px; left: 0; color: var(--mid-grey); pointer-events: none; transition: all 0.2s ease; }
    .input-group input:focus + label, .input-group input:valid + label { top: -15px; font-size: 0.8rem; color: var(--accent-green); }
    .button { background: none; border: 1px solid var(--accent-green); color: var(--accent-green); padding: 12px 30px; border-radius: 50px; cursor: pointer; font-weight: bold; transition: all 0.3s ease; text-decoration: none; display: inline-block; text-align: center; }
    .button.btn-primary { background-color: var(--accent-green); color: var(--dark-navy); width: 100%; }
    .form-links { text-align: right; margin-top: 20px; font-size: 0.9rem; }
    .form-links a { color: var(--accent-green); text-decoration: none; }
    .alert-danger { background-color: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #f8d7da; border-radius: 8px; padding: 15px; }
    @media (max-width: 850px) { .branding-panel { display: none; } .form-panel { width: 100%; } .auth-container { height: auto; max-height: 100vh; margin: 20px; } }
</style>
</head>
<body class="auth-page">

<div class="auth-wrapper">
    <div class="auth-container">
        <div class="branding-panel">
            <i class="fas fa-key logo-icon"></i>
            <h2>Account Recovery</h2>
            <p>Follow the steps to securely regain access to your account.</p>
        </div>
        <div class="form-panel">
            <h1>Forgot Password</h1>
            <?php if (!empty($errors)): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><p><?= htmlspecialchars($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            
            <?php if ($stage === 1): ?>
                <form action="forgot_password.php" method="POST">
                    <div class="input-group">
                        <input type="text" id="username" name="username" required>
                        <label for="username">Enter Username, Email, or Phone</label>
                    </div>
                    <button type="submit" class="button btn-primary">Continue</button>
                </form>
            <?php elseif ($stage === 2): ?>
                <p style="color: var(--mid-grey);">Answer the question below for user: <strong><?= htmlspecialchars($username) ?></strong></p>
                <form action="forgot_password.php" method="POST">
                    <input type="hidden" name="username_hidden" value="<?= htmlspecialchars($username) ?>">
                    <div class="form-group" style="margin-bottom: 35px;">
                        <label style="color: var(--mid-grey); font-size: 0.9rem;">Your Security Question:</label>
                        <p style="font-size: 1.1rem; color: var(--light-grey); margin-top: 5px;"><strong><?= htmlspecialchars($security_question) ?></strong></p>
                    </div>
                    <div class="input-group">
                        <input type="text" id="security_answer" name="security_answer" required autofocus>
                        <label for="security_answer">Your Answer</label>
                    </div>
                    <button type="submit" class="button btn-primary">Verify Answer</button>
                </form>
            <?php endif; ?>
            <div class="form-links"><a href="login.php">Back to Login</a></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.input-group input').forEach(input => {
            if (input.value) { input.setAttribute('valid', 'true'); }
        });
    });
</script>
</body>
</html>
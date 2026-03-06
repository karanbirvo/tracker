<?php
// --- PHP LOGIC (No changes needed here) ---
require_once 'includes/functions.php';
$token = $_GET['token'] ?? ''; $errors = []; $user = null;
if (empty($token)) { $errors[] = "No reset token provided."; }
else {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) { $errors[] = "This password reset link is invalid or has expired."; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password']; $password_confirm = $_POST['password_confirm'];
    if (empty($password) || strlen($password) < 6) { $errors[] = "Password must be at least 6 characters long."; }
    elseif ($password !== $password_confirm) { $errors[] = "Passwords do not match."; }
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
        $stmt->execute([$password_hash, $user['id']]);
        $_SESSION['message'] = "Your password has been reset successfully!"; $_SESSION['message_type'] = "success";
        header("Location: login.php"); exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - VO TimeTracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* --- Using the same CSS as the login page for consistency --- */
    :root { --dark-navy: #0b1a2e; --light-navy: #112240; --accent-green: #64ffda; --light-grey: #ccd6f6; --mid-grey: #8892b0; }
    @keyframes slideInLeft { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }  
    body.auth-page { font-family: 'Poppins', sans-serif;  color: var(--light-grey); overflow: hidden; }      /*background-color: var(--dark-navy); */
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
            <i class="fas fa-shield-alt logo-icon"></i>
            <h2>Set Your New Password</h2>
            <p>Create a new, strong password to secure your account.</p>
        </div>
        <div class="form-panel">
            <h1>Reset Password</h1>
            <?php if (!empty($errors)): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><p><?= htmlspecialchars($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            
            <?php if ($user && empty($errors)): ?>
                <form action="reset_password.php?token=<?= htmlspecialchars($token) ?>" method="POST">
                    <div class="input-group">
                        <input type="password" id="password" name="password" required>
                        <label for="password">New Password</label>
                    </div>
                    <div class="input-group">
                        <input type="password" id="password_confirm" name="password_confirm" required>
                        <label for="password_confirm">Confirm New Password</label>
                    </div>
                    <button type="submit" class="button btn-primary">Reset Password</button>
                </form>
            <?php else: ?>
                <p>The link is invalid or has expired. Please <a href="forgot_password.php" style="color:var(--accent-green);">start the recovery process over</a>.</p>
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
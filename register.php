<?php
// --- PHP LOGIC (No changes needed here) ---
require_once 'includes/functions.php';
if (isLoggedIn()) { header("Location: index.php"); exit(); }
$security_questions = ["The name of your first pet?", "What is your mother's name?", "Your first school name?", "In what city were you born?", "What is your favorite book?"];
$username = ""; $email = ""; $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']); $email = trim($_POST['email']); $password = $_POST['password']; $password_confirm = $_POST['password_confirm']; $security_question = $_POST['security_question']; $security_answer = trim($_POST['security_answer']);
    if (empty($username)) { $errors[] = "Username is required."; } elseif (strlen($username) < 3) { $errors[] = "Username must be at least 3 characters."; } else { $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username"); $stmt->execute(['username' => $username]); if ($stmt->fetch()) { $errors[] = "Username already taken."; } }
    if (empty($email)) { $errors[] = "Email address is required."; } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Please enter a valid email address."; } else { $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email"); $stmt->execute(['email' => $email]); if ($stmt->fetch()) { $errors[] = "That email address is already registered."; } }
    if (empty($password)) { $errors[] = "Password is required."; } elseif (strlen($password) < 6) { $errors[] = "Password must be at least 6 characters."; } elseif ($password !== $password_confirm) { $errors[] = "Passwords do not match."; }
    if (empty($security_question) || !in_array($security_question, $security_questions)) { $errors[] = "Please select a valid security question."; } if (empty($security_answer)) { $errors[] = "A security answer is required."; }
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT); $security_answer_hash = password_hash($security_answer, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, security_question, security_answer_hash) VALUES (:username, :email, :password_hash, :question, :answer_hash)");
        try { $stmt->execute(['username' => $username, 'email' => $email, 'password_hash' => $password_hash, 'question' => $security_question, 'answer_hash' => $security_answer_hash]);
            $_SESSION['message'] = "Registration successful! Please login."; $_SESSION['message_type'] = "success"; header("Location: login.php"); exit();
        } catch (PDOException $e) { error_log("Registration failed: " . $e->getMessage()); $errors[] = "An error occurred. Please try again."; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account - VO TimeTracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root { --dark-navy: #0b1a2e; --light-navy: #112240; --accent-green: #64ffda; --light-grey: #ccd6f6; --mid-grey: #8892b0; }
    @keyframes slideInLeft { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif;  color: var(--light-grey); overflow: hidden; }    /*background-color: var(--dark-navy);*/
    .register-wrapper { display: flex; width: 100vw; height: 100vh; align-items: center; justify-content: center; padding: 20px; }
    .register-container { display: flex; width: 100%; max-width: 950px; height: 650px; background-color: var(--light-navy); border-radius: 15px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); overflow: hidden; }
    .branding-panel { width: 45%; background: linear-gradient(160deg, #0d2c44, var(--light-navy)); padding: 50px; display: flex; flex-direction: column; justify-content: center; animation: slideInLeft 0.8s cubic-bezier(0.25, 1, 0.5, 1); text-align: center; }
    .branding-panel .logo-icon { font-size: 4rem; margin-bottom: 20px; color: var(--accent-green); }
    .branding-panel h2 { font-size: 2.5rem; font-weight: 600; margin: 0; color: #fff; }
    .branding-panel p { font-size: 1.1rem; color: var(--mid-grey); line-height: 1.6; margin-top: 15px; }
    .form-panel { width: 55%; padding: 50px; display: flex; flex-direction: column; justify-content: space-between; animation: slideInRight 0.8s cubic-bezier(0.25, 1, 0.5, 1); }
    .form-panel h1 { font-weight: 600; margin-bottom: 20px; text-align: left; }
    .form-container { overflow: hidden; position: relative; flex-grow: 1; margin-top: 20px; }
    .form-step { position: absolute; width: 100%; transition: transform 0.5s ease-in-out, opacity 0.5s ease-in-out; opacity: 0; transform: translateX(50px); }
    .form-step.active { opacity: 1; transform: translateX(0); z-index: 10; padding: 11px;}
    #security_question option {
    background-color: #64ffda;
    color: #112240;
    font-size: 12px;
}
    .form-step.inactive-left { opacity: 0; transform: translateX(-50px); }
    .input-group { position: relative; margin-bottom: 35px; }
    .input-group input, .input-group select { width: 100%; padding: 10px 0; background: transparent; border: none; border-bottom: 2px solid var(--mid-grey); color: #fff; font-size: 1rem; transition: border-bottom-color 0.3s ease; }
    .input-group input:focus, .input-group select:focus { outline: none; border-bottom-color: var(--accent-green); }
    .input-group label { position: absolute; top: 10px; left: 0; color: var(--mid-grey); pointer-events: none; transition: all 0.2s ease; }
    .input-group input:focus + label, .input-group input:valid + label, .input-group select:focus + label, .input-group select:valid + label, .input-group select:not([value=""]) + label { top: -15px; font-size: 0.8rem; color: var(--accent-green); }

    /* =================================================================== */
    /* === THE FIX FOR AUTOFILL BACKGROUND COLOR ========================= */
    /* =================================================================== */
    .input-group input:-webkit-autofill,
    .input-group input:-webkit-autofill:hover, 
    .input-group input:-webkit-autofill:focus, 
    .input-group input:-webkit-autofill:active {
        /* Use a large inset box-shadow to cover the browser's background */
        box-shadow: 0 0 0 30px var(--light-navy) inset !important;
        -webkit-box-shadow: 0 0 0 30px var(--light-navy) inset !important;
        
        /* Force the text color to be white */
        -webkit-text-fill-color: #fff !important;
        
        /* Smooth transition for when autofill is applied */
        transition: background-color 5000s ease-in-out 0s;
    }
    /* =================================================================== */
    /* === END OF FIX ==================================================== */
    /* =================================================================== */

    .form-navigation { display: flex; justify-content: space-between; align-items: center; }
    .step-dots { display: flex; gap: 10px; }
    .dot { width: 10px; height: 10px; background-color: #444; border-radius: 50%; transition: background-color 0.3s ease; }
    .dot.active { background-color: var(--accent-green); }
    .button { background: none; border: 1px solid var(--accent-green); color: var(--accent-green); padding: 10px 25px; border-radius: 50px; cursor: pointer; font-weight: bold; transition: all 0.3s ease; }
    .button.btn-next, .button.btn-submit { background-color: var(--accent-green); color: var(--dark-navy); }
    .button.btn-back { border: none; color: var(--mid-grey); }
    .button:hover { opacity: 0.8; }
    .login-link { text-align: right; margin-top: 20px; }
    .login-link a { color: var(--accent-green); font-weight: 500; text-decoration: none; }
    .login-link a:hover { text-decoration: underline; }
    .alert-danger { background-color: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #f8d7da; border-radius: 8px; padding: 15px; }
    @media (max-width: 850px) { .branding-panel { display: none; } .form-panel { width: 100%; padding: 20px; } .register-container {  max-height: 100vh; margin: 5px; } }
</style>
</head>
<body class="register-page">

<div class="register-wrapper">
    <div class="register-container">
        <!-- Left Branding Panel -->
        <div class="branding-panel">
            <i class="fas fa-clock logo-icon"></i>
            <h2>Join VO TimeTracker</h2>
            <p>Take control of your time, streamline your workflow, and boost your productivity. Your journey to efficiency starts now.</p>
        </div>

        <!-- Right Form Panel -->
        <div class="form-panel">
            <div>
                <h1>Create Account</h1>
                <?php if (!empty($errors)): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><p><?= htmlspecialchars($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            </div>

            <form id="register-form" action="register.php" method="POST" class="form-container">
                <!-- Step 1: Account Details -->
                <div id="step-1" class="form-step active">
                    <div class="input-group"><input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required><label for="username">Username</label></div>
                    <div class="input-group"><input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required><label for="email">Email Address</label></div>
                                        <div class="input-group"><input type="password" id="password" name="password" required><label for="password">Password (min. 6 characters)</label></div>
                    <div class="input-group"><input type="password" id="password_confirm" name="password_confirm" required><label for="password_confirm">Confirm Password</label></div>
                </div>

                <!-- Step 2: Security Setup -->
                <div id="step-2" class="form-step">

                    <div class="input-group"><select id="security_question" name="security_question" required><option value="" disabled selected></option><?php foreach ($security_questions as $question): ?><option value="<?= htmlspecialchars($question) ?>"><?= htmlspecialchars($question) ?></option><?php endforeach; ?></select><label for="security_question">Security Question</label></div>
                    <div class="input-group"><input type="text" id="security_answer" name="security_answer" required><label for="security_answer">Your Answer</label></div>
                </div>
            </form>
            
            <div>
                <div class="form-navigation">
                    <button type="button" id="back-btn" class="button btn-back" style="display: none;">&larr; Back</button>
                    <div class="step-dots">
                        <div id="dot-1" class="dot active"></div>
                        <div id="dot-2" class="dot"></div>
                    </div>
                    <button type="button" id="next-btn" class="button btn-next">Next &rarr;</button>
                    <button type="submit" form="register-form" id="submit-btn" class="button btn-submit" style="display: none;">Create Account</button>
                </div>
                <p class="login-link"><a href="login.php">Already have an account?</a></p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nextBtn = document.getElementById('next-btn');
    const backBtn = document.getElementById('back-btn');
    const submitBtn = document.getElementById('submit-btn');
    const steps = document.querySelectorAll('.form-step');
    const dots = document.querySelectorAll('.dot');
    let currentStep = 1;

    function showStep(stepNumber) {
        steps.forEach((step, index) => {
            const stepIndex = index + 1;
            if (stepIndex === stepNumber) {
                step.classList.remove('inactive-left');
                step.classList.add('active');
            } else if (stepIndex < stepNumber) {
                step.classList.remove('active');
                step.classList.add('inactive-left');
            } else {
                step.classList.remove('active', 'inactive-left');
            }
        });
        dots.forEach((dot, index) => {
            if (index + 1 === stepNumber) { dot.classList.add('active'); }
            else { dot.classList.remove('active'); }
        });
        currentStep = stepNumber;
        backBtn.style.display = (currentStep === 1) ? 'none' : 'inline-block';
        nextBtn.style.display = (currentStep === steps.length) ? 'none' : 'inline-block';
        submitBtn.style.display = (currentStep === steps.length) ? 'inline-block' : 'none';
    }

    nextBtn.addEventListener('click', () => { if (currentStep < steps.length) { showStep(currentStep + 1); } });
    backBtn.addEventListener('click', () => { if (currentStep > 1) { showStep(currentStep - 1); } });

    document.querySelectorAll('.input-group input, .input-group select').forEach(input => {
        if (input.value) { input.setAttribute('valid', 'true'); }
    });

    <?php if(!empty($errors)): ?> showStep(2); <?php endif; ?>
});
</script>

</body>
</html>
<?php
require_once 'includes/functions.php';
if (isLoggedIn()) { header("Location: index.php"); exit(); }
$login_identifier = ""; $errors = [];

// --- GET Request Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['identifier']) && isset($_GET['password'])) {
    $identifier = trim($_GET['identifier']);
    $password = $_GET['password'];
    
    // --- FIX 1: Use unique named placeholders ---
    $sql = "SELECT id, username, password_hash, user_role FROM users WHERE username = :uname OR email = :email OR mobile_number = :mobile";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uname' => $identifier, 'email' => $identifier, 'mobile' => $identifier]);
    
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $sessionToken = bin2hex(random_bytes(32));
        $update = $pdo->prepare("UPDATE users SET session_token = :token WHERE id = :id");
        $update->execute(['token' => $sessionToken, 'id' => $user['id']]);
        $_SESSION['user_id'] = $user['id']; $_SESSION['username'] = $user['username']; $_SESSION['user_role'] = $user['user_role']; $_SESSION['session_token'] = $sessionToken;
        header("Location: index.php");
        exit();
    } else {
        $errors[] = "Invalid credentials provided in the URL.";
        $login_identifier = $identifier;
    }
}

// --- POST Request Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identifier = trim($_POST['login_identifier']);
    $password = $_POST['password'];

    if (empty($login_identifier) || empty($password)) {
        $errors[] = "All fields are required.";
    } else {
        // --- FIX 2: Use unique named placeholders ---
        $sql = "SELECT id, username, password_hash, user_role FROM users WHERE username = :uname OR email = :email OR mobile_number = :mobile";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['uname' => $login_identifier, 'email' => $login_identifier, 'mobile' => $login_identifier]);
        
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $sessionToken = bin2hex(random_bytes(32));
            $update = $pdo->prepare("UPDATE users SET session_token = :token WHERE id = :id");
            $update->execute(['token' => $sessionToken, 'id' => $user['id']]);
            $_SESSION['user_id'] = $user['id']; $_SESSION['username'] = $user['username']; $_SESSION['user_role'] = $user['user_role']; $_SESSION['session_token'] = $sessionToken;
            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Invalid credentials. Please try again.";
        }
    }
}

// --- Dynamic Footer Parsing (Unchanged) ---
$footer_details = [ 'email' => null, 'phone' => null, 'address' => null, 'social' => [] ];
ob_start();
if (file_exists('includes/footer.php')) { include 'includes/footer.php'; }
$footer_html = ob_get_clean();
if (!empty($footer_html)) {
    $doc = new DOMDocument(); @$doc->loadHTML($footer_html); $xpath = new DOMXPath($doc);
    $emailNode = $xpath->query("//a[contains(@href, 'mailto:')]")->item(0);
    if ($emailNode) { $footer_details['email'] = ['href' => $emailNode->getAttribute('href'), 'text' => $emailNode->nodeValue]; }
    $phoneNode = $xpath->query("//a[contains(@href, 'tel:')]")->item(0);
    if ($phoneNode) { $footer_details['phone'] = ['href' => $phoneNode->getAttribute('href'), 'text' => $phoneNode->nodeValue]; }
    $addressNode = $xpath->query("//p[i[contains(@class, 'fa-map-marker-alt')]]")->item(0);
    if ($addressNode) { $footer_details['address'] = trim(str_replace($addressNode->getElementsByTagName('i')->item(0)->nodeValue, '', $addressNode->nodeValue)); }
    $socialNodes = $xpath->query("//div[contains(@class, 'social-links')]/a");
    foreach ($socialNodes as $node) { $footer_details['social'][] = [ 'href' => $node->getAttribute('href'), 'icon' => $node->getElementsByTagName('i')->item(0)->getAttribute('class') ]; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VO TimeTracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* --- Your existing beautiful CSS remains the same --- */
    :root { --dark-navy: #0b1a2e; --light-navy: #112240; --accent-green: #64ffda; --light-grey: #ccd6f6; --mid-grey: #8892b0; }
    @keyframes slideInLeft { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body.auth-page { font-family: 'Poppins', sans-serif;  color: var(--light-grey); overflow: hidden;}   /*background-color: var(--dark-navy);*/
    .auth-wrapper { display: flex; width: 100vw; height: 100vh; align-items: center; justify-content: center; padding: 20px; }
    .auth-container { display: flex; width: 100%; max-width: 1000px; height: 700px; background-color: var(--light-navy); border-radius: 15px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); overflow: hidden; }
    .branding-panel { width: 45%; background: linear-gradient(160deg, #0d2c44, var(--light-navy)); padding: 50px; display: flex; flex-direction: column; animation: slideInLeft 0.8s cubic-bezier(0.25, 1, 0.5, 1); }
    .branding-panel .logo img { max-width: 250px; margin-bottom: 20px; }
    .branding-panel h2 { font-size: 2.2rem; font-weight: 600; margin-top: 15px; color: #fff; }
    .branding-panel p { font-size: 1rem; color: var(--mid-grey); line-height: 1.6; margin-top: 15px; flex-grow: 1; }
    .contact-details { border-top: 1px solid var(--mid-grey); padding-top: 20px; margin-top: 20px; font-size: 0.9rem; }
    .contact-details a { color: var(--light-grey); text-decoration: none; display: flex; align-items: center; margin-bottom: 15px; transition: color 0.3s ease; }
    .contact-details a:hover { color: var(--accent-green); }
    .contact-details a i { color: var(--accent-green); margin-right: 15px; width: 20px; text-align: center; }
    .social-links { display: flex; gap: 20px; margin-top: 20px; }
    .social-links a { color: var(--light-grey); font-size: 1.2rem; transition: color 0.3s, transform 0.3s; }
    .social-links a:hover { color: var(--accent-green); transform: translateY(-3px); }
    .form-panel { width: 55%; padding: 50px; display: flex; flex-direction: column; justify-content: center; animation: slideInRight 0.8s cubic-bezier(0.25, 1, 0.5, 1); }
    .form-panel h1 { font-weight: 600; margin-bottom: 30px; }
    .input-group { position: relative; margin-bottom: 35px; }
    .input-group input { width: 100%; padding: 10px 0; background: transparent; border: none; border-bottom: 2px solid var(--mid-grey); color: #fff; font-size: 1rem; }
    .input-group input:focus { outline: none; border-bottom-color: var(--accent-green); }
    .input-group label { position: absolute; top: 10px; left: 0; color: var(--mid-grey); pointer-events: none; transition: all 0.2s ease; }
    
    /* =================================================================== */
    /* === THE FIX FOR FLOATING LABELS & AUTOFILL ======================== */
    /* =================================================================== */
    .input-group input:focus + label,
    .input-group input:valid + label,
    .input-group input:-webkit-autofill + label { /* This is the key CSS selector */
        top: -15px;
        font-size: 0.8rem;
        color: var(--accent-green);
    }
    .input-group input:-webkit-autofill {
        box-shadow: 0 0 0 30px var(--light-navy) inset !important;
        -webkit-text-fill-color: #fff !important;
    }
    /* =================================================================== */
    /* === END OF FIX ==================================================== */
    /* =================================================================== */

    .button { background: none; border: 1px solid var(--accent-green); color: var(--accent-green); padding: 12px 30px; border-radius: 50px; cursor: pointer; font-weight: bold; transition: all 0.3s ease; text-decoration: none; display: inline-block; text-align: center; }
    .button.btn-primary { background-color: var(--accent-green); color: var(--dark-navy); width: 100%; }
    .form-links { display: flex; justify-content: space-between; margin-top: 20px; font-size: 0.9rem; }
    .form-links a { color: var(--accent-green); text-decoration: none; }
    .alert-danger { background-color: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #f8d7da; border-radius: 8px; padding: 15px; }
    @media (max-width: 900px) { .branding-panel { ; } .form-panel { width: 100%; padding: 25px; padding-top: 0px;} .auth-container { height: auto; max-height: 100vh; flex-direction: column;} .m-dnnone { display: none;}.branding-panel {padding-bottom: 5px; width: 100%;}}
</style>
</head>
<body class="auth-page">

<div class="auth-wrapper">
    <div class="auth-container">
        <div class="branding-panel">
            <div class="logo"><a href="https://virtualoplossing.com" target="_blank"><img src="https://virtualoplossing.com/wp-content/uploads/2025/06/Virtual-Oplossing-White.svg" alt="Virtual Oplossing Logo"></a></div>
            <h2 class="m-dnnone">Welcome Back</h2>
            <p class="m-dnnone">Your time is valuable. Log in to continue tracking and managing your productivity.</p>
            <div class="contact-details m-dnnone">
                <?php if ($footer_details['email']): ?><a href="<?= htmlspecialchars($footer_details['email']['href']) ?>"><i class="fas fa-envelope"></i> <?= htmlspecialchars($footer_details['email']['text']) ?></a><?php endif; ?>
                <?php if ($footer_details['phone']): ?><a href="<?= htmlspecialchars($footer_details['phone']['href']) ?>"><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($footer_details['phone']['text']) ?></a><?php endif; ?>
                <?php if ($footer_details['address']): ?><a href="#"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($footer_details['address']) ?></a><?php endif; ?>
                <?php if (!empty($footer_details['social'])): ?><div class="social-links"><?php foreach($footer_details['social'] as $link): ?><a href="<?= htmlspecialchars($link['href']) ?>" target="_blank"><i class="<?= htmlspecialchars($link['icon']) ?>"></i></a><?php endforeach; ?></div><?php endif; ?>
            </div>
        </div>
        <div class="form-panel">
            <h1>Login</h1>
            <?php if (!empty($errors)): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><p><?= htmlspecialchars($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            <?php if (isset($_SESSION['message'])): ?><div class="alert alert-success" style="background-color: rgba(100, 255, 218, 0.2); border-color: var(--accent-green); color: var(--accent-green);"><?= htmlspecialchars($_SESSION['message']) ?></div><?php unset($_SESSION['message']); unset($_SESSION['message_type']); endif; ?>

            <form action="login.php" method="POST">
                <div class="input-group">
                    <input type="text" id="login_identifier" name="login_identifier" value="<?= htmlspecialchars($login_identifier) ?>" required>
                    <label for="login_identifier">Username, Email, or Phone</label>
                </div>
                <div class="input-group">
                    <input type="password" id="password" name="password" required>
                    <label for="password">Password</label>
                </div>
                <button type="submit" class="button btn-primary">Login</button>
            </form>
            <div class="form-links">
                <a href="register.php">Create an account</a>
                <a href="forgot_password.php">Forgot password?</a>
            </div>
        </div>
    </div>
</div>

<!-- =================================================================== -->
<!-- === JAVASCRIPT FIX FOR URL PARAMS & AUTOFILL ====================== -->
<!-- =================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const identifierField = document.getElementById('login_identifier');
    const passwordField = document.getElementById('password');

    // Function to check if an input has a value and trigger the label float
    function checkLabelState(input) {
        if (input.value) {
            // The ':valid' pseudo-class in the CSS will handle the floating
            // We just need to ensure the check happens
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    // Step 1: Check for URL parameters and populate the form
    const urlParams = new URLSearchParams(window.location.search);
    const urlIdentifier = urlParams.get('identifier');
    const urlPassword = urlParams.get('password');
    if (urlIdentifier) { identifierField.value = urlIdentifier; }
    if (urlPassword) { passwordField.value = urlPassword; }

    // Step 2: Initial check for pre-filled values (from URL or browser back button)
    checkLabelState(identifierField);
    checkLabelState(passwordField);

    // Step 3: Use a small timeout to let the browser's autofill engine run.
    // This is the safety net for Chrome's behavior.
    setTimeout(() => {
        checkLabelState(identifierField);
        checkLabelState(passwordField);
        
        // Step 4: If fields are now populated (from URL), submit the form
        if (urlIdentifier && urlPassword) {
            document.querySelector('form').submit();
        }
    }, 100);
});
</script>

</body>
</html>
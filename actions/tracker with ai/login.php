<?php
// // Set session lifetime (in seconds)
// $session_lifetime = 10; // 1 hour

// ini_set('session.gc_maxlifetime', $session_lifetime);
// ini_set('session.cookie_lifetime', $session_lifetime);

// session_set_cookie_params($session_lifetime);
// session_start();



require_once 'includes/header.php'; // Includes db.php and starts session

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// NEW: ADD THIS BLOCK TO HANDLE AUTO-LOGIN FROM URL
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['username']) && isset($_GET['password'])) {
    $username = trim($_GET['username']);
    $password = $_GET['password'];

    // This is the SAME login logic from your POST block below
    $stmt = $pdo->prepare("SELECT id, username, password_hash, user_role FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Login successful, set sessions and redirect
        $sessionToken = bin2hex(random_bytes(32));
        $update = $pdo->prepare("UPDATE users SET session_token = :token WHERE id = :id");
        $update->execute(['token' => $sessionToken, 'id' => $user['id']]);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['user_role'];
        $_SESSION['session_token'] = $sessionToken;
        session_regenerate_id(true);
        header("Location: index.php");
        exit();
    } else {
        // If login fails from URL, set an error message
        $errors[] = "Invalid username or password from URL.";
    }
}

$username = "";
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $errors[] = "Both username and password are required.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, user_role FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Generate secure session token
            $sessionToken = bin2hex(random_bytes(32));

            // Save session token to DB
            $update = $pdo->prepare("UPDATE users SET session_token = :token WHERE id = :id");
            $update->execute([
                'token' => $sessionToken,
                'id' => $user['id']
            ]);

          // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['user_role']; // <--- ADD THIS LINE
            $_SESSION['session_token'] = $sessionToken;

            session_regenerate_id(true); // Security: prevent session fixation

            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Invalid username or password.";
        }
    }
}
?>
<style>
    .container.main-content-area {
    max-width: 500px;
}
</style>
<h1>Login</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form action="login.php" method="POST">
    <div class="form-group">
        <label for="username">Username:</label>
       <input type="text" id="username" name="username"
       value="<?= isset($_GET['username']) ? htmlspecialchars($_GET['username']) : htmlspecialchars($username) ?>" required>
    </div>
    <div class="form-group">
        <label for="password">Password:</label>
       <input type="password" id="password" name="password"
       value="<?= isset($_GET['password']) ? htmlspecialchars($_GET['password']) : '' ?>" required>
    </div>
    <button type="submit">Login</button>
</form>

<p>Don't have an account? <a href="register.php">Register here</a>.</p>
<script>
    // This function runs as soon as the page content has finished loading
    window.onload = function() {
        // Find the username and password fields
        const usernameField = document.getElementById('username');
        const passwordField = document.getElementById('password');

        // Check if both fields have a value (pre-filled from the URL)
        if (usernameField.value && passwordField.value) {
            // If they do, find the form and submit it automatically
            document.querySelector('form').submit();
        }
    };
</script>
<?php require_once 'includes/footer.php'; ?>

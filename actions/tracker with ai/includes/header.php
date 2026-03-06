<?php
// Use __DIR__ for robust path resolution relative to the current file (header.php)
require_once __DIR__ . '/db.php';         // For database connection and session_start
require_once __DIR__ . '/functions.php'; // For helper functions like isLoggedIn()
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VO TimeTracker</title> <!-- Changed title back as per your original -->
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts - Poppins (Example) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Link to your external style.css for other page content -->
    <link rel="stylesheet" href="css/style.css">

   <script>setInterval(function() {
    fetch('/keepalive.php');
},5 * 60 * 1000); // every 5 minutes
</script> 
</head>
<body>
    <header class="site-header"> <!-- Changed from nav to header -->
        <div class="header-container"> <!-- Changed from div class="container" -->
            <a href="index.php" class="site-logo"> <!-- Changed from a class="logo" -->
                <i class="fas fa-clock"></i> VO TimeTracker
            </a>

            <div class="header-center-content">
                <div id="current-time-display" class="current-time">
                    <i class="far fa-clock"></i> <span id="time-now">Loading...</span>
                </div>
            </div>

            <nav class="site-navigation"> <!-- Added this wrapper for nav ul and toggle -->
                <button class="menu-toggle" id="menu-toggle" aria-label="Toggle Menu" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                    <i class="fas fa-times"></i>
                </button>
                <ul class="nav-links" id="nav-links">
                    <?php if (isLoggedIn()): ?>
                        <li><a href="index.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '') ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="reports.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '') ?>"><i class="fas fa-chart-bar"></i> My Reports</a></li>
                        
                        <!-- ADD THIS NEW LINE -->
                        <li><a href="email_settings.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'email_settings.php' ? 'active' : '') ?>"><i class="fas fa-envelope-cog"></i> Email Settings</a></li>
                        
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <li><a href="admin_reports.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'admin_reports.php' ? 'active' : '') ?>"><i class="fas fa-user-shield"></i> Admin Reports</a></li>
                            <li><a href="admin_users.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'active' : '') ?>"><i class="fas fa-users-cog"></i> Manage Users</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout (<?= htmlspecialchars($_SESSION['username']) ?>)</a></li>
                    <?php else: ?>
                        <!-- ... (login/register links) ... -->
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <div class="container main-content-area" style="padding-top: 20px; padding-bottom: 20px;">
        <?php
        // Display session messages
        if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['message_type']) ?>">
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        endif;
        ?>
  
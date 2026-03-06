<?php
// Use __DIR__ for robust path resolution version 9.86
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// --- Logic for Active States ---
$current_page = basename($_SERVER['PHP_SELF']);
$admin_pages = ['admin_reports.php', 'admin_users.php', 'admin_email_logs.php', 'edit_entry.php'];
$is_admin_page = in_array($current_page, $admin_pages);
$settings_pages = ['email_settings.php', 'email_layout_settings.php', 'profile.php', 'ai_history.php'];
$is_settings_page = in_array($current_page, $settings_pages);

// --- GLOBAL: CHECK FOR ACTIVE TASK (For Header Meter) ---
$headerActiveTask = null;
if (isLoggedIn()) {
    $headerActiveTask = getActiveTask($_SESSION['user_id'], $pdo);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VO TimeTracker</title>
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <!-- Main CSS -->
    <link rel="stylesheet" href="css/style.css">

    <style>
        :root {
            --header-height: 70px;
            --brand-color: #2D5A95;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --bg-hover: #f1f5f9;
        }
        body { 
            font-family: 'Inter', sans-serif; 
            margin-top: var(--header-height); 
            background-color: #f8f9fa;
        }
        a, a:hover, a:active {
            text-decoration:none;
        }
        .site-header {
            background-color: #ffffff; height: var(--header-height); position: fixed;
            top: 0; left: 0; width: 100%; z-index: 1000; border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }
        .header-inner {
            max-width: 1400px; margin: 0 auto; height: 100%; display: flex;
            align-items: center; justify-content: space-between; padding: 0 20px;
        }
        .brand-logo {
            font-size: 1.25rem; font-weight: 800; color: var(--brand-color);
            text-decoration: none; display: flex; align-items: center; gap: 10px; letter-spacing: -0.5px;
        }
        .nav-wrapper { display: flex; align-items: center; gap: 5px; }
        .nav-link-item {
            color: var(--text-dark); text-decoration: none; padding: 8px 14px;
            font-size: 0.9rem; font-weight: 500; border-radius: 6px;
            transition: all 0.2s ease; display: flex; align-items: center; gap: 8px;
        }
        .nav-link-item:hover { background-color: var(--bg-hover); color: var(--brand-color); }
        .nav-link-item.active { background-color: #eff6ff; color: var(--brand-color); font-weight: 600; }
        .nav-link-item i { color: var(--text-light); }
        .nav-link-item.active i { color: var(--brand-color); }
        .dropdown-wrapper { position: relative; }
        .dropdown-content {
            display: none; position: absolute; top: 100%; right: 0;
            background: white; min-width: 220px; border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0; padding: 6px; z-index: 1001;
            animation: slideDown 0.2s ease-out;
        }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .dropdown-wrapper:hover .dropdown-content { display: block; }
        .dropdown-link { display: block; padding: 10px 16px; color: var(--text-dark); text-decoration: none; font-size: 0.9rem; border-radius: 6px; transition: background 0.15s; }
        .dropdown-link:hover { background-color: var(--bg-hover); }
        .header-timer-widget {
            display: flex; align-items: center; gap: 12px; padding: 6px 12px;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 50px;
            margin-left: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); animation: fadeIn 0.5s;
        }
        .timer-dot { width: 8px; height: 8px; border-radius: 50%; }
        .timer-dot.green { background-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2); animation: pulseGreen 2s infinite; }
        .timer-dot.red { background-color: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); animation: pulseGreen 2s infinite; }
        @keyframes pulseGreen { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        .timer-time { font-family: 'Courier New', monospace; font-weight: 700; color: var(--text-dark); font-size: 1rem; }
        .timer-task-name { font-size: 0.8rem; color: var(--text-light); max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .timer-close { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1rem; padding: 0 4px; transition: color 0.2s; }
        .timer-close:hover { color: #ef4444; }
        .mobile-toggle { display: none; background: none; border: none; font-size: 1.5rem; color: var(--text-dark); cursor: pointer; }
        @media (max-width: 992px) {
            .mobile-toggle { display: block; }
            .nav-wrapper { display: none; position: absolute; top: var(--header-height); left: 0; width: 100%; background: white; flex-direction: column; padding: 20px; box-shadow: 0 10px 15px rgba(0,0,0,0.1); align-items: stretch; }
            .nav-wrapper.show { display: flex; }
            .header-timer-widget { display: none; }
            .dropdown-content { position: static; box-shadow: none; border: none; padding-left: 20px; display: none; width: 100%; }
            .dropdown-wrapper:hover .dropdown-content { display: block; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <!-- 1. Logo -->
            <a href="index.php" class="brand-logo">
                <img style="width:140px; height: auto;" src="https://virtualoplossing.com/wp-content/themes/blankslate/assets/img/virtual-oplossing-logo.svg" alt="VO TimeTracker Logo">
            </a>

            <div class="d-flex align-items-center">
                <!-- 2. Navigation -->
                <nav class="nav-wrapper" id="nav-menu">
                    <?php if (isLoggedIn()): ?>
                        <a href="index.php" class="nav-link-item <?= $current_page == 'index.php' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
                        <?php if (hasPermission('perm_reports')): ?><a href="reports.php" class="nav-link-item <?= $current_page == 'reports.php' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Reports</a><?php endif; ?>
                        <a href="my_email_history.php" class="nav-link-item <?= $current_page == 'my_email_history.php' ? 'active' : '' ?>"><i class="fas fa-history"></i> History</a>
                        <?php if (hasPermission('perm_view_all')): ?>
                            <div class="dropdown-wrapper">
                                <a href="#" class="nav-link-item <?= $is_admin_page ? 'active' : '' ?>"><i class="fas fa-shield-alt"></i> Admin <i class="fas fa-chevron-down fa-xs"></i></a>
                                <div class="dropdown-content">
                                    <a href="admin_reports.php" class="dropdown-link">Admin Reports</a>
                                    <a href="admin_users.php" class="dropdown-link">User Permissions</a>
                                    <a href="admin_email_logs.php" class="dropdown-link">System Logs</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="dropdown-wrapper">
                            <a href="#" class="nav-link-item <?= $is_settings_page ? 'active' : '' ?>"><i class="fas fa-cog"></i> Settings <i class="fas fa-chevron-down fa-xs"></i></a>
                            <div class="dropdown-content">
                                <a href="profile.php" class="dropdown-link">My Profile</a>
                                <?php if (hasPermission('perm_email')): ?>
                                    <a href="email_settings.php" class="dropdown-link">SMTP Settings</a>
                                    <a href="email_layout_settings.php" class="dropdown-link">Email Layout</a>
                                <?php endif; ?>
                                <?php if (hasPermission('perm_ai')): ?>
                                    <a href="ai_history.php" class="dropdown-link">AI History</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="logout.php" class="nav-link-item" style="color: #ef4444;"><i class="fas fa-sign-out-alt" style="color: #ef4444;"></i> Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="nav-link-item">Login</a>
                        <a href="register.php" class="nav-link-item">Register</a>
                    <?php endif; ?>
                </nav>

                <!-- 3. GLOBAL HEADER TIMER -->
                <?php if ($headerActiveTask): ?>
                    <?php 
                        $hType = $headerActiveTask['type'];
                        $dotClass = ($hType === 'tracker') ? 'green' : 'red';
                    ?>
                    <div class="header-timer-widget" id="header-timer-widget">
                        <div class="timer-dot <?= $dotClass ?>"></div>
                        <div class="timer-task-name" title="<?= htmlspecialchars($headerActiveTask['task_name']) ?>"><?= htmlspecialchars($headerActiveTask['task_name']) ?></div>
                        <div id="header-clock" class="timer-time">00:00:00</div>
                        <form action="actions/stop_current_task.php" method="POST" style="margin:0;display:flex;"><button type="submit" class="timer-close" title="Stop Task" style="color:#ef4444;"><i class="fas fa-stop-circle"></i></button></form>
                        <div style="border-left:1px solid #e2e8f0; height:15px; margin:0 2px;"></div>
                        <button onclick="document.getElementById('header-timer-widget').style.display='none'" class="timer-close" title="Hide Meter"><i class="fas fa-times"></i></button>
                    </div>
                <?php endif; ?>

                <button class="mobile-toggle" onclick="document.getElementById('nav-menu').classList.toggle('show')"><i class="fas fa-bars"></i></button>
            </div>
        </div>
    </header>

    <!-- JS for Keep Alive, Dynamic Title, and Header Timer -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js" defer></script>
    <script>
        setInterval(function() { fetch('/keepalive.php'); }, 5 * 60 * 1000);

        <?php if ($headerActiveTask): ?>
            const activeTaskStartTime = <?= strtotime($headerActiveTask['start_time']) * 1000 ?>;
            const activeTaskName = "<?= htmlspecialchars($headerActiveTask['task_name'], ENT_QUOTES) ?>";
            const originalTitle = document.title;
            
            function updateDynamicTitleAndClock() {
                const now = Date.now();
                const diff = Math.floor((now - activeTaskStartTime) / 1000);
                const h = Math.floor(diff / 3600);
                const m = Math.floor((diff % 3600) / 60);
                const s = diff % 60;

                const titleTimer = (h > 0 ? h + ":" : "") + (m < 10 ? "0" + m : m) + ":" + (s < 10 ? "0" + s : s);
                const clockTimer = (h > 0 ? h + "h " : "") + (m < 10 ? "0" + m : m) + "m " + (s < 10 ? "0" + s : s) + "s";
                
                document.title = `(${titleTimer}) ${activeTaskName}`;
                
                const headerClock = document.getElementById('header-clock');
                if (headerClock) headerClock.innerText = clockTimer;
            }
            setInterval(updateDynamicTitleAndClock, 1000);
            updateDynamicTitleAndClock();
        <?php endif; ?>
        
        // Mobile Toggle Logic
        document.addEventListener
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.querySelector('.mobile-toggle');
            const nav = document.getElementById('nav-menu');
            if(toggle && nav) {
                toggle.addEventListener('click', () => { nav.classList.toggle('show'); });
            }
        });
    </script>
    
    <div class="container main-content-area" style="padding-top: 30px;">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['message_type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['message'] // No htmlspecialchars to allow bold tags from server ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>
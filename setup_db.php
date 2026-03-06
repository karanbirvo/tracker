<!-- repair_database.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Repair & Sync Tool</title>
    <style>body{font-family:monospace;background:#1a1a1a;color:#eee;padding:20px;line-height:1.6;} a{color:#61dafb;} .success{color:#00ff7f;} .error{color:#ff4d4d;} .warn{color:#ffc107;}</style>
</head>
<body>
<h1>Full Database Repair & Migration</h1>
<?php
require_once __DIR__ . '/includes/db.php';

function run($pdo, $sql, $msg, $ignoreErrors = false) {
    try {
        $pdo->exec($sql);
        echo "<span class='success'>✔ $msg</span><br>";
    } catch (PDOException $e) {
        if ($ignoreErrors) {
             // Silently ignore "duplicate" or "already exists" errors
        } else {
            echo "<span class='error'>✖ Error ($msg): " . $e->getMessage() . "</span><br>";
        }
    }
}

try {
    // 1. Get DB Name
    $stmt = $pdo->query("SELECT DATABASE()");
    $dbName = $stmt->fetchColumn();
    echo "<h3>Step 1: Unlocking Tables in '$dbName'...</h3>";

    // 2. Find & Drop ALL existing Foreign Keys
    $sql = "SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = :dbname AND REFERENCED_TABLE_NAME IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['dbname' => $dbName]);
    $fks = $stmt->fetchAll();
    if (empty($fks)) { echo "No existing foreign keys found.<br>"; }
    foreach ($fks as $fk) {
        run($pdo, "ALTER TABLE `{$fk['TABLE_NAME']}` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`", "Dropped FK `{$fk['CONSTRAINT_NAME']}`", true);
    }

    echo "<br><h3>Step 2: Creating & Updating Schema...</h3>";

    // 3. Define and Create/Update all tables and columns
    
    // --- USERS ---
    run($pdo, "CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, email VARCHAR(100) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL) ENGINE=InnoDB;", "Verified `users` table");
    $userCols = ["mobile_number VARCHAR(20)","user_role ENUM('admin', 'user') DEFAULT 'user'","session_token VARCHAR(255)","created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP","smtp_host VARCHAR(255)","smtp_port VARCHAR(255)","smtp_username VARCHAR(255)","smtp_password VARCHAR(255)","default_recipients TEXT","email_sender_name VARCHAR(255)","email_reply_to VARCHAR(255)","email_header_html TEXT","email_footer_html TEXT","security_question VARCHAR(255)","security_answer_hash VARCHAR(255)","reset_token VARCHAR(255)","reset_token_expires_at DATETIME","is_email_verified TINYINT(1) DEFAULT 0","email_verification_token VARCHAR(255)","perm_delete TINYINT(1) DEFAULT 0","perm_edit TINYINT(1) DEFAULT 1","perm_ai TINYINT(1) DEFAULT 1","perm_email TINYINT(1) DEFAULT 1","perm_reports TINYINT(1) DEFAULT 1","perm_view_all TINYINT(1) DEFAULT 0"];
    foreach ($userCols as $col) { run($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS $col", "", true); }

    // --- PROJECTS ---
    run($pdo, "CREATE TABLE IF NOT EXISTS projects (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, project_name VARCHAR(255) NOT NULL, status ENUM('Active', 'Completed', 'Archived') DEFAULT 'Active') ENGINE=InnoDB;", "Verified `projects` table");
    $projectCols = ["client_name VARCHAR(255)","project_url VARCHAR(255)","project_type ENUM('Hourly', 'Fixed') DEFAULT 'Hourly'","description TEXT","deadline DATE", "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"];
    foreach ($projectCols as $col) { run($pdo, "ALTER TABLE projects ADD COLUMN IF NOT EXISTS $col", "", true); }

    // --- TIME_ENTRIES ---
    run($pdo, "CREATE TABLE IF NOT EXISTS time_entries (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, task_name VARCHAR(255) NOT NULL, start_time DATETIME NOT NULL) ENGINE=InnoDB;", "Verified `time_entries` table");
    $timeCols = ["description TEXT","end_time DATETIME","entry_date DATE NOT NULL","project_id INT", "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"];
    foreach ($timeCols as $col) { run($pdo, "ALTER TABLE time_entries ADD COLUMN IF NOT EXISTS $col", "", true); }

    // --- MANUAL_ENTRIES ---
    run($pdo, "CREATE TABLE IF NOT EXISTS manual_entries (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, task_name VARCHAR(255) NOT NULL, entry_date DATE NOT NULL) ENGINE=InnoDB;", "Verified `manual_entries` table");
    $manualCols = ["description TEXT","start_time DATETIME","end_time DATETIME","project_id INT", "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"];
    foreach ($manualCols as $col) { run($pdo, "ALTER TABLE manual_entries ADD COLUMN IF NOT EXISTS $col", "", true); }
    
    // --- SCHEDULED_TASKS ---
    run($pdo, "CREATE TABLE IF NOT EXISTS scheduled_tasks (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, task_name VARCHAR(255) NOT NULL) ENGINE=InnoDB;", "Verified `scheduled_tasks` table");
    $schedCols = ["description TEXT","project_id INT","status ENUM('pending','completed') DEFAULT 'pending'", "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"];
    foreach ($schedCols as $col) { run($pdo, "ALTER TABLE scheduled_tasks ADD COLUMN IF NOT EXISTS $col", "", true); }

    // --- LOGS ---
    run($pdo, "CREATE TABLE IF NOT EXISTS email_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, sent_from VARCHAR(255), sent_to TEXT, subject VARCHAR(255)) ENGINE=InnoDB;", "Verified `email_logs` table");
    $emailLogCols = ["body LONGTEXT","attachments TEXT","status ENUM('success', 'failed') DEFAULT 'success'","error_message TEXT","sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"];
    foreach($emailLogCols as $col) { run($pdo, "ALTER TABLE email_logs ADD COLUMN IF NOT EXISTS $col", "", true); }
    
    run($pdo, "CREATE TABLE IF NOT EXISTS ai_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL) ENGINE=InnoDB;", "Verified `ai_logs` table");
    $aiLogCols = ["prompt_text TEXT","response_text LONGTEXT","generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"];
    foreach($aiLogCols as $col) { run($pdo, "ALTER TABLE ai_logs ADD COLUMN IF NOT EXISTS $col", "", true); }
    
    // --- API KEYS (Secure storage for sensitive API keys) ---
    run($pdo, "CREATE TABLE IF NOT EXISTS api_keys (id INT AUTO_INCREMENT PRIMARY KEY, key_name VARCHAR(100) NOT NULL UNIQUE, key_value LONGTEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB;", "Verified `api_keys` table");
    
    echo "<br><h3>Step 3: Standardizing Column Types...</h3>";

    // 4. Standardize all ID types
    run($pdo, "ALTER TABLE users MODIFY id INT(11) NOT NULL AUTO_INCREMENT", "Standardized users.id");
    run($pdo, "ALTER TABLE projects MODIFY user_id INT(11) NOT NULL", "Standardized projects.user_id");
    run($pdo, "ALTER TABLE time_entries MODIFY user_id INT(11) NOT NULL", "Standardized time_entries.user_id");
    run($pdo, "ALTER TABLE time_entries MODIFY project_id INT(11) DEFAULT NULL", "Standardized time_entries.project_id");
    run($pdo, "ALTER TABLE manual_entries MODIFY user_id INT(11) NOT NULL", "Standardized manual_entries.user_id");
    run($pdo, "ALTER TABLE manual_entries MODIFY project_id INT(11) DEFAULT NULL", "Standardized manual_entries.project_id");
    run($pdo, "ALTER TABLE scheduled_tasks MODIFY user_id INT(11) NOT NULL", "Standardized scheduled_tasks.user_id");
    run($pdo, "ALTER TABLE scheduled_tasks MODIFY project_id INT(11) DEFAULT NULL", "Standardized scheduled_tasks.project_id");
    run($pdo, "ALTER TABLE email_logs MODIFY user_id INT(11) NOT NULL", "Standardized email_logs.user_id");
    run($pdo, "ALTER TABLE ai_logs MODIFY user_id INT(11) NOT NULL", "Standardized ai_logs.user_id");

    echo "<br><h3>Step 4: Unifying Character Sets...</h3>";

    // 5. Unify Collations
    $allTables = ['users', 'projects', 'time_entries', 'scheduled_tasks', 'manual_entries', 'email_logs', 'ai_logs'];
    foreach ($allTables as $table) {
        run($pdo, "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", "Unified collation for `$table`");
    }

    echo "<br><h3>Step 5: Rebuilding Table Relationships...</h3>";

    // 6. Re-add Foreign Keys
    run($pdo, "ALTER TABLE projects ADD CONSTRAINT fk_projects_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE", "Linked projects->users", true);
    run($pdo, "ALTER TABLE time_entries ADD CONSTRAINT fk_time_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE", "Linked time_entries->users", true);
    run($pdo, "ALTER TABLE time_entries ADD CONSTRAINT fk_time_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL", "Linked time_entries->projects", true);
    run($pdo, "ALTER TABLE manual_entries ADD CONSTRAINT fk_manual_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE", "Linked manual_entries->users", true);
    run($pdo, "ALTER TABLE manual_entries ADD CONSTRAINT fk_manual_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL", "Linked manual_entries->projects", true);
    run($pdo, "ALTER TABLE scheduled_tasks ADD CONSTRAINT fk_sched_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE", "Linked scheduled_tasks->users", true);
    run($pdo, "ALTER TABLE scheduled_tasks ADD CONSTRAINT fk_sched_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL", "Linked scheduled_tasks->projects", true);
    run($pdo, "ALTER TABLE email_logs ADD CONSTRAINT fk_email_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE", "Linked email_logs->users", true);
    run($pdo, "ALTER TABLE ai_logs ADD CONSTRAINT fk_ai_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE", "Linked ai_logs->users", true);
    
    echo "<br><h2 style='color: limegreen;'>DATABASE REPAIR COMPLETE!</h2><p>All tables and columns are now up-to-date and consistent. <a href='index.php'>Go to Dashboard</a></p>";

} catch (PDOException $e) {
    echo "<h2 class='error'>A CRITICAL ERROR OCCURRED: " . $e->getMessage() . "</h2>";
}
?>
</body>
</html>
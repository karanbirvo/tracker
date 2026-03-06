<?php
// --- ADD THE FOLLOWING LINE ---
// Replace 'YOUR_GEMINI_API_KEY' with the key you got from Google AI Studio.
define('GEMINI_API_KEY', 'AIzaSyDVMKQYehyL1fQkFGrtMpRz2KdG2n8tJS4');

$db_host = 'localhost';
$db_name = 'u220546828_tracker'; // Choose your DB name
$db_user = 'u220546828_tracker';            // Your DB username
$db_pass = '@Karan2020@';                // Your DB password

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}


// Start session and set timezone
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Kolkata');

// --- ADD THE FOLLOWING LINES ---
// IMPORTANT: Change this key to a long random string!
// You can use an online generator for a "random 32-byte string".
define('ENCRYPTION_KEY', 'P@ssw0rd123!KaranbirDhiman#VO2024'); // <-- CHANGE THIS TO YOUR OWN SECRET KEY
define('ENCRYPTION_CIPHER', 'aes-256-cbc');
?>
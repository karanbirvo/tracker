<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

session_unset();
session_destroy();
header("Location: login.php");
exit();

?>

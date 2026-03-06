<?php
require_once '../includes/functions.php';
requireLogin();
// 1. Permission Check
if (!hasPermission('perm_email')) {
    $_SESSION['message'] = "Access Denied: You do not have permission to send emails.";
    $_SESSION['message_type'] = "danger";
    header("Location: ../index.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

// Fetch user's settings
$stmt = $pdo->prepare("SELECT smtp_host, smtp_port, smtp_username, smtp_password, email_footer_html FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userSettings = $stmt->fetch();

if (!$userSettings || empty($userSettings['smtp_host'])) {
    $_SESSION['message'] = "SMTP settings are not configured.";
    $_SESSION['message_type'] = 'danger';
    header("Location: ../email_settings.php");
    exit();
}

// --- Prepare all email data BEFORE the try/catch block so it's available for logging in both success and failure cases ---
$fromEmail = decrypt_data($userSettings['smtp_username']);
$toRecipients = $_POST['to_email'];
$email_subject = $_POST['subject'];
$email_body_content = $_POST['email_body'];
$email_footer_html = $userSettings['email_footer_html'] ?? '';

// Generate the final HTML using the template
ob_start();
include '../includes/email_template.php'; // Include the template
$final_email_html = ob_get_clean(); // Get the captured content

// Prepare attachment list for logging
$attachment_list = [];
if (isset($_FILES['attachments'])) {
    foreach ($_FILES['attachments']['name'] as $filename) {
        if (!empty($filename)) {
            $attachment_list[] = $filename;
        }
    }
}
$attachments_for_db = implode(', ', $attachment_list);

// --- Initialize PHPMailer ---
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = decrypt_data($userSettings['smtp_host']);
    $mail->Username   = $fromEmail;
    $mail->Password   = decrypt_data($userSettings['smtp_password']);
    $mail->Port       = (int)decrypt_data($userSettings['smtp_port']);
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    // Recipients
    $mail->setFrom($fromEmail, $_SESSION['username']);
    $recipients = explode(',', $toRecipients);
    foreach ($recipients as $recipient) {
        if (!empty(trim($recipient))) {
            $mail->addAddress(trim($recipient));
        }
    }
    
    // Handle Attachments
    if (isset($_FILES['attachments'])) {
        for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $mail->addAttachment($_FILES['attachments']['tmp_name'][$i], $_FILES['attachments']['name'][$i]);
            }
        }
    }

    // Content
    $mail->isHTML(true);
    $mail->Subject = $email_subject;
    $mail->Body    = $final_email_html;
    $mail->AltBody = strip_tags($email_body_content);

    $mail->send();
    
    // --- SUCCESS LOGGING ---
    $logStmt = $pdo->prepare("INSERT INTO email_logs (user_id, sent_from, sent_to, subject, body, attachments, status) VALUES (?, ?, ?, ?, ?, ?, 'success')");
    $logStmt->execute([$_SESSION['user_id'], $fromEmail, $toRecipients, $email_subject, $final_email_html, $attachments_for_db]);
    
    $_SESSION['message'] = 'Email has been sent successfully!';
    $_SESSION['message_type'] = 'success';

} catch (Exception $e) {
    // --- FAILURE LOGGING (CORRECTED) ---
    // THE FIX: Use the $final_email_html variable here instead of the hardcoded string.
    $logStmt = $pdo->prepare("INSERT INTO email_logs (user_id, sent_from, sent_to, subject, body, attachments, status, error_message) VALUES (?, ?, ?, ?, ?, ?, 'failed', ?)");
    $logStmt->execute([$_SESSION['user_id'], $fromEmail, $toRecipients, $email_subject, $final_email_html, $attachments_for_db, $mail->ErrorInfo]);

    $_SESSION['message'] = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    $_SESSION['message_type'] = 'danger';
}

header("Location: ../reports.php");
exit();
?>
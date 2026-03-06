<?php
require_once '../includes/functions.php'; // This includes db.php and our encryption functions
requireLogin();

// Use PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Make sure the path to PHPMailer is correct
require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

// --- 1. Fetch User's ENCRYPTED SMTP Settings ---
$stmt = $pdo->prepare("SELECT smtp_host, smtp_port, smtp_username, smtp_password FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userSettings = $stmt->fetch();

// Check if settings are configured
if (!$userSettings || empty($userSettings['smtp_host']) || empty($userSettings['smtp_password'])) {
     $_SESSION['message'] = "SMTP settings are missing or incomplete. Please configure them in Email Settings before sending.";
     $_SESSION['message_type'] = 'danger';
     header("Location: ../email_settings.php");
     exit();
}

$mail = new PHPMailer(true);

try {
    // --- 2. Configure PHPMailer with DECRYPTED Credentials ---
    
    // To see full debug logs, uncomment the following line
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;                     
    
    $mail->isSMTP();
    
    // DECRYPT the settings right before you use them
    $mail->Host       = decrypt_data($userSettings['smtp_host']);
    $mail->Username   = decrypt_data($userSettings['smtp_username']);
    $mail->Password   = decrypt_data($userSettings['smtp_password']); // This should be the App Password
    $mail->Port       = (int)decrypt_data($userSettings['smtp_port']);
    
    $mail->SMTPAuth   = true;
    
    // For Gmail, the encryption settings are crucial.
    // If you use Port 587, SMTPSecure must be 'tls'
    // If you use Port 465, SMTPSecure must be 'ssl'
    if ($mail->Port == 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 'tls'
    } elseif ($mail->Port == 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 'ssl'
    }


    // --- 3. Set Recipients and Content from Form ---
    $fromEmail = $_POST['from_email'];
    $toEmails = $_POST['to_email'];
    
    $mail->setFrom($fromEmail, $_SESSION['username']);
    
    // Add multiple recipients
    $recipients = explode(',', $toEmails);
    foreach ($recipients as $recipient) {
        $trimmedRecipient = trim($recipient);
        if (!empty($trimmedRecipient)) {
            $mail->addAddress($trimmedRecipient);
        }
    }
    
    // Set email content
    $mail->isHTML(true); // Set email format to HTML
    $mail->Subject = $_POST['subject'];
    $mail->Body    = $_POST['email_body']; // The body comes from the rich text editor
    $mail->AltBody = strip_tags($_POST['email_body']); // Add a plain text version for non-HTML mail clients

    // --- 4. Send the Email ---
    $mail->send();
    $_SESSION['message'] = 'EOD report email has been sent successfully!';
    $_SESSION['message_type'] = 'success';

} catch (Exception $e) {
    // If sending fails, provide a helpful error message
    $errorMessage = "Message could not be sent. Please double-check your SMTP settings and ensure you are using a valid Google App Password. Mailer Error: {$mail->ErrorInfo}";
    // Log the detailed technical error for your own review
    error_log("PHPMailer Error for user ID {$_SESSION['user_id']}: " . $mail->ErrorInfo);
    
    $_SESSION['message'] = $errorMessage;
    $_SESSION['message_type'] = 'danger';
}

// Redirect back to the reports page in either case
header("Location: ../reports.php");
exit();
?>
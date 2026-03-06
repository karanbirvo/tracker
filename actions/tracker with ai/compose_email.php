<?php
require_once 'includes/header.php';
requireLogin();

// --- 1. Get Report Parameters from URL ---
$userId = (int)($_GET['user_id'] ?? $_SESSION['user_id']);
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$timeFrom = $_GET['time_from'] ?? '00:00:00';
$dateTo = $_GET['date_to'] ?? $dateFrom;
$timeTo = $_GET['time_to'] ?? '23:59:59';

$startDateTime = $dateFrom . ' ' . $timeFrom;
$endDateTime = $dateTo . ' ' . $timeTo;

// --- 2. Fetch Time Entries from Database ---
$stmt = $pdo->prepare("SELECT task_name, start_time, end_time FROM time_entries 
                       WHERE user_id = :user_id 
                       AND start_time >= :start_datetime 
                       AND start_time <= :end_datetime
                       ORDER BY start_time ASC");
$stmt->execute([
    'user_id' => $userId,
    'start_datetime' => $startDateTime,
    'end_datetime' => $endDateTime
]);
$entries = $stmt->fetchAll();

// --- 3. Format Data for the AI ---
$reportTextForAI = "EOD Report Summary:\n";
$reportTextForAI .= "User: " . htmlspecialchars($_SESSION['username']) . "\n";
$reportTextForAI .= "Period: " . htmlspecialchars($startDateTime) . " to " . htmlspecialchars($endDateTime) . "\n\n";
$reportTextForAI .= "Tasks Performed:\n";
$grandTotalSeconds = 0;

if (empty($entries)) {
    $reportTextForAI .= "- No tasks logged for this period.\n";
} else {
    foreach ($entries as $entry) {
        $durationStr = "Ongoing";
        if ($entry['end_time']) {
            $start = new DateTime($entry['start_time']);
            $end = new DateTime($entry['end_time']);
            $interval = $start->diff($end);
            $durationSeconds = $end->getTimestamp() - $start->getTimestamp();
            $grandTotalSeconds += $durationSeconds;
            $durationStr = $interval->format('%Hh %Im %Ss');
        }
        $reportTextForAI .= "- " . htmlspecialchars($entry['task_name']) . " (" . $durationStr . ")\n";
    }
}
$hours = floor($grandTotalSeconds / 3600);
$minutes = floor(($grandTotalSeconds % 3600) / 60);
$reportTextForAI .= "\nTotal Logged Time: " . sprintf('%02dh %02dm', $hours, $minutes) . "\n";

/**
 * SIMULATED Gemini Function.
 * Replace the logic inside this function with your actual API call to Gemini.
 */
function callGeminiToSummarizeEOD($reportData) {
    // --- THIS IS A PLACEHOLDER ---
    // In a real application, you would make an HTTP request to the Gemini API here.
    // The prompt would be something like:
    // "Please summarize the following EOD report into a professional and friendly email body.
    // Start with a greeting, list the key tasks, mention the total time, and end with a closing.
    // Here is the data: \n\n" . $reportData
    
    // For now, we will just format it nicely.
    $header = "Hi Team,\n\nPlease find my End of Day (EOD) report for the period below.\n\n";
    $footer = "\n\nI will continue with my pending tasks tomorrow.\n\nBest regards,\n" . htmlspecialchars($_SESSION['username']);

    // Replace the raw data part with a more structured version for the email body
    $formattedBody = str_replace("\n", "<br>", $reportData); // Convert newlines for HTML
    
    // In a real scenario, the AI would generate this text. We are just re-formatting.
    $generatedSummary = "Hello Team,<br><br>Here is a summary of my work for today.<br><br>" . $formattedBody . "<br>Please let me know if you have any questions.<br><br>Thank you,<br>" . htmlspecialchars($_SESSION['username']);
    
    return $generatedSummary;
}

$emailBody = callGeminiToSummarizeEOD($reportTextForAI);

// --- 4. Prepare Form Fields ---
$subject = "EOD Report: " . htmlspecialchars($_SESSION['username']) . " - " . date('F j, Y', strtotime($dateFrom));
if ($dateFrom !== $dateTo) {
    $subject = "Work Report: " . htmlspecialchars($_SESSION['username']) . " (" . $dateFrom . " to " . $dateTo . ")";
}

// Fetch user's default recipients
$stmtSettings = $pdo->prepare("SELECT default_recipients, smtp_username FROM users WHERE id = ?");
$stmtSettings->execute([$_SESSION['user_id']]);
$settings = $stmtSettings->fetch();
$defaultRecipients = decrypt_data($settings['default_recipients'] ?? '');
$fromEmail = decrypt_data($settings['smtp_username'] ?? 'Not Configured');

?>
<!-- Include a Rich Text Editor like TinyMCE for a better editing experience -->
<script src="https://cdn.tiny.cloud/1/n0wu92kefc4epvtdbx3gtl1yfw5oykhgc341ncwvosakbjdz/tinymce/8/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: 'textarea#email_body',
    plugins: 'lists link image table code help wordcount',
    toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | indent outdent | bullist numlist | code | help'
  });
</script>

<h1><i class="fas fa-paper-plane"></i> Compose & Send EOD Report</h1>

<?php if ($fromEmail === 'Not Configured' || empty($fromEmail)): ?>
    <div class="alert alert-danger">
        Your SMTP settings are not configured. You cannot send emails. 
        <a href="email_settings.php" class="alert-link">Please configure them now</a>.
    </div>
<?php else: ?>
    <div class="card" style="padding:25px;">
        <form action="actions/send_email.php" method="POST">
            <div class="form-group">
                <label for="from_email">From:</label>
                <input type="email" id="from_email" name="from_email" class="form-control" value="<?= htmlspecialchars($fromEmail) ?>" readonly>
            </div>
            <div class="form-group">
                <label for="to_email">To (separate emails with a comma):</label>
                <input type="text" id="to_email" name="to_email" class="form-control" value="<?= htmlspecialchars($defaultRecipients) ?>" required>
            </div>
            <div class="form-group">
                <label for="subject">Subject:</label>
                <input type="text" id="subject" name="subject" class="form-control" value="<?= htmlspecialchars($subject) ?>" required>
            </div>
            <div class="form-group">
                <label for="email_body">Body:</label>
                <textarea id="email_body" name="email_body" class="form-control" rows="18"><?= $emailBody // We don't use htmlspecialchars here because it's going into a rich text editor ?></textarea>
            </div>
            <div style="margin-top:20px;">
                <button type="submit" class="button button-primary"><i class="fas fa-paper-plane"></i> Send Email</button>
                <a href="reports.php" class="button secondary" style="margin-left:10px;">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
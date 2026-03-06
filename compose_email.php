<?php
// TEMPORARY: For debugging. REMOVE these two lines in production.
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/functions.php';
requireLogin();

// 1. Get Filters
$userId = (int)($_GET['user_id'] ?? $_SESSION['user_id']);
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$timeFrom = $_GET['time_from'] ?? '00:00:00';
$dateTo = $_GET['date_to'] ?? $dateFrom;
$timeTo = $_GET['time_to'] ?? '23:59:59';

// 2. CORRECTED & SIMPLIFIED QUERY
$sql = "
SELECT combined.*, p.project_name, p.client_name, p.project_url 
FROM (
    SELECT task_name, description, start_time, end_time, project_id, 'tracker' as type 
    FROM time_entries 
    WHERE user_id = :uid1 AND start_time >= :start_dt1 AND start_time <= :end_dt1
    
    UNION ALL
    
    SELECT task_name, description, start_time, end_time, project_id, 'manual' as type 
    FROM manual_entries 
    WHERE user_id = :uid2 AND start_time >= :start_dt2 AND start_time <= :end_dt2
) AS combined
LEFT JOIN projects p ON combined.project_id = p.id
ORDER BY p.project_name DESC, combined.start_time ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'uid1' => $userId, 'start_dt1' => "$dateFrom $timeFrom", 'end_dt1' => "$dateTo $timeTo",
    'uid2' => $userId, 'start_dt2' => "$dateFrom $timeFrom", 'end_dt2' => "$dateTo $timeTo"
]);
$entries = $stmt->fetchAll();


// 3. Group Tasks by Project
$groupedTasks = [];
foreach ($entries as $entry) {
    $key = !empty($entry['project_name']) ? $entry['project_name'] : 'General Tasks';
    $groupedTasks[$key]['url'] = $entry['project_url'] ?? '[No Link]';
    $groupedTasks[$key]['client'] = $entry['client_name'] ?? 'Internal';
    $groupedTasks[$key]['tasks'][] = [
        'name' => $entry['task_name'],
        'desc' => $entry['description']
    ];
}

// 4. Build Plain Text EOD Report Body (for AI)
$reportBodyPlainText = "Date: " . date('F j, Y', strtotime($dateFrom)) . "\n\n";

if (empty($groupedTasks)) {
    $reportBodyPlainText .= "No tasks logged for this period.\n";
} else {
    $reportBodyPlainText .= "1. Project Updates\n";
    foreach ($groupedTasks as $projectName => $data) {
        $reportBodyPlainText .= "Project Name: " . $projectName . " (" . $data['client'] . ")\n";
        $reportBodyPlainText .= "Design/Preview Link: " . $data['url'] . "\n";
        $reportBodyPlainText .= "Work Completed:\n";
        foreach ($data['tasks'] as $task) {
            $reportBodyPlainText .= "- " . $task['name'] . (!empty($task['desc']) ? " (" . $task['desc'] . ")" : "") . "\n";
        }
        $reportBodyPlainText .= "Work Pending: [Please fill]\n\n";
    }
}

$reportBodyPlainText .= "2. Tracker Status\nTotal Tracker: [Please fill]\nRunning Tracker: [Please fill]\nPending Tracker: [Please fill]\n\n";
$reportBodyPlainText .= "3. System Details\nSystem Seat Number: [Please fill]\nSystem Password: [Please fill]\nTracker Tabs Open: [Yes/No]\n\n";
$reportBodyPlainText .= "4. Client Communication\n[Any updates/Message for client]\n\n";
$reportBodyPlainText .= "5. Message for BD Team\n[Follow-up requirements]\n";

// Convert to HTML for TinyMCE
$default_email_body_html = nl2br(htmlspecialchars($reportBodyPlainText));

// 5. Settings
$subject = date('F j, Y', strtotime($dateFrom)) . " - EOD Report: " . htmlspecialchars($_SESSION['username']);
$stmtSettings = $pdo->prepare("SELECT default_recipients, smtp_username FROM users WHERE id = ?");
$stmtSettings->execute([$_SESSION['user_id']]);
$settings = $stmtSettings->fetch();
$defaultRecipients = decrypt_data($settings['default_recipients'] ?? '');
$fromEmail = decrypt_data($settings['smtp_username'] ?? 'Not Configured');

require_once 'includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-envelope-open-text text-primary me-2"></i>Compose Report</h2>
            <p class="text-muted m-0 small">Review and edit your daily report before sending.</p>
        </div>
    </div>

    <?php if ($fromEmail === 'Not Configured' || empty($fromEmail)): ?>
        <div class="alert alert-danger shadow-sm rounded-3">
            <i class="fas fa-exclamation-circle me-2"></i> SMTP settings missing. <a href="email_settings.php" class="alert-link">Configure now</a>.
        </div>
    <?php else: ?>
        
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <form id="email-compose-form" action="actions/send_email.php" method="POST" enctype="multipart/form-data">
                    
                    <!-- To & Subject -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">TO</label>
                            <input type="text" name="to_email" class="form-control" value="<?= htmlspecialchars($defaultRecipients) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">SUBJECT</label>
                            <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($subject) ?>" required>
                        </div>
                    </div>

                    <!-- Editor -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold text-muted m-0">MESSAGE BODY</label>
                            <div class="d-flex align-items-center gap-2">
                                <span id="ai-status" class="small text-muted fst-italic"></span>
                                <button type="button" id="regenerate-btn" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fas fa-magic me-1"></i> AI Rewrite
                                </button>
                            </div>
                        </div>
                        <textarea id="email_body" name="email_body"><?= $default_email_body_html ?></textarea>
                    </div>

                    <!-- Attachments -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">ATTACHMENTS</label>
                        <input type="file" name="attachments[]" class="form-control" multiple>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-success px-4 py-2 shadow-sm">
                            <i class="fas fa-paper-plane me-2"></i>Send Email
                        </button>
                        <button type="button" id="outlook-btn" class="btn btn-outline-secondary">
                            <i class="fab fa-windows me-2"></i>Desktop App
                        </button>
                        <button type="button" id="outlook-web-btn" class="btn btn-outline-secondary">
                            <i class="fas fa-globe me-2"></i>Outlook Web
                        </button>
                    </div>

                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/n0wu92kefc4epvtdbx3gtl1yfw5oykhgc341ncwvosakbjdz/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    tinymce.init({
        selector: 'textarea#email_body',
        height: 500,
        plugins: 'preview searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons',
        toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist | forecolor backcolor | link preview',
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#333; }',
        menubar: false,
        statusbar: false
    });

    // AI Rewrite
    const regenerateBtn = document.getElementById('regenerate-btn');
    const statusDiv = document.getElementById('ai-status');
    if(regenerateBtn) {
        regenerateBtn.addEventListener('click', () => {
            const currentContent = tinymce.get('email_body').getContent({ format: 'text' });
            statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span> Rewriting...';
            regenerateBtn.disabled = true;
            
            fetch('actions/regenerate_summary.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'report_data=' + encodeURIComponent(currentContent)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    tinymce.get('email_body').setContent(data.summary);
                    statusDiv.innerHTML = '<i class="fas fa-check text-success"></i> Done';
                } else {
                    statusDiv.innerHTML = '<span class="text-danger">Error</span>';
                }
            })
            .catch(e => statusDiv.innerHTML = 'Network Error')
            .finally(() => { regenerateBtn.disabled = false; });
        });
    }

    // Outlook Web (Copy & Open)
    const outlookWebBtn = document.getElementById('outlook-web-btn');
    if(outlookWebBtn) {
        outlookWebBtn.addEventListener('click', function() {
            const to = document.querySelector('input[name="to_email"]').value;
            const subject = document.querySelector('input[name="subject"]').value;
            const bodyHtml = tinymce.get('email_body').getContent(); 
            const bodyText = tinymce.get('email_body').getContent({ format: 'text' }); 

            try {
                const blob = new Blob([bodyHtml], { type: 'text/html' });
                const data = [new ClipboardItem({ 'text/html': blob })];
                navigator.clipboard.write(data).then(() => {
                    const url = `https://outlook.office.com/mail/deeplink/compose?to=${encodeURIComponent(to)}&subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(bodyText)}`;
                    window.open(url, '_blank');
                    const original = outlookWebBtn.innerHTML;
                    outlookWebBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    setTimeout(() => outlookWebBtn.innerHTML = original, 2000);
                });
            } catch (e) { alert('Copy failed. Opening plain text version.'); }
        });
    }
    
    // Outlook Desktop (Mailto)
    const outlookBtn = document.getElementById('outlook-btn');
    if(outlookBtn) {
        outlookBtn.addEventListener('click', function() {
            const to = document.querySelector('input[name="to_email"]').value;
            const subject = document.querySelector('input[name="subject"]').value;
            const bodyText = tinymce.get('email_body').getContent({ format: 'text' }); 
            window.location.href = `mailto:${to}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(bodyText)}`;
        });
    }
});
</script>

<style>body { background-color: #f8f9fa; }</style>

<?php require_once 'includes/footer.php'; ?>
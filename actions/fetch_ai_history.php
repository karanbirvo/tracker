<?php
require_once '../includes/functions.php';
requireLogin();

// Configuration
$items_per_page = 10;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($page - 1) * $items_per_page;

// Prepare SQL with named placeholders for both LIMIT and OFFSET
$stmt = $pdo->prepare("SELECT * FROM ai_logs WHERE user_id = :user_id ORDER BY generated_at DESC LIMIT :limit OFFSET :offset");

// *** FIX IS HERE: BIND ALL PARAMETERS EXPLICITLY ***
$stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT); // This was the missing part
$stmt->execute();

$logs = $stmt->fetchAll();

// We need to keep track of the index for unique accordion IDs
$startIndex = $offset;

foreach ($logs as $log) {
    $index = $startIndex++;
?>
<div class="accordion-item">
    <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $index ?>">
            <span class="fw-bold text-primary me-3"><?= date('M j, Y - H:i', strtotime($log['generated_at'])) ?></span>
            <span class="text-muted text-truncate" style="max-width: 400px;"><?= htmlspecialchars(substr(strip_tags($log['prompt_text']), 0, 80)) ?>...</span>
        </button>
    </h2>
    <div id="collapse-<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#aiHistoryAccordion">
        <div class="accordion-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="small fw-bold text-uppercase text-muted">Prompt Sent</h6>
                    <div class="prompt-box">
                        <pre><code class="language-text"><?= htmlspecialchars($log['prompt_text']) ?></code></pre>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="small fw-bold text-uppercase text-muted">AI Response (Rendered)</h6>
                    <div class="response-box">
                        <?= $log['response_text'] ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
}
?>
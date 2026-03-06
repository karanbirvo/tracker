<?php
require_once 'includes/functions.php';
requireLogin();

// Initial Load
$items_per_page = 10;
$stmt = $pdo->prepare("SELECT * FROM ai_logs WHERE user_id = :user_id ORDER BY generated_at DESC LIMIT :limit");

// *** FIX IS HERE: BIND PARAMETERS EXPLICITLY ***
$stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT); // This tells PDO it's a number
$stmt->execute();

$initial_logs = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-robot text-primary me-2"></i>AI Generation History</h2>
            <p class="text-muted m-0 small">A log of your interactions with the content generation AI.</p>
        </div>
    </div>
    
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($initial_logs)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="fas fa-magic fa-3x mb-3 opacity-25"></i>
                    <p>No AI interactions recorded yet.</p>
                </div>
            <?php else: ?>
                <!-- Accordion Container -->
                <div class="accordion accordion-flush" id="aiHistoryAccordion">
                    
                    <?php foreach ($initial_logs as $index => $log): ?>
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
                                        <!-- Left: Prompt -->
                                        <div class="col-md-6">
                                            <h6 class="small fw-bold text-uppercase text-muted">Prompt Sent</h6>
                                            <div class="prompt-box">
                                                <pre><code class="language-text"><?= htmlspecialchars($log['prompt_text']) ?></code></pre>
                                            </div>
                                        </div>
                                        <!-- Right: Response -->
                                        <div class="col-md-6">
                                            <h6 class="small fw-bold text-uppercase text-muted">AI Response (Rendered)</h6>
                                            <div class="response-box">
                                                <?= $log['response_text'] // Render the raw HTML ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>

                <!-- Load More Button -->
                <div id="load-more-container" class="text-center p-3 border-top bg-light">
                    <button id="load-more-btn" class="btn btn-sm btn-outline-secondary">Load More</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const loadMoreBtn = document.getElementById('load-more-btn');
    const accordionContainer = document.getElementById('aiHistoryAccordion');
    let currentPage = 1;

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            currentPage++;
            loadMoreBtn.disabled = true;
            loadMoreBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';

            fetch(`./actions/fetch_ai_history.php?page=${currentPage}`)
                .then(response => response.text())
                .then(html => {
                    if (html.trim() !== '') {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        
                        Array.from(tempDiv.children).forEach(newItem => {
                            accordionContainer.appendChild(newItem);
                        });

                        loadMoreBtn.disabled = false;
                        loadMoreBtn.innerHTML = 'Load More';
                    } else {
                        loadMoreBtn.parentElement.innerHTML = '<span class="text-muted small">No more entries.</span>';
                    }
                })
                .catch(error => {
                    loadMoreBtn.parentElement.innerHTML = '<span class="text-danger small">Error loading entries.</span>';
                });
        });
    }
});
</script>

<style>
body { background-color: #f8f9fa; }
.accordion-button:not(.collapsed) { background-color: #eff6ff; color: #2D5A95; box-shadow: none; }
.accordion-button:focus { box-shadow: none; }
.prompt-box, .response-box { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; max-height: 400px; overflow-y: auto; }
.prompt-box pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-size: 0.85rem; }
button.accordion-button.collapsed:hover {
    background-color: #0d6efd29;
}
</style>

<?php require_once 'includes/footer.php'; ?>
<?php
require_once 'includes/functions.php';
requireLogin();

// Admin Access Check (Using granular permission for better security)
if (!hasPermission('perm_view_all')) {
    $_SESSION['message'] = "Access Denied."; $_SESSION['message_type'] = "danger"; header("Location: index.php"); exit();
}

// Initial Load: Fetch first page of results
$items_per_page = 15;
$sql = "SELECT el.*, u.username 
        FROM email_logs el 
        JOIN users u ON el.user_id = u.id 
        ORDER BY el.sent_at DESC 
        LIMIT :limit";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$initial_logs = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-mail-bulk text-primary me-2"></i>System Email Logs</h2>
            <p class="text-muted m-0 small">A record of all emails sent by all users.</p>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($initial_logs)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="fas fa-envelope-open-text fa-3x mb-3 opacity-25"></i>
                    <p>No emails have been sent by any user yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="log-table">
                        <thead class="bg-light">
                            <tr class="text-uppercase small text-muted">
                                <th class="ps-4 py-3">Date</th>
                                <th>Sender</th>
                                <th>To</th>
                                <th>Subject</th>
                                <th class="text-center">Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="log-table-body">
                            <?php foreach ($initial_logs as $log): ?>
                                <tr>
                                    <td class="ps-4 small text-secondary">
                                        <?= date('M j, Y - H:i', strtotime($log['sent_at'])) ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['username']) ?></span></td>
                                    <td class="small text-muted text-truncate" style="max-width: 200px;"><?= htmlspecialchars($log['sent_to']) ?></td>
                                    <td>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 250px;">
                                            <?= htmlspecialchars($log['subject']) ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($log['status'] === 'success'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Success</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-light border view-details-btn" 
                                                data-to="<?= htmlspecialchars($log['sent_to']) ?>"
                                                data-from="<?= htmlspecialchars($log['sent_from']) ?>"
                                                data-subject="<?= htmlspecialchars($log['subject']) ?>"
                                                data-attachments="<?= htmlspecialchars($log['attachments'] ?: 'None') ?>"
                                                data-error="<?= htmlspecialchars($log['error_message']) ?>">
                                            <i class="fas fa-eye me-1"></i> View
                                        </button>
                                        <div class="log-body-content d-none"><?= $log['body'] ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Load More Button Area -->
                <div id="load-more-container" class="text-center p-3 border-top bg-light">
                    <button id="load-more-btn" class="btn btn-sm btn-outline-secondary">Load More</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL POPUP -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold" id="modal-subject">Email Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="small text-muted"><strong>From:</strong> <span id="modal-from"></span></div>
                <div class="small text-muted mb-2"><strong>To:</strong> <span id="modal-to"></span></div>
                <div class="small text-muted mb-3"><strong>Attachments:</strong> <span id="modal-attachments"></span></div>
                <div id="modal-error-section" class="alert alert-danger d-none">
                    <strong>Error:</strong> <pre id="modal-error" class="mb-0 small"></pre>
                </div>
                <hr>
                <iframe id="modal-body-content" style="width: 100%; height: 400px; border: 1px solid #eee; border-radius: 8px;"></iframe>
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- LOAD MORE ---
    const loadMoreBtn = document.getElementById('load-more-btn');
    const tableBody = document.getElementById('log-table-body');
    let currentPage = 1;

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            currentPage++;
            loadMoreBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            // IMPORTANT: The 'scope=admin' tells the fetch script to get ALL logs
            fetch(`./actions/fetch_email_history.php?page=${currentPage}&scope=admin`)
                .then(response => response.text())
                .then(html => {
                    if (html.trim()) {
                        tableBody.insertAdjacentHTML('beforeend', html);
                        loadMoreBtn.innerHTML = 'Load More';
                    } else {
                        loadMoreBtn.parentElement.innerHTML = '<span class="text-muted small">End of logs.</span>';
                    }
                });
        });
    }

    // --- MODAL ---
    const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
    const logTable = document.getElementById('log-table');

    if (logTable) {
        logTable.addEventListener('click', function(event) {
            const button = event.target.closest('.view-details-btn');
            if (button) {
                document.getElementById('modal-subject').textContent = button.dataset.subject;
                document.getElementById('modal-from').textContent = button.dataset.from;
                document.getElementById('modal-to').textContent = button.dataset.to;
                document.getElementById('modal-attachments').textContent = button.dataset.attachments;
                
                const errorSection = document.getElementById('modal-error-section');
                if (button.dataset.error) {
                    document.getElementById('modal-error').textContent = button.dataset.error;
                    errorSection.classList.remove('d-none');
                } else {
                    errorSection.classList.add('d-none');
                }
                
                const bodyHtml = button.closest('td').querySelector('.log-body-content').innerHTML;
                document.getElementById('modal-body-content').srcdoc = bodyHtml;
                
                detailsModal.show();
            }
        });
    }
});
</script>

<style>
body { background-color: #f8f9fa; }
.table-hover tbody tr:hover { background-color: #f8f9fa; }
.bg-success-subtle { background-color: #d1fae5 !important; }
.bg-danger-subtle { background-color: #fee2e2 !important; }
</style>

<?php require_once 'includes/footer.php'; ?>
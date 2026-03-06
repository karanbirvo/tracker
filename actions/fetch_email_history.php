<?php
require_once '../includes/functions.php';
requireLogin();

// --- Configuration ---
$items_per_page = 15; // How many items to load per request

// --- Get and Validate Input ---
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if ($page === false || $page < 1) {
    $page = 1; // Default to page 1 if input is invalid
}

// Check if this is for the admin view or personal view
$is_admin = (isset($_GET['scope']) && $_GET['scope'] === 'admin' && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

// --- Database Query ---
$offset = ($page - 1) * $items_per_page;

if ($is_admin) {
    // Admin query: fetch from all users, joining to get username
    $sql = "SELECT el.*, u.username 
            FROM email_logs el
            JOIN users u ON el.user_id = u.id
            ORDER BY el.sent_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
} else {
    // User query: fetch only for the logged-in user
    $sql = "SELECT * FROM email_logs WHERE user_id = :user_id ORDER BY sent_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
}

$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();


// --- Generate and Output HTML ---
// If no logs are found, this will output nothing, which our JavaScript will detect.
foreach ($logs as $log) {
?>
    <tr>
        <td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['sent_at']))) ?></td>
        <?php if ($is_admin): // Show username column only for admins ?>
            <td><?= htmlspecialchars($log['username']) ?></td>
        <?php endif; ?>
        <td><?= htmlspecialchars($log['sent_to']) ?></td>
        <td><?= htmlspecialchars($log['subject']) ?></td>
        <td>
            <?php if ($log['status'] === 'success'): ?>
                <span style="color: green; font-weight: bold;">Success</span>
            <?php else: ?>
                <span style="color: red; font-weight: bold;">Failed</span>
            <?php endif; ?>
        </td>
        <td>
            <button class="button-link view-details-btn" 
                    data-to="<?= htmlspecialchars($log['sent_to']) ?>"
                    data-from="<?= htmlspecialchars($log['sent_from']) ?>"
                    data-subject="<?= htmlspecialchars($log['subject']) ?>"
                    data-attachments="<?= !empty($log['attachments']) ? htmlspecialchars($log['attachments']) : 'None' ?>"
                    data-error="<?= htmlspecialchars($log['error_message']) ?>">
                <i class="fas fa-eye"></i> View Details
            </button>
            <div class="log-body-content" style="display: none;"><?= $log['body'] ?></div>
        </td>
    </tr>
<?php
} // End foreach
?>
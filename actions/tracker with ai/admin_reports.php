<?php
require_once 'includes/header.php'; // This includes db.php and functions.php
requireLogin();

// Admin Access Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access Denied: You do not have administrative privileges to view this page.";
    $_SESSION['message_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Fetch all users for the dropdown
$stmtUsers = $pdo->query("SELECT id, username FROM users ORDER BY username ASC");
$allUsers = $stmtUsers->fetchAll();

$selectedUserId = null;
$reportEntries = [];
$userHasEntries = false;
$selectedUsername = '';

// Default date range: current month
$defaultEndDate = date('Y-m-d');
$defaultStartDate = date('Y-m-01');

$filterDateFrom = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : $defaultStartDate;
$filterDateTo = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : $defaultEndDate;

// Validate GET parameters
if (isset($_GET['user_id_selected']) && !empty($_GET['user_id_selected']) && is_numeric($_GET['user_id_selected'])) {
    $selectedUserId = (int)$_GET['user_id_selected'];

    // Validate date formats from GET
    $dFrom = DateTime::createFromFormat('Y-m-d', $filterDateFrom);
    if (!$dFrom || $dFrom->format('Y-m-d') !== $filterDateFrom) $filterDateFrom = $defaultStartDate;
    $dTo = DateTime::createFromFormat('Y-m-d', $filterDateTo);
    if (!$dTo || $dTo->format('Y-m-d') !== $filterDateTo) $filterDateTo = $defaultEndDate;

    // Fetch entries for the selected user and date range
    $stmt = $pdo->prepare("SELECT te.*, u.username FROM time_entries te JOIN users u ON te.user_id = u.id WHERE te.user_id = :user_id AND te.entry_date BETWEEN :date_from AND :date_to ORDER BY te.entry_date DESC, te.start_time DESC");
    $stmt->execute(['user_id' => $selectedUserId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]);
    $reportEntries = $stmt->fetchAll();
    $userHasEntries = !empty($reportEntries);
    if($userHasEntries){
        $selectedUsername = $reportEntries[0]['username']; // Get username from the first entry
    } else {
        // Fetch username even if no entries, to display in "no entries found" message
        $stmtUserOnly = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmtUserOnly->execute([$selectedUserId]);
        $user = $stmtUserOnly->fetch();
        if($user) $selectedUsername = $user['username'];
    }
}
?>

<h1><i class="fas fa-user-shield"></i> Admin - User Reports</h1>

<form method="GET" action="admin_reports.php" class="form-group card" style="padding: 20px; background-color: #f8f9fa; border-radius: var(--border-radius-lg); margin-bottom: 25px;">
    <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end;">
        <div class="form-group" style="flex-grow:1; min-width:200px;">
            <label for="user_id_selected" style="font-weight: 500; display:block; margin-bottom:5px;">Select User:</label>
            <select name="user_id_selected" id="user_id_selected" required class="form-control" style="padding: 10px;">
                <option value="">-- Select User --</option>
                <?php foreach ($allUsers as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= ($selectedUserId == $user['id'] ? 'selected' : '') ?>>
                        <?= htmlspecialchars($user['username']) ?> (ID: <?= $user['id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="date_from_admin" style="font-weight: 500; display:block; margin-bottom:5px;">From Date:</label>
            <input type="date" name="date_from" id="date_from_admin" value="<?= htmlspecialchars($filterDateFrom) ?>" class="form-control" style="padding: 9px;">
        </div>
        <div class="form-group">
            <label for="date_to_admin" style="font-weight: 500; display:block; margin-bottom:5px;">To Date:</label>
            <input type="date" name="date_to" id="date_to_admin" value="<?= htmlspecialchars($filterDateTo) ?>" class="form-control" style="padding: 9px;">
        </div>
        <div class="form-group">
             <button type="submit" class="button button-primary" style="padding-top: 10px; padding-bottom: 10px;"><i class="fas fa-search"></i> View Report</button>
        </div>
    </div>
</form>

<?php if ($selectedUserId): // Only show report section if a user is selected ?>
    <?php if (!$userHasEntries && !empty($selectedUsername)): ?>
        <div class="alert alert-info" style="margin-top: 20px;"><i class="fas fa-info-circle"></i> No time entries found for <strong><?= htmlspecialchars($selectedUsername) ?></strong> for the selected period.</div>
    <?php elseif (!$userHasEntries && empty($selectedUsername) && $selectedUserId != ''): // User selected but not found or no username for them (edge case) ?>
         <div class="alert alert-warning" style="margin-top:20px;"><i class="fas fa-exclamation-triangle"></i> Selected user (ID: <?= htmlspecialchars($selectedUserId)?>) not found or no entries for the period.</div>
    <?php elseif ($userHasEntries): ?>
        <div class="card" style="margin-top: 20px;">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #eee; padding-bottom:10px; margin-bottom:15px;">
                <h3>Report for: <?= htmlspecialchars($selectedUsername) ?></h3>
                <span style="font-size:0.9em; color:#555;">(<?= htmlspecialchars($filterDateFrom) ?> to <?= htmlspecialchars($filterDateTo) ?>)</span>
            </div>
            <div style="margin-bottom: 15px; padding-top:10px;">
                <form action="actions/download_report.php" method="POST" style="display:inline-block; margin-right:10px;">
                    <input type="hidden" name="target_user_id" value="<?= $selectedUserId ?>">
                    <input type="hidden" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
                    <input type="hidden" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
                    <input type="hidden" name="format" value="csv">
                    <button type="submit" class="button" style="background-color: var(--color-accent, #28a745); color: white;"><i class="fas fa-file-csv"></i> Download as CSV</button>
                </form>
                <form action="actions/download_report.php" method="POST" style="display:inline-block;">
                    <input type="hidden" name="target_user_id" value="<?= $selectedUserId ?>">
                    <input type="hidden" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
                    <input type="hidden" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
                    <input type="hidden" name="format" value="pdf">
                    <button type="submit" class="button" style="background-color: var(--color-danger, #dc3545); color: white;"><i class="fas fa-file-pdf"></i> Download as PDF</button>
                </form>
            </div>

            <table class="reports-table">
                <thead>
                    <tr>
                        <th>Entry ID</th>
                        <th>Date</th>
                        <th>Task Name</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Duration</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grandTotalSeconds = 0;
                    foreach ($reportEntries as $entry):
                        $durationStr = "Ongoing";
                        $currentEntrySeconds = 0;
                        if ($entry['end_time']) {
                            try { // Added try-catch for safety with DateTime
                                $start = new DateTime($entry['start_time']);
                                $end = new DateTime($entry['end_time']);
                                $interval = $start->diff($end);
                                $durationStr = $interval->format('%Hh %Im %Ss');
                                $currentEntrySeconds = ($end->getTimestamp() - $start->getTimestamp());
                                $grandTotalSeconds += $currentEntrySeconds;
                            } catch (Exception $e) {
                                $durationStr = "Error in duration";
                            }
                        } else {
                            // Calculate duration for ongoing task using your formatDuration function
                            $durationStr = formatDuration($entry['start_time'], null) . " (Active)";
                        }
                    ?>
                    <tr>
                        <td><?= $entry['id'] ?></td>
                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($entry['entry_date']))) ?></td>
                        <td><?= htmlspecialchars($entry['task_name']) ?></td>
                        <td><?= date('H:i:s', strtotime($entry['start_time'])) ?></td>
                        <td><?= $entry['end_time'] ? date('H:i:s', strtotime($entry['end_time'])) : 'Active' ?></td>
                        <td><?= $durationStr ?></td>
                        <td style="white-space: nowrap;">
                            <a href="edit_entry.php?id=<?= $entry['id'] ?>" class="button-link button" style="font-size:0.8em; padding: 5px 8px; background-color: var(--color-primary-light, #0d6efd); color:white; margin-right: 5px; text-decoration:none;"><i class="fas fa-edit"></i> Edit</a>
                            <form action="actions/delete_entry.php" method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this time entry (ID: <?= $entry['id'] ?>)? This action cannot be undone.');">
                                <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                <input type="hidden" name="user_id_selected" value="<?= $selectedUserId ?>">
                                <input type="hidden" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
                                <input type="hidden" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
                                <button type="submit" class="button" style="font-size:0.8em; padding: 5px 8px; background-color: var(--color-danger, #dc3545); color:white;"><i class="fas fa-trash-alt"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($grandTotalSeconds > 0 || count($reportEntries) > 0) : ?>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align:right; font-weight:bold;">Total Logged Time for Period:</td>
                        <td style="font-weight:bold;">
                            <?php
                                $hours = floor($grandTotalSeconds / 3600);
                                $minutes = floor(($grandTotalSeconds % 3600) / 60);
                                $seconds = $grandTotalSeconds % 60;
                                echo sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);
                            ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="alert alert-info" style="margin-top:20px;"><i class="fas fa-info-circle"></i> Please select a user and date range to view their report.</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
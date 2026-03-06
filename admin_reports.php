<?php
require_once 'includes/functions.php';
requireLogin();

// Admin Access Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access Denied.";
    $_SESSION['message_type'] = "danger";
    header("Location: index.php");
    exit();
}
// 1. Permission Check (Must be able to view ALL data to use this page)
if (!hasPermission('perm_view_all')) {
    $_SESSION['message'] = "Access Denied: You cannot view other users' reports.";
    $_SESSION['message_type'] = "danger";
    header("Location: index.php");
    exit();
}
// 1. Fetch Users
$stmtUsers = $pdo->query("SELECT id, username FROM users ORDER BY username ASC");
$allUsers = $stmtUsers->fetchAll();

// 2. Filters
$selectedUserId = $_GET['user_id_selected'] ?? '';
$fDateFrom = $_GET['date_from'] ?? date('Y-m-01');
$fDateTo   = $_GET['date_to']   ?? date('Y-m-d');
$fSearch   = trim($_GET['search'] ?? '');

$reportEntries = [];
$selectedUsername = '';

// 3. Query
if (!empty($selectedUserId) && is_numeric($selectedUserId)) {
    $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmtUser->execute([$selectedUserId]);
    $userRow = $stmtUser->fetch();
    $selectedUsername = $userRow ? $userRow['username'] : 'Unknown User';

    $sql = "
    SELECT combined.*, p.project_name 
    FROM (
        SELECT id, task_name, description, start_time, end_time, project_id, 'tracker' as type 
        FROM time_entries WHERE user_id = :uid1
        UNION ALL
        SELECT id, task_name, description, start_time, end_time, project_id, 'manual' as type 
        FROM manual_entries WHERE user_id = :uid2
    ) AS combined
    LEFT JOIN projects p ON combined.project_id = p.id
    WHERE combined.start_time >= :start_dt AND combined.start_time <= :end_dt
    ";

    $params = [
        'uid1' => $selectedUserId,
        'uid2' => $selectedUserId,
        'start_dt' => "$fDateFrom 00:00:00",
        'end_dt' => "$fDateTo 23:59:59"
    ];

    if (!empty($fSearch)) {
        $sql .= " AND (combined.task_name LIKE :search OR combined.description LIKE :search OR p.project_name LIKE :search)";
        $params['search'] = "%$fSearch%";
    }

    $sql .= " ORDER BY combined.start_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportEntries = $stmt->fetchAll();
}

require_once 'includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-user-shield text-primary me-2"></i>Admin Reports</h2>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4 bg-white rounded-3">
        <div class="card-body p-4">
            <form method="GET" action="admin_reports.php" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="small fw-bold text-muted">User</label>
                    <select name="user_id_selected" class="form-select" required>
                        <option value="">-- Choose User --</option>
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($selectedUserId == $u['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['username']) ?> (ID: <?= $u['id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><label class="small fw-bold text-muted">From</label><input type="date" name="date_from" value="<?= htmlspecialchars($fDateFrom) ?>" class="form-control"></div>
                <div class="col-md-2"><label class="small fw-bold text-muted">To</label><input type="date" name="date_to" value="<?= htmlspecialchars($fDateTo) ?>" class="form-control"></div>
                <div class="col-md-3"><label class="small fw-bold text-muted">Search</label><input type="text" name="search" value="<?= htmlspecialchars($fSearch) ?>" class="form-control" placeholder="..."></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> View</button></div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <?php if ($selectedUserId): ?>
        <?php if (empty($reportEntries)): ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center"><p class="text-muted">No entries found.</p></div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                    <div><strong class="text-primary"><?= htmlspecialchars($selectedUsername) ?></strong> <span class="text-muted small ms-2">(<?= count($reportEntries) ?> entries)</span></div>
                    
                    <!-- Downloads -->
                    <div class="d-flex gap-2">
                        <form action="actions/download_report.php" method="POST" target="_blank">
                            <input type="hidden" name="target_user_id" value="<?= $selectedUserId ?>">
                            <input type="hidden" name="date_from" value="<?= $fDateFrom ?>">
                            <input type="hidden" name="date_to" value="<?= $fDateTo ?>">
                            <button type="submit" name="format" value="csv" class="btn btn-sm btn-outline-success"><i class="fas fa-download"></i> CSV</button>
                            <button type="submit" name="format" value="pdf" class="btn btn-sm btn-outline-danger"><i class="fas fa-download"></i> PDF</button>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="text-uppercase small text-muted">
                                <th class="ps-4 py-3">Type</th>
                                <th>Date</th>
                                <th>Project</th>
                                <th width="35%">Task</th>
                                <th>Range</th>
                                <th>Duration</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalSeconds = 0;
                            foreach ($reportEntries as $entry):
                                $durationStr = "Running...";
                                if ($entry['end_time']) {
                                    $start = new DateTime($entry['start_time']);
                                    $end = new DateTime($entry['end_time']);
                                    $diff = $start->diff($end);
                                    $durationStr = $diff->format('%Hh %Im %Ss');
                                    $totalSeconds += ($end->getTimestamp() - $start->getTimestamp());
                                }
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <?php if ($entry['type'] === 'tracker'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">TRACKER</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">MANUAL</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-secondary"><?= date('M j', strtotime($entry['start_time'])) ?></td>
                                <td><?= htmlspecialchars($entry['project_name'] ?? '-') ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($entry['task_name']) ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 250px;"><?= htmlspecialchars($entry['description'] ?? '') ?></div>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= date('H:i', strtotime($entry['start_time'])) ?> - <?= $entry['end_time'] ? date('H:i', strtotime($entry['end_time'])) : '...' ?>
                                </td>
                                <td class="fw-medium font-monospace"><?= $durationStr ?></td>
                                <td class="text-end pe-4">
                                    
                                    <!-- EDIT BUTTON -->
                                    <a href="edit_entry.php?id=<?= $entry['id'] ?>&type=<?= $entry['type'] ?>" class="btn btn-sm btn-light text-primary border me-1" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <!-- DELETE BUTTON -->
                                    <form action="actions/delete_entry.php" method="POST" onsubmit="return confirm('Delete this entry?');" class="d-inline">
                                        <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                        <input type="hidden" name="entry_type" value="<?= $entry['type'] ?>"> <!-- Updated to support types -->
                                        <input type="hidden" name="user_id_selected" value="<?= $selectedUserId ?>">
                                        <button type="submit" class="btn btn-sm btn-light text-danger border" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-light border-top">
                            <tr>
                                <td colspan="5" class="text-end py-3 fw-bold text-uppercase text-secondary">Total Logged Time</td>
                                <td class="fw-bold fs-6 text-dark font-monospace">
                                    <?php
                                        $h = floor($totalSeconds / 3600);
                                        $m = floor(($totalSeconds % 3600) / 60);
                                        $s = $totalSeconds % 60;
                                        echo sprintf('%02dh %02dm %02ds', $h, $m, $s);
                                    ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
body { background-color: #f8f9fa; }
.bg-success-subtle { background-color: #d1fae5 !important; }
.bg-danger-subtle { background-color: #fee2e2 !important; }
</style>

<?php require_once 'includes/footer.php'; ?>
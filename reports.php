<?php
require_once 'includes/functions.php';
requireLogin();

// 1. Permission Check
if (!hasPermission('perm_reports')) {
    require_once 'includes/header.php';
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access Denied: You do not have permission to view reports.</div></div>";
    require_once 'includes/footer.php';
    exit();
}

$userId = $_SESSION['user_id'];

// --- Filter Parameters ---
$fDateFrom = $_GET['date_from'] ?? date('Y-m-d');
$fDateTo   = $_GET['date_to']   ?? date('Y-m-d');
$fProject  = $_GET['project_id'] ?? '';
$fSearch   = trim($_GET['search'] ?? '');

// --- Fetch Projects for Filter ---
$stmtProjs = $pdo->prepare("SELECT id, project_name FROM projects WHERE user_id = ? ORDER BY project_name ASC");
$stmtProjs->execute([$userId]);
$allProjects = $stmtProjs->fetchAll();

// --- Main Query ---
$sql = "SELECT combined.*, p.project_name, p.client_name, p.project_url FROM (SELECT id, task_name, description, start_time, end_time, project_id, 'tracker' as type FROM time_entries WHERE user_id = :uid1 UNION ALL SELECT id, task_name, description, start_time, end_time, project_id, 'manual' as type FROM manual_entries WHERE user_id = :uid2) AS combined LEFT JOIN projects p ON combined.project_id = p.id WHERE combined.start_time >= :start_dt AND combined.start_time <= :end_dt";
$params = ['uid1' => $userId, 'uid2' => $userId, 'start_dt' => "$fDateFrom 00:00:00", 'end_dt' => "$fDateTo 23:59:59"];

if (!empty($fProject)) { $sql .= " AND combined.project_id = :pid"; $params['pid'] = $fProject; }
if (!empty($fSearch)) { $sql .= " AND (combined.task_name LIKE :search OR p.project_name LIKE :search OR combined.description LIKE :search)"; $params['search'] = "%$fSearch%"; }
$sql .= " ORDER BY combined.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reportEntries = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- === HEADER === -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-chart-pie text-primary me-2"></i>My Reports</h2>
            <p class="text-muted m-0 small">Analyze your productivity across all tasks.</p>
        </div>
        <!-- Action Buttons -->
        <?php if (!empty($reportEntries)): ?>
        <div class="d-flex gap-2">
            <form action="actions/download_report.php" method="POST" target="_blank">
                <input type="hidden" name="date_from" value="<?= $fDateFrom ?>"><input type="hidden" name="date_to" value="<?= $fDateTo ?>">
                <button type="submit" name="format" value="csv" class="btn btn-outline-success btn-sm"><i class="fas fa-file-csv me-1"></i> CSV</button>
                <button type="submit" name="format" value="pdf" class="btn btn-outline-danger btn-sm"><i class="fas fa-file-pdf me-1"></i> PDF</button>
            </form>
            <form action="actions/send_eod_report.php" method="POST">
                <input type="hidden" name="date_from" value="<?= $fDateFrom ?>"><input type="hidden" name="date_to" value="<?= $fDateTo ?>">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane me-1"></i> Email Report</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- === FILTER BAR === -->
    <div class="card border-0 shadow-sm mb-4 bg-light">
        <div class="card-body p-3">
            <form method="GET" action="reports.php" class="row g-2 align-items-end">
                <div class="col-md-2"><label class="small fw-bold text-muted">From</label><input type="date" name="date_from" value="<?= $fDateFrom ?>" class="form-control form-control-sm border-0 shadow-sm"></div>
                <div class="col-md-2"><label class="small fw-bold text-muted">To</label><input type="date" name="date_to" value="<?= $fDateTo ?>" class="form-control form-control-sm border-0 shadow-sm"></div>
                <div class="col-md-3"><label class="small fw-bold text-muted">Project</label><select name="project_id" class="form-select form-select-sm border-0 shadow-sm"><option value="">All Projects</option><?php foreach ($allProjects as $p): ?><option value="<?= $p['id'] ?>" <?= ($fProject == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['project_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="small fw-bold text-muted">Search</label><input type="text" name="search" value="<?= htmlspecialchars($fSearch) ?>" class="form-control form-control-sm border-0 shadow-sm" placeholder="Task, Client..."></div>
                <div class="col-md-2 d-grid"><button type="submit" class="btn btn-dark btn-sm shadow-sm"><i class="fas fa-filter me-1"></i> Filter</button></div>
            </form>
        </div>
    </div>

    <!-- === RESULTS TABLE === -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <?php if (empty($reportEntries)): ?>
                <div class="p-5 text-center text-muted"><i class="fas fa-search fa-3x mb-3 opacity-25"></i><p>No entries found for this period.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light border-bottom">
                            <tr class="text-uppercase small text-muted">
                                <th class="ps-4 py-3">Type</th>
                                <th>Date</th>
                                <th>Project</th>
                                <th width="35%">Task & Description</th>
                                <th>Time Range</th>
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
                                    $start = new DateTime($entry['start_time']); $end = new DateTime($entry['end_time']);
                                    $diff = $start->diff($end); $durationStr = $diff->format('%Hh %Im %Ss');
                                    $totalSeconds += ($end->getTimestamp() - $start->getTimestamp());
                                } else {
                                    $start = new DateTime($entry['start_time']); $now = new DateTime();
                                    $diff = $start->diff($now); $durationStr = "<span class='text-primary fw-bold'>Running... (" . $diff->format('%H:%I') . ")</span>";
                                }
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <?php if ($entry['type'] === 'tracker'): ?><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">TRACKER</span><?php else: ?><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">MANUAL</span><?php endif; ?>
                                </td>
                                <td class="small text-secondary"><?= date('M j, Y', strtotime($entry['start_time'])) ?></td>
                                <td>
                                    <?php if ($entry['project_name']): ?>
                                        <span class="badge bg-light text-dark border fw-normal"><?= htmlspecialchars($entry['project_name']) ?></span>
                                        <?php if ($entry['project_url']): ?><a href="<?= htmlspecialchars($entry['project_url']) ?>" target="_blank" class="text-secondary ms-1"><i class="fas fa-external-link-alt fa-xs"></i></a><?php endif; ?>
                                    <?php else: ?><span class="text-muted small">-</span><?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($entry['task_name']) ?></div>
                                    <?php if ($entry['description']): ?><div class="small text-muted" style="max-width: 350px;" title="<?= htmlspecialchars($entry['description']) ?>"><?= htmlspecialchars($entry['description']) ?></div><?php endif; ?>
                                </td>
                                <td class="small text-muted font-monospace"><?= date('H:i', strtotime($entry['start_time'])) ?> - <?= $entry['end_time'] ? date('H:i', strtotime($entry['end_time'])) : '...' ?></td>
                                <td class="fw-medium font-monospace"><?= $durationStr ?></td>
                                <td class="text-end pe-4">
                                    <?php if (hasPermission('perm_edit')): ?>
                                        <button class="btn btn-sm btn-light border edit-btn" data-bs-toggle="modal" data-bs-target="#editEntryModal" data-id="<?= $entry['id'] ?>" data-type="<?= $entry['type'] ?>"><i class="fas fa-edit text-primary"></i></button>
                                    <?php endif; ?>
                                    <?php if (hasPermission('perm_delete')): ?>
                                        <form action="actions/delete_entry.php" method="POST" class="d-inline" onsubmit="return confirm('Delete entry?');">
                                            <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>"><input type="hidden" name="entry_type" value="<?= $entry['type'] ?>">
                                            <button type="submit" class="btn btn-sm btn-light border"><i class="fas fa-trash-alt text-danger"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-light border-top">
                            <tr>
                                <td colspan="6" class="text-end py-3 fw-bold text-uppercase text-secondary">Total Duration</td>
                                <td class="text-end pe-4 fw-bold fs-5 text-dark font-monospace">
                                    <?php $h = floor($totalSeconds / 3600); $m = floor(($totalSeconds % 3600) / 60); $s = $totalSeconds % 60; echo sprintf('%02dh %02dm %02ds', $h, $m, $s); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
body { background-color: #f8f9fa; }
.form-control:focus, .form-select:focus { box-shadow: none; border-color: #2D5A95; }
.bg-success-subtle { background-color: #d1fae5 !important; }
.bg-danger-subtle { background-color: #fee2e2 !important; }
</style>

<!-- *** BOOTSTRAP JS - THIS WAS THE MISSING PIECE *** -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- INCLUDE MODAL HTML -->
<?php
$allProjectsForModal = $allProjects;
require_once 'includes/partials/edit_entry_modal.php'; 
?>

<!-- INCLUDE MODAL JAVASCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editEntryModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const entryId = button.dataset.id;
            const entryType = button.dataset.type;

            document.getElementById('editModalTitle').innerText = `Edit Entry #${entryId}`;
            document.getElementById('edit_entry_id').value = entryId;
            document.getElementById('edit_current_type').value = entryType;

            // Fetch and populate
            fetch(`api/get_entry_details.php?id=${entryId}&type=${entryType}`)
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        const data = res.data;
                        const startTime = new Date(data.start_time);
                        const endTime = data.end_time ? new Date(data.end_time) : null;
                        
                        document.getElementById('edit_entry_type').value = entryType;
                        document.getElementById('edit_project_id').value = data.project_id || '';
                        document.getElementById('edit_task_name').value = data.task_name;
                        document.getElementById('edit_description').value = data.description || '';
                        document.getElementById('edit_entry_date').value = startTime.toISOString().split('T')[0];
                        document.getElementById('edit_start_time').value = startTime.toTimeString().split(' ')[0];
                        document.getElementById('edit_end_time').value = endTime ? endTime.toTimeString().split(' ')[0] : '';
                    } else {
                        alert('Error: ' + res.message);
                    }
                });
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
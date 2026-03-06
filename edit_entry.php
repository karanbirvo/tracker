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
// 1. Permission Check
if (!hasPermission('perm_edit')) {
    $_SESSION['message'] = "Access Denied: You do not have permission to edit entries.";
    $_SESSION['message_type'] = "danger";
    header("Location: index.php");
    exit();
}

$entryId = (int)($_GET['id'] ?? 0);
$currentType = $_GET['type'] ?? 'tracker'; // 'tracker' or 'manual'
$tableName = ($currentType === 'manual') ? 'manual_entries' : 'time_entries';

// --- 1. Fetch Entry Data ---
$stmt = $pdo->prepare("SELECT te.*, u.username FROM $tableName te JOIN users u ON te.user_id = u.id WHERE te.id = ?");
$stmt->execute([$entryId]);
$entry = $stmt->fetch();

if (!$entry) {
    $_SESSION['message'] = "Entry not found.";
    $_SESSION['message_type'] = "danger";
    header("Location: admin_reports.php");
    exit();
}

// --- 2. Fetch Projects (For Dropdown) ---
$stmtProj = $pdo->prepare("SELECT id, project_name FROM projects WHERE user_id = ? ORDER BY project_name ASC");
$stmtProj->execute([$entry['user_id']]);
$projects = $stmtProj->fetchAll();

// --- 3. Handle Form Submit ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newTaskName = trim($_POST['task_name']);
    $newDesc = trim($_POST['description']);
    $newProjectId = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $newDate = $_POST['entry_date'];
    $newStartTime = $_POST['start_time'];
    $newEndTime = $_POST['end_time'];
    $newType = $_POST['entry_type']; // 'tracker' or 'manual'

    // Combine Date & Time
    $startDateTime = "$newDate $newStartTime";
    $endDateTime = !empty($newEndTime) ? "$newDate $newEndTime" : null;

    // Handle overnight logic
    if ($endDateTime && $newEndTime < $newStartTime) {
        $endDateTime = date('Y-m-d H:i:s', strtotime("$newDate $newEndTime +1 day"));
    }

    try {
        $pdo->beginTransaction();

        // SCENARIO A: Type stayed the same (Simple Update)
        if ($newType === $currentType) {
            $sql = "UPDATE $tableName SET task_name=?, description=?, start_time=?, end_time=?, entry_date=?, project_id=? WHERE id=?";
            $pdo->prepare($sql)->execute([$newTaskName, $newDesc, $startDateTime, $endDateTime, $newDate, $newProjectId, $entryId]);
        } 
        // SCENARIO B: Type Changed (Move Data)
        else {
            $targetTable = ($newType === 'manual') ? 'manual_entries' : 'time_entries';
            
            // 1. Insert into new table
            $ins = "INSERT INTO $targetTable (user_id, project_id, task_name, description, start_time, end_time, entry_date) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($ins)->execute([$entry['user_id'], $newProjectId, $newTaskName, $newDesc, $startDateTime, $endDateTime, $newDate]);
            
            // 2. Delete from old table
            $del = "DELETE FROM $tableName WHERE id = ?";
            $pdo->prepare($del)->execute([$entryId]);
        }

        $pdo->commit();
        $_SESSION['message'] = "Entry updated successfully.";
        $_SESSION['message_type'] = "success";
        header("Location: admin_reports.php?user_id_selected=" . $entry['user_id']);
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Database Error: " . $e->getMessage();
    }
}

// Prepare values for display
$valProject = $entry['project_id'];
$valTask = $entry['task_name'];
$valDesc = $entry['description'];
$valDate = date('Y-m-d', strtotime($entry['start_time']));
$valStart = date('H:i:s', strtotime($entry['start_time']));
$valEnd = $entry['end_time'] ? date('H:i:s', strtotime($entry['end_time'])) : '';

require_once 'includes/header.php';
?>

<div class="container mt-5 mb-5" style="max-width: 700px;">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <div class="card shadow rounded-4 border-0">
        <div class="card-header bg-white border-bottom p-4">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="m-0 fw-bold text-primary"><i class="fas fa-edit me-2"></i>Edit Entry</h4>
                <span class="badge bg-light text-dark border">ID: #<?= $entryId ?></span>
            </div>
            <p class="text-muted small m-0 mt-1">User: <strong><?= htmlspecialchars($entry['username']) ?></strong></p>
        </div>
        
        <div class="card-body p-4">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger rounded-3"><i class="fas fa-exclamation-triangle me-2"></i><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                
                <!-- 1. Type & Project -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase">Entry Type</label>
                        <select name="entry_type" class="form-select bg-light">
                            <option value="tracker" <?= $currentType == 'tracker' ? 'selected' : '' ?>>On Tracker (Green)</option>
                            <option value="manual" <?= $currentType == 'manual' ? 'selected' : '' ?>>Manual / Off (Red)</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-secondary text-uppercase">Project</label>
                        <select name="project_id" class="form-select">
                            <option value="">-- No Project --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $valProject == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 2. Task Name -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Task Name</label>
                    <input type="text" name="task_name" class="form-control fw-bold" value="<?= htmlspecialchars($valTask) ?>" required>
                </div>

                <!-- 3. Description -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($valDesc) ?></textarea>
                </div>

                <hr class="my-4 text-muted opacity-25">

                <!-- 4. Date & Times -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase">Date</label>
                        <input type="date" name="entry_date" class="form-control" value="<?= $valDate ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase">Start Time</label>
                        <input type="time" name="start_time" class="form-control" value="<?= $valStart ?>" step="1" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase">End Time</label>
                        <input type="time" name="end_time" class="form-control" value="<?= $valEnd ?>" step="1">
                        <div class="form-text small">Leave blank if currently running.</div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="d-flex justify-content-end gap-2 mt-5">
                    <a href="admin_reports.php?user_id_selected=<?= $entry['user_id'] ?>" class="btn btn-light border px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="fas fa-save me-2"></i>Update Entry</button>
                </div>

            </form>
        </div>
    </div>
</div>

<style>
/* Clean up form controls */
.form-control:focus, .form-select:focus {
    border-color: #2D5A95;
    box-shadow: 0 0 0 0.2rem rgba(45, 90, 149, 0.15);
}
.form-label {
    letter-spacing: 0.5px;
    font-size: 0.75rem;
}
</style>

<?php require_once 'includes/footer.php'; ?>
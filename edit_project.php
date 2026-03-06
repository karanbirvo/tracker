<?php
require_once 'includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];
$projectId = (int)($_GET['id'] ?? 0);

if ($projectId === 0) {
    header("Location: projects.php"); exit();
}

// Fetch the project to edit
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$projectId, $userId]);
$project = $stmt->fetch();

if (!$project) {
    $_SESSION['message'] = "Project not found or you don't have permission to edit it.";
    $_SESSION['message_type'] = "danger";
    header("Location: projects.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pName = trim($_POST['project_name']);
    $cName = trim($_POST['client_name']);
    $pUrl  = trim($_POST['project_url']);
    $pType = $_POST['project_type'];
    $pStatus = $_POST['status'];
    $desc = trim($_POST['description']);
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

    if (!empty($pName)) {
        $sql = "UPDATE projects SET 
                project_name = ?, client_name = ?, project_url = ?, 
                project_type = ?, status = ?, description = ?, deadline = ?
                WHERE id = ? AND user_id = ?";
        $pdo->prepare($sql)->execute([$pName, $cName, $pUrl, $pType, $pStatus, $desc, $deadline, $projectId, $userId]);
        
        $_SESSION['message'] = "Project updated successfully.";
        $_SESSION['message_type'] = "success";
        header("Location: projects.php");
        exit();
    }
}

require_once 'includes/header.php';
?>

<div class="container-fluid mt-4 mb-5" style="max-width: 800px;">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-edit text-primary me-2"></i>Edit Project</h2>
            <p class="text-muted m-0 small">Update the details for your project.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="edit_project.php?id=<?= $projectId ?>" method="POST">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted">PROJECT NAME</label>
                        <input type="text" name="project_name" class="form-control" value="<?= htmlspecialchars($project['project_name']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted">CLIENT NAME</label>
                        <input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($project['client_name'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted">PROJECT URL</label>
                        <input type="url" name="project_url" class="form-control" value="<?= htmlspecialchars($project['project_url'] ?? '') ?>" placeholder="https://...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">BILLING TYPE</label>
                        <select name="project_type" class="form-select">
                            <option value="Hourly" <?= ($project['project_type'] == 'Hourly') ? 'selected' : '' ?>>Hourly / Tracker</option>
                            <option value="Fixed" <?= ($project['project_type'] == 'Fixed') ? 'selected' : '' ?>>Fixed Price</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">STATUS</label>
                        <select name="status" class="form-select">
                            <option value="Active" <?= ($project['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Completed" <?= ($project['status'] == 'Completed') ? 'selected' : '' ?>>Completed</option>
                            <option value="Archived" <?= ($project['status'] == 'Archived') ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">DEADLINE</label>
                        <input type="date" name="deadline" class="form-control" value="<?= htmlspecialchars($project['deadline'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted">DESCRIPTION</label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                        <a href="projects.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="fas fa-save me-2"></i>Update Project</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>body { background-color: #f8f9fa; }</style>
<?php require_once 'includes/footer.php'; ?>
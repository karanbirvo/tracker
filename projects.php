<?php
require_once 'includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];

// --- Handle Form Submissions (No changes here) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    // ... (Your existing form processing logic remains the same)
    $pName = trim($_POST['project_name']);
    $cName = trim($_POST['client_name']);
    $pUrl  = trim($_POST['project_url']);
    $pType = $_POST['project_type'];
    $desc = trim($_POST['description']);
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

    if (!empty($pName)) {
        $stmt = $pdo->prepare("INSERT INTO projects (user_id, project_name, client_name, project_url, project_type, description, deadline) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $pName, $cName, $pUrl, $pType, $desc, $deadline]);
        $_SESSION['message'] = "Project created successfully.";
        $_SESSION['message_type'] = "success";
    }
    header("Location: projects.php");
    exit();
}

// --- Fetch BOTH Active and Archived Projects ---
$activeProjects = $pdo->prepare("SELECT * FROM projects WHERE user_id = ? AND status = 'Active' ORDER BY created_at DESC");
$activeProjects->execute([$userId]);

$archivedProjects = $pdo->prepare("SELECT * FROM projects WHERE user_id = ? AND status = 'Archived' ORDER BY created_at DESC");
$archivedProjects->execute([$userId]);

// Determine which tab to show
$currentTab = $_GET['tab'] ?? 'active';

require_once 'includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-briefcase text-primary me-2"></i>Project Management</h2>
            <p class="text-muted m-0 small">Organize your work by clients, projects, and deadlines.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Add New Project Form -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-secondary mb-4"><i class="fas fa-plus-circle me-2"></i>Create a New Project</h5>
                    <form action="projects.php" method="POST">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label small fw-bold text-muted">PROJECT NAME</label><input type="text" name="project_name" class="form-control" required></div>
                            <div class="col-12"><label class="form-label small fw-bold text-muted">CLIENT NAME</label><input type="text" name="client_name" class="form-control"></div>
                            <div class="col-12"><label class="form-label small fw-bold text-muted">PROJECT URL</label><input type="url" name="project_url" class="form-control" placeholder="https://..."></div>
                            <div class="col-md-6"><label class="form-label small fw-bold text-muted">BILLING TYPE</label><select name="project_type" class="form-select"><option value="Hourly">Hourly</option><option value="Fixed">Fixed</option></select></div>
                            <div class="col-md-6"><label class="form-label small fw-bold text-muted">DEADLINE</label><input type="date" name="deadline" class="form-control"></div>
                            <div class="col-12"><label class="form-label small fw-bold text-muted">DESCRIPTION</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                            <div class="col-12 mt-4"><button type="submit" name="add_project" class="btn btn-primary w-100 py-2 shadow-sm"><i class="fas fa-save me-2"></i>Save Project</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Project Lists with Tabs -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <!-- Tab Navigation -->
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <ul class="nav nav-tabs nav-tabs-flush" id="projectTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button id="archiveprobtn" class="nav-link <?= $currentTab == 'active' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#active-projects" type="button">Active Projects</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button id="archiveprobtn" class="nav-link <?= $currentTab == 'archived' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#archived-projects" type="button">Archived</button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-3">
                    <div class="tab-content" id="projectTabsContent">
                        
                        <!-- ACTIVE PROJECTS TAB -->
                        <div class="tab-pane fade <?= $currentTab == 'active' ? 'show active' : '' ?>" id="active-projects" role="tabpanel">
                            <div class="project-list-container">
                                <?php if ($activeProjects->rowCount() == 0): ?>
                                    <div class="text-center text-muted p-5"><i class="fas fa-folder-open fa-2x mb-2"></i><p>No active projects.</p></div>
                                <?php else: ?>
                                    <div class="vstack gap-2">
                                        <?php foreach ($activeProjects as $p): ?>
                                            <div class="project-item p-3 rounded-3 bg-light border">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <div class="fw-bold text-dark text-truncate"><?= htmlspecialchars($p['project_name']) ?></div>
                                                        <div class="small text-muted text-truncate"><?= htmlspecialchars($p['client_name'] ?? 'No client') ?></div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 ps-3 flex-shrink-0">
                                                        <a href="edit_project.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-light border" title="Edit"><i class="fas fa-edit text-primary"></i></a>
                                                        <a href="actions/archive_project.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-light border" title="Archive" onclick="return confirm('Archive?');"><i class="fas fa-archive text-danger"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ARCHIVED PROJECTS TAB -->
                        <div class="tab-pane fade <?= $currentTab == 'archived' ? 'show active' : '' ?>" id="archived-projects" role="tabpanel">
                            <div class="project-list-container">
                                <?php if ($archivedProjects->rowCount() == 0): ?>
                                    <div class="text-center text-muted p-5"><i class="fas fa-archive fa-2x mb-2"></i><p>No archived projects.</p></div>
                                <?php else: ?>
                                    <div class="vstack gap-2">
                                        <?php foreach ($archivedProjects as $p): ?>
                                            <div class="project-item p-3 rounded-3 bg-light border opacity-75">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <div class="fw-bold text-dark text-truncate"><?= htmlspecialchars($p['project_name']) ?></div>
                                                        <div class="small text-muted text-truncate"><?= htmlspecialchars($p['client_name'] ?? 'No client') ?></div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 ps-3 flex-shrink-0">
                                                        <a href="actions/restore_project.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-light border" title="Restore Project" onclick="return confirm('Restore this project?');"><i class="fas fa-undo text-success"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
body { background-color: #f8f9fa; }
.nav-tabs-flush .nav-link { border: 0; border-bottom: 2px solid transparent; color: #6c757d; }
.nav-tabs-flush .nav-link.active { color: #2D5A95; border-color: #2D5A95; font-weight: 600; }
.project-list-container { max-height: 500px; overflow-y: auto; padding-right: 10px; }
.project-item:hover { background-color: #e9ecef !important; }
</style>

<?php require_once 'includes/footer.php'; ?>
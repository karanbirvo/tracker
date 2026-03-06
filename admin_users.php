<?php
require_once 'includes/functions.php';
requireLogin();

// Access Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php"); exit();
}

// Fetch Users
$users = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();

require_once 'includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-users-cog text-primary me-2"></i>User Management</h2>
            <p class="text-muted m-0 small">Manage roles and fine-grained permissions.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom">
                    <tr class="text-uppercase small text-muted">
                        <th class="ps-4">User</th>
                        <th>Role</th>
                        <th class="text-center">Delete</th>
                        <th class="text-center">Edit</th>
                        <th class="text-center">AI Tool</th>
                        <th class="text-center">Email</th>
                        <th class="text-center">Reports</th>
                        <th class="text-center">View All</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php $isOwner = ($u['id'] == 1); ?>
                        <tr class="<?= $isOwner ? 'bg-warning-subtle' : '' ?>">
                            <!-- FORM WRAPPER -->
                            <form action="actions/update_user_permissions.php" method="POST">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                
                                <!-- User Info -->
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($u['username']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($u['email']) ?></div>
                                </td>

                                <!-- Role Selector -->
                                <td>
                                    <select name="user_role" class="form-select form-select-sm border-0 bg-white shadow-sm" <?= $isOwner ? 'disabled' : '' ?>>
                                        <option value="user" <?= $u['user_role'] == 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $u['user_role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <?php if($isOwner): ?><input type="hidden" name="user_role" value="admin"><?php endif; ?>
                                </td>

                                <!-- PERMISSION TOGGLES -->
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" name="perm_delete" <?= $u['perm_delete'] ? 'checked' : '' ?> <?= $isOwner ? 'disabled' : '' ?>>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" name="perm_edit" <?= $u['perm_edit'] ? 'checked' : '' ?> <?= $isOwner ? 'disabled' : '' ?>>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" name="perm_ai" <?= $u['perm_ai'] ? 'checked' : '' ?> <?= $isOwner ? 'disabled' : '' ?>>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" name="perm_email" <?= $u['perm_email'] ? 'checked' : '' ?> <?= $isOwner ? 'disabled' : '' ?>>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" name="perm_reports" <?= $u['perm_reports'] ? 'checked' : '' ?> <?= $isOwner ? 'disabled' : '' ?>>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input bg-danger border-danger" type="checkbox" name="perm_view_all" <?= $u['perm_view_all'] ? 'checked' : '' ?> <?= $isOwner ? 'disabled' : '' ?>>
                                    </div>
                                </td>

                                <!-- Save Button -->
                                <td class="text-end pe-4">
                                    <?php if(!$isOwner): ?>
                                        <button type="submit" class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-save"></i></button>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">OWNER</span>
                                    <?php endif; ?>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Make switches easier to click */
.form-check-input { cursor: pointer; width: 2.5em; height: 1.25em; }
.form-check-input:checked { background-color: #2D5A95; border-color: #2D5A95; }
/* Special color for the dangerous "View All" permission */
.form-check-input.bg-danger:checked { background-color: #dc3545; border-color: #dc3545; }
</style>

<?php require_once 'includes/footer.php'; ?>
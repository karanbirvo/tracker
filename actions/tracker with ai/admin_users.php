<?php
require_once 'includes/header.php';
requireLogin();
define('PROJECT_OWNER_USER_ID', 1); // Define the protected User ID at the top
// Admin Access Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access Denied: You do not have administrative privileges.";
    $_SESSION['message_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Handle role change POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id_to_change']) && isset($_POST['new_role'])) {
    $userIdToChange = (int)$_POST['user_id_to_change'];
    $newRole = $_POST['new_role'];


    // CORRECTED: 1. Prevent changing the role of the PROJECT_OWNER_USER_ID
    if ($userIdToChange === PROJECT_OWNER_USER_ID) {
        $_SESSION['message'] = " Karanbir Singh is the owner of that Product, Nobody have access to Change the User role of Karan";
        $_SESSION['message_type'] = "danger"; // Use "danger" for critical prohibitions
        header("Location: admin_users.php");
        exit();
    }


    // Prevent admin from demoting themselves if they are the only admin
    if ($userIdToChange === (int)$_SESSION['user_id'] && $newRole === 'user') {
        $stmtAdminCount = $pdo->query("SELECT COUNT(*) as admin_count FROM users WHERE user_role = 'admin'");
        $adminCount = $stmtAdminCount->fetchColumn();
        if ($adminCount <= 1) {
            $_SESSION['message'] = "Action Prohibited: Cannot demote the only remaining admin account.";
            $_SESSION['message_type'] = "warning";
            header("Location: admin_users.php");
            exit();
        }
    }



 

    if (in_array($newRole, ['user', 'admin'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET user_role = :new_role WHERE id = :user_id");
            $stmt->execute(['new_role' => $newRole, 'user_id' => $userIdToChange]);
            $_SESSION['message'] = "User role updated successfully.";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            // error_log("Error updating user role: " . $e->getMessage());
            $_SESSION['message'] = "Error: Could not update user role.";
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Invalid role specified.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: admin_users.php"); // Redirect to refresh the page and show message
    exit();
}

// Fetch all users to display
$stmtUsers = $pdo->query("SELECT id, username, user_role, created_at FROM users");   //previous query- SELECT id, username, user_role, created_at FROM users ORDER BY user_id ASC
$allUsers = $stmtUsers->fetchAll();
?>

<h1><i class="fas fa-users-cog"></i> Admin - Manage User Roles</h1>
<p>From here, you can promote users to 'Admin' or demote 'Admin' users to 'User'.</p>

<div class="card" style="margin-top: 20px;">
<?php if (empty($allUsers)): ?>
    <p class="alert alert-info">No users found in the system.</p>
<?php else: ?>
    <table class="reports-table"> <!-- Using reports-table for consistent styling -->
        <thead>
            <tr>
                <th>User ID</th>
                <th>Username</th>
                <th>Current Role</th>
                <th>Registered On</th>
                <th>Change Role To</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allUsers as $user): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <!--<td><span class="badge role-<?= strtolower(htmlspecialchars($user['user_role'])) ?>"><?= ucfirst(htmlspecialchars($user['user_role'])) ?></span></td>-->
                <td><?= htmlspecialchars($user['user_role']) ?></td>
                <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                <td>
                    <?php
                    // Logic to determine if the current admin can change this user's role
                    $canChangeRole = true;
                    if ($user['id'] === (int)$_SESSION['user_id']) { // If it's the current admin themselves
                        $stmtAdminCountCheck = $pdo->query("SELECT COUNT(*) FROM users WHERE user_role = 'admin'");
                        if ($stmtAdminCountCheck->fetchColumn() <= 1 && $user['user_role'] === 'admin') {
                            $canChangeRole = false; // pPrevent demoting self if only admin - handled in POST
                        }
                    }
                    ?>
                    <?php if ($canChangeRole): ?>
                        <form action="admin_users.php" method="POST" style="display:inline-block;">
                            <input type="hidden" name="user_id_to_change" value="<?= $user['id'] ?>">
                            <select name="new_role" style="padding: 8px; border-radius: var(--border-radius-md); border: 1px solid var(--color-border);"
                                    onchange="if(confirm('Are you sure you want to change the role for <?= htmlspecialchars(addslashes($user['username'])) ?> to ' + this.options[this.selectedIndex].text + '?')) { this.form.submit(); } else { this.value = '<?= $user['user_role'] ?>'; }">
                                <option value="user" <?= ($user['user_role'] == 'user' ? 'selected' : '') ?>>User</option>
                                <option value="admin" <?= ($user['user_role'] == 'admin' ? 'selected' : '') ?>>Admin</option>
                            </select>
                            <noscript><button type="submit" class="button" style="margin-left:5px;">Set Role</button></noscript>
                        </form>
                    <?php else: ?>
                        <!-- N/A (Should only apply if strict self-demotion prevention UI is added here, currently handled in POST) -->
                         <!-- This user is the only admin. -->
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<!-- Add some basic CSS for role badges (add to your style.css or a <style> block for testing) -->
<style>
    .badge {
        padding: 0.3em 0.6em;
        font-size: 0.85em;
        font-weight: 500;
        border-radius: var(--border-radius-md);
        color: white;
    }
    .role-admin {
        background-color: var(--color-primary); /* Or your admin color */
    }
    .role-user {
        background-color: var(--color-secondary); /* Or your user color */
    }
</style>

<?php require_once 'includes/footer.php'; ?>
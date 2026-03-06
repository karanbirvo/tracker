<?php
require_once 'includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];
$errors = [];
$success_message = '';

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. UPDATE PROFILE INFO
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $mobile = trim($_POST['mobile_number']);

        if (empty($username)) $errors[] = "Username cannot be empty.";
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $userId]);
        if ($stmt->fetch()) $errors[] = "Username or Email is already in use by another account.";

        if (empty($errors)) {
            $sql = "UPDATE users SET username = ?, email = ?, mobile_number = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$username, $email, $mobile, $userId]);
            $_SESSION['username'] = $username; // Update session with new name
            $success_message = "Profile information updated successfully.";
        }
    }

    // 2. CHANGE PASSWORD
    elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!password_verify($current, $user['password_hash'])) $errors[] = "Your current password is incorrect.";
        if (strlen($new) < 6) $errors[] = "The new password must be at least 6 characters long.";
        if ($new !== $confirm) $errors[] = "The new passwords do not match.";

        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$hash, $userId]);
            $success_message = "Your password has been changed successfully.";
        }
    }

    // 3. UPDATE SECURITY QUESTION
    elseif (isset($_POST['update_security'])) {
        $question = $_POST['security_question'];
        $answer = trim($_POST['security_answer']);
        if (empty($answer)) $errors[] = "The security answer cannot be empty.";

        if (empty($errors)) {
            $hash = password_hash($answer, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET security_question = ?, security_answer_hash = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$question, $hash, $userId]);
            $success_message = "Your security question has been updated successfully.";
        }
    }
}

// --- Fetch Current User Data for Form ---
$stmt = $pdo->prepare("SELECT username, email, mobile_number, security_question FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user_data = $stmt->fetch();
$security_questions_list = ["The name of your first pet?", "What is your mother's name?", "Your first school name?", "In what city were you born?", "What is your favorite book?"];

require_once 'includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark m-0"><i class="fas fa-user-circle text-primary me-2"></i>My Profile</h2>
            <p class="text-muted m-0 small">Manage your personal information and security settings.</p>
        </div>
    </div>
    
    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger rounded-3 shadow-sm">
            <?php foreach($errors as $e): ?><p class="mb-0"><?= $e ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if($success_message): ?>
        <div class="alert alert-success rounded-3 shadow-sm"><?= $success_message ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        
        <!-- Tab Navigation -->
        <div class="card-header bg-white border-bottom p-0">
            <ul class="nav nav-tabs nav-tabs-flush" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                        <i class="fas fa-id-card me-2"></i>Profile Details
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                        <i class="fas fa-shield-alt me-2"></i>Security Settings
                    </button>
                </li>
            </ul>
        </div>
        
        <div class="card-body p-4">
            <div class="tab-content" id="profileTabsContent">

                <!-- 1. PROFILE TAB PANE -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <form action="profile.php" method="POST">
                        <h5 class="fw-bold mb-4">Personal Information</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">USERNAME</label>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user_data['username']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">EMAIL ADDRESS</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">MOBILE NUMBER (OPTIONAL)</label>
                                <input type="tel" name="mobile_number" class="form-control" value="<?= htmlspecialchars($user_data['mobile_number'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="update_profile" class="btn btn-primary px-4 shadow-sm"><i class="fas fa-save me-2"></i>Save Profile</button>
                        </div>
                    </form>
                </div>

                <!-- 2. SECURITY TAB PANE -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <!-- Change Password Form -->
                    <form action="profile.php" method="POST">
                        <h5 class="fw-bold mb-4">Change Password</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">CURRENT PASSWORD</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">NEW PASSWORD</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">CONFIRM NEW PASSWORD</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="change_password" class="btn btn-dark px-4 shadow-sm"><i class="fas fa-key me-2"></i>Update Password</button>
                        </div>
                    </form>

                    <hr class="my-5">

                    <!-- Security Question Form -->
                    <form action="profile.php" method="POST">
                        <h5 class="fw-bold mb-4">Password Recovery Question</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">SECURITY QUESTION</label>
                                <select name="security_question" class="form-select">
                                    <?php foreach($security_questions_list as $q): ?>
                                        <option value="<?= htmlspecialchars($q) ?>" <?= ($user_data['security_question'] == $q) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($q) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">YOUR ANSWER</label>
                                <input type="text" name="security_answer" class="form-control" placeholder="Enter new answer to update" required>
                                <div class="form-text">This is case-sensitive.</div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="update_security" class="btn btn-dark px-4 shadow-sm"><i class="fas fa-question-circle me-2"></i>Update Security Question</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
body { background-color: #f8f9fa; }
.nav-tabs-flush .nav-link {
    border: 0;
    border-bottom: 2px solid transparent;
    color: #6c757d;
    font-weight: 500;
}
.nav-tabs-flush .nav-link.active {
    color: #2D5A95;
    border-color: #2D5A95;
    font-weight: 600;
}
.form-control:focus, .form-select:focus {
    box-shadow: 0 0 0 0.2rem rgba(45, 90, 149, 0.15);
    border-color: #2D5A95;
}
</style>

<?php require_once 'includes/footer.php'; ?>
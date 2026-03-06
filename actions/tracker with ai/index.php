<?php
require_once 'includes/header.php'; // This now includes functions.php
requireLogin();

$userId = $_SESSION['user_id'];
$todaysEntries = getTodaysEntries($userId, $pdo);
$activeTask = getActiveTask($userId, $pdo);
$uniqueDynamicTasks = getUniqueTaskNamesForToday($userId, $pdo); // From functions.php, assumed to be updated

// Define your static tasks (can associate icons here too if desired for buttons)
$staticTasksConfig = [
    'Login' => 'fas fa-sign-in-alt',
    'in' => 'fas fa-play-circle', // Example icon
    'out' => 'fas fa-stop-circle', // Example icon
    'Break' => 'fas fa-coffee',
    'Lunch' => 'fas fa-utensils',
    'Breakfast' => 'fas fa-mug-saucer',
    'Dinner' => 'fas fa-drumstick-bite', // Changed icon
    'Bio Break' => 'fas fa-restroom',
    'Fun Friday' => 'fas fa-glass-cheers',
    'logout' => 'fas fa-sign-out-alt'
];
$staticTaskNames = array_keys($staticTasksConfig); // Get just the names for existing logic if needed
?>

<div class="container-fluid mt-4 mb-5"> <!-- Bootstrap container-fluid for full width with padding -->
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <div class="dashboard-header-bs mb-4 p-3 bg-light rounded-3 shadow-sm">
        <h1 class="display-6 fw-bold text-primary">
            <i class="fas fa-tachometer-alt me-2"></i>Time Tracker Dashboard
        </h1>
        <div class="current-activity-status-bs mt-2" style="display: flex; justify-content: flex-end;">
            <?php if ($activeTask): ?>
                <p class="lead mb-0">
                    <i class="fas fa-play-circle text-success me-1"></i> Currently on:
                    <strong class="text-danger"><?= htmlspecialchars($activeTask['task_name']) ?></strong>
                    <form action="actions/stop_current_task.php" method="POST" class="d-inline-block ms-2">
                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Stop this task without starting another">
                            <i class="fas fa-stop me-1"></i>End Current
                        </button>
                    </form>
                </p>
            <?php else: ?>
                <p class="lead mb-0 text-muted"><i class="fas fa-hourglass-start me-1"></i>What are you working on?</p>
            <?php endif; ?>
        </div>
    </div>


    <!-- Task Input Area -->
    <div class="card shadow-sm mb-4 task-input-card-bs">
        <div class="card-body">
            <form action="actions/add_task.php" method="POST" class="row g-3 align-items-center">
                <div class="col-md">
                    <label for="task_name_input" class="visually-hidden">New Task Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                        <input type="text" name="task_name" id="task_name_input" class="form-control form-control-lg" placeholder="Enter new task name..." required autofocus>
                    </div>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-plus-circle me-2"></i>Add / Switch Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Task Buttons Area -->
    <div class="card shadow-sm mb-4 quick-actions-card-bs">
        <div class="card-header bg-white">
            <h3 class="mb-0 h5 fw-normal"><i class="fas fa-bolt me-2 text-warning"></i>Quick Actions / Switch Task</h3>
        </div>
        <div class="card-body">
            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-2 task-buttons-grid-bs">
                <?php foreach ($staticTasksConfig as $staticTaskName => $iconClass): ?>
                    <div class="col">
                        <form action="actions/switch_task.php" method="POST" class="d-grid">
                            <input type="hidden" name="task_name" value="<?= htmlspecialchars($staticTaskName) ?>">
                            <button type="submit" class="btn btn-outline-primary p-2 static-task-bs <?= ($activeTask && $activeTask['task_name'] == $staticTaskName) ? 'active' : '' ?>">
                                <i class="<?= $iconClass ?> d-block mb-1 fs-4"></i>
                                <span class="button-text-bs"><?= htmlspecialchars($staticTaskName) ?></span>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <?php if (!empty($uniqueDynamicTasks)): ?>
                    <?php foreach ($uniqueDynamicTasks as $dynamicTaskName): ?>
                         <?php
                         $isCurrentlyActive = ($activeTask && $activeTask['task_name'] == $dynamicTaskName);
                         // Ensure it's not also in our static list (though getUnique should handle this)
                         if (!$isCurrentlyActive && !array_key_exists($dynamicTaskName, $staticTasksConfig)):
                         ?>
                            <div class="col">
                                <form action="actions/switch_task.php" method="POST" class="d-grid">
                                    <input type="hidden" name="task_name" value="<?= htmlspecialchars($dynamicTaskName) ?>">
                                    <button type="submit" class="btn btn-outline-success p-2 dynamic-task-bs">
                                        <i class="fas fa-redo-alt d-block mb-1 fs-4"></i>
                                        <span class="button-text-bs"><?= htmlspecialchars($dynamicTaskName) ?></span>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Current Log Area -->
    <div class="card shadow-sm mb-4 current-log-card-bs">
        <div class="card-header bg-white">
            <h3 class="mb-0 h5 fw-normal"><i class="far fa-calendar-alt me-2 text-info"></i>Today's Log <small class="text-muted fw-light fs-6">(<?= date('D, M j, Y') ?>)</small></h3>
        </div>
        <div class="card-body p-0"> <!-- p-0 to make list group flush -->
            <?php if (empty($todaysEntries)): ?>
                <div class="alert alert-info m-3 text-center">
                    <i class="fas fa-info-circle me-2"></i>No entries for today yet. Start your first task!
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($todaysEntries as $entry): ?>
                        <li class="list-group-item task-entry-bs d-flex justify-content-between align-items-center flex-wrap <?= ($activeTask && $activeTask['id'] == $entry['id']) ? 'active-task-bs list-group-item-primary' : '' ?>">
                            <div class="task-info-bs mb-1 mb-md-0">
                                <strong class="task-name-bs d-block"><?= htmlspecialchars($entry['task_name']) ?></strong>
                                <small class="task-time-bs text-muted">
                                    <?= date('h:i A', strtotime($entry['start_time'])) ?> -
                                    <?= $entry['end_time'] ? date('h:i A', strtotime($entry['end_time'])) : 'Ongoing' ?>
                                </small>
                            </div>
                            <div class="task-duration-bs">
                                <span class="badge rounded-pill bg-success-subtle text-success-emphasis p-2">
                                    <i class="far fa-clock me-1"></i><?= formatDuration($entry['start_time'], $entry['end_time']) ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- End Day Button -->
    <div class="text-end mt-4">
        <form action="actions/download_report.php" method="POST">
            <input type="hidden" name="date_from" value="<?= date('Y-m-d') ?>">
            <input type="hidden" name="date_to" value="<?= date('Y-m-d') ?>">
            <input type="hidden" name="format" value="csv">
            <input type="hidden" name="source" value="end_day">
            <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Are you sure you want to end your day and download the EOD report?');">
                <i class="fas fa-door-closed me-2"></i>End Day & Download EOD
            </button>
        </form>
    </div>
    <!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<style>
    .dashboard-header-bs {
    /* border-left: 5px solid var(--bs-primary, #0d6efd); */ /* Example accent */
}

.dashboard-header-bs .display-6 { /* Main dashboard title */
    color: var(--header-logo-color, #2D5A95) !important; /* Ensure brand color, !important if BS overrides */
}

.task-input-card-bs .input-group-text {
    background-color: #e9ecef;
    border-right: 0; /* Seamless look with input */
}
.task-input-card-bs .form-control-lg {
    font-size: 1.1rem; /* Slightly smaller than default lg for better fit */
}

.quick-actions-card-bs .card-header,
.current-log-card-bs .card-header {
    border-bottom: 1px solid #dee2e6;
}

.task-buttons-grid-bs .btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 78px; /* Consistent button height */
    font-size: 0.85rem; /* Smaller text for button content */
    white-space: normal; /* Allow button text to wrap */
    line-height: 1.2;
    word-break: break-word; /* Help with long task names */
    transition: all 0.2s ease-in-out;
}
.task-buttons-grid-bs .btn i {
    margin-bottom: 0.3rem !important; /* Bootstrap's mb-1 might be too much */
}
.task-buttons-grid-bs .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.form-control:focus {
    box-shadow: 0 0 0 0.08rem rgba(13, 110, 253, .25);
}
.task-buttons-grid-bs .btn.static-task-bs.active {
    background-color: var(--bs-primary, #0d6efd);
    color: white;
    border-color: var(--bs-primary, #0d6efd);
}
.task-buttons-grid-bs .btn.static-task-bs.active i {
    color: white; /* Ensure icon color changes too */
}


.task-entry-bs {
    padding: 0.8rem 1rem;
    transition: background-color 0.2s ease;
}
.task-entry-bs:hover {
    background-color: #f8f9fa; /* Subtle hover */
}

.task-entry-bs.active-task-bs {
    /* Bootstrap's list-group-item-primary is good, or define custom: */
    /* background-color: #cfe2ff; */
    /* border-left: 4px solid var(--bs-primary, #0d6efd); */
    font-weight: 500;
}
.task-entry-bs.active-task-bs .task-name-bs {
    color: var(--bs-primary, #0d6efd);
}


.task-info-bs {
    flex-grow: 1; /* Allow task name and time to take space */
}
.task-name-bs {
    font-size: 1.05em;
    font-weight: 500;
}
.task-time-bs {
    font-size: 0.85em;
}
.task-duration-bs .badge {
    font-size: 0.9em; /* Slightly larger badge text */
}


/* Responsive adjustments for task log items */
@media (max-width: 576px) { /* Small devices */
    .task-entry-bs {
        flex-direction: column;
        align-items: flex-start !important; /* Override d-flex align-items-center */
        gap: 0.3rem;
    }
    .task-info-bs {
        margin-bottom: 0.3rem;
    }
    .task-duration-bs {
        align-self: flex-start; /* Align badge to left when stacked */
    }
}
</style>
</div> <!-- .container-fluid -->

<?php require_once 'includes/footer.php'; ?>
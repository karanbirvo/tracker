<?php
require_once 'includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];
$todaysEntries = getTodaysEntries($userId, $pdo);
$activeTask = getActiveTask($userId, $pdo);
$activeProjects = getUserProjects($userId, $pdo);

// --- PAUSE LOGIC ---
$pausedTask = $_SESSION['paused_task'] ?? null;
$selectedProject = $pausedTask['project_id'] ?? $_SESSION['last_selected_project'] ?? '';
$formTaskName = $pausedTask['task_name'] ?? '';
$formDescription = $pausedTask['description'] ?? '';

// Scheduled Tasks
$stmtSched = $pdo->prepare("SELECT st.*, p.project_name FROM scheduled_tasks st LEFT JOIN projects p ON st.project_id = p.id WHERE st.user_id = ? AND st.status = 'pending' ORDER BY st.created_at ASC");
$stmtSched->execute([$userId]);
$scheduledTasks = $stmtSched->fetchAll();

$activeStartTime = $activeTask ? strtotime($activeTask['start_time']) * 1000 : 0;

require_once 'includes/header.php'; 
?>

<div class="container-fluid mt-4 mb-5"> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- === 1. HEADER (METER) === -->
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark m-0">Dashboard</h2>
            <p class="text-muted m-0"><i class="far fa-calendar me-2"></i><?= date('l, F j, Y') ?></p>
        </div>
        
        <?php if ($activeTask): ?>
            <?php 
                $isManual = ($activeTask['type'] === 'manual');
                $borderColor = $isManual ? 'border-danger' : 'border-success';
                $textColor = $isManual ? 'text-danger' : 'text-success';
                $statusText = $isManual ? 'WITHOUT TRACKER (RUNNING)' : 'ON TRACKER (RUNNING)';
            ?>
            <div class="active-timer-card shadow-sm d-flex align-items-center gap-3 ps-4 py-2 rounded-pill bg-white border <?= $borderColor ?> border-2">
                <div class="spinner-grow <?= $textColor ?> spinner-grow-sm"></div>
                <div>
                    <div class="small fw-bold text-uppercase <?= $textColor ?>" style="font-size: 0.7rem;"><?= $statusText ?></div>
                    <div class="fw-bold text-dark text-truncate" style="max-width: 150px;"><?= htmlspecialchars($activeTask['task_name']) ?></div>
                </div>
                <div class="vr mx-1"></div>
                <div id="liveTimerDisplay" class="fs-4 fw-light font-monospace text-dark">00:00:00</div>
                <div class="d-flex gap-1 pe-2">
                    <form action="actions/pause_task.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-circle" data-bs-toggle="tooltip" title="Pause Task" style="width: 32px; height: 32px;"><i class="fas fa-pause"></i></button>
                    </form>
                    <form action="actions/stop_current_task.php" method="POST" class="m-0">
                        <button type="submit" class="btn btn-sm btn-danger rounded-circle" data-bs-toggle="tooltip" title="Stop Task" style="width: 32px; height: 32px;"><i class="fas fa-stop"></i></button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="text-muted fst-italic px-3 py-2 border rounded-pill bg-light"><i class="fas fa-bed me-2"></i>No timer running</div>
        <?php endif; ?>
    </div>

    <!-- === 2. MIDDLE ROW === -->
    <div class="row g-4">
        
        <!-- INPUT FORM -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 bg-white">
                    <h5 class="text-secondary fw-bold mb-4">
                        <?php if($pausedTask): ?><i class="fas fa-play-circle text-primary me-2"></i>Resume Paused Task<?php else: ?><i class="fas fa-layer-group me-2"></i>New Entry<?php endif; ?>
                    </h5>
                    <form id="trackerForm" action="actions/add_task.php" method="POST">
                        <input type="hidden" name="action_type" id="action_type" value="tracker">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">PROJECT</label>
                                <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fas fa-briefcase text-secondary"></i></span><select name="project_id" id="project_select" class="form-select border-start-0 bg-light" onchange="loadTasksForProject(this.value)"><option value="">-- No Project --</option><?php foreach ($activeProjects as $proj): ?><option value="<?= $proj['id'] ?>" <?= ($selectedProject == $proj['id']) ? 'selected' : '' ?>><?= htmlspecialchars($proj['project_name']) ?></option><?php endforeach; ?></select><a href="projects.php" class="btn btn-outline-secondary" title="Add Project"><i class="fas fa-plus"></i></a></div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-bold text-muted">TASK NAME</label>
                                <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fas fa-pen text-secondary"></i></span><input type="text" name="task_name" id="task_name" class="form-control border-start-0 bg-light" list="taskSuggestions" value="<?= htmlspecialchars($formTaskName) ?>" placeholder="What are you working on?" required autocomplete="off"><datalist id="taskSuggestions"></datalist></div>
                            </div>
                            <div class="col-12"><textarea name="description" class="form-control bg-light border-0 p-3" rows="2" placeholder="Add optional notes..."><?= htmlspecialchars($formDescription) ?></textarea></div>
                            <div class="col-12 mt-4 d-flex flex-wrap gap-2">
                                <button type="submit" onclick="setAction('tracker')" class="btn btn-success btn-lg px-4 flex-grow-1 shadow-sm"><i class="fas fa-play me-2"></i>ON TRACKER</button>
                                <button type="submit" onclick="setAction('manual')" class="btn btn-danger btn-lg px-4 shadow-sm"><i class="fas fa-user-clock me-2"></i>WITHOUT TRACKER</button>
                                <button type="submit" onclick="setAction('schedule')" class="btn btn-info text-white btn-lg px-4 shadow-sm"><i class="fas fa-calendar-plus me-2"></i>SCHEDULE</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- UPCOMING TASKS -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-secondary m-0"><i class="fas fa-list-ul me-2"></i>Upcoming</h5>
                    <span class="badge bg-light text-dark rounded-pill"><?= count($scheduledTasks) ?></span>
                </div>
                <div class="card-body p-3 task-scroll-container">
                    <?php if (empty($scheduledTasks)): ?>
                        <div class="text-center text-muted py-5"><small>No scheduled tasks.</small></div>
                    <?php else: ?>
                        <div class="vstack gap-2">
                            <?php foreach ($scheduledTasks as $st): ?>
                                <div class="p-3 rounded-3 bg-light border d-flex justify-content-between align-items-center">
                                    <div style="min-width: 0;">
                                        <?php if(!empty($st['project_name'])): ?><div class="badge bg-white border text-secondary mb-1" style="font-size:0.65rem"><?= htmlspecialchars($st['project_name']) ?></div><?php endif; ?><div class=""><?= htmlspecialchars($st['created_at']) ?></div>
                                        <div class="fw-bold text-dark lh-sm text-truncate"><?= htmlspecialchars($st['task_name']) ?></div>
                                        <div class=""><?= htmlspecialchars($st['description']) ?></div>
                                        
                                        
                                    </div>
                                    <div class="d-flex flex-column gap-1 ms-2 flex-shrink-0">
                                        <form action="actions/add_task.php" method="POST"><input type="hidden" name="task_name" value="<?= htmlspecialchars($st['task_name']) ?>"><input type="hidden" name="project_id" value="<?= $st['project_id'] ?>"><input type="hidden" name="description" value="<?= htmlspecialchars($st['description']) ?>"><input type="hidden" name="scheduled_id" value="<?= $st['id'] ?>"><button type="submit" name="action_type" value="tracker" class="btn btn-success btn-sm schedule-start-btn" data-bs-toggle="tooltip" title="Start On Tracker">Tracker</button></form>
                                        <form action="actions/add_task.php" method="POST"><input type="hidden" name="task_name" value="<?= htmlspecialchars($st['task_name']) ?>"><input type="hidden" name="project_id" value="<?= $st['project_id'] ?>"><input type="hidden" name="description" value="<?= htmlspecialchars($st['description']) ?>"><input type="hidden" name="scheduled_id" value="<?= $st['id'] ?>"><button type="submit" name="action_type" value="manual" class="btn btn-danger btn-sm schedule-start-btn" data-bs-toggle="tooltip" title="Start Without Tracker">Manual</button></form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="p-3 border-top mt-auto">
                    <form action="actions/download_report.php" method="POST"><input type="hidden" name="date_from" value="<?= date('Y-m-d') ?>"><input type="hidden" name="date_to" value="<?= date('Y-m-d') ?>"><input type="hidden" name="format" value="csv"><input type="hidden" name="source" value="end_day"><button type="submit" class="btn btn-dark w-100 rounded-3 py-2" onclick="return confirm('End day?');"><i class="fas fa-file-export me-2"></i>End Day</button></form>
                </div>
            </div>
        </div>
    </div>

    <!-- === 3. FULL WIDTH LOG === -->
    <div class="row mt-4">
        <div class="col-12">
            <h6 class="text-muted fw-bold text-uppercase mb-3 ps-2">Today's Activity Log</h6>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <?php if (empty($todaysEntries)): ?>
                        <div class="p-5 text-center text-muted"><p>No activity yet today.</p></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light"><tr class="text-uppercase small text-muted"><th class="ps-4 py-3" width="10%">Type</th><th width="15%">Project</th><th width="40%">Task</th><th width="15%">Time</th><th width="10%">Duration</th><th class="text-end pe-4" width="10%">Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($todaysEntries as $entry): ?>
                                        <tr class="<?= ($activeTask && $activeTask['id'] == $entry['id'] && $activeTask['type'] == $entry['type']) ? 'bg-primary-subtle' : '' ?>">
                                            <td class="ps-4"><?php if($entry['type'] == 'tracker'): ?><span class="badge bg-success-subtle text-success border border-success-subtle">ON TRACKER</span><?php else: ?><span class="badge bg-danger-subtle text-danger border border-danger-subtle">OFF TRACKER</span><?php endif; ?></td>
                                            <td><?php if(!empty($entry['project_name'])): ?><span class="badge bg-white text-dark border fw-normal"><?= htmlspecialchars($entry['project_name']) ?></span><?php else: ?><span class="text-muted small">-</span><?php endif; ?></td>
                                            <td><div class="fw-bold text-dark"><?= htmlspecialchars($entry['task_name']) ?></div><?php if(!empty($entry['description'])): ?><div class="small text-muted" style="max-width:500px"><?= htmlspecialchars($entry['description']) ?></div><?php endif; ?></td>
                                            <td class="small text-secondary"><?= date('H:i', strtotime($entry['start_time'])) ?><?php if($entry['end_time']): ?> - <?= date('H:i', strtotime($entry['end_time'])) ?><?php else: ?><span class="fw-bold ms-1 text-primary">Running...</span><?php endif; ?></td>
                                            <td><?php if($activeTask && $activeTask['id'] == $entry['id'] && $activeTask['type'] == $entry['type']): ?><span id="activeRowTimer" class="fw-bold text-primary font-monospace">00:00:00</span><?php else: ?><span class="fw-medium text-dark font-monospace"><?= formatDuration($entry['start_time'], $entry['end_time']) ?></span><?php endif; ?></td>
                                            <td class="text-end pe-4"><?php if (hasPermission('perm_edit')): ?><button class="btn btn-sm btn-light border edit-btn" data-bs-toggle="modal" data-bs-target="#editEntryModal" data-id="<?= $entry['id'] ?>" data-type="<?= $entry['type'] ?>"><i class="fas fa-edit text-primary"></i></button><?php endif; ?><?php if (hasPermission('perm_delete')): ?><form action="actions/deleteusingpopup.php" method="POST" class="d-inline" onsubmit="return confirm('Delete?');"><input type="hidden" name="entry_id" value="<?= $entry['id'] ?>"><input type="hidden" name="entry_type" value="<?= $entry['type'] ?>"><button type="submit" class="btn btn-sm btn-light border"><i class="fas fa-trash-alt text-danger"></i></button></form><?php endif; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php
$allProjects = $activeProjects;
require_once 'includes/partials/edit_entry_modal.php'; 
?>

<!-- *** MERGED & CORRECTED SCRIPT BLOCK *** -->
<script>
// --- CORE FUNCTIONS ---
function setAction(type) { document.getElementById('action_type').value = type; }

function loadTasksForProject(projectId) {
    const datalist = document.getElementById('taskSuggestions');
    datalist.innerHTML = '<option value="Loading...">'; 
    fetch(`api/get_project_tasks.php?project_id=${projectId}`)
        .then(res => res.json())
        .then(data => { 
            datalist.innerHTML = ''; 
            data.forEach(task => { 
                const opt = document.createElement('option'); 
                opt.value = task; 
                datalist.appendChild(opt); 
            }); 
        }).catch(e => datalist.innerHTML = '');
}

const startTime = <?= $activeStartTime ?>;
function updateTimer() {
    if(!startTime) return; 
    const diff = Math.floor((Date.now() - startTime)/1000); 
    const h = Math.floor(diff/3600); 
    const m = Math.floor((diff%3600)/60); 
    const s = diff%60; 
    const str = (h>0?h+'h ':'') + (m<10?'0'+m:m)+'m ' + (s<10?'0'+s:s)+'s'; 
    const d1 = document.getElementById('liveTimerDisplay'); 
    const d2 = document.getElementById('activeRowTimer'); 
    if(d1) d1.innerText = str; 
    if(d2) d2.innerText = str;
}
if(startTime > 0) setInterval(updateTimer, 1000);


// --- DOMContentLoaded: ALL INITIALIZATION LOGIC GOES HERE ---
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Initialize Task Suggestions Dropdown
    const p = document.getElementById('project_select'); 
    if(p.value) {
        loadTasksForProject(p.value);
    }

    // 2. Initialize Bootstrap Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { 
        return new bootstrap.Tooltip(tooltipTriggerEl) 
    });

    // 3. Initialize Edit Modal Logic
    const editModal = document.getElementById('editEntryModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; 
            const entryId = button.dataset.id; 
            const entryType = button.dataset.type;
            
            document.getElementById('editModalTitle').innerText = `Edit Entry #${entryId}`; 
            document.getElementById('edit_entry_id').value = entryId; 
            document.getElementById('edit_current_type').value = entryType;

            fetch(`/api/get_entry_details.php?id=${entryId}&type=${entryType}`)
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


<style>
.schedule-start-btn { padding: 3px 8px !important; font-size: 0.75rem !important; font-weight: 600; }
.task-scroll-container { max-height: 230px; overflow-y: auto; } .task-scroll-container::-webkit-scrollbar { width: 5px; } .task-scroll-container::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }
body { background-color: #f8f9fa; } .form-control:focus, .form-select:focus { box-shadow: none; border-color: #2D5A95; }
input[list]::-webkit-calendar-picker-indicator { display: block !important; opacity: 0.5; cursor: pointer; } .bg-primary-subtle { background-color: #e0f2fe !important; }
div#header-timer-widget {
    display: none;
}
</style>

<?php require_once 'includes/footer.php'; ?>

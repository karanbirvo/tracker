<?php
// This partial expects $allProjects to be available from the parent page
$allProjectsForModal = $allProjects ?? [];
?>
<div class="modal fade" id="editEntryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="actions/update_entry.php" method="POST">
                <div class="modal-header border-bottom bg-light">
                    <h5 class="modal-title fw-bold" id="editModalTitle">Edit Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Hidden fields to track the entry -->
                    <input type="hidden" name="entry_id" id="edit_entry_id">
                    <input type="hidden" name="current_type" id="edit_current_type">

                    <div class="row g-3">
                        <!-- Type & Project -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">TYPE</label>
                            <select name="entry_type" id="edit_entry_type" class="form-select">
                                <option value="tracker">On Tracker (Green)</option>
                                <option value="manual">Manual / Off (Red)</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold text-secondary">PROJECT</label>
                            <select name="project_id" id="edit_project_id" class="form-select">
                                <option value="">-- No Project --</option>
                                <?php foreach ($allProjectsForModal as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Task Name -->
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">TASK NAME</label>
                            <input type="text" name="task_name" id="edit_task_name" class="form-control" required>
                        </div>
                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">DESCRIPTION</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                        </div>
                        <!-- Date & Time -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">DATE</label>
                            <input type="date" name="entry_date" id="edit_entry_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">START TIME</label>
                            <input type="time" name="start_time" id="edit_start_time" class="form-control" step="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">END TIME</label>
                            <input type="time" name="end_time" id="edit_end_time" class="form-control" step="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
require_once 'includes/header.php';
requireLogin();

// Admin Access Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['message'] = "Access Denied: You do not have administrative privileges.";
    $_SESSION['message_type'] = "danger";
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "Invalid entry ID specified.";
    $_SESSION['message_type'] = "danger";
    header("Location: admin_reports.php");
    exit();
}

$entryId = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT te.*, u.username FROM time_entries te JOIN users u ON te.user_id = u.id WHERE te.id = ?");
$stmt->execute([$entryId]);
$entry = $stmt->fetch();

if (!$entry) {
    $_SESSION['message'] = "Time entry not found.";
    $_SESSION['message_type'] = "danger";
    header("Location: admin_reports.php");
    exit();
}

$original_user_id = $entry['user_id'];
$original_entry_date_for_redirect = date('Y-m-d', strtotime($entry['entry_date']));

// Initialize form variables with current entry data
$taskName_form = $entry['task_name'];
// For the form, we usually show the date associated with the start of the task
$entryDate_form = date('Y-m-d', strtotime($entry['start_time']));
$startTime_form = date('H:i:s', strtotime($entry['start_time']));
$endTime_form = $entry['end_time'] ? date('H:i:s', strtotime($entry['end_time'])) : '';
// We also need the original end date if it exists and is different, for smarter validation
$original_end_date_for_validation = $entry['end_time'] ? date('Y-m-d', strtotime($entry['end_time'])) : null;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskName_form = trim($_POST['task_name']);
    $entryDate_form_input = $_POST['entry_date']; // This is the date selected in the form for the task
    $startTime_form_input = $_POST['start_time'];
    $endTime_form_input = $_POST['end_time'];

    $errors = [];

    if (empty($taskName_form)) {
        $errors[] = "Task name cannot be empty.";
    }

    // Validate the date selected in the form
    $formSelectedDateObj = DateTime::createFromFormat('Y-m-d', $entryDate_form_input);
    if (!$formSelectedDateObj || $formSelectedDateObj->format('Y-m-d') !== $entryDate_form_input) {
        $errors[] = "Invalid entry date format in form. Use YYYY-MM-DD.";
    }

    // Construct and Validate Start DateTime
    $finalStartTimeObj = null;
    if ($formSelectedDateObj) {
        $stFormats = ['Y-m-d H:i:s', 'Y-m-d H:i'];
        foreach ($stFormats as $format) {
            $tempObj = DateTime::createFromFormat($format, $entryDate_form_input . ' ' . $startTime_form_input);
            if ($tempObj) {
                $finalStartTimeObj = $tempObj;
                break;
            }
        }
        if (!$finalStartTimeObj) {
            $errors[] = "Invalid start time format. Use HH:MM or HH:MM:SS.";
        }
    } else {
        if (empty($errors)) $errors[] = "Start time cannot be validated due to invalid date from form.";
    }

    // Construct and Validate End DateTime
    $finalEndTimeObj = null;
    $finalEndTimeForDB = null;

    if (!empty(trim($endTime_form_input))) {
        if ($formSelectedDateObj && $finalStartTimeObj) { // Only proceed if form date and start time are valid
            // By default, assume end date is the same as the form's selected date
            $endDateForEndTime = $entryDate_form_input;

            // Heuristic: If end time is numerically smaller than start time,
            // and the original entry spanned midnight, assume end time is on the next day.
            // This gets tricky if the user changes the main 'entryDate_form_input'.
            // A better approach for complex edits is separate start/end date fields.
            // For now, let's be a bit more direct:
            // If original entry spanned midnight, and user hasn't changed the end time to be *after* start time on the *same new date*,
            // we might need to adjust.

            // Let's try to construct the end datetime based on the input time and form date first.
            // Then, if it's before the start datetime, we check if the input time itself implies "next day".
            $etFormats = ['Y-m-d H:i:s', 'Y-m-d H:i'];
            foreach ($etFormats as $format) {
                $tempObj = DateTime::createFromFormat($format, $endDateForEndTime . ' ' . $endTime_form_input);
                if ($tempObj) {
                    $finalEndTimeObj = $tempObj;
                    break;
                }
            }

            if (!$finalEndTimeObj) {
                $errors[] = "Invalid end time format. Use HH:MM or HH:MM:SS, or leave blank.";
            } else {
                // If end time constructed with form date is before start time also constructed with form date
                if ($finalEndTimeObj < $finalStartTimeObj) {
                    // This is the scenario: e.g., Start 23:00 on DateX, End 01:00 on DateX.
                    // We need to make the End Time on DateX+1
                    $finalEndTimeObj->add(new DateInterval('P1D')); // Add one day
                }
                // Now, after potentially adding a day, do the final check against the start time.
                // This check is now more robust because $finalEndTimeObj might be on the next day.
                if ($finalEndTimeObj < $finalStartTimeObj) {
                     // This would only happen if adding P1D still resulted in it being earlier (e.g. large timezone shift or error)
                     // Or if the initial assumption to add P1D was wrong for a same-day correction.
                     // For simplicity, we'll stick to the P1D addition if end time < start time on same date.
                     // A truly robust solution requires more UI (separate end date) or very complex heuristics.
                    // $errors[] = "End time cannot be before start time, even considering potential day change."; // Too complex for now.
                }
            }
             if($finalEndTimeObj) $finalEndTimeForDB = $finalEndTimeObj->format('Y-m-d H:i:s');
        } else {
            if (empty($errors)) $errors[] = "End time cannot be validated due to invalid start date/time from form.";
        }
    }


    if (empty($errors)) {
        try {
            $finalStartTimeForDB = $finalStartTimeObj->format('Y-m-d H:i:s');
            // The $entryDate_form to be saved in the DB should be the date of the start_time
            $dbEntryDate = $finalStartTimeObj->format('Y-m-d');

            $updateStmt = $pdo->prepare("UPDATE time_entries SET task_name = ?, start_time = ?, end_time = ?, entry_date = ? WHERE id = ?");
            $updateStmt->execute([$taskName_form, $finalStartTimeForDB, $finalEndTimeForDB, $dbEntryDate, $entryId]);

            $_SESSION['message'] = "Entry ID <strong>" . $entryId . "</strong> updated successfully for <em>" . htmlspecialchars($entry['username']) . "</em>.";
            $_SESSION['message_type'] = "success";
            header("Location: admin_reports.php?user_id_selected=" . $original_user_id . "&date_from=" . urlencode($dbEntryDate) . "&date_to=" . urlencode($dbEntryDate));
            exit();
        } catch (PDOException $e) {
            error_log("DB Error updating entry $entryId: " . $e->getMessage());
            $errors[] = "Database error occurred. Could not update entry. Check server logs for details.";
        }
    }

    // If errors, re-assign form values from input
    $entryDate_form = $entryDate_form_input; // Show what user selected in form
    $startTime_form = $startTime_form_input;
    $endTime_form = $endTime_form_input;

    if (!empty($errors)) {
        $_SESSION['message'] = "<strong>Please correct the following errors:</strong><br>" . implode("<br>", array_map('htmlspecialchars', $errors));
        $_SESSION['message_type'] = "danger";
    }
}
?>

<h1><i class="fas fa-edit"></i> Edit Time Entry for <?= htmlspecialchars($entry['username']) ?> <small>(ID: <?= $entryId ?>)</small></h1>

<div class="card" style="max-width: 600px;">
    <form action="edit_entry.php?id=<?= $entryId ?>" method="POST">
        <div class="form-group">
            <label for="task_name">Task Name:</label>
            <input type="text" name="task_name" id="task_name" class="form-control" value="<?= htmlspecialchars($taskName_form) ?>" required>
        </div>
        <div class="form-group">
            <label for="entry_date">Task Date (primarily for Start Time):</label>
            <input type="date" name="entry_date" id="entry_date" class="form-control" value="<?= htmlspecialchars($entryDate_form) ?>" required>
            <small class="form-text text-muted">This date will be used for the start time. End time may fall on the next day if it crosses midnight.</small>
        </div>
        <div class="form-group">
            <label for="start_time">Start Time (HH:MM or HH:MM:SS):</label>
            <input type="time" name="start_time" id="start_time" class="form-control" value="<?= htmlspecialchars($startTime_form) ?>" step="1" required>
            <!-- <small class="form-text text-muted">Use 24-hour format or select using picker.</small> -->
        </div>
        <div class="form-group">
            <label for="end_time">End Time (HH:MM or HH:MM:SS, or blank):</label>
            <input type="time" name="end_time" id="end_time" class="form-control" value="<?= htmlspecialchars($endTime_form) ?>" step="1">
            <!-- <small class="form-text text-muted">Leave blank if task is ongoing or had no defined end.</small> -->
        </div>
        <div style="margin-top: 20px;">
            <button type="submit" class="button button-primary"><i class="fas fa-save"></i> Update Entry</button>
            <a href="admin_reports.php?user_id_selected=<?= $original_user_id ?>&date_from=<?= urlencode($original_entry_date_for_redirect) ?>&date_to=<?= urlencode($original_entry_date_for_redirect) ?>" class="button" style="background-color: var(--color-secondary); color:white; margin-left: 10px;"><i class="fas fa-times-circle"></i> Cancel</a>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
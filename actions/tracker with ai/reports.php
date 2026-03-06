<?php
require_once 'includes/header.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Default date and time range
$defaultEndDate = date('Y-m-d');
$defaultStartDate = date('Y-m-d'); // For today's report by default
$defaultStartTime = '00:00:00';
$defaultEndTime = '23:59:59';

// Get values from GET request or use defaults
$filterDateFrom = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : $defaultStartDate;
$filterTimeFrom = isset($_GET['time_from']) && !empty($_GET['time_from']) ? $_GET['time_from'] : $defaultStartTime;

$filterDateTo = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : $defaultEndDate;
$filterTimeTo = isset($_GET['time_to']) && !empty($_GET['time_to']) ? $_GET['time_to'] : $defaultEndTime;

// --- Validate and Sanitize Inputs ---
// Date From
$dFromObj = DateTime::createFromFormat('Y-m-d', $filterDateFrom);
if (!$dFromObj || $dFromObj->format('Y-m-d') !== $filterDateFrom) {
    $filterDateFrom = $defaultStartDate; // Reset to default if invalid
}
// Time From
$tFromObj = DateTime::createFromFormat('H:i:s', $filterTimeFrom);
if (!$tFromObj) { // Try H:i if H:i:s fails (common for time inputs)
    $tFromObj = DateTime::createFromFormat('H:i', $filterTimeFrom);
}
if (!$tFromObj || ($tFromObj->format('H:i:s') !== $filterTimeFrom && $tFromObj->format('H:i') !== $filterTimeFrom) ) {
    $filterTimeFrom = $defaultStartTime; // Reset to default if invalid
} else {
    $filterTimeFrom = $tFromObj->format('H:i:s'); // Normalize to H:i:s
}

// Date To
$dToObj = DateTime::createFromFormat('Y-m-d', $filterDateTo);
if (!$dToObj || $dToObj->format('Y-m-d') !== $filterDateTo) {
    $filterDateTo = $defaultEndDate; // Reset to default
}
// Time To
$tToObj = DateTime::createFromFormat('H:i:s', $filterTimeTo);
if (!$tToObj) { // Try H:i if H:i:s fails
    $tToObj = DateTime::createFromFormat('H:i', $filterTimeTo);
}
if (!$tToObj || ($tToObj->format('H:i:s') !== $filterTimeTo && $tToObj->format('H:i') !== $filterTimeTo) ) {
    $filterTimeTo = $defaultEndTime; // Reset to default
} else {
    $filterTimeTo = $tToObj->format('H:i:s'); // Normalize to H:i:s
}
// --- End Validation ---


// Combine date and time for the SQL query
$startDateTimeFilter = $filterDateFrom . ' ' . $filterTimeFrom;
$endDateTimeFilter = $filterDateTo . ' ' . $filterTimeTo;


// Modify SQL query to filter by the full start_time DATETIME field
// We assume entry_date is still useful for broad indexing but the primary filter is start_time
$stmt = $pdo->prepare("SELECT * FROM time_entries 
                       WHERE user_id = :user_id 
                       AND start_time >= :start_datetime 
                       AND start_time <= :end_datetime
                       ORDER BY start_time ASC");
$stmt->execute([
    'user_id' => $userId,
    'start_datetime' => $startDateTimeFilter,
    'end_datetime' => $endDateTimeFilter
]);
$reportEntries = $stmt->fetchAll();

?>

<h1>My Reports</h1>
<style>
    .container {
    width: 90%;
     max-width: 100%; 
    /*margin: 20px auto;*/
    margin: 94px auto 20px auto;
    padding: 20px;
    background-color: #fff;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
}
</style>
<form method="GET" action="reports.php" class="form-group" style="display:flex; flex-wrap: wrap; align-items: flex-end; gap: 15px; margin-bottom:20px; padding:20px; background-color:#f9f9f9; border-radius:5px;">
    <div style="flex-basis: 200px;">
        <label for="date_from">From Date:</label>
        <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>" class="form-control" style="padding: 8px;">
    </div>
    <div style="flex-basis: 150px;">
        <label for="time_from">From Time:</label>
        <input type="time" name="time_from" id="time_from" value="<?= htmlspecialchars(substr($filterTimeFrom, 0, 5)) // Show HH:MM for input ?>" class="form-control" style="padding: 8px;" step="1">
    </div>
    <div style="flex-basis: 200px;">
        <label for="date_to">To Date:</label>
        <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($filterDateTo) ?>" class="form-control" style="padding: 8px;">
    </div>
    <div style="flex-basis: 150px;">
        <label for="time_to">To Time:</label>
        <input type="time" name="time_to" id="time_to" value="<?= htmlspecialchars(substr($filterTimeTo, 0, 5)) // Show HH:MM for input ?>" class="form-control" style="padding: 8px;" step="1">
    </div>
    <button type="submit" class="button-link" style="padding: 9px 15px; height: fit-content;">View Report</button>
</form>


<?php if (empty($reportEntries)): ?>
    <p class="alert alert-info">No entries found for the selected period (<?= htmlspecialchars($filterDateFrom . ' ' . $filterTimeFrom) ?> to <?= htmlspecialchars($filterDateTo . ' ' . $filterTimeTo) ?>).</p>
<?php else: ?>
    <h3>Report for: <?= htmlspecialchars($filterDateFrom . ' ' . substr($filterTimeFrom,0,5)) ?> to <?= htmlspecialchars($filterDateTo . ' ' . substr($filterTimeTo,0,5)) ?></h3>
    <div style="margin-bottom: 15px;">
        <form action="actions/download_report.php" method="POST" style="display:inline-block; margin-right:10px;">
            <input type="hidden" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
            <input type="hidden" name="time_from" value="<?= htmlspecialchars($filterTimeFrom) ?>">
            <input type="hidden" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
            <input type="hidden" name="time_to" value="<?= htmlspecialchars($filterTimeTo) ?>">
            <input type="hidden" name="format" value="csv">
            <button type="submit" class="button-link secondary">Download as CSV</button>
        </form>
        <form action="actions/download_report.php" method="POST" style="display:inline-block;">
            <input type="hidden" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
            <input type="hidden" name="time_from" value="<?= htmlspecialchars($filterTimeFrom) ?>">
            <input type="hidden" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
            <input type="hidden" name="time_to" value="<?= htmlspecialchars($filterTimeTo) ?>">
            <input type="hidden" name="format" value="pdf">
            <button type="submit" class="button-link secondary">Download as PDF</button>
        </form>
        <form action="actions/send_eod_report.php" method="POST" style="display:inline-block;">
    <input type="hidden" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
    <input type="hidden" name="time_from" value="<?= htmlspecialchars($filterTimeFrom) ?>">
    <input type="hidden" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
    <input type="hidden" name="time_to" value="<?= htmlspecialchars($filterTimeTo) ?>">
    <button type="submit" class="button-link">Send EOD Report</button>
</form>
        <!-- The "Press Alt + h + o + i" seems like a custom Excel shortcut instruction, which is fine to keep if relevant -->
        <p>Press Alt + h + o + i to extract the excel file </p>
    </div>

    <table class="reports-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Task Name</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Duration</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $grandTotalSeconds = 0;
            foreach ($reportEntries as $entry):
                $durationStr = "Ongoing";
                $currentEntrySeconds = 0;
                if ($entry['end_time']) {
                    $start = new DateTime($entry['start_time']);
                    $end = new DateTime($entry['end_time']);
                    $interval = $start->diff($end);
                    $durationStr = $interval->format('%Hh %Im %Ss');
                    $currentEntrySeconds = ($end->getTimestamp() - $start->getTimestamp());
                    $grandTotalSeconds += $currentEntrySeconds;
                } else {
                    // For ongoing tasks, calculate duration up to "now" or the end of the filter period if "now" is past it
                    $now = new DateTime();
                    $effectiveEndTimeForDuration = $now; 
                    // If you want to cap duration at the filter's end time for ongoing tasks:
                    // $filterEndDateTimeObj = new DateTime($endDateTimeFilter);
                    // if ($now > $filterEndDateTimeObj) $effectiveEndTimeForDuration = $filterEndDateTimeObj;
                    
                    $start = new DateTime($entry['start_time']);
                    $interval = $start->diff($effectiveEndTimeForDuration);
                    $durationStr = $interval->format('%Hh %Im %Ss') . " (Ongoing)";

                }
            ?>
            <tr>
                <td><?= htmlspecialchars(date('Y-m-d', strtotime($entry['start_time']))) ?></td>
                <td><?= htmlspecialchars($entry['task_name']) ?></td>
                <td><?= date('H:i:s', strtotime($entry['start_time'])) ?></td>
                <td><?= $entry['end_time'] ? date('H:i:s', strtotime($entry['end_time'])) : 'Active' ?></td>
                <td><?= $durationStr ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right; font-weight:bold;">Total Logged Time for Period:</td>
                <td style="font-weight:bold;">
                    <?php
                        $hours = floor($grandTotalSeconds / 3600);
                        $minutes = floor(($grandTotalSeconds % 3600) / 60);
                        $seconds = $grandTotalSeconds % 60;
                        echo sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);
                    ?>
                </td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateFromInput = document.getElementById('date_from');
    const timeFromInput = document.getElementById('time_from');
    const dateToInput = document.getElementById('date_to');
    const timeToInput = document.getElementById('time_to');

    function validateDateTimeRange() {
        if (dateFromInput.value && timeFromInput.value && dateToInput.value && timeToInput.value) {
            const fromDateTimeStr = dateFromInput.value + 'T' + timeFromInput.value;
            const toDateTimeStr = dateToInput.value + 'T' + timeToInput.value;

            try {
                const from = new Date(fromDateTimeStr);
                const to = new Date(toDateTimeStr);

                if (to < from) {
                    alert("'To Date/Time' cannot be earlier than 'From Date/Time'.");
                    // Consider resetting dateToInput or timeToInput
                    return false;
                }

                // 3 Month Validation (Simplified, focuses on date part)
                let tempFromDate = new Date(dateFromInput.value);
                tempFromDate.setMonth(tempFromDate.getMonth() + 3);
                let toDate = new Date(dateToInput.value);

                if (toDate > tempFromDate) {
                    let diffInMonths = (toDate.getFullYear() - new Date(dateFromInput.value).getFullYear()) * 12 + (toDate.getMonth() - new Date(dateFromInput.value).getMonth());
                    if (toDate.getDate() > new Date(dateFromInput.value).getDate() && diffInMonths >=3) {
                         alert("The date range cannot exceed 3 months.");
                         return false;
                    } else if (diffInMonths > 3) {
                         alert("The date range cannot exceed 3 months.");
                         return false;
                    }
                }
            } catch (e) {
                // Invalid date/time string, browser might handle this but good to be aware
                console.error("Error parsing date/time for validation:", e);
                return false;
            }
        }
        return true;
    }

    // Attach listeners
    const inputsToValidate = [dateFromInput, timeFromInput, dateToInput, timeToInput];
    inputsToValidate.forEach(input => {
        if (input) { // Check if element exists
            input.addEventListener('change', validateDateTimeRange);
        }
    });

    // Also attach to form submit for a final check
    const reportForm = document.querySelector('form[action="reports.php"]');
    if (reportForm) {
        reportForm.addEventListener('submit', function(event) {
            if (!validateDateTimeRange()) {
                event.preventDefault(); // Stop form submission if validation fails
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
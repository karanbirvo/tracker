<?php
require_once '../includes/functions.php';
require_once '../lib/fpdf/fpdf.php'; // Ensure path to FPDF is correct
requireLogin();

$userId = $_SESSION['user_id'];
$requestingUserId = $userId;
$usernameForFile = $_SESSION['username'];

// For Admin downloading other user's report
if (isset($_POST['target_user_id']) && is_numeric($_POST['target_user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $targetUserId = (int)$_POST['target_user_id'];
    // Fetch target user's username for the filename
    $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmtUser->execute([$targetUserId]);
    $targetUserData = $stmtUser->fetch();
    if ($targetUserData) {
        $requestingUserId = $targetUserId;
        $usernameForFile = $targetUserData['username'];
    } else {
        // Handle error: target user not found
        $_SESSION['message'] = "Target user for report not found.";
        $_SESSION['message_type'] = "danger";
        header("Location: ../admin_reports.php"); // Or appropriate redirect
        exit();
    }
}


// Default date and time values
$defaultDate = date('Y-m-d');
$defaultStartTime = '00:00:00';
$defaultEndTime = '23:59:59';

// Get date and time parameters from POST
$dateFrom = isset($_POST['date_from']) && !empty($_POST['date_from']) ? $_POST['date_from'] : $defaultDate;
$timeFrom = isset($_POST['time_from']) && !empty($_POST['time_from']) ? $_POST['time_from'] : $defaultStartTime;
$dateTo = isset($_POST['date_to']) && !empty($_POST['date_to']) ? $_POST['date_to'] : $dateFrom; // Default to_date to from_date if not set
$timeTo = isset($_POST['time_to']) && !empty($_POST['time_to']) ? $_POST['time_to'] : $defaultEndTime;

// --- Validate and Sanitize Inputs for Download Script ---
// Date From
$dFromObj = DateTime::createFromFormat('Y-m-d', $dateFrom);
if (!$dFromObj || $dFromObj->format('Y-m-d') !== $dateFrom) $dateFrom = $defaultDate;
// Time From
$tFromObj = DateTime::createFromFormat('H:i:s', $timeFrom);
if (!$tFromObj) $tFromObj = DateTime::createFromFormat('H:i', $timeFrom);
if (!$tFromObj || ($tFromObj->format('H:i:s') !== $timeFrom && $tFromObj->format('H:i') !== $timeFrom)) {
    $timeFrom = $defaultStartTime;
} else {
    $timeFrom = $tFromObj->format('H:i:s');
}

// Date To
$dToObj = DateTime::createFromFormat('Y-m-d', $dateTo);
if (!$dToObj || $dToObj->format('Y-m-d') !== $dateTo) $dateTo = $dateFrom; // Default to $dateFrom
// Time To
$tToObj = DateTime::createFromFormat('H:i:s', $timeTo);
if (!$tToObj) $tToObj = DateTime::createFromFormat('H:i', $timeTo);
if (!$tToObj || ($tToObj->format('H:i:s') !== $timeTo && $tToObj->format('H:i') !== $timeTo)) {
    $timeTo = $defaultEndTime;
} else {
    $timeTo = $tToObj->format('H:i:s');
}
// --- End Validation ---

$format = (isset($_POST['format']) && in_array($_POST['format'], ['csv', 'pdf'])) ? $_POST['format'] : 'csv';

// Combine date and time for SQL
$startDateTimeFilter = $dateFrom . ' ' . $timeFrom;
$endDateTimeFilter = $dateTo . ' ' . $timeTo;


// Validate date range for download (max 3 months) - applies to date part primarily
$startDateObj = new DateTime($dateFrom);
$endDateObj = new DateTime($dateTo);
$interval = $startDateObj->diff($endDateObj);
$months = $interval->y * 12 + $interval->m;
if ($interval->d > 0 && $endDateObj > $startDateObj) { // Basic check for days spilling into next month for 3 month limit
    $months++;
}

if ($months > 3) {
    $_SESSION['message'] = "Report download range cannot exceed 3 months.";
    $_SESSION['message_type'] = "danger";
    $redirectUrl = (isset($_POST['target_user_id']) && $_SESSION['user_role'] === 'admin') ? '../admin_reports.php' : '../reports.php';
    // Append GET params to redirect URL if needed
    $redirectParams = "?date_from=$dateFrom&time_from=".substr($timeFrom,0,5)."&date_to=$dateTo&time_to=".substr($timeTo,0,5);
    if(isset($_POST['target_user_id'])) $redirectParams .= "&user_id_selected=".$_POST['target_user_id'];
    header("Location: " . $redirectUrl . $redirectParams);
    exit();
}

// If this is an "End Day" action from index.php, stop the current task for the logged-in user
if (isset($_POST['source']) && $_POST['source'] === 'end_day' && $requestingUserId === $userId) {
    stopCurrentTask($userId, $pdo);
    // For "End Day", the date/time range will be for today, from 00:00 to 23:59 implicitly by default values
    // or explicitly if the "End Day" button's form values were different.
}

// Fetch entries for the specified datetime range and user
$stmt = $pdo->prepare("SELECT * FROM time_entries 
                       WHERE user_id = :user_id 
                       AND start_time >= :start_datetime 
                       AND start_time <= :end_datetime 
                       ORDER BY start_time ASC");
$stmt->execute([
    'user_id' => $requestingUserId,
    'start_datetime' => $startDateTimeFilter,
    'end_datetime' => $endDateTimeFilter
]);
$entriesForReport = $stmt->fetchAll();

if (empty($entriesForReport)) {
    $_SESSION['message'] = "No entries for " . htmlspecialchars($usernameForFile) . " within the selected date/time range (" . htmlspecialchars($startDateTimeFilter) . " to " . htmlspecialchars($endDateTimeFilter) . ").";
    $_SESSION['message_type'] = "info";
    $redirectUrl = (isset($_POST['target_user_id']) && $_SESSION['user_role'] === 'admin') ? '../admin_reports.php' : '../reports.php';
    $redirectParams = "?date_from=$dateFrom&time_from=".substr($timeFrom,0,5)."&date_to=$dateTo&time_to=".substr($timeTo,0,5);
    if(isset($_POST['target_user_id'])) $redirectParams .= "&user_id_selected=".$_POST['target_user_id'];
    header("Location: " . $redirectUrl . $redirectParams);
    exit();
}

$filename = "TimeReport_" . $usernameForFile . "_" . str_replace('-', '', $dateFrom) . str_replace(':', '', substr($timeFrom,0,5)) . "_to_" . str_replace('-', '', $dateTo) . str_replace(':', '', substr($timeTo,0,5)) . "." . $format;

if ($format == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Task Name', 'Start Time (Full)', 'End Time (Full)', 'Duration (HH:MM:SS)']);
    foreach ($entriesForReport as $entry) {
        $startTime = new DateTime($entry['start_time']);
        $endTime = $entry['end_time'] ? new DateTime($entry['end_time']) : null; // Use current if somehow not ended
        
        $durationFormatted = 'N/A (Ongoing)';
        if($endTime){
            $durationInterval = $startTime->diff($endTime);
            $durationFormatted = $durationInterval->format('%H:%I:%S');
        } else {
            // If task is ongoing, calculate duration up to "now" for the report
            $now = new DateTime();
            $durationInterval = $startTime->diff($now);
            $durationFormatted = $durationInterval->format('%H:%I:%S') . ' (Ongoing)';
        }

        fputcsv($output, [
            $startTime->format('Y-m-d'), // Date part of start_time
            $entry['task_name'],
            $startTime->format('Y-m-d H:i:s'),
            $entry['end_time'] ? $endTime->format('Y-m-d H:i:s') : 'N/A',
            $durationFormatted
        ]);
    }
    fclose($output);
} elseif ($format == 'pdf') {
    $pdf = new FPDF();
    $pdf->AddPage('L'); // Landscape for more space
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Time Report for ' . htmlspecialchars($usernameForFile), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 7, 'Period: ' . htmlspecialchars($startDateTimeFilter) . ' to ' . htmlspecialchars($endDateTimeFilter), 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 9); // Smaller font for more columns
    $pdf->Cell(25, 7, 'Date', 1);
    $pdf->Cell(80, 7, 'Task Name', 1); // Wider task name
    $pdf->Cell(45, 7, 'Start DateTime', 1);
    $pdf->Cell(45, 7, 'End DateTime', 1);
    $pdf->Cell(35, 7, 'Duration', 1); // Wider duration
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 8);
    $grandTotalSeconds = 0;
    foreach ($entriesForReport as $entry) {
        $startTime = new DateTime($entry['start_time']);
        $endTime = $entry['end_time'] ? new DateTime($entry['end_time']) : null;

        $durationFormatted = 'N/A (Ongoing)';
        $currentEntrySeconds = 0;
        if($endTime){
            $durationInterval = $startTime->diff($endTime);
            $durationFormatted = $durationInterval->format('%H:%I:%S');
            $currentEntrySeconds = ($endTime->getTimestamp() - $startTime->getTimestamp());
            $grandTotalSeconds += $currentEntrySeconds;
        } else {
            $now = new DateTime();
            $durationInterval = $startTime->diff($now);
            $durationFormatted = $durationInterval->format('%H:%I:%S') . ' (Ongoing)';
        }

        $pdf->Cell(25, 6, $startTime->format('Y-m-d'), 1);
        $pdf->Cell(80, 6, substr(htmlspecialchars($entry['task_name']), 0, 50), 1); // substr for safety
        $pdf->Cell(45, 6, $startTime->format('Y-m-d H:i:s'), 1);
        $pdf->Cell(45, 6, $entry['end_time'] ? $endTime->format('Y-m-d H:i:s') : 'N/A', 1);
        $pdf->Cell(35, 6, $durationFormatted, 1);
        $pdf->Ln();
    }
    
    $hours = floor($grandTotalSeconds / 3600);
    $minutes = floor(($grandTotalSeconds % 3600) / 60);
    $seconds = $grandTotalSeconds % 60;
    $totalDurationFormatted = sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(25 + 80 + 45 + 45, 7, 'Total Logged Time:', 1, 0, 'R'); // Sum of previous cell widths
    $pdf->Cell(35, 7, $totalDurationFormatted, 1, 1, 'L');

    $pdf->Output('D', $filename);
}
exit();
?>
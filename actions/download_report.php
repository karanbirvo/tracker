<?php
// 1. SUPPRESS ERRORS & CLEAN BUFFER
error_reporting(0);
ini_set('display_errors', 0);
if (ob_get_level()) { ob_end_clean(); }

require_once '../includes/functions.php';
require_once '../lib/fpdf/fpdf.php';
requireLogin();

// --- HELPER FUNCTION TO CLEAN TEXT FOR PDF ---
function clean_text($text) {
    // Decode HTML entities like &quot; -> "
    $text = htmlspecialchars_decode($text, ENT_QUOTES);
    // Transliterate UTF-8 to something FPDF can handle (ISO-8859-1), replacing unsupported chars
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
}


class ReportPDF extends FPDF {
    function Header() {
        if (file_exists('../images/logo.png')) $this->Image('../images/logo.png', 10, 8, 40);
        else { $this->SetFont('Arial', 'B', 14); $this->Cell(40, 10, 'VO TimeTracker'); }
        $this->SetFont('Arial', 'B', 18); $this->Cell(0, 10, 'Time Report', 0, 1, 'C');
        $this->Ln(10);
    }
    function Footer() {
        $this->SetY(-15); $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    function DrawTableHeader($widths) {
        $this->SetFont('Arial', 'B', 8); $this->SetFillColor(245, 245, 245); $this->SetTextColor(100);
        $this->Cell($widths[0], 10, 'TYPE', 1, 0, 'C', true);
        $this->Cell($widths[1], 10, 'DATE', 1, 0, 'C', true);
        $this->Cell($widths[2], 10, 'PROJECT', 1, 0, 'C', true);
        $this->Cell($widths[3], 10, 'TASK', 1, 0, 'C', true);
        $this->Cell($widths[4], 10, 'NOTES / DESCRIPTION', 1, 0, 'C', true);
        $this->Cell($widths[5], 10, 'TIME RANGE', 1, 0, 'C', true);
        $this->Cell($widths[6], 10, 'DURATION', 1, 1, 'C', true);
        $this->SetTextColor(0);
    }
    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw']; if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize; $s = str_replace("\r", '', $txt);
        $nb = strlen($s); if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i]; if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i; $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; } else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }
}

// 2. Get Parameters and Data
$requestingUserForQuery = $_SESSION['user_id'];
$usernameForFile = $_SESSION['username'];
$dateFrom = $_POST['date_from'] ?? date('Y-m-01');
$dateTo = $_POST['date_to'] ?? date('Y-m-d');
$format = $_POST['format'] ?? 'csv';

$sql = "SELECT combined.*, p.project_name FROM (SELECT task_name, description, start_time, end_time, project_id, 'tracker' as type FROM time_entries WHERE user_id = :uid1 UNION ALL SELECT task_name, description, start_time, end_time, project_id, 'manual' as type FROM manual_entries WHERE user_id = :uid2) AS combined LEFT JOIN projects p ON combined.project_id = p.id WHERE combined.start_time >= :start_dt AND combined.start_time <= :end_dt ORDER BY combined.start_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['uid1' => $requestingUserForQuery, 'uid2' => $requestingUserForQuery, 'start_dt' => "$dateFrom 00:00:00", 'end_dt' => "$dateTo 23:59:59"]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$entries) { exit("No entries found for this period."); }
$filename = "TimeReport_{$usernameForFile}_{$dateFrom}_to_{$dateTo}.{$format}";

// 4. Generate PDF
if ($format == 'pdf') {
    $pdf = new ReportPDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, clean_text("Report for: " . $usernameForFile), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, "Period: " . date('M j, Y', strtotime($dateFrom)) . " to " . date('M j, Y', strtotime($dateTo)), 0, 1);
    $pdf->Ln(8);

    $colWidths = [22, 23, 35, 50, 65, 25, 25];
    $pdf->DrawTableHeader($colWidths);
    $totalSeconds = 0;

    foreach ($entries as $entry) {
        // --- DYNAMIC HEIGHT CALCULATION ---
        $pdf->SetFont('Arial', 'B', 9);
        $taskLines = $pdf->NbLines($colWidths[3] - 4, clean_text($entry['task_name']));
        
        $pdf->SetFont('Arial', '', 8);
        $descLines = !empty($entry['description']) ? $pdf->NbLines($colWidths[4] - 4, clean_text($entry['description'])) : 0;
        
        $lineHeight = 5;
        $rowHeight = max($taskLines, $descLines, 1) * $lineHeight + 4; // Add padding

        if ($pdf->GetY() + $rowHeight > $pdf->GetPageHeight() - 25) {
            $pdf->AddPage();
            $pdf->DrawTableHeader($colWidths);
        }

        $y = $pdf->GetY();
        $x = $pdf->GetX();

        // --- MANUALLY DRAW ROW ---
        // Draw Borders
        $pdf->Rect($x, $y, array_sum($colWidths), $rowHeight);
        $currentX = $x;
        for($i=0; $i<count($colWidths)-1; $i++) {
            $currentX += $colWidths[$i];
            $pdf->Line($currentX, $y, $currentX, $y + $rowHeight);
        }

        // --- Fill Content with Vertical Centering ---
        $vPadding = ($rowHeight / 2) - ($lineHeight / 2);

        // Type Badge
        if ($entry['type'] === 'tracker') { $pdf->SetFillColor(209, 250, 229); $pdf->SetTextColor(21, 128, 61); } 
        else { $pdf->SetFillColor(254, 226, 226); $pdf->SetTextColor(185, 28, 28); }
        $pdf->Rect($x, $y, $colWidths[0], $rowHeight, 'F');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetXY($x, $y + $vPadding); $pdf->Cell($colWidths[0], $lineHeight, strtoupper($entry['type']), 0, 0, 'C');
        $pdf->SetTextColor(0);

        // Simple Cells
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY($x + $colWidths[0], $y + $vPadding);
        $pdf->Cell($colWidths[1], $lineHeight, date('M j, Y', strtotime($entry['start_time'])), 0, 0, 'C');
        
        $pdf->SetXY($x + $colWidths[0] + $colWidths[1], $y + $vPadding);
        $pdf->Cell($colWidths[2], $lineHeight, clean_text($entry['project_name'] ?? '-'), 0, 0, 'C');
        
        // Task (MultiCell for wrapping)
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetXY($x + $colWidths[0] + $colWidths[1] + $colWidths[2] + 2, $y + 2);
        $pdf->MultiCell($colWidths[3] - 4, $lineHeight, clean_text($entry['task_name']), 0, 'L');
        
        // Notes (MultiCell for wrapping)
        $pdf->SetFont('Arial', 'I', 8); $pdf->SetTextColor(100);
        $pdf->SetXY($x + $colWidths[0] + $colWidths[1] + $colWidths[2] + $colWidths[3] + 2, $y + 2);
        $pdf->MultiCell($colWidths[4] - 4, $lineHeight, clean_text($entry['description']), 0, 'L');
        $pdf->SetTextColor(0);

        // Time Range
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY($x + array_sum(array_slice($colWidths, 0, 5)), $y + $vPadding);
        $pdf->Cell($colWidths[5], $lineHeight, date('H:i', strtotime($entry['start_time'])) . ' - ' . ($entry['end_time'] ? date('H:i', strtotime($entry['end_time'])) : '...'), 0, 0, 'C');
        
        // Duration
        $durationStr = "Running...";
        if ($entry['end_time']) {
            $start = new DateTime($entry['start_time']); $end = new DateTime($entry['end_time']);
            $currentSeconds = $end->getTimestamp() - $start->getTimestamp();
            $totalSeconds += $currentSeconds;
            $durationStr = sprintf('%02dh %02dm', floor($currentSeconds / 3600), floor(($currentSeconds % 3600) / 60));
        }
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetXY($x + array_sum(array_slice($colWidths, 0, 6)), $y + $vPadding);
        $pdf->Cell($colWidths[6], $lineHeight, $durationStr, 0, 0, 'C');
        
        // Move cursor to the start of the next row
        $pdf->SetY($y + $rowHeight);
    }
    
    // Grand Total
    $pdf->SetFont('Arial', 'B', 10); $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(array_sum($colWidths) - $colWidths[6], 10, 'TOTAL DURATION', 1, 0, 'R', true);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell($colWidths[6], 10, sprintf('%02dh %02dm', floor($totalSeconds / 3600), floor(($totalSeconds % 3600) / 60)), 1, 1, 'C', true);

    $pdf->Output('D', $filename);
} 
// --- 5. CSV Generation ---
else {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Project', 'Task Name', 'Description', 'Start Time', 'End Time', 'Duration (HH:MM:SS)', 'Type']);
    foreach ($entries as $entry) {
        $duration = 'N/A';
        if ($entry['end_time']) { $start = new DateTime($entry['start_time']); $end = new DateTime($entry['end_time']); $duration = $start->diff($end)->format('%H:%I:%S'); }
        fputcsv($output, [ date('Y-m-d', strtotime($entry['start_time'])), $entry['project_name'] ?? '', $entry['task_name'], $entry['description'] ?? '', $entry['start_time'], $entry['end_time'] ?? 'N/A', $duration, $entry['type'] ]);
    }
    fclose($output);
}
exit();
?>
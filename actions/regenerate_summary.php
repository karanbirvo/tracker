<?php

require_once '../includes/functions.php';
requireLogin();

// Permission Check
if (!hasPermission('perm_ai')) {
    echo json_encode([
        'success' => false,
        'message' => 'Access Denied: AI permission required.'
    ]);
    exit();
}

header('Content-Type: application/json');

// --- Timezone ---
date_default_timezone_set('Asia/Kolkata');
$currentDate = date('d F Y');

// --- Check Gemini API Key ---
if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY') {
    echo json_encode([
        'success' => false,
        'message' => 'Gemini API key is not configured.'
    ]);
    exit();
}

// --- Get Form Data ---
$reportData = $_POST['report_data'] ?? '';

if (empty($reportData)) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: No report text provided.'
    ]);
    exit();
}

$userId = $_SESSION['user_id'];

// --------------------------------------
// CALCULATE TRACKER HOURS FROM DATABASE
// --------------------------------------

try {

    $stmt = $pdo->prepare("
        SELECT start_time,end_time
        FROM time_entries
        WHERE user_id = ?
        AND DATE(start_time) = CURDATE()
        AND end_time IS NOT NULL
    ");

    $stmt->execute([$userId]);

    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalSeconds = 0;

    foreach ($entries as $entry) {

        $start = strtotime($entry['start_time']);
        $end   = strtotime($entry['end_time']);

        if ($start && $end) {
            $totalSeconds += ($end - $start);
        }
    }

    $hours = floor($totalSeconds / 3600);
    $minutes = floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;

    $totalTracker = sprintf("%02dh %02dm %02ds", $hours, $minutes, $seconds);

} catch (PDOException $e) {

    error_log("Tracker Calculation Error: " . $e->getMessage());
    $totalTracker = "N/A";
}

// --- Gemini API Endpoint ---
$apiKey = GEMINI_API_KEY;
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

// Replace placeholders with calculated values

$reportData = str_replace(
    '[Please fill]',
    'N/A',
    $reportData
);

$reportData = str_replace(
    'Total Tracker: N/A',
    'Total Tracker: ' . $totalTracker,
    $reportData
);

$reportData = str_replace(
    'Running Tracker: N/A',
    'Running Tracker: ' . $totalTracker,
    $reportData
);
// --------------------------------------
// AI PROMPT
// --------------------------------------
$prompt = "
You are a professional assistant tasked with rewriting an EOD report into a polished email body.

Today's Date: $currentDate

IMPORTANT RULES:

1. Always use this date: $currentDate
2. Replace any '[Insert Date]' with $currentDate
3. If any field is empty, write N/A
4. If 'Work Pending' has no data, write N/A
5. Do NOT invent numbers or times
6. Do NOT include exact times
7. Output must be CLEAN HTML suitable for email body
8. Do NOT include markdown or ```html

EOD REPORT FORMAT:

Date: $currentDate

1. Project Updates

Project Name: [Client or Project Title]

Page/Task Name: [Page name or feature worked on]

Design/Preview Link: [Figma / Webflow / Staging link / Project Link]

Work Completed:
[List tasks]

Work Pending:
[List tasks or N/A]

2. Tracker Status

Total Tracker: $totalTracker

Running Tracker: $totalTracker

Pending Tracker: N/A

3. System Details

System Seat Number: if data available add -> [Seat Number], else -> 'Contact IT support team' if unavailable

System Password: if data available add -> [Password], else -> 'Contact IT support team' if unavailable

Here is the raw report data to rewrite:
4. Client Communication
if data available add -> [Any updates/Message for client] else add -> no specific information available for client or already updated to client

5. Message for BD Team
[Follow-up requirements] else add -> no specific information available for BD Team or already updated to BD Team or gemini please generate a dandom messege from upper projects description regarding what i do todat in 100 words
$reportData
";
//GEMINI REQUEST
// --------------------------------------

$data = [
    "contents" => [
        [
            "parts" => [
                [
                    "text" => $prompt
                ]
            ]
        ]
    ]
];


// --------------------------------------
// CURL REQUEST
// --------------------------------------

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);


// --- SSL CERTIFICATE ---
$ca_path = __DIR__ . '/../../certs/cacert.pem';

if (file_exists($ca_path)) {
    curl_setopt($ch, CURLOPT_CAINFO, $ca_path);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
} else {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
}


// --- EXECUTE REQUEST ---
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

curl_close($ch);


// --------------------------------------
// ERROR HANDLING
// --------------------------------------

if ($response === false || $http_code != 200) {

    $error = "cURL Error: " . ($curl_error ?: 'Unknown');
    $error .= " | HTTP Code: " . $http_code;

    error_log("Gemini API Error: " . $error . " | Raw Response: " . $response);

    echo json_encode([
        'success' => false,
        'message' => 'Could not connect to AI service. ' . $error
    ]);

    exit();
}


// --------------------------------------
// PARSE RESPONSE
// --------------------------------------

$result = json_decode($response, true);

$generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($generatedText)) {

    error_log("Gemini Response Error: " . $response);

    echo json_encode([
        'success' => false,
        'message' => 'The AI returned an empty response.'
    ]);

    exit();
}


// --- Replace Date Safety ---
$generatedText = str_replace('[Insert Date]', $currentDate, $generatedText);


// --------------------------------------
// LOG AI RESPONSE
// --------------------------------------

try {

    $logStmt = $pdo->prepare("
        INSERT INTO ai_logs (user_id, prompt_text, response_text)
        VALUES (?, ?, ?)
    ");

    $logStmt->execute([
        $_SESSION['user_id'],
        strip_tags($reportData),
        $generatedText
    ]);

} catch (PDOException $e) {

    error_log("AI Log Save Error: " . $e->getMessage());
}


// --------------------------------------
// RETURN RESULT
// --------------------------------------

echo json_encode([
    'success' => true,
    'summary' => trim($generatedText)
]);

?>

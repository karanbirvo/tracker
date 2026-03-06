<?php
require_once '../includes/functions.php';
requireLogin();

// Permission Check
if (!hasPermission('perm_ai')) {
    echo json_encode(['success' => false, 'message' => 'Access Denied: AI permission required.']);
    exit();
}

header('Content-Type: application/json');

// --- Get Current Date in IST ---
date_default_timezone_set('Asia/Kolkata');
$currentDate = date('d F Y');

// --- Check Gemini API Key ---
if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY' || empty(GEMINI_API_KEY)) {
    echo json_encode(['success' => false, 'message' => 'Error: Gemini API key is not configured in includes/db.php']);
    exit();
}

// --- Get Form Data ---
$reportData = $_POST['report_data'] ?? '';

if (empty($reportData)) {
    echo json_encode(['success' => false, 'message' => 'Error: No text was sent to the AI.']);
    exit();
}

$apiKey = GEMINI_API_KEY;

// Updated Gemini API Endpoint
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

// --- AI Prompt ---
$prompt = "
You are a professional assistant.

Today's Date: $currentDate

Your task is to refine and rewrite the following EOD report draft into a professional email body.

IMPORTANT RULES:

1. Always use this date: $currentDate
2. Replace any '[Insert Date]' with $currentDate
3. If any field is empty write N/A
4. If Work Pending has no data write N/A
5. If Tracker fields have no values write N/A
6. Do NOT invent fake numbers
7. Do NOT add exact times
8. Output must be CLEAN HTML for email body
9. Do NOT add markdown
10. Do NOT add ```html or ``` anywhere

EOD REPORT FORMAT

Date: $currentDate

1. Project Updates

Project Name: [Client or Project Title]

Page/Task Name: [Page name or feature worked on]

Design/Preview Link: [Figma / Webflow / Staging link / Project Link]

Work Completed:
[List tasks]

Work Pending:
If empty write N/A

2. Tracker Status

Total Tracker: If not provided write N/A

Running Tracker: If not provided write N/A

Pending Tracker: If not provided write N/A

3. System Details

System Seat Number: [Seat Number]

System Password: [Password]

Tracker Tabs Open: Yes / No / N/A

4. Client Communication

If no message write [No specific updates or messages for the client were provided in the draft.]

5. Message for BD Team

If not applicable write [No specific follow-up requirements for the Night Shift were provided in the draft.]

ADDITIONAL RULES

• If multiple tasks exist, group them by project.
• Make the report clean and professional.
• At the end add a small note that tracker values are approximate.

CRITICAL OUTPUT RULES

• Do NOT wrap response in markdown
• Do NOT include ```html
• Start directly with HTML tags like <p>

Here is the draft to improve:

---
" . strip_tags($reportData);


// --- API Request Data ---
$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ]
];

// --- cURL Request ---
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// SSL Certificate
$ca_path = __DIR__ . '/../../certs/cacert.pem';

if (file_exists($ca_path)) {
    curl_setopt($ch, CURLOPT_CAINFO, $ca_path);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
} else {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
}

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

curl_close($ch);

// --- Error Handling ---
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

// --- Decode Response ---
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

// --- Force Replace Date (Extra Safety) ---
$generatedText = str_replace('[Insert Date]', $currentDate, $generatedText);

// --- Database Logging ---
try {

    $logStmt = $pdo->prepare(
        "INSERT INTO ai_logs (user_id, prompt_text, response_text) VALUES (?, ?, ?)"
    );

    $logStmt->execute([
        $_SESSION['user_id'],
        strip_tags($reportData),
        $generatedText
    ]);

} catch (PDOException $e) {

    error_log("AI Log Save Error: " . $e->getMessage());
}

// --- Return Result ---
echo json_encode([
    'success' => true,
    'summary' => trim($generatedText)
]);
?>

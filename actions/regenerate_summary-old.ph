<?php
require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

// --- Step 1: Check for the API Key ---
if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY' || empty(GEMINI_API_KEY)) {
    echo json_encode(['success' => false, 'message' => 'Error: Gemini API key is not configured in the includes/db.php file.']);
    exit();
}

// --- Step 2: Get the data from the form ---
$reportData = $_POST['report_data'] ?? '';
if (empty($reportData)) {
    echo json_encode(['success' => false, 'message' => 'Error: No text was sent to the AI.']);
    exit();
}

// --- Step 3: Prepare the API Request ---
$apiKey = GEMINI_API_KEY;
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

// Your detailed, custom prompt is preserved exactly as you wrote it.
$prompt = "You are a professional assistant. Your task is to refine and rewrite the following EOD report draft into a clear and professional email body.

**Instructions:**
1.  Correct any grammar or spelling mistakes.
2.  Improve the overall tone and flow.
3.  The final output must be in clean HTML format, suitable for an email body.

**Crucial Rule:**
- **DO NOT** wrap your response in Markdown code blocks.
- **DO NOT** include ```html or ``` at the beginning or end of your response.
- Your response must start directly with the first HTML tag (e.g., `<p>Hello Team,`)
- also do not add exact time and make sure if the content is about 3 4 and more projects  at that time please add the report project vise please categorise the content and at last make sure please add not this is ai generated content so its not a actual or exact values its aprox values   make sure pleas edetect   is it is a project type or something else so add content accordingly

Here is the draft to improve:
---
" . strip_tags($reportData); // Send clean text to the AI

$data = [
    'contents' => [
        [ 'parts' => [ ['text' => $prompt] ] ]
    ]
];

// --- Step 4: Make the cURL Request ---
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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

// --- Step 5: Process the Response with Enhanced Error Reporting ---
if ($response === false || $http_code != 200) {
    $detailed_error = "cURL Error: " . ($curl_error ? $curl_error : 'No cURL error message.');
    $detailed_error .= " | HTTP Code: " . $http_code;
    error_log("Gemini API Error: " . $detailed_error . " | Raw Response: " . $response);
    echo json_encode(['success' => false, 'message' => 'Could not connect to the AI service. ' . $detailed_error]);
    exit();
}

$result = json_decode($response, true);
$generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($generatedText)) {
    error_log("Gemini API Response Format Error: " . $response);
    echo json_encode(['success' => false, 'message' => 'The AI returned an empty or invalid response.']);
    exit();
}

// ===================================================================
// === NEW: LOG THE SUCCESSFUL INTERACTION TO THE DATABASE ===========
// ===================================================================
try {
    $logStmt = $pdo->prepare(
        "INSERT INTO ai_logs (user_id, prompt_text, response_text) VALUES (?, ?, ?)"
    );
    // We log the original, unformatted text that was sent (prompt_text)
    // and the formatted HTML we received from the AI (response_text)
    $logStmt->execute([$_SESSION['user_id'], strip_tags($reportData), $generatedText]);
} catch (PDOException $e) {
    // If logging fails for any reason, don't break the user's experience.
    // Just log the database error for the admin to review later.
    error_log("CRITICAL: Failed to save AI log to database: " . $e->getMessage());
}
// ===================================================================

// Success! Return the AI-generated HTML
echo json_encode([
    'success' => true,
    'summary' => trim($generatedText)
]);
?>
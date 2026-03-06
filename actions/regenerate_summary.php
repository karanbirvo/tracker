<?php
require_once '../includes/functions.php';
requireLogin();
// 1. Permission Check
if (!hasPermission('perm_ai')) {
    echo json_encode(['success' => false, 'message' => 'Access Denied: AI permission required.']);
    exit();
}

header('Content-Type: application/json');

// --- Step 1: Check for the API Key ---
if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY' || GEMINI_API_KEY === '123456' || empty(GEMINI_API_KEY)) {
    echo json_encode(['success' => false, 'message' => 'Error: Gemini API key is not configured. Please add your API key at: /api_keys_settings.php']);
    exit();
}

// --- Step 2: Get the data from the form (No changes here) ---
$reportData = $_POST['report_data'] ?? '';
if (empty($reportData)) {
    echo json_encode(['success' => false, 'message' => 'Error: No text was sent to the AI.']);
    exit();
}

// --- Step 3: Prepare the API Request (URL is updated) ---
$apiKey = GEMINI_API_KEY;

// ===================================================================
// === THE FIX IS HERE: UPDATED API URL AND MODEL ====================
// ===================================================================
// The old URL used 'v1beta', which has likely been retired by Google, causing the 404 error.
// We are updating to the new, stable v1 endpoint and the latest, most efficient Flash model.
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
// ===================================================================
// === END OF FIX ====================================================
// ===================================================================

// Your detailed, custom prompt is preserved exactly as you wrote it.
// Get today's date to pass to Gemini
$todaysDate = date('F d, Y');
$prompt = "You are a professional assistant. Your task is to refine and rewrite the following EOD report draft into a clear and professional email body.
**Instructions:**
1.  Correct any grammar or spelling mistakes.
2.  Improve the overall tone and flow.
3.  The final output must be in clean HTML format, suitable for an email body.
4. Add today's date (" . $todaysDate . ") in the date field.
 here is the format of eod report which i want 

 ***format of eod report start ***
 EOD Report Format
Date: " . $todaysDate . " ->> This is todays date
1. Project Updates
Project Name: [Client or Project Title]
Page/Task Name: [Page name or feature worked on]
Design/Preview Link: [Figma / Webflow / Staging link / Project Link etc.]
Work Completed: [List of completed tasks]
Work Pending: [Tasks in progress or scheduled for the next day]
2. Tracker Status
Total Tracker: [Total tracker hours for today]
Running Tracker: [Hours actually running today]
Pending Tracker: [Remaining hours to run today]
3. System Details (Where the tracker was used)
System Seat Number: [Enter Seat No.]
System Password: [Enter Password]
Tracker Tabs Open :  Yes/No [Need updates..]
4. Client Communication
[Any updates/Message for client, in proper format]
5. Message for BD Team (if applicable)
[Follow-up requirements in Night Shift]

***Format of eod report end***
**Crucial Rule:**
- **DO NOT** wrap your response in Markdown code blocks.
- **DO NOT** include html or at the beginning or end of your response.
- Your response must start directly with the first HTML tag (e.g., `<p>Hello Team,`)
- also do not add exact time and make sure if the content is about 3 4 and more tasks  at that time please add the report project vise please categorise the content and at last make sure please add not this is ai generated content so its not a actual or exact values its aprox values   make sure pleas edetect   is it is a project type or something else so add content accordingly
  note:- do not add this ```html  and this ''' in the messege body 
Here is the draft to improve:
---
" . strip_tags($reportData); // Send clean text to the AI

$data = [
    'contents' => [
        [ 'parts' => [ ['text' => $prompt] ] ]
    ]
];

// --- Step 4: Make the cURL Request (No changes here) ---
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
    
    // Check for specific error messages
    $errorResponse = json_decode($response, true);
    if (isset($errorResponse['error']['message'])) {
        $errorMsg = $errorResponse['error']['message'];
        
        // Check for expired API key
        if (stripos($errorMsg, 'expired') !== false || stripos($errorMsg, 'invalid') !== false) {
            error_log("Gemini API Error: " . $detailed_error . " | Raw Response: " . $response);
            echo json_encode(['success' => false, 'message' => '⚠️ Your Gemini API key has expired or is invalid. Please renew it at: /api_keys_settings.php']);
            exit();
        }
    }
    
    error_log("Gemini API Error: " . $detailed_error . " | Raw Response: " . $response);
    echo json_encode(['success' => false, 'message' => 'Could not connect to the AI service. Please check your API key and try again.']);
    exit();
}

$result = json_decode($response, true);
$generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($generatedText)) {
    error_log("Gemini API Response Format Error: " . $response);
    echo json_encode(['success' => false, 'message' => 'The AI returned an empty or invalid response.']);
    exit();
}

// --- Your database logging logic is preserved exactly ---
try {
    $logStmt = $pdo->prepare(
        "INSERT INTO ai_logs (user_id, prompt_text, response_text) VALUES (?, ?, ?)"
    );
    $logStmt->execute([$_SESSION['user_id'], strip_tags($reportData), $generatedText]);
} catch (PDOException $e) {
    error_log("CRITICAL: Failed to save AI log to database: " . $e->getMessage());
}

// --- Success! Return the AI-generated HTML (No changes here) ---
echo json_encode([
    'success' => true,
    'summary' => trim($generatedText)
]);
?>
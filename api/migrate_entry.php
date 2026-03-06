<?php
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'log' => "FATAL PHP ERROR: {$error['message']} in {$error['file']} on line {$error['line']}"
        ]);
    }
});

require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn() || !hasPermission('perm_view_all')) {
    echo json_encode(['success' => false, 'log' => 'Access denied']);
    exit;
}

function parseWithAI($text, $apiKey) {
    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash-latest:generateContent?key={$apiKey}";
    $prompt = "Return JSON only: {\"project_name\":\"\",\"task_name\":\"\",\"description\":\"\"}\nText:\n{$text}";

    $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) return null;

    $json = json_decode($res, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = substr($text, strpos($text, '{'));

    return json_decode($text, true);
}

$id = (int)($_POST['id'] ?? 0);
$table = $_POST['table'] ?? '';

if (!$id || !in_array($table, ['time_entries', 'manual_entries'])) {
    echo json_encode(['success' => false, 'log' => 'Invalid request']);
    exit;
}

/** ✅ Proper SELECT (NO bugs) */
$stmt = $pdo->prepare("SELECT id, task_name, user_id FROM {$table} WHERE id = ?");
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    echo json_encode(['success' => false, 'log' => "Entry #{$id} not found"]);
    exit;
}

$parsed = parseWithAI($entry['task_name'], GEMINI_API_KEY);

if (!$parsed || empty($parsed['task_name'])) {
    echo json_encode(['success' => false, 'log' => "AI failed for #{$id}"]);
    exit;
}

try {
    $pdo->beginTransaction();

    $projectId = null;
    $projectName = trim($parsed['project_name'] ?? '');

    if ($projectName !== '') {
        $stmt = $pdo->prepare(
            "SELECT id FROM projects WHERE project_name = ? AND user_id = ? LIMIT 1"
        );
        $stmt->execute([$projectName, $entry['user_id']]);
        $projectId = $stmt->fetchColumn();

        if (!$projectId) {
            $stmt = $pdo->prepare(
                "INSERT INTO projects (user_id, project_name) VALUES (?, ?)"
            );
            $stmt->execute([$entry['user_id'], $projectName]);
            $projectId = $pdo->lastInsertId();
        }
    }

    /** ❗ NEVER write project_id = 0 */
    $stmt = $pdo->prepare(
        "UPDATE {$table}
         SET project_id = ?, task_name = ?, description = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $projectId,
        $parsed['task_name'],
        $parsed['description'] ?? $entry['task_name'],
        $entry['id']
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'log' => "Updated #{$id} → Project: " . ($projectName ?: 'None')
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

<?php
/**
 * VO TimeTracker - AI Migration (PDO Safe)
 */

/* =======================================
   DEBUG (DISABLE AFTER SUCCESS)
======================================= */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* =======================================
   LOAD DB
======================================= */
require_once __DIR__ . '/includes/db.php';

/* =======================================
   CONFIG
======================================= */
define('GEMINI_API_KEY', 'PUT_YOUR_GEMINI_KEY_HERE');
define('GEMINI_MODEL', 'gemini-2.5-flash');
define('ENABLE_AI', true);
define('RATE_LIMIT_SLEEP', 3);

/* =======================================
   LOGGER
======================================= */
function log_msg($msg)
{
    $line = "[" . date('Y-m-d H:i:s') . "] $msg\n";
    echo $line;
    file_put_contents(__DIR__ . '/migration.log', $line, FILE_APPEND);
}

/* =======================================
   FALLBACK (NO AI)
======================================= */
function fallback_parse(string $text): array
{
    $text = trim(preg_replace('/\s+/', ' ', $text));

    if (preg_match('/bio break|break/i', $text)) {
        return [
            'project_name' => 'Internal',
            'task_name' => 'Break',
            'description' => 'Bio break'
        ];
    }

    $first = explode(' ', $text)[0] ?? 'General';

    return [
        'project_name' => strtoupper($first),
        'task_name' => substr($text, 0, 60),
        'description' => $text
    ];
}

/* =======================================
   GEMINI CALL (SAFE)
======================================= */
function call_gemini(string $text): ?array
{
    if (!ENABLE_AI) return null;

    $prompt = <<<TXT
You are organizing old time tracking data.

Given this text:
"$text"

Return ONLY valid JSON:
{
  "project_name": "Short project name",
  "task_name": "Short task title",
  "description": "Clean task description"
}
TXT;

    $payload = json_encode([
        "contents" => [[
            "parts" => [["text" => $prompt]]
        ]]
    ]);

    $ch = curl_init(
        "https://generativelanguage.googleapis.com/v1beta/models/"
        . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY
    );

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);

    if (!$response) {
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    $data = json_decode($response, true);

    if (isset($data['error'])) {
        log_msg("AI ERROR: " . $data['error']['message']);
        return null;
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return json_decode($text, true);
}

/* =======================================
   PROJECT HANDLER
======================================= */
function get_project_id(PDO $pdo, int $user_id, string $name): int
{
    $stmt = $pdo->prepare(
        "SELECT id FROM projects WHERE user_id = ? AND project_name = ? LIMIT 1"
    );
    $stmt->execute([$user_id, $name]);

    if ($id = $stmt->fetchColumn()) {
        return $id;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO projects (user_id, project_name, status)
         VALUES (?, ?, 'Active')"
    );
    $stmt->execute([$user_id, $name]);

    return (int) $pdo->lastInsertId();
}

/* =======================================
   MIGRATOR
======================================= */
function migrate(PDO $pdo, string $table)
{
    log_msg("Migrating {$table}");

    $entries = $pdo->query(
        "SELECT * FROM {$table} WHERE project_id IS NULL ORDER BY id ASC"
    );

    foreach ($entries as $row) {
        log_msg("Processing Entry #{$row['id']} ({$table})");

        $raw = trim(($row['task_name'] ?? '') . ' ' . ($row['description'] ?? ''));

        $ai = call_gemini($raw);

        if (!$ai) {
            log_msg("AI failed — fallback used");
            $ai = fallback_parse($raw);
        }

        $project_id = get_project_id(
            $pdo,
            (int)$row['user_id'],
            $ai['project_name']
        );

        $stmt = $pdo->prepare(
            "UPDATE {$table}
             SET project_id = ?, task_name = ?, description = ?
             WHERE id = ?"
        );

        $stmt->execute([
            $project_id,
            $ai['task_name'],
            $ai['description'],
            $row['id']
        ]);

        log_msg("Entry #{$row['id']} migrated");

        sleep(RATE_LIMIT_SLEEP);
    }
}

/* =======================================
   RUN
======================================= */
migrate($pdo, 'time_entries');
migrate($pdo, 'manual_entries');

log_msg("MIGRATION COMPLETE");

<?php
/**
 * api/download.php — Renders invitation and saves both HTML + field values
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

require_once __DIR__ . '/render.php';

$template_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['template'] ?? '');
if ($template_id === '') {
    http_response_code(400);
    exit('Missing template.');
}
$schema_path = dirname(__DIR__) . "/template/{$template_id}/{$template_id}.json";
if (!is_readable($schema_path)) {
    http_response_code(404);
    exit('Template not found.');
}

try {
    $output = render_invitation($schema_path, $_POST);
} catch (RuntimeException $e) {
    http_response_code(500);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/* ---- Save rendered HTML ---- */
$html_path = dirname(__DIR__) . "/template/{$template_id}/{$template_id}.html";
if (file_put_contents($html_path, $output) === false) {
    http_response_code(500);
    exit('Failed to save HTML.');
}

/* ---- Update field values in JSON ---- */
$schema = json_decode(file_get_contents($schema_path), true);
foreach ($schema['fields'] as &$field) {
    $key = $field['key'];
    if (isset($_POST[$key])) {
        $field['value'] = trim($_POST[$key]);
    }
}
file_put_contents($schema_path, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

/* ---- Redirect back ---- */
header('Location: /?template=' . urlencode($template_id) . '&saved=1');
exit;

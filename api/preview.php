<?php
/**
 * api/preview.php — Accepts POST form data and returns rendered invitation HTML
 */
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

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
    echo render_invitation($schema_path, $_POST);
} catch (RuntimeException $e) {
    http_response_code(500);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

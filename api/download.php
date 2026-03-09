<?php
/**
 * api/download.php — Generates a downloadable invitation HTML file
 * Shares the same rendering logic as preview.php, only adds download response headers
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

require_once __DIR__ . '/render.php';

try {
    $output = render_invitation(dirname(__DIR__) . '/template/20260226/20260226.json', $_POST);
} catch (RuntimeException $e) {
    http_response_code(500);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/* ---- Generate filename ---- */
$bride = trim(($_POST['bride_firstname'] ?? '') . ' ' . ($_POST['bride_lastname'] ?? ''));
$groom = trim(($_POST['groom_firstname'] ?? '') . ' ' . ($_POST['groom_lastname'] ?? ''));
$slug  = preg_replace('/[^a-z0-9]+/', '-', strtolower("$bride $groom"));
$slug  = trim($slug, '-') ?: 'wedding-invitation';
$filename = $slug . '-invitation.html';

/* ---- Output download response ---- */
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($output));
header('X-Content-Type-Options: nosniff');

echo $output;

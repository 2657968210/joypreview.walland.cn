<?php
/**
 * download.php — Generates a downloadable invitation HTML file
 * Shares the same rendering logic as preview.php, only adds download response headers
 */

// Reuse preview.php rendering logic, capture output first
ob_start();
// Only accepts POST (download endpoint)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/* ---- Helper functions (same as preview.php) ---- */

function h(string $s): string {
    return htmlspecialchars(trim($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function format_text(string $s): string {
    return nl2br(h($s));
}

function safe_url(string $url, string $fallback = '#'): string {
    $url = trim($url);
    if ($url === '') return $fallback;
    $parsed = parse_url($url);
    if ($parsed === false) return $fallback;
    $scheme = strtolower($parsed['scheme'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true)) return $fallback;
    return htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/* ---- Read and sanitize input ---- */

$bride_firstname = h($_POST['bride_firstname'] ?? 'Olivia');
$bride_lastname  = h($_POST['bride_lastname']  ?? '');
$groom_firstname = h($_POST['groom_firstname'] ?? 'Antonio');
$groom_lastname  = h($_POST['groom_lastname']  ?? '');
$ceremony_date   = h($_POST['ceremony_date']   ?? 'MAY 10, 2026');
$ceremony_time   = h($_POST['ceremony_time']   ?? "4 O'CLOCK IN THE AFTERNOON");
$ceremony_venue  = h($_POST['ceremony_venue']  ?? 'LIBERTY GARDENS');
$ceremony_addr   = $_POST['ceremony_addr']     ?? "123 ANNETTE STREET\nDENVER, COLORADO";
$reception_time  = h($_POST['reception_time']  ?? '5:30 PM IN THE EVENING');
$reception_venue = h($_POST['reception_venue'] ?? 'VILLA BENEZIA');
$reception_addr  = $_POST['reception_addr']    ?? "1234 BANK ST, GRASS VALLEY\nCALIFORNIA";
$rsvp_deadline   = h($_POST['rsvp_deadline']   ?? 'BY APRIL 1ST');
$rsvp_link       = safe_url($_POST['rsvp_link'] ?? '', 'https://forms.gle/');
$map_link        = safe_url($_POST['map_link']  ?? '', '#');

/* ---- Combine fields ---- */

$bride_full   = trim($bride_firstname . ($bride_lastname  ? ' ' . $bride_lastname  : ''));
$groom_full   = trim($groom_firstname . ($groom_lastname  ? ' ' . $groom_lastname  : ''));
$couple_names = ($bride_full && $groom_full)
    ? "$bride_full &amp; $groom_full"
    : ($bride_full ?: ($groom_full ?: 'Your Wedding'));

$ceremony_venue_html  = $ceremony_venue . ($ceremony_addr !== ''
    ? '<br>' . format_text($ceremony_addr) : '');
$reception_venue_html = $reception_venue . ($reception_addr !== ''
    ? '<br>' . format_text($reception_addr) : '');

/* ---- Load template ---- */

$tpl_path = __DIR__ . '/template/20260226.tpl.html';
if (!is_readable($tpl_path)) {
    http_response_code(500);
    exit('Template not found.');
}
$html = file_get_contents($tpl_path);

/* ---- Replace placeholders ---- */

$replacements = [
    '{{COUPLE_NAMES}}'         => $couple_names,
    '{{CEREMONY_DATE}}'        => $ceremony_date,
    '{{CEREMONY_TIME}}'        => $ceremony_time,
    '{{CEREMONY_VENUE_HTML}}'  => $ceremony_venue_html,
    '{{RECEPTION_TIME}}'       => $reception_time,
    '{{RECEPTION_VENUE_HTML}}' => $reception_venue_html,
    '{{MAP_LINK}}'             => $map_link,
    '{{RSVP_DEADLINE}}'        => $rsvp_deadline,
    '{{RSVP_LINK}}'            => $rsvp_link,
];

$output = str_replace(array_keys($replacements), array_values($replacements), $html);

/* ---- Generate filename ---- */
$slug = preg_replace('/[^a-z0-9]+/', '-',
    strtolower(strip_tags($bride_full . '-' . $groom_full)));
$slug = trim($slug, '-') ?: 'wedding-invitation';
$filename = $slug . '-invitation.html';

/* ---- Output download response ---- */
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($output));
header('X-Content-Type-Options: nosniff');

echo $output;

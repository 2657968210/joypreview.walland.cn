<?php
/**
 * render.php — Data-driven invitation renderer.
 *
 * Reads a JSON schema that declares form fields and placeholder rules,
 * then applies POST data to the corresponding .tpl.html file.
 *
 * Usage:
 *   require_once __DIR__ . '/render.php';
 *   $html = render_invitation(__DIR__ . '/template/20260226.json', $_POST);
 */

/* ---- Internal helpers (prefixed to avoid global collisions) ---- */

function _ri_h(string $s): string {
    return htmlspecialchars(trim($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function _ri_nl2br(string $s): string {
    return nl2br(_ri_h($s));
}

function _ri_safe_url(string $url, string $fallback): string {
    $url = trim($url);
    if ($url === '') return $fallback;
    $p = parse_url($url);
    if ($p === false) return $fallback;
    $scheme = strtolower($p['scheme'] ?? '');
    return in_array($scheme, ['http', 'https'], true)
        ? htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        : $fallback;
}

/* ---- Public renderer ---- */

/**
 * @param  string $schema_path  Absolute path to the template's .json schema file.
 * @param  array  $post         Raw POST data (typically $_POST).
 * @return string               Rendered HTML ready to output.
 * @throws RuntimeException     If the schema or template file cannot be read.
 */
function render_invitation(string $schema_path, array $post): string
{
    if (!is_readable($schema_path)) {
        throw new RuntimeException("Schema not found: {$schema_path}");
    }

    $schema = json_decode(file_get_contents($schema_path), true, 512, JSON_THROW_ON_ERROR);

    $tpl_path = dirname($schema_path) . '/' . $schema['template'];
    if (!is_readable($tpl_path)) {
        throw new RuntimeException("Template not found: {$tpl_path}");
    }

    $tpl = file_get_contents($tpl_path);

    /* ---- Collect raw input for every declared field ---- */
    $data = [];
    foreach ($schema['form_fields'] as $f) {
        $data[$f['id']] = trim($post[$f['id']] ?? '');
    }

    /* ---- Build placeholder → value map via rule engine ---- */
    $replacements = [];

    foreach ($schema['placeholders'] as $ph) {
        $key     = $ph['key'];
        $default = $ph['default'] ?? '';

        switch ($ph['rule']) {

            // Combine up to four name parts: "First Last & Partner First Last"
            case 'couple_names':
                $src     = $ph['sources'];
                $bride   = trim(($data[$src[0]] ?? '') . ' ' . ($data[$src[1]] ?? ''));
                $groom   = trim(($data[$src[2]] ?? '') . ' ' . ($data[$src[3]] ?? ''));
                $bride_e = _ri_h($bride);
                $groom_e = _ri_h($groom);
                if ($bride_e && $groom_e) {
                    $replacements[$key] = "{$bride_e} &amp; {$groom_e}";
                } elseif ($bride_e || $groom_e) {
                    $replacements[$key] = $bride_e ?: $groom_e;
                } else {
                    $replacements[$key] = _ri_h($default);
                }
                break;

            // Single text field, with optional fallback and date formatting
            case 'direct':
                $val = $data[$ph['source']] ?? '';
                if ($val === '' && !empty($ph['fallback'])) {
                    $raw = $data[$ph['fallback']] ?? '';
                    if ($raw !== '' && ($ph['fallback_format'] ?? '') === 'date_to_text') {
                        $ts  = strtotime($raw);
                        $val = $ts !== false
                            ? strtoupper(date('F', $ts)) . ' ' . date('j', $ts) . ', ' . date('Y', $ts)
                            : $raw;
                    } else {
                        $val = $raw;
                    }
                }
                $replacements[$key] = $val !== '' ? _ri_h($val) : _ri_h($default);
                break;

            // Venue name + multiline address → HTML
            case 'venue_html':
                $name = _ri_h($data[$ph['sources'][0]] ?? '');
                $addr = $data[$ph['sources'][1]] ?? '';
                if ($name !== '') {
                    $replacements[$key] = $addr !== ''
                        ? $name . '<br>' . _ri_nl2br($addr)
                        : $name;
                } else {
                    $replacements[$key] = $default;
                }
                break;

            // URL with scheme validation
            case 'safe_url':
                $replacements[$key] = _ri_safe_url($data[$ph['source']] ?? '', $default);
                break;
        }
    }

    return str_replace(array_keys($replacements), array_values($replacements), $tpl);
}

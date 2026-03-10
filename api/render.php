<?php
/**
 * api/render.php — Simple template renderer with key-value replacement
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

    $html = file_get_contents($tpl_path);

    // Replace each placeholder with POST value or default
    foreach ($schema['fields'] as $field) {
        $key = $field['key'];
        $val = trim($post[$key] ?? '');
        
        if ($val === '') {
            $val = $field['default'] ?? '';
        }

        // Convert \n to <br> for HTML output
        $val = nl2br(htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8'), false);
        
        $html = str_replace($key, $val, $html);
    }

    return $html;
}

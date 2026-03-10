<?php
$template_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['template'] ?? '');
$saved = isset($_GET['saved']);

if ($template_id === '') {
    http_response_code(400);
    exit('Missing or invalid ?template= parameter.');
}

$schema_path = __DIR__ . "/template/{$template_id}/{$template_id}.json";
if (!is_readable($schema_path)) {
    http_response_code(404);
    exit('Template not found: ' . htmlspecialchars($template_id, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

$schema = json_decode(file_get_contents($schema_path), true);
$fields = $schema['fields'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wedding Invitation Editor</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --accent: #e91e8c;
            --accent-hover: #c4186e;
            --border: #e0e0e0;
            --label: #6b6b6b;
            --text: #1a1a1a;
            --bg-preview: #f2ede7;
        }
        html, body { height: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: var(--text);
            background: #fff;
        }
        .editor-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        .panel-preview {
            flex: 0 0 58%;
            background: var(--bg-preview);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .panel-preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 24px;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(6px);
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            color: var(--label);
            z-index: 10;
            flex-shrink: 0;
        }
        .panel-preview-header span { font-weight: 600; color: var(--text); }
        .preview-actions { display: flex; gap: 12px; }
        .preview-actions a {
            background: none;
            border: 1px solid var(--border);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 12px;
            color: var(--label);
            cursor: pointer;
            text-decoration: none;
            transition: border-color .2s, color .2s;
        }
        .preview-actions a:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        .preview-sandbox {
            flex: 1;
            position: relative;
            overflow: hidden;
        }
        #previewFrame {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
        .panel-form {
            flex: 0 0 42%;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            background: #fff;
            border-left: 1px solid var(--border);
        }
        .form-inner {
            padding: 40px 48px 60px;
            max-width: 480px;
            margin: 0 auto;
            width: 100%;
        }
        .form-title {
            font-size: 26px;
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 32px;
            color: var(--text);
        }
        .form-group {
            position: relative;
            margin-bottom: 20px;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            padding: 20px 16px 8px;
            font-size: 15px;
            color: var(--text);
            background: #fff;
            outline: none;
            transition: border-color .2s;
            resize: vertical;
            font-family: inherit;
        }
        .form-group textarea {
            padding-top: 24px;
            min-height: 120px;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--accent);
        }
        .form-group label {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: var(--label);
            pointer-events: none;
            transition: all .18s ease;
            white-space: nowrap;
            overflow: hidden;
            max-width: calc(100% - 32px);
            text-overflow: ellipsis;
        }
        .form-group textarea ~ label {
            top: 20px;
            transform: none;
        }
        .form-group input:focus ~ label,
        .form-group input:not(:placeholder-shown) ~ label,
        .form-group textarea:focus ~ label,
        .form-group textarea:not(:placeholder-shown) ~ label {
            top: 8px;
            transform: none;
            font-size: 11px;
            color: var(--accent);
        }
        .btn-download {
            display: block;
            width: 100%;
            padding: 16px;
            background: #1a1a1a;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: background .2s;
            margin-top: 28px;
        }
        .btn-download:hover { background: #333; }
        .btn-open-invite {
            display: block;
            width: 100%;
            padding: 14px;
            background: none;
            border: 1.5px solid var(--accent);
            color: var(--accent);
            font-size: 15px;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            margin-top: 12px;
            transition: background .2s, color .2s;
        }
        .btn-open-invite:hover {
            background: var(--accent);
            color: #fff;
        }
        .save-success {
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            color: #2e7d32;
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            border-radius: 6px;
            padding: 10px 16px;
            margin-bottom: 20px;
        }
        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 24px 0;
        }
        @media (max-width: 900px) {
            .editor-layout {
                flex-direction: column;
                height: auto;
                overflow: visible;
            }
            .panel-preview {
                flex: none;
                height: 50vw;
                min-height: 240px;
            }
            .panel-form {
                flex: none;
                border-left: none;
                border-top: 1px solid var(--border);
                overflow-y: visible;
            }
            .form-inner {
                padding: 28px 24px 48px;
            }
        }
    </style>
</head>
<body>
<div class="editor-layout">
    <div class="panel-preview">
        <div class="panel-preview-header">
            <span>Live Preview</span>
            <div class="preview-actions">
                <a href="template/<?= htmlspecialchars($template_id, ENT_QUOTES) ?>/<?= htmlspecialchars($template_id, ENT_QUOTES) ?>.html" target="_blank">Full Screen ↗</a>
            </div>
        </div>
        <div class="preview-sandbox">
            <iframe id="previewFrame" src="template/<?= htmlspecialchars($template_id, ENT_QUOTES) ?>/<?= htmlspecialchars($template_id, ENT_QUOTES) ?>.html" title="Invitation Preview"></iframe>
        </div>
    </div>

    <div class="panel-form">
        <div class="form-inner">
            <h1 class="form-title">Personalize your invitation</h1>
            
            <?php if ($saved): ?>
            <p class="save-success">✓ Saved successfully!</p>
            <?php endif; ?>

            <?php foreach ($fields as $field):
                $key = htmlspecialchars($field['key'], ENT_QUOTES);
                $label = htmlspecialchars($field['label']);
                $value = htmlspecialchars($field['value'] ?: $field['default'], ENT_QUOTES);
                $is_textarea = stripos($field['label'], 'multi-line') !== false;
            ?>
            <div class="form-group">
                <?php if ($is_textarea): ?>
                <textarea id="field_<?= $key ?>" name="<?= $key ?>" placeholder=" "><?= $value ?></textarea>
                <?php else: ?>
                <input type="text" id="field_<?= $key ?>" name="<?= $key ?>" placeholder=" " value="<?= $value ?>">
                <?php endif; ?>
                <label for="field_<?= $key ?>"><?= $label ?></label>
            </div>
            <?php endforeach; ?>

            <hr class="divider">

            <form id="saveForm" method="POST" action="api/download.php">
                <input type="hidden" name="template" value="<?= htmlspecialchars($template_id, ENT_QUOTES) ?>">
                <?php foreach ($fields as $field): ?>
                <input type="hidden" name="<?= htmlspecialchars($field['key'], ENT_QUOTES) ?>" id="save_<?= htmlspecialchars($field['key'], ENT_QUOTES) ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn-download" onclick="syncSaveForm()">💾 Save Invitation</button>
            </form>
            <a href="template/<?= htmlspecialchars($template_id, ENT_QUOTES) ?>/<?= htmlspecialchars($template_id, ENT_QUOTES) ?>.html" target="_blank" class="btn-open-invite">📩 Open Invitation</a>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const TEMPLATE_ID = <?= json_encode($template_id) ?>;
    const KEYS = <?= json_encode(array_column($fields, 'key')) ?>;

    window.syncSaveForm = function () {
        KEYS.forEach(key => {
            const input = document.getElementById('field_' + key);
            const hidden = document.getElementById('save_' + key);
            if (input && hidden) hidden.value = input.value;
        });
    };

    function debounce(fn, ms) {
        let t;
        return function () { clearTimeout(t); t = setTimeout(() => fn.apply(this, arguments), ms); };
    }

    function updatePreview() {
        const fd = new FormData();
        fd.append('template', TEMPLATE_ID);
        KEYS.forEach(key => {
            const el = document.getElementById('field_' + key);
            if (el) fd.append(key, el.value);
        });
        const base = location.origin + '/template/' + TEMPLATE_ID + '/';
        fetch('api/preview.php', { method: 'POST', body: fd })
            .then(r => r.text())
            .then(html => {
                html = html.replace(/(<head[^>]*>)/i, '$1<base href="' + base + '">');
                document.getElementById('previewFrame').srcdoc = html;
            })
            .catch(() => {});
    }

    const debouncedPreview = debounce(updatePreview, 350);

    KEYS.forEach(key => {
        const el = document.getElementById('field_' + key);
        if (el) el.addEventListener('input', debouncedPreview);
    });

    // Trigger preview on load
    setTimeout(updatePreview, 100);
})();
</script>
</body>
</html>

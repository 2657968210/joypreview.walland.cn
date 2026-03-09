<?php
// Validate and resolve the template from the query string
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

// Saved field values (persisted by last save)
$saved_values = $schema['values'] ?? [];

// Derive step titles from the first field of each step
$steps_meta = [];
foreach ($schema['fields'] as $f) {
    $s = $f['step'];
    if (!isset($steps_meta[$s])) {
        $steps_meta[$s] = ['id' => $s, 'title' => $f['step_title'] ?? 'Step ' . $s];
    }
}
$total_steps = count($steps_meta);

// Group fields: step → section → []
$by_step = [];
foreach ($schema['fields'] as $f) {
    $by_step[$f['step']][$f['section'] ?? ''][] = $f;
}

// Field IDs sent to download form (exclude download:false)
$download_ids = array_values(array_map(
    fn($f) => $f['id'],
    array_filter($schema['fields'], fn($f) => ($f['download'] ?? true) !== false)
));

function field_html(array $f, string $val = ''): string {
    $id  = htmlspecialchars($f['id'], ENT_QUOTES);
    $lbl = htmlspecialchars($f['label']);
    $t   = $f['type'] ?? 'text';
    $ac  = htmlspecialchars($f['autocomplete'] ?? 'off', ENT_QUOTES);
    $esc = htmlspecialchars($val, ENT_QUOTES);
    if ($t === 'textarea') {
        $ctrl = "<textarea id=\"{$id}\" name=\"{$id}\" placeholder=\" \" rows=\"3\">{$esc}</textarea>";
    } else {
        $ctrl = "<input type=\"{$t}\" id=\"{$id}\" name=\"{$id}\" placeholder=\" \" autocomplete=\"{$ac}\" value=\"{$esc}\">";
    }
    return "<div class=\"form-group\">{$ctrl}<label for=\"{$id}\">{$lbl}</label></div>\n";
}

function render_fields(array $fields, array $values = []): string {
    $out = '';
    $n   = count($fields);
    $i   = 0;
    while ($i < $n) {
        $row_id = $fields[$i]['row'] ?? null;
        if ($row_id !== null) {
            $group = [];
            while ($i < $n && ($fields[$i]['row'] ?? null) === $row_id) {
                $group[] = $fields[$i++];
            }
            if (count($group) > 1) {
                $out .= '<div class="form-row">';
                foreach ($group as $gf) $out .= field_html($gf, $values[$gf['id']] ?? '');
                $out .= "</div>\n";
            } else {
                $out .= field_html($group[0], $values[$group[0]['id']] ?? '');
            }
        } else {
            $out .= field_html($fields[$i++], $values[$fields[$i-1]['id']] ?? '');
        }
    }
    return $out;
}
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

        /* ─── Two-column layout ─── */
        .editor-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Left: preview panel */
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

        .preview-actions a, .preview-actions button {
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

        .preview-actions a:hover, .preview-actions button:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* Scale container */
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

        /* ─── Right: form panel ─── */
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

        /* Step indicator */
        .step-indicator {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--label);
            margin-bottom: 10px;
        }

        .form-title {
            font-size: 26px;
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 32px;
            color: var(--text);
        }

        /* Step show/hide */
        .step { display: none; }
        .step.active { display: block; }

        /* Progress bar */
        .progress-bar {
            height: 3px;
            background: #e8e8e8;
            border-radius: 2px;
            margin-bottom: 36px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 2px;
            transition: width .4s ease;
        }

        /* Form row (two columns) */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        /* Floating label */
        .form-group {
            position: relative;
            margin-bottom: 12px;
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
            min-height: 80px;
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

        /* Label floats on focus or when filled */
        .form-group input:focus ~ label,
        .form-group input:not(:placeholder-shown) ~ label,
        .form-group textarea:focus ~ label,
        .form-group textarea:not(:placeholder-shown) ~ label {
            top: 8px;
            transform: none;
            font-size: 11px;
            color: var(--accent);
        }

        /* Section title */
        .section-title {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--label);
            margin: 28px 0 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }

        /* Buttons */
        .btn-continue {
            display: block;
            width: 100%;
            padding: 16px;
            background: var(--accent);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: background .2s, transform .1s;
            margin-top: 28px;
        }

        .btn-continue:hover { background: var(--accent-hover); }
        .btn-continue:active { transform: scale(.98); }

        .btn-continue:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-back {
            background: none;
            border: none;
            color: var(--label);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            margin-bottom: 24px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: color .2s;
        }

        .btn-back:hover { color: var(--text); }

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
            margin-top: 14px;
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
            margin-bottom: 14px;
        }

        .helper-text {
            text-align: center;
            font-size: 13px;
            color: var(--label);
            margin-top: 14px;
        }

        /* Divider */
        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 24px 0;
        }

        /* ─── Responsive: mobile ─── */
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

        @media (max-width: 480px) {
            .form-row { grid-template-columns: 1fr; }

            .form-title { font-size: 22px; }
        }
    </style>
</head>
<body>
<div class="editor-layout">

    <!-- ░░ Left panel: live preview ░░ -->
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

    <!-- ░░ Right panel: edit form ░░ -->
    <div class="panel-form">
        <div class="form-inner">
            <!-- Step indicator (hidden for single-step) -->
            <?php if ($total_steps > 1): ?>
            <div class="step-indicator" id="stepIndicator">Step 1 of <?= $total_steps ?></div>
            <div class="progress-bar"><div class="progress-fill" id="progressFill" style="width:<?= round(1 / $total_steps * 100) ?>%"></div></div>
            <?php endif; ?>
            <h1 class="form-title" id="formTitle"><?= htmlspecialchars($steps_meta[1]['title']) ?></h1>

            <?php foreach ($by_step as $step_id => $sections):
                $is_first = $step_id === array_key_first($by_step);
                $is_last  = $step_id === array_key_last($by_step);
            ?>
            <div class="step <?= $is_first ? 'active' : '' ?>" id="step<?= $step_id ?>">
                <?php if (!$is_first): ?>
                <button class="btn-back" onclick="goToStep(<?= $step_id - 1 ?>)">← Back</button>
                <?php endif; ?>

                <?php foreach ($sections as $section_name => $section_fields):
                    if ($section_name !== ''): ?>
                <div class="section-title"><?= htmlspecialchars($section_name) ?></div>
                    <?php endif; ?>
                <?= render_fields($section_fields, $saved_values) ?>
                <?php endforeach; ?>

                <?php if ($is_first && !$is_last): ?>
                <button class="btn-continue" id="btnStep1" onclick="goToStep(<?= $step_id + 1 ?>)">Continue</button>
                <p class="helper-text">You can easily edit this info later.</p>
                <?php elseif ($is_last): ?>
                <hr class="divider">
                <?php if ($saved): ?>
                <p class="save-success">✓ Saved successfully!</p>
                <?php endif; ?>
                <form id="downloadForm" method="POST" action="api/download.php">
                    <input type="hidden" name="template" value="<?= htmlspecialchars($template_id, ENT_QUOTES) ?>">
                    <?php foreach ($download_ids as $did): ?>
                    <input type="hidden" name="<?= htmlspecialchars($did, ENT_QUOTES) ?>" id="dl_<?= htmlspecialchars($did, ENT_QUOTES) ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="btn-download" onclick="syncDownloadForm()">💾 Save Invitation</button>
                </form>
                <a href="template/<?= htmlspecialchars($template_id, ENT_QUOTES) ?>/<?= htmlspecialchars($template_id, ENT_QUOTES) ?>.html" target="_blank" class="btn-open-invite">📩 Open Invitation</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const FIELDS       = <?= json_encode($download_ids) ?>;
    const TOTAL        = <?= $total_steps ?>;
    const TITLES       = <?= json_encode(array_map(fn($s) => $s['title'], $steps_meta)) ?>;
    const SAVED_VALUES = <?= json_encode($saved_values) ?>;

    window.goToStep = function (n) {
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        document.getElementById('step' + n).classList.add('active');

        document.getElementById('stepIndicator').textContent = `Step ${n} of ${TOTAL}`;
        document.getElementById('progressFill').style.width  = (n / TOTAL * 100) + '%';
        document.getElementById('formTitle').textContent = TITLES[n] || '';
        document.querySelector('.panel-form').scrollTop = 0;
    };

    window.syncDownloadForm = function () {
        FIELDS.forEach(id => {
            const src = document.getElementById(id);
            const dst = document.getElementById('dl_' + id);
            if (src && dst) dst.value = src.value;
        });
    };

    /* ── Live preview ── */
    function debounce(fn, ms) {
        let t;
        return function () { clearTimeout(t); t = setTimeout(() => fn.apply(this, arguments), ms); };
    }

    function updatePreview() {
        const fd = new FormData();
        fd.append('template', TEMPLATE_ID);
        FIELDS.forEach(id => {
            const el = document.getElementById(id);
            if (el) fd.append(id, el.value);
        });
        const base = location.origin + '/template/' + TEMPLATE_ID + '/';
        fetch('api/preview.php', { method: 'POST', body: fd })
            .then(r => r.text())
            .then(html => {
                // Inject <base> so relative paths in the template resolve correctly
                html = html.replace(/(<head[^>]*>)/i, '$1<base href="' + base + '">');
                document.getElementById('previewFrame').srcdoc = html;
            })
            .catch(() => {});
    }

    const debouncedPreview = debounce(updatePreview, 350);

    FIELDS.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', debouncedPreview);
    });

    // Trigger preview on load if there are saved values
    if (Object.keys(SAVED_VALUES).length > 0) updatePreview();
})();
</script>
</body>
</html>

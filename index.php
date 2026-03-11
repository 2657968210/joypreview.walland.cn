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
$sections = $schema['sections'] ?? [];

// Group fields by section
$fields_by_section = [];
foreach ($fields as $field) {
    $section_id = $field['section'] ?? 'general';
    if (!isset($fields_by_section[$section_id])) {
        $fields_by_section[$section_id] = [];
    }
    $fields_by_section[$section_id][] = $field;
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
            flex-direction: row;
            overflow: hidden;
            background: #fff;
            border-left: 1px solid var(--border);
        }
        .form-sidebar {
            flex: 0 0 240px;
            background: #fafafa;
            border-right: 1px solid var(--border);
            overflow-y: auto;
            padding: 24px 0;
        }
        .form-sidebar h3 {
            padding: 0 20px 12px;
            font-size: 12px;
            font-weight: 700;
            color: var(--label);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-nav {
            list-style: none;
        }
        .section-nav-item {
            display: block;
        }
        .section-nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            transition: background .2s;
            cursor: pointer;
        }
        .section-nav-link:hover {
            background: rgba(0,0,0,0.03);
        }
        .section-nav-link.active {
            background: #fff;
            color: var(--accent);
            font-weight: 600;
            border-left: 3px solid var(--accent);
            padding-left: 17px;
        }
        .section-nav-arrow {
            color: var(--label);
            font-size: 16px;
        }
        .form-content {
            flex: 1;
            overflow-y: auto;
        }
        .form-inner {
            padding: 40px 48px 60px;
            max-width: 540px;
            width: 100%;
        }
        .section-block {
            margin-bottom: 48px;
        }
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
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
        .file-upload-group {
            margin-bottom: 20px;
        }
        .file-upload-group label.field-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }
        .file-upload-box {
            border: 2px dashed var(--border);
            border-radius: 6px;
            padding: 24px;
            text-align: center;
            background: #fafafa;
            cursor: pointer;
            transition: border-color .2s, background .2s;
        }
        .file-upload-box:hover {
            border-color: var(--accent);
            background: #fff;
        }
        .file-upload-box.dragover {
            border-color: var(--accent);
            background: #fff5f9;
        }
        .file-upload-box input[type="file"] {
            display: none;
        }
        .file-upload-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }
        .file-upload-text {
            font-size: 14px;
            color: var(--label);
        }
        .file-upload-hint {
            font-size: 12px;
            color: var(--label);
            margin-top: 4px;
        }
        .file-preview {
            margin-top: 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
        }
        .file-preview img {
            width: 100%;
            height: auto;
            display: block;
        }
        .file-preview video {
            width: 100%;
            height: auto;
            display: block;
        }
        .file-preview audio {
            width: 100%;
            display: block;
            margin: 12px 0;
        }
        .file-preview-actions {
            padding: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f9f9f9;
            font-size: 12px;
        }
        .file-preview-name {
            color: var(--label);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }
        .file-preview-remove {
            color: var(--accent);
            cursor: pointer;
            text-decoration: none;
            margin-left: 8px;
        }
        .file-preview-remove:hover {
            color: var(--accent-hover);
        }
        .upload-progress {
            margin-top: 12px;
            padding: 8px 12px;
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 4px;
            font-size: 13px;
            color: #1976d2;
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
                flex-direction: column;
                border-left: none;
                border-top: 1px solid var(--border);
                overflow: visible;
            }
            .form-sidebar {
                flex: none;
                border-right: none;
                border-bottom: 1px solid var(--border);
                padding: 16px 0;
            }
            .form-content {
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
        <div class="form-sidebar">
            <h3>Pages</h3>
            <ul class="section-nav">
                <?php foreach ($sections as $section): ?>
                <li class="section-nav-item">
                    <a href="#section-<?= htmlspecialchars($section['id'], ENT_QUOTES) ?>" 
                       class="section-nav-link" 
                       data-section="<?= htmlspecialchars($section['id'], ENT_QUOTES) ?>">
                        <span><?= htmlspecialchars($section['title']) ?></span>
                        <span class="section-nav-arrow">›</span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="form-content">
            <div class="form-inner">
                <h1 class="form-title">Personalize your invitation</h1>
                
                <?php if ($saved): ?>
                <p class="save-success">✓ Saved successfully!</p>
                <?php endif; ?>

                <?php foreach ($sections as $section): ?>
                    <?php if (isset($fields_by_section[$section['id']]) && count($fields_by_section[$section['id']]) > 0): ?>
                    <div class="section-block" id="section-<?= htmlspecialchars($section['id'], ENT_QUOTES) ?>">
                        <h2 class="section-title"><?= htmlspecialchars($section['title']) ?></h2>
                        
                        <?php foreach ($fields_by_section[$section['id']] as $field):
                $key = htmlspecialchars($field['key'], ENT_QUOTES);
                $label = htmlspecialchars($field['label']);
                $value = htmlspecialchars($field['value'] ?: $field['default'], ENT_QUOTES);
                $type = $field['type'] ?? 'text';
                $is_textarea = stripos($field['label'], 'multi-line') !== false;
                $is_file = in_array($type, ['image', 'video', 'audio']);
            ?>
            <?php if ($is_file): ?>
            <?php 
                $accept = $type === 'image' ? 'image/*' : ($type === 'video' ? 'video/*' : 'audio/*');
                $icon = $type === 'image' ? '🖼️' : ($type === 'video' ? '🎬' : '🎵');
                $text = $type === 'image' ? '图片' : ($type === 'video' ? '视频' : '音频');
                $hint = $type === 'image' ? 'JPG, PNG, GIF, WebP' : ($type === 'video' ? 'MP4, WebM, MOV' : 'MP3, M4A, WAV, OGG');
            ?>
            <div class="file-upload-group" data-key="<?= $key ?>" data-type="<?= $type ?>">
                <label class="field-label"><?= $label ?></label>
                <div class="file-upload-box" onclick="document.getElementById('file_<?= $key ?>').click()">
                    <input type="file" id="file_<?= $key ?>" accept="<?= $accept ?>" data-key="<?= $key ?>">
                    <div class="file-upload-icon"><?= $icon ?></div>
                    <div class="file-upload-text">点击或拖拽上传<?= $text ?></div>
                    <div class="file-upload-hint"><?= $hint ?> (最大50MB)</div>
                </div>
                <div id="preview_<?= $key ?>" class="file-preview" style="display: none;"></div>
                <div id="progress_<?= $key ?>" class="upload-progress" style="display: none;">上传中...</div>
                <input type="hidden" id="field_<?= $key ?>" name="<?= $key ?>" value="<?= $value ?>">
            </div>
            <?php elseif ($is_textarea): ?>
            <div class="form-group">
                <textarea id="field_<?= $key ?>" name="<?= $key ?>" placeholder=" "><?= $value ?></textarea>
                <label for="field_<?= $key ?>"><?= $label ?></label>
            </div>
            <?php else: ?>
            <div class="form-group">
                <input type="text" id="field_<?= $key ?>" name="<?= $key ?>" placeholder=" " value="<?= $value ?>">
                <label for="field_<?= $key ?>"><?= $label ?></label>
            </div>
            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
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
</div>

<script>
(function () {
    'use strict';

    const TEMPLATE_ID = <?= json_encode($template_id) ?>;
    const KEYS = <?= json_encode(array_column($fields, 'key')) ?>;
    const FIELDS = <?= json_encode($fields) ?>;

    // File upload functionality
    function setupFileUpload() {
        document.querySelectorAll('input[type="file"]').forEach(input => {
            const key = input.dataset.key;
            const uploadGroup = input.closest('.file-upload-group');
            const uploadBox = uploadGroup.querySelector('.file-upload-box');
            const previewDiv = document.getElementById('preview_' + key);
            const progressDiv = document.getElementById('progress_' + key);
            const hiddenInput = document.getElementById('field_' + key);
            const fileType = uploadGroup.dataset.type;

            // Check if there's already a value and show preview
            if (hiddenInput.value) {
                showPreview(key, hiddenInput.value, fileType);
            }

            // File input change
            input.addEventListener('change', function(e) {
                if (this.files.length > 0) {
                    uploadFile(this.files[0], key, fileType);
                }
            });

            // Drag and drop
            uploadBox.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('dragover');
            });

            uploadBox.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.remove('dragover');
            });

            uploadBox.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    uploadFile(e.dataTransfer.files[0], key, fileType);
                }
            });
        });
    }

    function uploadFile(file, key, fileType) {
        const previewDiv = document.getElementById('preview_' + key);
        const progressDiv = document.getElementById('progress_' + key);
        const hiddenInput = document.getElementById('field_' + key);

        const fd = new FormData();
        fd.append('file', file);
        fd.append('template', TEMPLATE_ID);

        progressDiv.style.display = 'block';
        progressDiv.textContent = '上传中...';
        previewDiv.style.display = 'none';

        fetch('api/upload.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            progressDiv.style.display = 'none';
            if (data.success) {
                hiddenInput.value = data.url;
                showPreview(key, data.url, fileType);
                debouncedPreview();
            } else {
                alert('上传失败: ' + (data.error || '未知错误'));
            }
        })
        .catch(err => {
            progressDiv.style.display = 'none';
            alert('上传失败: ' + err.message);
        });
    }

    function showPreview(key, url, fileType) {
        const previewDiv = document.getElementById('preview_' + key);
        const fileName = url.split('/').pop();
        
        let previewHTML = '';
        if (fileType === 'image') {
            previewHTML = '<img src="' + url + '" alt="Preview">';
        } else if (fileType === 'video') {
            previewHTML = '<video controls><source src="' + url + '" type="video/mp4"></video>';
        } else if (fileType === 'audio') {
            previewHTML = '<audio controls><source src="' + url + '"></audio>';
        }
        
        previewHTML += '<div class="file-preview-actions">';
        previewHTML += '<span class="file-preview-name">' + fileName + '</span>';
        previewHTML += '<a href="#" class="file-preview-remove" onclick="removeFile(\'' + key + '\'); return false;">✕ 删除</a>';
        previewHTML += '</div>';
        
        previewDiv.innerHTML = previewHTML;
        previewDiv.style.display = 'block';
    }

    window.removeFile = function(key) {
        const hiddenInput = document.getElementById('field_' + key);
        const previewDiv = document.getElementById('preview_' + key);
        const fileInput = document.getElementById('file_' + key);
        
        hiddenInput.value = '';
        previewDiv.style.display = 'none';
        previewDiv.innerHTML = '';
        fileInput.value = '';
        
        debouncedPreview();
    };

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

    // Initialize file uploads
    setupFileUpload();

    // Section navigation
    const sectionLinks = document.querySelectorAll('.section-nav-link');
    const formContent = document.querySelector('.form-content');
    const previewFrame = document.getElementById('previewFrame');
    
    // Smooth scroll to section
    sectionLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            
            if (targetSection) {
                // Remove active class from all links
                sectionLinks.forEach(l => l.classList.remove('active'));
                // Add active class to clicked link
                this.classList.add('active');
                
                // Smooth scroll to section in form
                const formContentRect = formContent.getBoundingClientRect();
                const targetRect = targetSection.getBoundingClientRect();
                const scrollTop = formContent.scrollTop;
                const offsetTop = targetRect.top - formContentRect.top + scrollTop - 20;
                
                formContent.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
                
                // Also scroll preview iframe to corresponding section
                scrollPreviewToSection(this.dataset.section);
            }
        });
    });
    
    // Scroll preview iframe to section
    function scrollPreviewToSection(sectionId) {
        try {
            const iframeDoc = previewFrame.contentDocument || previewFrame.contentWindow.document;
            if (iframeDoc) {
                const targetElement = iframeDoc.getElementById(sectionId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    // Fallback: scroll to top if section not found
                    previewFrame.contentWindow.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }
        } catch (err) {
            console.log('Unable to scroll preview iframe:', err);
        }
    }
    
    // Highlight active section on scroll
    function updateActiveSectionNav() {
        const sections = document.querySelectorAll('.section-block');
        const scrollTop = formContent.scrollTop;
        const formContentRect = formContent.getBoundingClientRect();
        
        let activeSection = null;
        sections.forEach(section => {
            const rect = section.getBoundingClientRect();
            const top = rect.top - formContentRect.top + scrollTop;
            if (scrollTop >= top - 100) {
                activeSection = section.id;
            }
        });
        
        if (activeSection) {
            sectionLinks.forEach(link => {
                const href = link.getAttribute('href').substring(1);
                if (href === activeSection) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        }
    }
    
    formContent.addEventListener('scroll', debounce(updateActiveSectionNav, 100));
    
    // Set initial active section
    if (sectionLinks.length > 0) {
        sectionLinks[0].classList.add('active');
    }

    // Trigger preview on load
    setTimeout(updatePreview, 100);
})();
</script>
</body>
</html>

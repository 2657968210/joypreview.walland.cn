<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>电子请柬编辑器</title>
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

        /* ─── 双栏布局 ─── */
        .editor-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* 左：预览区 */
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

        /* 缩放容器 */
        .preview-sandbox {
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        #previewFrame {
            position: absolute;
            top: 0;
            left: 0;
            border: none;
            display: block;
            /* width / transform-origin set by JS */
        }

        /* 加载遮罩 */
        .preview-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-preview);
            z-index: 5;
            transition: opacity .3s;
        }

        .preview-overlay.hidden { opacity: 0; pointer-events: none; }

        .preview-placeholder {
            text-align: center;
            color: #999;
        }

        .preview-placeholder .icon {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            opacity: .4;
        }

        .preview-placeholder p {
            font-size: 14px;
            line-height: 1.6;
        }

        .preview-loading {
            display: none;
            width: 32px;
            height: 32px;
            border: 3px solid #ddd;
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        .preview-loading.active { display: block; }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ─── 右：表单区 ─── */
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

        /* 步骤指示 */
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

        /* 步骤显示/隐藏 */
        .step { display: none; }
        .step.active { display: block; }

        /* 进度条 */
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

        /* 表单行（两列） */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        /* 浮动标签 */
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

        /* 有内容或focus时标签上浮 */
        .form-group input:focus ~ label,
        .form-group input:not(:placeholder-shown) ~ label,
        .form-group textarea:focus ~ label,
        .form-group textarea:not(:placeholder-shown) ~ label {
            top: 8px;
            transform: none;
            font-size: 11px;
            color: var(--accent);
        }

        /* 区块标题 */
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

        /* 按钮 */
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

        .helper-text {
            text-align: center;
            font-size: 13px;
            color: var(--label);
            margin-top: 14px;
        }

        /* 日期输入 */
        .form-group input[type="date"] {
            color: var(--text);
            cursor: pointer;
        }

        /* 分隔线 */
        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 24px 0;
        }

        /* ─── 响应式：移动端 ─── */
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

    <!-- ░░ 左栏：实时预览 ░░ -->
    <div class="panel-preview">
        <div class="panel-preview-header">
            <span>实时预览</span>
            <div class="preview-actions">
                <div class="preview-loading" id="loadingSpinner"></div>
                <a id="btnFullPreview" href="#" target="_blank">全屏预览 ↗</a>
            </div>
        </div>

        <div class="preview-sandbox" id="previewSandbox">
            <!-- 占位提示（填写前显示） -->
            <div class="preview-overlay" id="previewOverlay">
                <div class="preview-placeholder">
                    <span class="icon">💌</span>
                    <p>填写右侧信息<br>即可在此看到请柬预览</p>
                </div>
            </div>
            <iframe id="previewFrame" title="请柬预览" sandbox="allow-scripts allow-same-origin"></iframe>
        </div>
    </div>

    <!-- ░░ 右栏：编辑表单 ░░ -->
    <div class="panel-form">
        <div class="form-inner">
            <!-- 步骤指示 -->
            <div class="step-indicator" id="stepIndicator">第 1 步，共 2 步</div>
            <div class="progress-bar"><div class="progress-fill" id="progressFill" style="width:50%"></div></div>
            <h1 class="form-title" id="formTitle">看起来不错！来填写你们的信息</h1>

            <!-- ── Step 1：基本信息 ── -->
            <div class="step active" id="step1">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" id="bride_firstname" name="bride_firstname" placeholder=" " autocomplete="given-name">
                        <label for="bride_firstname">您的名字</label>
                    </div>
                    <div class="form-group">
                        <input type="text" id="bride_lastname" name="bride_lastname" placeholder=" " autocomplete="family-name">
                        <label for="bride_lastname">您的姓氏</label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <input type="text" id="groom_firstname" name="groom_firstname" placeholder=" " autocomplete="off">
                        <label for="groom_firstname">伴侣的名字</label>
                    </div>
                    <div class="form-group">
                        <input type="text" id="groom_lastname" name="groom_lastname" placeholder=" " autocomplete="off">
                        <label for="groom_lastname">伴侣的姓氏</label>
                    </div>
                </div>

                <div class="form-group">
                    <input type="date" id="wedding_date" name="wedding_date" placeholder=" ">
                    <label for="wedding_date">婚礼日期</label>
                </div>

                <div class="form-group">
                    <input type="text" id="wedding_city" name="wedding_city" placeholder=" " autocomplete="off">
                    <label for="wedding_city">婚礼城市（可选）</label>
                </div>

                <button class="btn-continue" id="btnStep1" onclick="goToStep(2)">继续</button>
                <p class="helper-text">您随时可以回来修改这些信息</p>
            </div>

            <!-- ── Step 2：仪式与详情 ── -->
            <div class="step" id="step2">
                <button class="btn-back" onclick="goToStep(1)">← 返回</button>

                <div class="section-title">婚礼仪式</div>

                <div class="form-group">
                    <input type="text" id="ceremony_date" name="ceremony_date" placeholder=" " autocomplete="off">
                    <label for="ceremony_date">仪式日期（如：MAY 10, 2026）</label>
                </div>

                <div class="form-group">
                    <input type="text" id="ceremony_time" name="ceremony_time" placeholder=" " autocomplete="off">
                    <label for="ceremony_time">仪式时间（如：4 O'CLOCK IN THE AFTERNOON）</label>
                </div>

                <div class="form-group">
                    <input type="text" id="ceremony_venue" name="ceremony_venue" placeholder=" " autocomplete="off">
                    <label for="ceremony_venue">仪式场地名称</label>
                </div>

                <div class="form-group">
                    <textarea id="ceremony_addr" name="ceremony_addr" placeholder=" " rows="3"></textarea>
                    <label for="ceremony_addr">仪式场地地址（可多行）</label>
                </div>

                <div class="section-title">招待会</div>

                <div class="form-group">
                    <input type="text" id="reception_time" name="reception_time" placeholder=" " autocomplete="off">
                    <label for="reception_time">招待会时间（如：5:30 PM IN THE EVENING）</label>
                </div>

                <div class="form-group">
                    <input type="text" id="reception_venue" name="reception_venue" placeholder=" " autocomplete="off">
                    <label for="reception_venue">招待会场地名称</label>
                </div>

                <div class="form-group">
                    <textarea id="reception_addr" name="reception_addr" placeholder=" " rows="3"></textarea>
                    <label for="reception_addr">招待会场地地址（可多行）</label>
                </div>

                <div class="form-group">
                    <input type="url" id="map_link" name="map_link" placeholder=" " autocomplete="off">
                    <label for="map_link">地图链接（Google Maps 等，可选）</label>
                </div>

                <div class="section-title">RSVP 回复</div>

                <div class="form-group">
                    <input type="text" id="rsvp_deadline" name="rsvp_deadline" placeholder=" " autocomplete="off">
                    <label for="rsvp_deadline">回复截止日期（如：BY APRIL 1ST）</label>
                </div>

                <div class="form-group">
                    <input type="url" id="rsvp_link" name="rsvp_link" placeholder=" " autocomplete="off">
                    <label for="rsvp_link">RSVP 表单链接（可选）</label>
                </div>

                <hr class="divider">

                <!-- 下载表单（POST 方式下载文件） -->
                <form id="downloadForm" method="POST" action="download.php" target="_blank">
                    <input type="hidden" name="bride_firstname"  id="dl_bride_firstname">
                    <input type="hidden" name="bride_lastname"   id="dl_bride_lastname">
                    <input type="hidden" name="groom_firstname"  id="dl_groom_firstname">
                    <input type="hidden" name="groom_lastname"   id="dl_groom_lastname">
                    <input type="hidden" name="ceremony_date"    id="dl_ceremony_date">
                    <input type="hidden" name="ceremony_time"    id="dl_ceremony_time">
                    <input type="hidden" name="ceremony_venue"   id="dl_ceremony_venue">
                    <input type="hidden" name="ceremony_addr"    id="dl_ceremony_addr">
                    <input type="hidden" name="reception_time"   id="dl_reception_time">
                    <input type="hidden" name="reception_venue"  id="dl_reception_venue">
                    <input type="hidden" name="reception_addr"   id="dl_reception_addr">
                    <input type="hidden" name="map_link"         id="dl_map_link">
                    <input type="hidden" name="rsvp_deadline"    id="dl_rsvp_deadline">
                    <input type="hidden" name="rsvp_link"        id="dl_rsvp_link">
                    <button type="submit" class="btn-download" onclick="syncDownloadForm()">⬇ 下载请柬 HTML</button>
                </form>

                <p class="helper-text">下载后可直接部署到网站</p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    /* ── 表单字段列表 ── */
    const FIELDS = [
        'bride_firstname', 'bride_lastname',
        'groom_firstname', 'groom_lastname',
        'ceremony_date', 'ceremony_time',
        'ceremony_venue', 'ceremony_addr',
        'reception_time', 'reception_venue', 'reception_addr',
        'map_link', 'rsvp_deadline', 'rsvp_link'
    ];

    const previewFrame   = document.getElementById('previewFrame');
    const previewSandbox = document.getElementById('previewSandbox');
    const previewOverlay = document.getElementById('previewOverlay');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const btnFullPreview = document.getElementById('btnFullPreview');

    let debounceTimer = null;
    let hasInput      = false;

    /* ── iframe 缩放 ── */
    function scalePreview() {
        const panelW = previewSandbox.offsetWidth;
        const panelH = previewSandbox.offsetHeight;
        if (!panelW) return;

        const TEMPLATE_W = 1920;
        const scale = panelW / TEMPLATE_W;
        const frameH = Math.ceil(panelH / scale);

        previewFrame.style.width          = TEMPLATE_W + 'px';
        previewFrame.style.height         = frameH + 'px';
        previewFrame.style.transform      = `scale(${scale})`;
        previewFrame.style.transformOrigin = 'top left';
    }

    window.addEventListener('resize', scalePreview);
    scalePreview();

    /* ── 收集当前表单数据 ── */
    function collectData() {
        const data = new FormData();

        // 从日期输入格式化为大写文字
        const dateInput = document.getElementById('wedding_date').value;
        if (dateInput && !document.getElementById('ceremony_date').value) {
            const d = new Date(dateInput + 'T00:00:00');
            const months = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE',
                            'JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];
            const formatted = months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
            data.append('ceremony_date', formatted);
        }

        FIELDS.forEach(id => {
            const el = document.getElementById(id);
            if (el) data.append(id, el.value);
        });

        return data;
    }

    /* ── 触发预览更新 ── */
    function schedulePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(updatePreview, 600);
    }

    async function updatePreview() {
        hasInput = true;
        loadingSpinner.classList.add('active');

        try {
            const resp = await fetch('preview.php', {
                method: 'POST',
                body: collectData()
            });

            if (!resp.ok) throw new Error('preview failed');

            const html = await resp.text();
            previewFrame.srcdoc = html;

            // 隐藏占位提示
            previewOverlay.classList.add('hidden');

            // 更新全屏预览链接（用 Blob URL）
            const blob = new Blob([html], { type: 'text/html' });
            const old = btnFullPreview._blobUrl;
            if (old) URL.revokeObjectURL(old);
            btnFullPreview._blobUrl = URL.createObjectURL(blob);
            btnFullPreview.href = btnFullPreview._blobUrl;

        } catch (e) {
            console.error('Preview error:', e);
        } finally {
            loadingSpinner.classList.remove('active');
        }
    }

    /* ── 监听所有字段 ── */
    FIELDS.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', schedulePreview);
    });

    // 日期选择器
    document.getElementById('wedding_date').addEventListener('change', schedulePreview);

    /* ── 步骤切换 ── */
    window.goToStep = function (n) {
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        document.getElementById('step' + n).classList.add('active');

        const total = 2;
        document.getElementById('stepIndicator').textContent = `第 ${n} 步，共 ${total} 步`;
        document.getElementById('progressFill').style.width  = (n / total * 100) + '%';

        const titles = {
            1: '看起来不错！来填写你们的信息',
            2: '完善仪式与 RSVP 详情'
        };
        document.getElementById('formTitle').textContent = titles[n] || '';
        document.querySelector('.panel-form').scrollTop = 0;

        // 进入第2步时触发第一次预览
        if (n === 2 && !hasInput) updatePreview();
    };

    /* ── 同步下载表单隐藏字段 ── */
    window.syncDownloadForm = function () {
        FIELDS.forEach(id => {
            const src = document.getElementById(id);
            const dst = document.getElementById('dl_' + id);
            if (src && dst) dst.value = src.value;
        });

        // 同步日期
        const dateInput = document.getElementById('wedding_date').value;
        const dlDate    = document.getElementById('dl_ceremony_date');
        if (dateInput && !document.getElementById('ceremony_date').value && dlDate) {
            const d = new Date(dateInput + 'T00:00:00');
            const months = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE',
                            'JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];
            dlDate.value = months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
        }
    };

    // 初始化缩放
    setTimeout(scalePreview, 100);
})();
</script>
</body>
</html>

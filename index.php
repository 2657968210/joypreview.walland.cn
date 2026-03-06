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
            position: absolute;
            top: 0;
            left: 0;
            border: none;
            display: block;
            /* width / transform-origin set by JS */
        }

        /* Loading overlay */
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

        .helper-text {
            text-align: center;
            font-size: 13px;
            color: var(--label);
            margin-top: 14px;
        }

        /* Date input */
        .form-group input[type="date"] {
            color: var(--text);
            cursor: pointer;
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
                <div class="preview-loading" id="loadingSpinner"></div>
                <a id="btnFullPreview" href="#" target="_blank">Full Screen ↗</a>
            </div>
        </div>

        <div class="preview-sandbox" id="previewSandbox">
            <!-- Placeholder (shown before input) -->
            <div class="preview-overlay" id="previewOverlay">
                <div class="preview-placeholder">
                    <span class="icon">💌</span>
                    <p>Fill in your details on the right<br>to see a preview of your invitation</p>
                </div>
            </div>
            <iframe id="previewFrame" title="Invitation Preview" sandbox="allow-scripts allow-same-origin"></iframe>
        </div>
    </div>

    <!-- ░░ Right panel: edit form ░░ -->
    <div class="panel-form">
        <div class="form-inner">
            <!-- Step indicator -->
            <div class="step-indicator" id="stepIndicator">Step 1 of 2</div>
            <div class="progress-bar"><div class="progress-fill" id="progressFill" style="width:50%"></div></div>
            <h1 class="form-title" id="formTitle">Looks great! Let's add your info</h1>

            <!-- ── Step 1: Basic Info ── -->
            <div class="step active" id="step1">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" id="bride_firstname" name="bride_firstname" placeholder=" " autocomplete="given-name">
                        <label for="bride_firstname">Your first name</label>
                    </div>
                    <div class="form-group">
                        <input type="text" id="bride_lastname" name="bride_lastname" placeholder=" " autocomplete="family-name">
                        <label for="bride_lastname">Your last name</label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <input type="text" id="groom_firstname" name="groom_firstname" placeholder=" " autocomplete="off">
                        <label for="groom_firstname">Partner's first name</label>
                    </div>
                    <div class="form-group">
                        <input type="text" id="groom_lastname" name="groom_lastname" placeholder=" " autocomplete="off">
                        <label for="groom_lastname">Partner's last name</label>
                    </div>
                </div>

                <div class="form-group">
                    <input type="date" id="wedding_date" name="wedding_date" placeholder=" ">
                    <label for="wedding_date">Wedding date</label>
                </div>

                <div class="form-group">
                    <input type="text" id="wedding_city" name="wedding_city" placeholder=" " autocomplete="off">
                    <label for="wedding_city">Wedding city (optional)</label>
                </div>

                <button class="btn-continue" id="btnStep1" onclick="goToStep(2)">Continue</button>
                <p class="helper-text">You can easily edit this info later.</p>
            </div>

            <!-- ── Step 2: Ceremony & Details ── -->
            <div class="step" id="step2">
                <button class="btn-back" onclick="goToStep(1)">← Back</button>

                <div class="section-title">CEREMONY</div>

                <div class="form-group">
                    <input type="text" id="ceremony_date" name="ceremony_date" placeholder=" " autocomplete="off">
                    <label for="ceremony_date">Ceremony date (e.g. MAY 10, 2026)</label>
                </div>

                <div class="form-group">
                    <input type="text" id="ceremony_time" name="ceremony_time" placeholder=" " autocomplete="off">
                    <label for="ceremony_time">Ceremony time (e.g. 4 O'CLOCK IN THE AFTERNOON)</label>
                </div>

                <div class="form-group">
                    <input type="text" id="ceremony_venue" name="ceremony_venue" placeholder=" " autocomplete="off">
                    <label for="ceremony_venue">Ceremony venue name</label>
                </div>

                <div class="form-group">
                    <textarea id="ceremony_addr" name="ceremony_addr" placeholder=" " rows="3"></textarea>
                    <label for="ceremony_addr">Ceremony address (multi-line)</label>
                </div>

                <div class="section-title">RECEPTION</div>

                <div class="form-group">
                    <input type="text" id="reception_time" name="reception_time" placeholder=" " autocomplete="off">
                    <label for="reception_time">Reception time (e.g. 5:30 PM IN THE EVENING)</label>
                </div>

                <div class="form-group">
                    <input type="text" id="reception_venue" name="reception_venue" placeholder=" " autocomplete="off">
                    <label for="reception_venue">Reception venue name</label>
                </div>

                <div class="form-group">
                    <textarea id="reception_addr" name="reception_addr" placeholder=" " rows="3"></textarea>
                    <label for="reception_addr">Reception address (multi-line)</label>
                </div>

                <div class="form-group">
                    <input type="url" id="map_link" name="map_link" placeholder=" " autocomplete="off">
                    <label for="map_link">Map link (Google Maps, optional)</label>
                </div>

                <div class="section-title">RSVP</div>

                <div class="form-group">
                    <input type="text" id="rsvp_deadline" name="rsvp_deadline" placeholder=" " autocomplete="off">
                    <label for="rsvp_deadline">RSVP deadline (e.g. BY APRIL 1ST)</label>
                </div>

                <div class="form-group">
                    <input type="url" id="rsvp_link" name="rsvp_link" placeholder=" " autocomplete="off">
                    <label for="rsvp_link">RSVP form link (optional)</label>
                </div>

                <hr class="divider">

                <!-- Download form (POST) -->
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
                    <button type="submit" class="btn-download" onclick="syncDownloadForm()">⬇ Download Invitation HTML</button>
                </form>

                <p class="helper-text">Ready to deploy after download</p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    /* ── Form field list ── */
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

    /* ── iframe scaling ── */
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

    /* ── Collect form data ── */
    function collectData() {
        const data = new FormData();

        FIELDS.forEach(id => {
            const el = document.getElementById(id);
            if (el) data.append(id, el.value);
        });

        // Format date picker value to uppercase text
        // Must run after FIELDS loop so data.set() correctly overrides the empty ceremony_date
        if (!document.getElementById('ceremony_date').value) {
            const dateInput = document.getElementById('wedding_date').value;
            if (dateInput) {
                const d = new Date(dateInput + 'T00:00:00');
                const months = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE',
                                'JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];
                data.set('ceremony_date', months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear());
            }
        }

        return data;
    }

    /* ── Trigger preview update ── */
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

            // Hide placeholder overlay
            previewOverlay.classList.add('hidden');

            // Update full-screen preview link (Blob URL)
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

    /* ── Listen to all fields ── */
    FIELDS.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', schedulePreview);
    });

    // Date picker
    document.getElementById('wedding_date').addEventListener('change', schedulePreview);

    /* ── Step navigation ── */
    window.goToStep = function (n) {
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        document.getElementById('step' + n).classList.add('active');

        const total = 2;
        document.getElementById('stepIndicator').textContent = `Step ${n} of ${total}`;
        document.getElementById('progressFill').style.width  = (n / total * 100) + '%';

        const titles = {
            1: "Looks great! Let's add your info",
            2: 'Add ceremony & RSVP details'
        };
        document.getElementById('formTitle').textContent = titles[n] || '';
        document.querySelector('.panel-form').scrollTop = 0;

        // Trigger first preview on entering step 2
        if (n === 2 && !hasInput) updatePreview();
    };

    /* ── Sync hidden download form fields ── */
    window.syncDownloadForm = function () {
        FIELDS.forEach(id => {
            const src = document.getElementById(id);
            const dst = document.getElementById('dl_' + id);
            if (src && dst) dst.value = src.value;
        });

        // Sync date
        const dlDate = document.getElementById('dl_ceremony_date');
        if (dlDate && !document.getElementById('ceremony_date').value) {
            const dateInput = document.getElementById('wedding_date').value;
            if (dateInput) {
                const d = new Date(dateInput + 'T00:00:00');
                const months = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE',
                                'JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];
                dlDate.value = months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
            }
        }
    };

    // Init scaling
    setTimeout(scalePreview, 100);
})();
</script>
</body>
</html>

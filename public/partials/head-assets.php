    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        border: '#e4e4e7',
                        input: '#e4e4e7',
                        ring: '#18181b',
                        background: '#c9c9ce',
                        foreground: '#18181b',
                        muted: '#f4f4f5',
                        'muted-foreground': '#71717a',
                        primary: '#18181b',
                        'primary-foreground': '#fafafa',
                    },
                    boxShadow: {
                        soft: '0 18px 60px rgba(24, 24, 27, 0.08)',
                        button: '0 1px 2px rgba(24, 24, 27, 0.08)',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.16s ease-out; }
        .animate-slide-up, .animate-slide-up-delay, .animate-slide-up-late { animation: fadeIn 0.16s ease-out; }
        #settingsDrawer { transform: translateX(100%); }
        #settingsDrawer.drawer-open { transform: translateX(0); }
        #settingsBackdrop[hidden] { display: none; }
        :root {
            --dr-border: #e7e5e4;
            --dr-shadow: 0 1px 2px rgba(15,23,42,.04), 0 6px 20px rgba(15,23,42,.04);
            --dr-shadow-hover: 0 2px 4px rgba(15,23,42,.04), 0 10px 26px rgba(15,23,42,.07);
        }
        /* Panel surface lives on .dr-card; this only keeps the stacking context for dropdowns. */
        #dailyReportForm > div, #dailyReportForm > fieldset { position: relative; }
        .settings-card {
            position:relative;
            background:color-mix(in srgb,var(--setting-accent,#a8a29e) 8%,#fff) !important;
            border:1px solid var(--dr-border) !important;
            border-radius:12px;
            box-shadow:0 1px 3px rgba(15,23,42,.035);
        }
        .settings-card { border-color:color-mix(in srgb,var(--setting-accent,#a8a29e) 28%,#e7e5e4) !important; }
        .setting-default { --setting-accent:#8b5cf6; }
        .setting-projects { --setting-accent:#38bdf8; }
        .setting-form { --setting-accent:#34d399; }
        .setting-range { --setting-accent:#f59e0b; }
        .setting-wepro { --setting-accent:#818cf8; }
        .project-setting { border-color:#e7e5e4 !important; transition:box-shadow .18s ease; }
        .project-setting:has(.project-setting-body:not(.hidden)) { box-shadow:0 4px 14px rgba(15,23,42,.06); }
        .project-setting-body:not(.hidden), #googleFormSettingsBody:not(.hidden) { animation: revealPanel .12s ease-out; }
        @keyframes revealPanel { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
        #dailyReportForm input:not([type="hidden"]), #dailyReportForm select, #dailyReportForm textarea,
        #settingsDrawer input:not([type="checkbox"]), #settingsDrawer select, #settingsDrawer textarea {
            min-height:38px;
            border:1px solid #dedbd7 !important;
            border-radius:10px !important;
            background-color:#fff !important;
            box-shadow:0 1px 2px rgba(15,23,42,.03) !important;
            transition:border-color .16s ease, box-shadow .16s ease;
        }
        #dailyReportForm select, #settingsDrawer select {
            appearance:none;
            padding-right:32px;
            background-image:linear-gradient(45deg,transparent 50%,#78716c 50%),linear-gradient(135deg,#78716c 50%,transparent 50%) !important;
            background-position:calc(100% - 15px) 50%,calc(100% - 10px) 50% !important;
            background-size:5px 5px,5px 5px !important;
            background-repeat:no-repeat !important;
        }
        #dailyReportForm input:not([type="hidden"]):focus, #dailyReportForm select:focus, #dailyReportForm textarea:focus,
        #settingsDrawer input:not([type="checkbox"]):focus, #settingsDrawer select:focus, #settingsDrawer textarea:focus {
            border-color:#a8a29e !important;
            box-shadow:0 0 0 3px rgba(120,113,108,.1) !important;
        }
        #dailyReportForm button, #settingsDrawer button { border-radius:10px; transition:background-color .16s ease, box-shadow .16s ease, transform .12s ease; }
        #dailyReportForm button:active, #settingsDrawer button:active { transform:scale(.985); }
        .dr-select { position:relative; min-width:0; }
        .dr-select.is-open { z-index:200; }
        #dailyReportForm > div:has(.dr-select.is-open), .task-today-item:has(.dr-select.is-open), .task-tomorrow-item:has(.dr-select.is-open) { z-index:190; }
        .dr-select-native { position:absolute !important; width:1px !important; height:1px !important; padding:0 !important; margin:-1px !important; overflow:hidden !important; clip:rect(0,0,0,0) !important; white-space:nowrap !important; border:0 !important; }
        .dr-select-trigger { display:flex; width:100%; min-height:38px; align-items:center; justify-content:space-between; gap:8px; border:1px solid #dedbd7; border-radius:10px; background:#fff; padding:0 10px 0 12px; color:#292524; font-size:14px; box-shadow:0 1px 2px rgba(15,23,42,.03); text-align:left; }
        .dr-select-trigger:hover { background:#fafaf9; }
        .dr-select-trigger:focus { outline:none; border-color:#a8a29e; box-shadow:0 0 0 3px rgba(120,113,108,.1); }
        .dr-select-caret { color:#a8a29e; transition:transform .16s ease; }
        .dr-select.is-open .dr-select-caret { transform:rotate(180deg); }
        .dr-select-menu { position:fixed; z-index:2147483647; inset:auto; margin:0; max-height:240px; overflow:auto; border:1px solid #e7e5e4; border-radius:11px; background:#fff; padding:5px; box-shadow:0 14px 34px rgba(15,23,42,.13); animation:revealPanel .1s ease-out; }
        .dr-select-option { display:flex; width:100%; min-height:34px; align-items:center; justify-content:space-between; border-radius:7px !important; padding:7px 9px; color:#57534e; font-size:13px; text-align:left; }
        .dr-select-option:hover,.dr-select-option.is-active { background:#f5f5f4; color:#1c1917; }
        .dr-select-option.is-selected { color:#0c4a6e; font-weight:600; }
        .dr-select-option.is-selected::after { content:"✓"; color:#0284c7; }
        .dr-date { position:relative; min-width:0; }
        .dr-date.is-open { z-index:210; }
        #dailyReportForm > div:has(.dr-date.is-open), .task-today-item:has(.dr-date.is-open) { z-index:200; }
        .dr-date-native { position:absolute !important; width:1px !important; height:1px !important; margin:-1px !important; overflow:hidden !important; clip:rect(0,0,0,0) !important; border:0 !important; }
        .dr-date-trigger { display:flex; width:100%; min-height:38px; align-items:center; justify-content:space-between; gap:8px; border:1px solid #dedbd7; border-radius:10px; background:#fff; padding:0 10px 0 12px; color:#292524; font-size:13px; text-align:left; box-shadow:0 1px 2px rgba(15,23,42,.03); }
        .dr-date-trigger:hover { background:#fafaf9; }
        .dr-date-trigger:focus { outline:none; border-color:#a8a29e; box-shadow:0 0 0 3px rgba(120,113,108,.1); }
        .dr-date-placeholder { color:#a8a29e; }
        .dr-calendar { position:fixed; z-index:2147483647; inset:auto; margin:0; width:292px; border:1px solid #e7e5e4; border-radius:13px; background:#fff; padding:10px; box-shadow:0 16px 38px rgba(15,23,42,.14); animation:revealPanel .1s ease-out; }
        .dr-calendar-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
        .dr-calendar-title { color:#292524; font-size:13px; font-weight:650; }
        .dr-calendar-nav { display:flex; height:30px; width:30px; align-items:center; justify-content:center; border-radius:8px !important; color:#78716c; }
        .dr-calendar-nav:hover { background:#f5f5f4; }
        .dr-calendar-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:2px; }
        .dr-calendar-weekday { padding:5px 0; color:#a8a29e; font-size:10px; font-weight:600; text-align:center; }
        .dr-calendar-day { display:flex; aspect-ratio:1; align-items:center; justify-content:center; border-radius:8px !important; color:#57534e; font-size:12px; }
        .dr-calendar-day:hover { background:#f5f5f4; color:#1c1917; }
        .dr-calendar-day.is-today { box-shadow:inset 0 0 0 1px #cbd5e1; }
        .dr-calendar-day.is-selected { background:#18181b; color:#fff; font-weight:600; }
        .dr-calendar-day.is-outside { color:#d6d3d1; }
        .dr-calendar-footer { display:flex; justify-content:space-between; gap:6px; margin-top:8px; border-top:1px solid #f0efed; padding-top:8px; }
        .dr-calendar-action { border-radius:8px !important; padding:5px 8px; color:#57534e; font-size:11px; }
        .dr-calendar-action:hover { background:#f5f5f4; }
        .task-drag-handle { cursor:grab; touch-action:none; user-select:none; }
        .task-drag-handle:active { cursor:grabbing; }
        .task-sort-ghost { position:relative; opacity:1 !important; border:1.5px dashed #38bdf8; border-radius:10px; background:#f0f9ff; box-shadow:none; }
        .task-sort-ghost > * { visibility:hidden; }
        .task-sort-ghost::after { content:"Thả task tại đây"; position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#0284c7; font-size:11px; font-weight:650; visibility:visible; }
        .task-sort-chosen { cursor:grabbing; }
        .task-sort-drag { opacity:.96 !important; border-radius:10px; background:#fff; box-shadow:0 14px 34px rgba(15,23,42,.16); transform:rotate(.5deg); }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
        }
        .dr-swal {
            width: min(92vw, 680px);
            border: 1px solid #e4e4e7;
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 18px 60px rgba(24, 24, 27, 0.12);
        }
        .dr-swal-title {
            padding: 18px 20px 0;
            color: #09090b;
            font-size: 18px;
            font-weight: 650;
            letter-spacing: 0;
            text-align: left;
        }
        .dr-swal-html {
            margin: 0;
            padding: 14px 20px 0;
        }
        .dr-modal-field {
            min-height: 220px;
            width: 100%;
            resize: vertical;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            background: #fff;
            padding: 12px;
            color: #18181b;
            font-size: 13px;
            line-height: 1.55;
            box-shadow: 0 1px 2px rgba(24, 24, 27, 0.08);
            outline: none;
        }
        .dr-modal-field:focus {
            border-color: #18181b;
            box-shadow: 0 0 0 2px rgba(24, 24, 27, 0.12);
        }
        .dr-modal-field[readonly] {
            min-height: 360px;
            background: #fafafa;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
        }
        .dr-modal-actions {
            justify-content: flex-end;
            gap: 8px;
            padding: 16px 20px 20px;
        }
        .dr-modal-confirm,
        .dr-modal-cancel {
            height: 36px;
            border-radius: 6px;
            padding: 0 14px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(24, 24, 27, 0.08);
        }
        .dr-modal-confirm {
            border: 1px solid #18181b;
            background: #18181b;
            color: #fff;
        }
        .dr-modal-cancel {
            border: 1px solid #e4e4e7;
            background: #fff;
            color: #3f3f46;
        }

        /* ===== Section cards: neutral surface + colored accent so sections stay distinct ===== */
        .dr-card { position:relative; overflow:hidden; border:1px solid #d4d4d8; border-left:4px solid var(--dr-accent,#a1a1aa); border-radius:12px; background:#fff; box-shadow:0 1px 2px rgba(24,24,27,.08),0 10px 28px rgba(24,24,27,.10); }
        .dr-card-head { display:flex; align-items:center; gap:10px; border-bottom:1px solid #f4f4f5; background:var(--dr-accent-soft,#fafafa); padding:10px 14px; }
        .dr-card-badge { display:flex; height:26px; width:26px; flex-shrink:0; align-items:center; justify-content:center; border-radius:8px; background:var(--dr-accent,#a1a1aa); color:#fff; font-size:12px; font-weight:700; }
        .dr-card-title { color:#18181b; font-size:14px; font-weight:650; line-height:1.2; }
        .dr-card-hint { display:block; margin-top:2px; color:#71717a; font-size:11px; font-weight:400; }
        .dr-card-body { padding:12px 14px; }

        /* ===== Action buttons: always tinted/filled, never white — inputs are the white ones ===== */
        .dr-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; border-top:1px dashed #e4e4e7; padding-top:10px; }
        .dr-action { display:inline-flex; height:34px; align-items:center; justify-content:center; gap:6px; border:1px solid #d4d4d8; border-radius:8px; background:#f4f4f5; padding:0 12px; color:#3f3f46; font-size:13px; font-weight:600; box-shadow:0 1px 1px rgba(24,24,27,.04); }
        .dr-action:hover { border-color:#a1a1aa; background:#e4e4e7; color:#18181b; }
        .dr-action:focus-visible { outline:none; box-shadow:0 0 0 3px rgba(24,24,27,.14); }
        .dr-action-primary { border-color:#18181b; background:#18181b; color:#fff; }
        .dr-action-primary:hover { border-color:#27272a; background:#27272a; color:#fff; }
        .dr-action-icon { height:15px; width:15px; }
        /* Colour = function: indigo WePRO, sky logtime, emerald bulk input, violet AI, amber backfill, red destructive */
        .dr-action-indigo { border-color:#4f46e5; background:#4f46e5; color:#fff; }
        .dr-action-indigo:hover { border-color:#4338ca; background:#4338ca; color:#fff; }
        .dr-action-indigo-soft { border-color:#c7d2fe; background:#eef2ff; color:#4338ca; }
        .dr-action-indigo-soft:hover { border-color:#a5b4fc; background:#e0e7ff; color:#3730a3; }
        .dr-action-sky { border-color:#bae6fd; background:#f0f9ff; color:#0369a1; }
        .dr-action-sky:hover { border-color:#7dd3fc; background:#e0f2fe; color:#075985; }
        .dr-action-emerald { border-color:#a7f3d0; background:#ecfdf5; color:#047857; }
        .dr-action-emerald:hover { border-color:#6ee7b7; background:#d1fae5; color:#065f46; }
        .dr-action-violet { border-color:#ddd6fe; background:#f5f3ff; color:#6d28d9; }
        .dr-action-violet:hover { border-color:#c4b5fd; background:#ede9fe; color:#5b21b6; }
        .dr-action-danger { margin-left:auto; color:#be123c; }
        .dr-action-danger:hover { border-color:#fecdd3; background:#fff1f2; color:#e11d48; }

        /* ===== Progress strip, optional chips, more-menu, status badge ===== */
        .dr-progress { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
        .dr-progress-step { display:flex; align-items:center; gap:8px; border:1px solid #d4d4d8; border-radius:10px; background:#fff; padding:8px 12px; font-size:13px; font-weight:600; color:#52525b; text-align:left; cursor:pointer; }
        .dr-progress-step:hover { border-color:#a1a1aa; color:#18181b; }
        .dr-progress-num { display:flex; height:24px; width:24px; flex-shrink:0; align-items:center; justify-content:center; border-radius:999px; background:#e4e4e7; color:#3f3f46; font-size:12px; font-weight:700; }
        .dr-progress-step.is-done { color:#047857; border-color:#a7f3d0; background:#f0fdf4; }
        .dr-progress-step.is-done .dr-progress-num { background:#059669; color:#fff; }
        @media (max-width:640px) { .dr-progress-step { font-size:12px; padding:8px; } }
        .dr-optional-bar { display:flex; flex-wrap:wrap; align-items:center; gap:8px; padding:0 2px; }
        .dr-optional-label { font-size:12.5px; font-weight:600; color:#3f3f46; }
        .dr-chip { height:32px; border:1px dashed #71717a; border-radius:999px; background:rgba(255,255,255,.75); padding:0 12px; font-size:12.5px; font-weight:600; color:#3f3f46; cursor:pointer; }
        .dr-chip:hover { border-style:solid; background:#fff; color:#18181b; }
        .dr-chip.hidden { display:none; }
        .dr-optional.hidden { display:none; }
        .dr-optional-bar.hidden { display:none; }
        .dr-card-close { margin-left:auto; height:26px; width:26px; flex-shrink:0; border:1px solid #e4e4e7; border-radius:7px; background:#fff; color:#71717a; font-size:14px; line-height:1; cursor:pointer; }
        .dr-card-close:hover { border-color:#fecdd3; background:#fff1f2; color:#e11d48; }
        .dr-status { display:inline-flex; align-items:center; gap:6px; height:36px; border:1px solid #d4d4d8; border-radius:8px; background:#fff; padding:0 10px; font-size:12px; font-weight:600; color:#52525b; }
        .dr-status.hidden { display:none; }
        .dr-status-dot { height:8px; width:8px; border-radius:999px; background:#a1a1aa; }
        .dr-status.ok .dr-status-dot { background:#059669; }
        .dr-status.err .dr-status-dot { background:#e11d48; }
        .dr-status.err { color:#be123c; border-color:#fecdd3; cursor:pointer; }
        .dr-status.wait .dr-status-dot { background:#f59e0b; animation:drPulse 1s ease-in-out infinite; }
        .dr-status.off { color:#71717a; border-style:dashed; cursor:pointer; }
        .dr-status { cursor:pointer; }
        .dr-status.noproj { color:#b45309; border-color:#fde68a; background:#fffbeb; }
        .dr-status.noproj .dr-status-dot { background:#f59e0b; }
        .dr-ws { text-align:left; display:flex; flex-direction:column; gap:6px; }
        .dr-ws-row { display:grid; grid-template-columns:130px 1fr; gap:10px; border-bottom:1px dashed #e4e4e7; padding:6px 0; font-size:13px; }
        .dr-ws-label { font-weight:600; color:#52525b; }
        .dr-ws-value { color:#18181b; word-break:break-word; }
        .dr-ws-value.is-ok { color:#047857; font-weight:600; }
        .dr-ws-value.is-bad { color:#be123c; font-weight:600; }
        .dr-status.off .dr-status-dot { background:#a1a1aa; }
        @keyframes drPulse { 0%,100% { opacity:1; } 50% { opacity:.35; } }
        /* ===== Send panel: in-flow card below the form; docked right on wide screens ===== */
        .quality-btn.is-selected, .react-emoji.is-selected { outline:2.5px solid #18181b; outline-offset:2px; font-weight:700; }
        .dr-sendbar { margin:20px auto 0; max-width:64rem; }
        .dr-sendbar-inner { padding:12px 14px 12px; display:flex; flex-direction:column; gap:12px; }
        .dr-dest-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
        .dr-dest { display:flex; align-items:center; gap:10px; min-height:54px; border:1px solid #e4e4e7; border-radius:12px; background:#fafafa; padding:8px 12px 8px 10px; cursor:pointer; user-select:none; transition:border-color .16s ease, background-color .16s ease, box-shadow .16s ease; }
        .dr-dest:hover { border-color:#d4d4d8; background:#fff; }
        .dr-dest:has(.dr-switch-input:checked) { border-color:#6ee7b7; background:#f0fdf4; box-shadow:inset 0 0 0 1px #a7f3d0; }
        .dr-dest-muted { border-style:dashed; }
        .dr-dest-muted:has(.dr-switch-input:checked) { border-style:solid; border-color:#a5b4fc; background:#eef2ff; box-shadow:inset 0 0 0 1px #c7d2fe; }
        .dr-dest-muted:has(.dr-switch-input:checked) .dr-switch-track { background:#4f46e5; }
        .dr-dest-icon { display:flex; height:34px; width:34px; flex-shrink:0; align-items:center; justify-content:center; border-radius:10px; color:var(--c,#3f3f46); font-size:16px; font-weight:800; }
        .dr-dest-text { display:flex; min-width:0; flex:1; flex-direction:column; gap:1px; }
        .dr-dest-name { font-size:13px; font-weight:700; color:#18181b; }
        .dr-dest-desc { font-size:11px; color:#71717a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .dr-dest .dr-switch-track { flex-shrink:0; }
        .dr-dest:has(.dr-switch-input:checked) .dr-switch-track { background:#059669; }
        .dr-dest:has(.dr-switch-input:checked) .dr-switch-thumb { transform:translateX(18px); }
        .dr-dest:has(.dr-switch-input:focus-visible) { border-color:#18181b; box-shadow:0 0 0 3px rgba(24,24,27,.12); }
        .dr-sendbar-actions { display:grid; grid-template-columns:minmax(0,2.4fr) minmax(0,1fr); gap:8px; }
        .dr-send-btn { display:flex; height:46px; align-items:center; justify-content:center; gap:6px; border:1px solid #047857; border-radius:12px; background:linear-gradient(180deg,#059669,#047857); padding:0 18px; font-size:15px; font-weight:700; color:#fff; box-shadow:0 1px 2px rgba(5,150,105,.3),0 8px 20px rgba(5,150,105,.25); transition:transform .12s ease, box-shadow .16s ease; }
        .dr-send-btn:hover { box-shadow:0 2px 4px rgba(5,150,105,.3),0 12px 26px rgba(5,150,105,.32); }
        .dr-send-btn:active { transform:scale(.99); }
        .dr-send-btn:disabled { opacity:.7; cursor:not-allowed; }
        .dr-preview-btn { height:46px; border-radius:12px; }
        .dr-sendbar-draft { font-size:11.5px; color:#71717a; text-align:right; }
        @media (max-width:640px) { .dr-dest-list { grid-template-columns:1fr; } .dr-sendbar-actions { grid-template-columns:1fr; } }
        /* Wide screens: content stays centered, the panel floats at the right edge */
        @media (min-width:1440px) {
            /* Docked 16px right of the 64rem content column (content left edge = 50% - 32rem). */
            .dr-sendbar { position:fixed; left:calc(50% + 32rem + 16px); top:138px; margin:0; width:320px; z-index:40; box-shadow:0 1px 2px rgba(24,24,27,.08),0 14px 34px rgba(24,24,27,.16); }
            .dr-sendbar .dr-card-hint { display:none; }
            .dr-sendbar-inner { padding:10px 12px 12px; gap:10px; }
            .dr-dest-list { grid-template-columns:minmax(0,1fr); gap:6px; }
            .dr-dest { min-height:44px; padding:6px 10px 6px 8px; }
            .dr-dest-icon { height:28px; width:28px; font-size:14px; border-radius:8px; }
            .dr-dest-desc { display:none; }
            .dr-sendbar-actions { grid-template-columns:1fr; gap:6px; }
            .dr-send-btn { height:44px; font-size:14px; white-space:nowrap; }
            .dr-preview-btn { height:38px; }
        }
        /* ===== WePRO create task ===== */
        .dr-wc { text-align:left; display:flex; flex-direction:column; gap:10px; }
        .dr-wc.hidden { display:none; }
        .dr-wc-tabs { display:flex; gap:6px; }
        .dr-wc-tabs .dr-chip.is-active { border-style:solid; border-color:#18181b; background:#18181b; color:#fff; }
        .dr-wc-pane { display:flex; flex-direction:column; gap:10px; }
        .dr-wc-pane.hidden { display:none; }
        .dr-wc-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .dr-wc-grid.hidden { display:none; }
        .dr-wc-field { display:flex; flex-direction:column; gap:5px; min-width:0; }
        .dr-wc-field > span { font-size:12px; font-weight:600; color:#52525b; }
        .dr-wc-field input, .dr-wc-field select, .dr-wc-field textarea { width:100%; min-width:0; border:1px solid #d4d4d8; border-radius:8px; background:#fff; padding:8px 10px; font-size:13px; color:#18181b; }
        .dr-wc-field input:focus, .dr-wc-field select:focus, .dr-wc-field textarea:focus { outline:none; border-color:#18181b; box-shadow:0 0 0 2px rgba(24,24,27,.12); }
        .dr-wc-field textarea { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12.5px; line-height:1.5; resize:vertical; }
        .dr-wc-field .dr-date-trigger { min-height:38px; }
        .dr-wc-hint { margin:0; font-size:12px; color:#71717a; }
        .dr-wc-check { display:flex; align-items:center; gap:8px; font-size:13px; color:#3f3f46; cursor:pointer; }
        .dr-wc-check input { height:16px; width:16px; accent-color:#18181b; }
        .dr-wc-results { margin:0; padding:0; list-style:none; text-align:left; font-size:13px; line-height:1.6; }
        .dr-wc-results a { color:#4f46e5; font-weight:600; }
        .dr-wc-results .is-bad { color:#be123c; }
        .dr-wc-rows-head { display:grid; grid-template-columns:1.3fr 1fr 54px 118px 118px 28px; gap:6px; font-size:11.5px; font-weight:600; color:#52525b; padding:0 2px; }
        .dr-wc-row { display:grid; grid-template-columns:1.3fr 1fr 54px 118px 118px 28px; gap:6px; margin-top:6px; align-items:center; }
        .dr-wc-row input[type=date] { padding:6px 6px; font-size:12px; }
        .dr-wc-paste { border:1px dashed #a1a1aa; border-radius:10px; padding:8px 10px; margin-bottom:8px; background:#fafafa; }
        .dr-wc-paste summary { cursor:pointer; font-size:12.5px; font-weight:600; color:#3f3f46; }
        .dr-wc-paste textarea { width:100%; margin-top:8px; border:1px solid #d4d4d8; border-radius:8px; padding:8px 10px; font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; line-height:1.5; resize:vertical; }
        .dr-wc-paste textarea:focus { outline:none; border-color:#18181b; box-shadow:0 0 0 2px rgba(24,24,27,.12); }
        .dr-wc-paste-foot { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-top:8px; }
        .dr-wc-row input { width:100%; min-width:0; border:1px solid #d4d4d8; border-radius:8px; background:#fff; padding:7px 9px; font-size:13px; color:#18181b; }
        .dr-wc-row input:focus { outline:none; border-color:#18181b; box-shadow:0 0 0 2px rgba(24,24,27,.12); }
        .dr-wc-row-del { height:30px; width:28px; border:1px solid #e4e4e7; border-radius:7px; background:#fff; color:#71717a; font-size:15px; line-height:1; cursor:pointer; }
        .dr-wc-row-del:hover { border-color:#fecdd3; background:#fff1f2; color:#e11d48; }
        .dr-wc-rows-foot { display:flex; align-items:center; gap:10px; margin-top:8px; }
        .dr-wc-pbtn { width:100%; text-align:left; border:1px solid #d4d4d8; border-radius:8px; background:#fff; padding:8px 10px; font-size:13px; color:#71717a; cursor:pointer; }
        .dr-wc-pbtn.is-set { color:#18181b; font-weight:600; }
        .dr-wc-pbtn:hover { border-color:#a1a1aa; }
        .dr-wc-pbox { margin-top:6px; border:1px solid #d4d4d8; border-radius:10px; background:#fff; padding:8px; box-shadow:0 10px 28px rgba(24,24,27,.12); }
        .dr-wc-pbox input { width:100%; border:1px solid #d4d4d8; border-radius:8px; padding:7px 9px; font-size:13px; }
        .dr-wc-pbox input:focus { outline:none; border-color:#18181b; box-shadow:0 0 0 2px rgba(24,24,27,.12); }
        .dr-wc-plist { max-height:220px; overflow-y:auto; margin-top:6px; }
        .dr-wc-popt { display:block; width:100%; text-align:left; border:0; border-radius:7px; background:transparent; padding:7px 9px; font-size:12.5px; color:#18181b; cursor:pointer; }
        .dr-wc-popt b { color:#71717a; font-weight:650; margin-right:4px; }
        .dr-wc-popt.is-child { padding-left:24px; }
        .dr-wc-popt:hover { background:#f4f4f5; }
        .dr-wc-pempty { padding:8px; font-size:12.5px; color:#a1a1aa; }
        .dr-wepro-addchild { flex-shrink:0; height:22px; border:1px solid #c7d2fe; border-radius:6px; background:#eef2ff; padding:0 7px; color:#4338ca; font-size:10.5px; font-weight:700; cursor:pointer; }
        .dr-wepro-addchild:hover { background:#e0e7ff; }
        /* Popup overlay: darker and blurred so the page behind reads as inactive */
        .swal2-container.swal2-backdrop-show, .swal2-container.swal2-noanimation { background:rgba(24,24,27,.62) !important; backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); }
        /* ===== WePRO task picker ===== */
        .dr-wepro-tools { display:flex; gap:8px; align-items:center; margin-bottom:8px; }
        .dr-wepro-tools.hidden { display:none; }
        /* Its own rules: .dr-modal-field is a 220px-tall textarea style and broke this input. */
        .dr-wepro-search { flex:1; min-width:0; height:36px; border:1px solid #e4e4e7; border-radius:8px; background:#fff; padding:0 10px; color:#18181b; font-size:13px; outline:none; box-shadow:0 1px 2px rgba(24,24,27,.06); }
        .dr-wepro-search:focus { border-color:#18181b; box-shadow:0 0 0 2px rgba(24,24,27,.12); }
        .dr-wepro-tools .dr-action { flex-shrink:0; height:36px; white-space:nowrap; }
        .dr-wepro-list { max-height:340px; overflow-y:auto; text-align:left; }
        .dr-wepro-item.hidden { display:none; }
        .dr-wepro-group-label { margin:10px 0 6px; font-size:10.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#a1a1aa; text-align:left; }
        .dr-wepro-group-label:first-child { margin-top:0; }
        .dr-wepro-children { margin:0 0 4px 18px; border-left:2px solid #e0e7ff; padding-left:8px; }
        .dr-wepro-item.is-child { background:#fbfbfd; }
        .dr-wlog-total { margin:6px 0 0; text-align:right; font-size:12.5px; font-weight:700; color:#0284c7; }
        .dr-wlog-badge { display:inline-flex; align-items:center; height:34px; padding:0 10px; border-radius:8px; background:#e0f2fe; color:#0369a1; font-size:12px; font-weight:650; }
        .dr-wlog-badge.hidden { display:none; }
        .dr-gf-presets { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px; }
        .dr-gf-preset.is-active { border-style:solid; border-color:#18181b; background:#18181b; color:#fff; }
        .dr-gf-range { display:grid; grid-template-columns:1fr 1fr; gap:12px; text-align:left; }
        .dr-gf-field { display:flex; flex-direction:column; gap:6px; }
        .dr-gf-field > span { font-size:12px; font-weight:600; color:#52525b; }
        .dr-gf-field .dr-date-trigger { min-height:42px; font-size:14px; }
        .dr-gf-skip { display:flex; align-items:center; gap:8px; margin-top:12px; font-size:13px; color:#3f3f46; cursor:pointer; }
        .dr-gf-skip input { height:16px; width:16px; accent-color:#18181b; }
        .dr-gf-summary { margin:10px 0 0; border-radius:8px; background:#f4f4f5; padding:8px 10px; font-size:12.5px; font-weight:600; color:#52525b; }
        .dr-gf-summary.ok { background:#ecfdf5; color:#047857; }
        .dr-wlog-date { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
        .dr-wlog-date label { font-size:12px; font-weight:600; color:#52525b; white-space:nowrap; }
        .dr-wlog-date .dr-wepro-search { max-width:170px; }
        .dr-wlog-row { display:flex; align-items:flex-start; gap:8px; padding:8px; border:1px solid #e4e4e7; border-radius:8px; margin-bottom:6px; background:#fff; }
        .dr-wlog-check { margin-top:4px; flex-shrink:0; }
        .dr-wlog-body { flex:1; min-width:0; display:flex; flex-direction:column; gap:4px; }
        .dr-wlog-code { font-size:10.5px; font-weight:650; color:#71717a; }
        .dr-wlog-fields { display:grid; grid-template-columns:150px 1fr 70px; gap:6px; }
        .dr-wlog-fields select, .dr-wlog-fields input { height:32px; min-width:0; border:1px solid #e4e4e7; border-radius:6px; background:#fff; padding:0 8px; font-size:12.5px; color:#18181b; }
        .dr-wlog-fields select:focus, .dr-wlog-fields input:focus { outline:none; border-color:#18181b; box-shadow:0 0 0 2px rgba(24,24,27,.12); }
        .dr-wepro-children.is-folded { display:none; }
        .dr-wepro-fold { flex-shrink:0; width:22px; height:22px; margin:-2px -2px 0 0; border:0; border-radius:6px; background:transparent; color:#71717a; font-size:14px; line-height:1; cursor:pointer; }
        .dr-wepro-fold:hover { background:#f4f4f5; color:#18181b; }
        .dr-wepro-badge { margin-left:6px; border-radius:999px; background:#eef2ff; padding:1px 6px; color:#4f46e5; font-size:9.5px; font-weight:700; }
        .dr-wepro-item { display:flex; align-items:flex-start; gap:8px; padding:7px 8px; border:1px solid #e4e4e7; border-radius:8px; margin-bottom:6px; cursor:pointer; background:#fff; }
        .dr-wepro-item:hover { border-color:#a1a1aa; background:#fafafa; }
        .dr-wepro-check { margin-top:3px; flex-shrink:0; }
        .dr-wepro-body { display:flex; flex-direction:column; gap:1px; min-width:0; flex:1; }
        .dr-wepro-code { font-size:10px; font-weight:650; color:#71717a; }
        .dr-wepro-link { font-size:10.5px; color:#4f46e5; text-decoration:none; word-break:break-all; border-bottom:1px dashed #c7d2fe; align-self:flex-start; }
        .dr-wepro-link:hover { color:#3730a3; border-bottom-style:solid; }
        .dr-wepro-title { font-size:12.5px; color:#18181b; word-break:break-word; }
        .dr-wepro-en { font-size:11px; color:#a1a1aa; word-break:break-word; }
        .dr-wepro-pct { flex-shrink:0; font-size:11px; font-weight:650; color:#0284c7; }

        /* ===== Task rows: one task per line, columns line up with .dr-colhead ===== */
        .dr-taskrow { display:flex; flex-wrap:wrap; align-items:center; gap:8px; border:1px solid #e4e4e7; border-radius:10px; background:#fafafa; padding:8px; }
        .dr-taskrow:focus-within { border-color:#a1a1aa; background:#fff; box-shadow:0 0 0 3px rgba(24,24,27,.06); }
        .dr-col-issue { flex:0 0 108px; width:108px; }
        .dr-col-title { flex:1 1 220px; min-width:0; }
        .dr-col-type { flex:0 0 128px; width:128px; }
        .dr-col-status { flex:0 0 144px; width:144px; }
        .dr-col-progress { flex:0 0 92px; width:92px; }
        .dr-col-date { flex:0 0 148px; width:148px; }
        .dr-colhead { display:none; align-items:center; gap:8px; padding:0 9px 6px; }
        @media (min-width:900px) { .dr-colhead { display:flex; } }
        @media (max-width:899px) {
            .dr-col-title { flex-basis:100%; }
            .dr-col-issue, .dr-col-type, .dr-col-status, .dr-col-progress, .dr-col-date { flex:1 1 120px; width:auto; }
        }
        .dr-field-label { color:#71717a; font-size:11px; font-weight:600; letter-spacing:.01em; }
        .dr-taskrow-wepro { flex-basis:100%; display:flex; align-items:center; gap:6px; padding-left:2px; }
        .dr-taskrow-wepro.hidden { display:none; }
        .dr-taskrow-wepro .wepro-timelog { font-size:11px; font-weight:650; color:#4f46e5; text-decoration:none; border-bottom:1px dashed #c7d2fe; }
        .dr-taskrow-wepro .wepro-timelog:hover { color:#3730a3; border-bottom-color:#6366f1; }
        .dr-taskrow-wepro-code { font-size:10.5px; color:#a1a1aa; }
        .dr-taskrow-drag { display:none; height:38px; width:20px; flex-shrink:0; align-items:center; justify-content:center; color:#d4d4d8; }
        .dr-taskrow-drag:hover { color:#71717a; }
        @media (min-width:640px) { .dr-taskrow-drag { display:flex; } }
        .dr-taskrow-remove { display:flex; height:38px; width:38px; flex-shrink:0; align-items:center; justify-content:center; border:1px solid #e4e4e7; border-radius:8px; background:#fff; color:#a1a1aa; }
        .dr-taskrow-remove:hover { border-color:#fecdd3; background:#fff1f2; color:#e11d48; }
        .dr-input { height:38px; min-width:0; padding:0 10px; color:#18181b; font-size:13px; }
        .dr-input::placeholder { color:#a1a1aa; }
        .dr-input:focus { outline:none; }

        /* ===== Callout: giữ ô tự đánh giá khỏi bị lướt qua ===== */
        .dr-callout { margin-top:12px; border:1px solid #fde68a; border-left:4px solid #f59e0b; border-radius:10px; background:#fffbeb; padding:10px 12px; }
        .dr-callout-label { display:flex; align-items:center; gap:8px; color:#92400e; font-size:13px; font-weight:650; }
        .dr-callout-tag { border-radius:999px; background:#fef3c7; padding:1px 7px; color:#b45309; font-size:10px; font-weight:600; }
        .dr-callout-hint { margin-top:2px; color:#a16207; font-size:11px; }
        .dr-callout-input { margin-top:8px; resize:vertical; }
        .dr-callout-foot { margin-top:4px; text-align:right; color:#b45309; font-size:10px; }

        /* ===== Toggle switch cho khối "Gửi đến" ===== */
        .dr-switch-list { display:flex; flex-direction:column; gap:6px; }
        .dr-switch-row { display:flex; min-height:46px; cursor:pointer; align-items:center; gap:12px; border:1px solid #e4e4e7; border-radius:10px; background:#fafafa; padding:8px 12px; transition:border-color .16s ease, background-color .16s ease; }
        .dr-switch-row:hover { border-color:#d4d4d8; background:#fff; }
        .dr-switch-row:has(.dr-switch-input:checked) { border-color:#a7f3d0; background:#f0fdf4; }
        .dr-switch-row-muted { border-style:dashed; }
        .dr-switch-row-muted:has(.dr-switch-input:checked) { border-style:solid; border-color:#c7d2fe; background:#eef2ff; }
        .dr-switch-row-muted:has(.dr-switch-input:checked) .dr-switch-track { background:#4f46e5; }
        .dr-switch-text { display:flex; min-width:0; flex:1; flex-direction:column; }
        .dr-switch-name { color:#18181b; font-size:13px; font-weight:600; }
        .dr-switch-desc { color:#71717a; font-size:11px; }
        .dr-switch-input { position:absolute !important; width:1px !important; height:1px !important; min-height:0 !important; margin:-1px !important; overflow:hidden !important; clip:rect(0,0,0,0) !important; border:0 !important; }
        .dr-switch-track { position:relative; display:block; height:22px; width:40px; flex-shrink:0; border-radius:999px; background:#d4d4d8; transition:background-color .16s ease; }
        .dr-switch-thumb { position:absolute; top:3px; left:3px; display:block; height:16px; width:16px; border-radius:999px; background:#fff; box-shadow:0 1px 2px rgba(24,24,27,.3); transition:transform .16s ease; }
        .dr-switch-row:has(.dr-switch-input:checked) .dr-switch-track { background:#059669; }
        .dr-switch-row:has(.dr-switch-input:checked) .dr-switch-thumb { transform:translateX(18px); }
        .dr-switch-row:has(.dr-switch-input:focus-visible) { border-color:#18181b; box-shadow:0 0 0 3px rgba(24,24,27,.12); }

        /* ===== Combobox "Loại": input tự do + menu gợi ý dùng chung style dr-select ===== */
        .dr-combo { position:relative; min-width:0; }
        .dr-combo-toggle { position:absolute; top:0; right:0; display:flex; height:38px; width:28px; align-items:center; justify-content:center; border-radius:0 10px 10px 0 !important; color:#a8a29e; }
        .dr-combo-toggle:hover { color:#57534e; }
        .dr-combo input { padding-right:28px !important; }
        .dr-combo.is-open .dr-combo-toggle { transform:rotate(180deg); }

        /* ===== Task content: textarea tự cao theo nội dung ===== */
        textarea.dr-input { resize:none; overflow:hidden; min-height:38px; padding-top:9px; padding-bottom:9px; line-height:1.45; }

        .dr-modal-note { margin:0 0 10px; border-radius:8px; background:#f4f4f5; padding:9px 11px; color:#52525b; font-size:12px; line-height:1.55; text-align:left; }
        .dr-modal-note b { color:#18181b; }

        .dr-modal-validation {
            margin: 12px 20px 0;
            border-radius: 8px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 13px;
        }
    </style>

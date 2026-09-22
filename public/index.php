<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$today = date('l, d/m/Y');
$configFile = file_exists(__DIR__ . '/history/app-config.php')
    ? __DIR__ . '/history/app-config.php'
    : __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$projects = $config['projects'] ?? [];
if (!$projects) {
    $projects = [
        'JRR' => ['webhook' => '', 'avatar' => ''],
        'Primass' => ['webhook' => '', 'avatar' => ''],
    ];
}
$googleForm = $config['google_form'] ?? [];
$wepro = $config['wepro'] ?? [];
$defaultProject = $config['default_project'] ?? 'JRR';
$projectNames = array_keys($projects);
if (!isset($projects[$defaultProject])) {
    $defaultProject = $projectNames[0] ?? 'JRR';
}
$selectedProject = $projects[$defaultProject] ?? [];
$projectLogos = [];
foreach ($projects as $projectName => $projectConfig) {
    $projectLogos[$projectName] = $projectConfig['avatar'] ?? '';
}
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Report</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <style>
        /* Critical UI state: parsed before CDN scripts to prevent settings flash. */
        #settingsDrawer[hidden], #settingsBackdrop[hidden] { display: none !important; }
    </style>
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
                        background: '#fafafa',
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
        .dr-card { position:relative; overflow:hidden; border:1px solid #e4e4e7; border-left:4px solid var(--dr-accent,#a1a1aa); border-radius:12px; background:#fff; box-shadow:0 1px 2px rgba(24,24,27,.05),0 8px 22px rgba(24,24,27,.035); }
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
        .dr-wepro-badge { margin-left:6px; border-radius:999px; background:#eef2ff; padding:1px 6px; color:#4f46e5; font-size:9.5px; font-weight:700; }
        .dr-wepro-item { display:flex; align-items:flex-start; gap:8px; padding:7px 8px; border:1px solid #e4e4e7; border-radius:8px; margin-bottom:6px; cursor:pointer; background:#fff; }
        .dr-wepro-item:hover { border-color:#a1a1aa; background:#fafafa; }
        .dr-wepro-check { margin-top:3px; flex-shrink:0; }
        .dr-wepro-body { display:flex; flex-direction:column; gap:1px; min-width:0; flex:1; }
        .dr-wepro-code { font-size:10px; font-weight:650; color:#71717a; }
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
</head>

<body class="min-h-screen bg-background text-foreground antialiased">
    <main class="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(212,175,55,0.14),transparent_34%),linear-gradient(180deg,#ffffff_0%,#fafafa_42%,#f4f4f5_100%)] px-3 py-4 sm:px-4 sm:py-6">
    <div class="w-full max-w-6xl mx-auto animate-fade-in">

        <!-- Header -->
        <div class="mb-4 animate-slide-up">
            <div class="flex flex-col gap-3 rounded-xl border border-zinc-200/80 bg-white/85 p-4 shadow-soft backdrop-blur sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 sm:text-3xl">Daily Report</h1>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars($today) ?></p>
                </div>
                <div class="flex gap-2">
                    <a href="history.php" class="inline-flex h-9 items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-800 shadow-button transition-colors hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">Lịch sử</a>
                    <button type="button" id="openSettings" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-zinc-950 px-3 text-sm font-medium text-white shadow-button transition-colors hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" aria-controls="settingsDrawer" aria-expanded="false">
                        <span aria-hidden="true">⚙</span> Thiết lập
                    </button>
                </div>
            </div>
        </div>

        <div>
        <div id="settingsBackdrop" class="fixed inset-0 z-40 bg-zinc-950/35 backdrop-blur-[1px]" aria-hidden="true" hidden></div>
        <aside id="settingsDrawer" class="fixed inset-y-0 right-0 z-50 w-full max-w-md translate-x-full overflow-y-auto border-l border-zinc-200 bg-white p-4 shadow-2xl transition-transform duration-200" aria-hidden="true" hidden>
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-950">Thiết lập</h2>
                    <p class="mt-1 text-xs text-zinc-500">Project, Google Chat và chấm công</p>
                </div>
                <button type="button" id="closeSettings" class="flex h-9 w-9 items-center justify-center rounded-md border border-zinc-200 text-xl text-zinc-500 hover:bg-zinc-50" aria-label="Đóng thiết lập">×</button>
            </div>

            <details class="mb-3 overflow-hidden rounded-xl bg-stone-100/80 text-stone-700">
                <summary class="cursor-pointer list-none px-3 py-2.5 text-xs font-semibold marker:hidden">? Hướng dẫn lấy thông tin thiết lập</summary>
                <div class="space-y-3 border-t border-stone-200 px-3 py-3 text-[11px] leading-relaxed text-stone-600">
                    <section>
                        <h3 class="font-semibold text-stone-900">Google Chat webhook URL</h3>
                        <ol class="mt-1 list-decimal space-y-1 pl-4">
                            <li>Mở Google Chat và vào Space nhận daily report.</li>
                            <li>Mở menu tên Space → <strong>Apps & integrations</strong> → <strong>Webhooks</strong>.</li>
                            <li>Tạo webhook, đặt tên theo project (ví dụ JRR hoặc Babyface), rồi copy URL bắt đầu bằng <code>https://chat.googleapis.com/</code>.</li>
                        </ol>
                    </section>
                    <section>
                        <h3 class="font-semibold text-stone-900">Logo/avatar URL</h3>
                        <p class="mt-1">Dùng link ảnh HTTPS truy cập công khai, mở được khi không đăng nhập. Có thể dùng logo website hoặc ảnh trên CDN; ảnh vuông sẽ hiển thị đẹp nhất trên Google Chat.</p>
                    </section>
                    <section>
                        <h3 class="font-semibold text-stone-900">Webhook Slack</h3>
                        <p class="mt-1">Trong Slack App, bật Incoming Webhooks → Add New Webhook to Workspace → chọn channel. Dán URL vào Webhook Slack của project rồi lưu thiết lập.</p>
                    </section>
                    <p class="rounded-md bg-rose-50 p-2 text-rose-700">Webhook là thông tin bí mật. Không gửi cho người khác hoặc commit URL thật lên Git.</p>
                </div>
            </details>

            <form id="settingsForm" class="space-y-2.5">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="settings-card p-3">
                    <label for="reporterSetting" class="mb-1 block text-sm font-medium text-zinc-900">Người báo cáo</label>
                    <input name="reporter" id="reporterSetting" maxlength="100" value="<?= e($config['reporter'] ?? '') ?>" class="h-9 w-full rounded-md border border-zinc-200 bg-white px-3 text-sm" placeholder="vuong.toan">
                    <p class="mt-1 text-xs text-zinc-500">Lưu để dùng cho các báo cáo sau. Bắt buộc khi gửi Slack.</p>
                </div>
                <div class="settings-card setting-default p-3">
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-violet-900"><span aria-hidden="true">◆</span> Mặc định</div>
                    <label class="mb-1 block text-xs font-medium text-zinc-700">Project mặc định khi mở form</label>
                    <select name="default_project" id="defaultProjectSelect" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-sm text-zinc-950 shadow-button focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                        <?php foreach ($projectNames as $projectName): ?>
                            <option value="<?= e($projectName) ?>" <?= $defaultProject === $projectName ? 'selected' : '' ?>><?= e($projectName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="settings-card setting-projects p-2.5">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <div>
                            <div class="text-xs font-semibold text-sky-900">📁 Danh sách project</div>
                            <div class="text-[11px] text-zinc-500">Tên, webhook Google Chat/Slack, logo/avatar.</div>
                        </div>
                        <button type="button" id="addProject" class="h-7 rounded-md border border-zinc-200 bg-white px-2 text-xs font-medium text-zinc-800 hover:bg-zinc-50">Thêm</button>
                    </div>
                    <div id="projectSettingsList" class="space-y-2">
                        <?php foreach ($projects as $projectName => $projectConfig): ?>
                            <div class="project-setting overflow-hidden rounded-lg border border-sky-200 bg-white" data-project="<?= e($projectName) ?>">
                                <button type="button" class="toggle-project flex w-full items-center justify-between gap-2 bg-sky-50 px-3 py-2 text-left" aria-expanded="false">
                                    <span class="project-summary min-w-0 truncate text-xs font-semibold text-sky-950">📁 <?= e($projectName) ?></span>
                                    <span class="project-chevron text-sky-500 transition-transform">⌄</span>
                                </button>
                                <div class="project-setting-body hidden p-2.5">
                                <div class="mb-1 grid grid-cols-[1fr_auto] gap-2">
                                    <label class="block">
                                        <span class="mb-1 block text-[11px] font-medium text-zinc-600">Tên project</span>
                                        <input name="projects[name][]" class="project-name h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="VD: JRR" value="<?= e($projectName) ?>">
                                    </label>
                                    <button type="button" class="remove-project mt-5 h-8 rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600">Xóa</button>
                                </div>
                                <label class="mb-1 block">
                                    <span class="mb-1 block text-[11px] font-medium text-zinc-600">WePRO Project ID</span>
                                    <input name="projects[wepro_project_id][]" class="project-wepro-id h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="VD: 366" value="<?= e($projectConfig['wepro_project_id'] ?? '') ?>">
                                </label>
                                <label class="mb-1 block">
                                    <span class="mb-1 block text-[11px] font-medium text-zinc-600">Webhook Google Chat</span>
                                    <input name="projects[webhook][]" class="project-webhook h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="https://chat.googleapis.com/..." value="<?= e($projectConfig['webhook'] ?? '') ?>">
                                </label>
                                <label class="mb-1 block">
                                    <span class="mb-1 block text-[11px] font-medium text-zinc-600">Webhook Slack</span>
                                    <input name="projects[slack_webhook][]" class="project-slack-webhook h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs" placeholder="https://hooks.slack.com/services/..." value="<?= e($projectConfig['slack_webhook'] ?? '') ?>">
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-[11px] font-medium text-zinc-600">Logo/avatar URL</span>
                                    <input name="projects[avatar][]" class="project-avatar h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="https://..." value="<?= e($projectConfig['avatar'] ?? '') ?>">
                                </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="settings-card setting-wepro overflow-hidden">
                    <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                        <button type="button" id="toggleWeproSettings" class="flex min-w-0 flex-1 items-center justify-between gap-2 text-left" aria-expanded="false">
                            <span><span class="text-xs font-semibold text-emerald-900">▣ WePRO</span><span class="mt-0.5 block text-[11px] text-emerald-700">Lấy danh sách task từ WePRO</span></span>
                            <span id="weproSettingsChevron" class="text-emerald-600 transition-transform">⌄</span>
                        </button>
                        <label class="flex flex-shrink-0 items-center gap-1.5 text-[11px] font-medium text-emerald-800">
                            Bật
                            <input type="checkbox" name="wepro_enabled" value="1" <?= !empty($wepro['enabled']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-emerald-300 text-emerald-700 focus:ring-emerald-600">
                        </label>
                    </div>
                    <div id="weproSettingsBody" class="hidden space-y-2 border-t border-emerald-200 p-3">
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-medium text-zinc-600">Base URL</span>
                            <input name="wepro_base_url" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="https://wepro.rcvn.work" value="<?= e($wepro['base_url'] ?? '') ?>">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-medium text-zinc-600">Basic auth user</span>
                            <input name="wepro_basic_user" autocomplete="off" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="user" value="<?= e($wepro['basic_user'] ?? '') ?>">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-medium text-zinc-600">Basic auth password</span>
                            <input type="password" name="wepro_basic_pass" autocomplete="new-password" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="<?= !empty($wepro['basic_pass']) ? '(đã lưu — để trống nếu giữ nguyên)' : 'password' ?>" value="">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-medium text-zinc-600">Cookie remember</span>
                            <textarea name="wepro_remember_cookie" rows="2" autocomplete="off" class="w-full rounded-md border border-zinc-200 bg-white px-2 py-1.5 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="<?= !empty($wepro['remember_cookie']) ? '(đã lưu — để trống nếu giữ nguyên)' : 'remember_web_xxx=eyJpdiI6...' ?>"></textarea>
                        </label>
                        <label class="flex items-center gap-2 text-[11px] font-medium text-zinc-600">
                            <input type="checkbox" name="wepro_include_subtasks" value="1" <?= !empty($wepro['include_subtasks']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-zinc-300">
                            Lấy cả task con
                        </label>
                        <p class="dr-modal-note">Project ID đặt riêng cho từng project ở mục <b>Danh sách project</b> phía trên; user/password ở đây dùng chung.</p>
                        <p class="dr-modal-note">Lấy cookie: mở WePRO trên trình duyệt → F12 → Application → Cookies → copy cả tên và giá trị của <b>remember_web_...</b>. Cookie này sống rất lâu, chỉ phải lấy lại khi bạn logout hoặc đổi mật khẩu.</p>
                        <button type="button" id="weproTestBtn" class="dr-action">Kiểm tra kết nối</button>
                        <p id="weproTestResult" class="dr-modal-note hidden"></p>
                    </div>
                </div>

                <div class="settings-card setting-form overflow-hidden">
                    <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                        <button type="button" id="toggleGoogleFormSettings" class="flex min-w-0 flex-1 items-center justify-between gap-2 text-left" aria-expanded="false">
                            <span><span class="text-xs font-semibold text-emerald-900">▣ Google Form</span><span class="mt-0.5 block text-[11px] text-emerald-700">Cấu hình chấm công</span></span>
                            <span id="googleFormSettingsChevron" class="text-emerald-600 transition-transform">⌄</span>
                        </button>
                        <label class="flex flex-shrink-0 items-center gap-1.5 text-[11px] font-medium text-emerald-800">
                            Bật
                            <input type="checkbox" name="google_form_enabled" value="1" <?= !empty($googleForm['enabled']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-emerald-300 text-emerald-700 focus:ring-emerald-600">
                        </label>
                    </div>
                    <div id="googleFormSettingsBody" class="hidden space-y-2 border-t border-emerald-200 p-3">
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-medium text-zinc-600">Google Form response URL</span>
                            <input name="google_form_url" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="https://docs.google.com/forms/..." value="<?= e($googleForm['url'] ?? '') ?>">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-medium text-zinc-600">Email chấm công</span>
                            <input name="google_form_email" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="name@example.com" value="<?= e($googleForm['email'] ?? '') ?>">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-medium text-zinc-600">Bộ phận/nhóm</span>
                            <input name="google_form_department" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="Department" value="<?= e($googleForm['department'] ?? '') ?>">
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block">
                                <span class="mb-1 block text-[11px] font-medium text-zinc-600">Giờ bắt đầu</span>
                                <input name="start_hour" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="08" value="<?= e($googleForm['start_hour'] ?? '08') ?>">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-[11px] font-medium text-zinc-600">Giờ kết thúc</span>
                                <input name="end_hour" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="17" value="<?= e($googleForm['end_hour'] ?? '17') ?>">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-[11px] font-medium text-zinc-600">Phút bắt đầu</span>
                                <input name="start_minute" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="00" value="<?= e($googleForm['start_minute'] ?? '00') ?>">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-[11px] font-medium text-zinc-600">Phút kết thúc</span>
                                <input name="end_minute" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="00" value="<?= e($googleForm['end_minute'] ?? '00') ?>">
                            </label>
                        </div>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-medium text-zinc-600">Ghi chú gửi lên form</span>
                            <input name="report_note" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="Đã nhập đầy đủ..." value="<?= e($googleForm['report_note'] ?? '') ?>">
                        </label>
                    </div>
                </div>

                <button type="submit" id="saveSettingsBtn" class="flex h-9 w-full items-center justify-center rounded-md bg-zinc-950 px-3 text-sm font-medium text-white shadow-button transition-colors hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                    Lưu thiết lập
                </button>
            </form>


            <form id="googleFormRangeForm" class="settings-card setting-range mt-3 space-y-2.5 p-3">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div>
                    <div class="text-xs font-semibold text-zinc-900">Submit Google Form bù</div>
                    <div class="mt-0.5 text-[11px] text-zinc-500">Chỉ gửi form chấm công, không gửi report.</div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-medium text-zinc-600">Từ ngày</span>
                        <input type="date" name="start_date" id="googleFormStartDate" required class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-medium text-zinc-600">Đến ngày</span>
                        <input type="date" name="end_date" id="googleFormEndDate" required class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                    </label>
                </div>
                <button type="submit" id="submitGoogleFormRangeBtn" class="flex h-9 w-full items-center justify-center rounded-md border border-zinc-300 bg-white px-3 text-sm font-medium text-zinc-900 shadow-button transition-colors hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                    <span id="submitGoogleFormRangeText">Submit các ngày</span>
                </button>
            </form>
        </aside>

        <form data-reporter="<?= e($config['reporter'] ?? '') ?>" id="dailyReportForm" class="mx-auto max-w-5xl space-y-3 animate-slide-up">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- Project -->
            <div class="dr-card" style="--dr-accent:#7c3aed;--dr-accent-soft:#f5f3ff;">
                <div class="dr-card-head">
                    <span class="dr-card-badge" aria-hidden="true">◉</span>
                    <span class="dr-card-title">Nơi gửi &amp; ngày báo cáo<span class="dr-card-hint">Chọn project nhận report và ngày của báo cáo</span></span>
                </div>
                <div class="dr-card-body">
                <div class="grid gap-3 sm:grid-cols-[52px_minmax(0,1fr)_180px] sm:items-center">
                    <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
                        <img id="projectLogo" class="<?= !empty($selectedProject['avatar']) ? '' : 'hidden' ?> h-full w-full object-cover" src="<?= e($selectedProject['avatar'] ?? '') ?>" alt="">
                        <span id="projectLogoFallback" class="<?= !empty($selectedProject['avatar']) ? 'hidden' : '' ?> text-xs font-semibold text-zinc-500"><?= e(substr($defaultProject, 0, 2)) ?></span>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-zinc-900">Project gửi report</label>
                        <select name="project" id="project" data-logos='<?= e(json_encode($projectLogos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>' class="h-9 w-full cursor-pointer appearance-none rounded-md border border-input bg-white px-3 text-sm text-zinc-950 shadow-button transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2">
                    <?php foreach ($projectNames as $projectName): ?>
                        <option value="<?= e($projectName) ?>" <?= $defaultProject === $projectName ? 'selected' : '' ?>><?= e($projectName) ?></option>
                    <?php endforeach; ?>
                        </select>
                    </div>
                    <label class="block text-sm font-medium text-zinc-900">Ngày báo cáo
                        <input type="date" name="report_date" id="reportDate" value="<?= e(date('Y-m-d')) ?>" class="mt-1.5 h-9 w-full rounded-md border border-zinc-200 bg-white px-3">
                    </label>
                </div>
                </div>
            </div>

            <!-- Task hôm nay -->
            <div class="dr-card" style="--dr-accent:#0284c7;--dr-accent-soft:#f0f9ff;">
                <div class="dr-card-head">
                    <span class="dr-card-badge" aria-hidden="true">1</span>
                    <span class="dr-card-title">Công việc trong ngày<span class="dr-card-hint">Mỗi task ghi rõ issue, loại việc, trạng thái và tiến độ — để trống Issue nếu không có</span></span>
                </div>
                <div class="dr-card-body">
                <div class="dr-colhead" aria-hidden="true">
                    <span style="flex:0 0 20px;"></span>
                    <span class="dr-field-label dr-col-issue">Issue</span>
                    <span class="dr-field-label dr-col-title">Task</span>
                    <span class="dr-field-label dr-col-type">Loại</span>
                    <span class="dr-field-label dr-col-status">Trạng thái</span>
                    <span class="dr-field-label dr-col-progress">Tiến độ</span>
                    <span class="dr-field-label dr-col-date">Dự kiến xong</span>
                    <span style="flex:0 0 38px;"></span>
                </div>
                <div id="tasks-today-list" class="space-y-2"></div>
                <datalist id="workTypeList">
                    <?php foreach (['Coding', 'Fix bug', 'Feature', 'Testing', 'Review', 'Analysis', 'Research', 'Discussion', 'Design', 'Detail Design', 'Meeting', 'Deploy', 'Khác'] as $type): ?>
                        <option value="<?= e($type) ?>">
                    <?php endforeach; ?>
                </datalist>
                <div class="dr-actions">
                    <button type="button" id="add-task-today" class="dr-action dr-action-primary">
                        <svg class="dr-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"></path></svg>
                        Thêm task
                    </button>
                    <button type="button" id="wepro-pick-task" class="dr-action" title="Chọn task từ WePRO và điền vào report">
                        ▤ Chọn task WePRO
                    </button>
                    <button type="button" id="bulk-task-today" class="dr-action">
                        <svg class="dr-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h10"></path></svg>
                        Nhập nhiều dòng
                    </button>
                    <button type="button" id="ai-task-prompt" class="dr-action" title="Lấy prompt để nhờ AI tổng hợp task trong ngày, rồi dán kết quả vào Nhập nhiều dòng">
                        <svg class="dr-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.75 11 7l3.25 1.25L11 9.5l-1.25 3.25L8.5 9.5 5.25 8.25 8.5 7l1.25-3.25ZM16.5 11l.75 2 2 .75-2 .75-.75 2-.75-2-2-.75 2-.75.75-2Z"></path></svg>
                        Prompt AI
                    </button>
                </div>
                </div>
            </div>

            <!-- Kế hoạch tiếp theo -->
            <div class="dr-card" style="--dr-accent:#4f46e5;--dr-accent-soft:#eef2ff;">
                <div class="dr-card-head">
                    <span class="dr-card-badge" aria-hidden="true">→</span>
                    <span class="dr-card-title">Kế hoạch tiếp theo<span class="dr-card-hint">Tùy chọn — việc dự kiến cho buổi làm việc kế tiếp, không nhất thiết là ngày mai</span></span>
                </div>
                <div class="dr-card-body">
                    <div id="tasks-tomorrow-list" class="space-y-2"></div>
                    <div class="dr-actions">
                        <button type="button" id="add-task-tomorrow" class="dr-action dr-action-primary">
                            <svg class="dr-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"></path></svg>
                            Thêm task
                        </button>
                    </div>
                </div>
            </div>

            <!-- Self evaluation -->
            <div class="dr-card" style="--dr-accent:#d97706;--dr-accent-soft:#fffbeb;">
                <div class="dr-card-head">
                    <span class="dr-card-badge" aria-hidden="true">2</span>
                    <span class="dr-card-title">Tự đánh giá &amp; tinh thần<span class="dr-card-hint">Chọn một mức ở mỗi hàng</span></span>
                </div>
                <div class="dr-card-body">
                <div class="space-y-3">
                    <!-- Quality -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-700">Kết quả & chất lượng công việc hôm nay</label>
                        <div id="quality-list" class="grid grid-cols-1 gap-2 sm:grid-cols-5">
                            <button type="button" class="quality-btn min-h-20 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-2 text-center text-sm text-zinc-600 transition-all hover:border-zinc-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" data-value="1">
                                <span class="mb-1 block text-xs text-zinc-400">1</span>
                                <span class="text-xs"> </span>
                                <small>Không hoàn thành</small>
                            </button>
                            <button type="button" class="quality-btn min-h-20 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-2 text-center text-sm text-zinc-600 transition-all hover:border-zinc-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" data-value="2">
                                <span class="mb-1 block text-xs text-zinc-400">2</span>
                                <span class="text-xs">Trung bình</span>
                                <small>Hoàn thành nhưng còn lỗi</small>
                            </button>
                            <button type="button" class="quality-btn min-h-20 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-2 text-center text-sm text-zinc-600 transition-all hover:border-zinc-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" data-value="3" data-default="true">
                                <span class="mb-1 block text-xs text-zinc-400">3</span>
                                <span class="text-xs">Khá</span>
                                <small>Hoàn thành đúng yêu cầu</small>
                            </button>
                            <button type="button" class="quality-btn min-h-20 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-2 text-center text-sm text-zinc-600 transition-all hover:border-zinc-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" data-value="4">
                                <span class="mb-1 block text-xs text-zinc-400">4</span>
                                <span class="text-xs">Tốt</span>
                                <small>Hoàn thành vượt yêu cầu</small>
                            </button>
                            <button type="button" class="quality-btn min-h-20 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-2 text-center text-sm text-zinc-600 transition-all hover:border-zinc-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" data-value="5">
                                <span class="mb-1 block text-xs text-zinc-400">5</span>
                                <span class="text-xs">Xuất sắc</span>
                                <small>Kết quả nổi bật</small>
                            </button>
                        </div>
                        <input type="hidden" name="quality" id="quality" required>
                        <div class="dr-callout">
                            <label for="dailyResult" class="dr-callout-label">Tự đánh giá ngắn gọn<span class="dr-callout-tag">tùy chọn</span></label>
                            <p class="dr-callout-hint">Một câu về kết quả và chất lượng công việc hôm nay — phần này hiện ngay trong báo cáo gửi đi.</p>
                            <textarea name="daily_result" id="dailyResult" maxlength="2000" rows="2" class="dr-callout-input w-full px-3 py-2 text-sm" placeholder="VD: Đã xử lý xong lỗi chính, đang kiểm tra lại trên Prod."></textarea>
                            <p class="dr-callout-foot"><span id="dailyResultCount">0</span>/2000 ký tự</p>
                        </div>
                    </div>

                    <!-- Spirit -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-700">Tinh thần</label>
                        <div id="spirit-list" class="grid grid-cols-5 gap-2">
                            <button type="button" class="react-emoji flex min-h-16 flex-col items-center justify-center rounded-lg border border-zinc-200 bg-white px-1 transition-all hover:border-rose-300 hover:bg-rose-50" data-value="1" title="Rất không tốt"><span class="text-2xl" aria-hidden="true">😣</span><small class="mt-1 text-[10px] text-zinc-500">Rất không tốt</small></button>
                            <button type="button" class="react-emoji flex min-h-16 flex-col items-center justify-center rounded-lg border border-zinc-200 bg-white px-1 transition-all hover:border-orange-300 hover:bg-orange-50" data-value="2" title="Không tốt"><span class="text-2xl" aria-hidden="true">😕</span><small class="mt-1 text-[10px] text-zinc-500">Không tốt</small></button>
                            <button type="button" class="react-emoji flex min-h-16 flex-col items-center justify-center rounded-lg border border-zinc-200 bg-white px-1 transition-all hover:border-sky-300 hover:bg-sky-50" data-value="3" data-default="true" title="Bình thường"><span class="text-2xl" aria-hidden="true">😐</span><small class="mt-1 text-[10px] text-zinc-500">Bình thường</small></button>
                            <button type="button" class="react-emoji flex min-h-16 flex-col items-center justify-center rounded-lg border border-zinc-200 bg-white px-1 transition-all hover:border-emerald-300 hover:bg-emerald-50" data-value="4" title="Tốt"><span class="text-2xl" aria-hidden="true">🙂</span><small class="mt-1 text-[10px] text-zinc-500">Tốt</small></button>
                            <button type="button" class="react-emoji flex min-h-16 flex-col items-center justify-center rounded-lg border border-zinc-200 bg-white px-1 transition-all hover:border-amber-300 hover:bg-amber-50" data-value="5" title="Rất tốt"><span class="text-2xl" aria-hidden="true">😄</span><small class="mt-1 text-[10px] text-zinc-500">Rất tốt</small></button>
                        </div>
                        <input type="hidden" name="spirit" id="spirit" required>
                    </div>
                </div>
                </div>
            </div>

            <div class="dr-card" style="--dr-accent:#e11d48;--dr-accent-soft:#fff1f2;">
                <div class="dr-card-head">
                    <span class="dr-card-badge" aria-hidden="true">3</span>
                    <span class="dr-card-title">Chia sẻ thêm <span class="font-normal text-zinc-500">(nếu có)</span><span class="dr-card-hint">Hãy chia sẻ ngắn gọn điều ảnh hưởng đến cảm xúc hoặc tinh thần và chất lượng công việc của bạn hôm nay.</span></span>
                </div>
                <div class="dr-card-body">
                <textarea name="note" class="min-h-20 w-full resize-none rounded-md border border-input bg-white px-3 py-2 text-sm text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2" rows="2" placeholder="VD: Hôm nay họp nhiều nên ít thời gian code, tinh thần vẫn ổn."></textarea>
                </div>
            </div>

            <fieldset class="dr-card" style="--dr-accent:#059669;--dr-accent-soft:#ecfdf5;">
                <div class="dr-card-head">
                    <span class="dr-card-badge" aria-hidden="true">↗</span>
                    <span class="dr-card-title">Gửi đến<span class="dr-card-hint">Bật ít nhất một nơi nhận — có thể gửi nhiều nơi trong cùng một lần</span></span>
                </div>
                <div class="dr-card-body">
                <div class="dr-switch-list">
                    <label class="dr-switch-row" for="submitGoogleForm">
                        <span class="dr-switch-text"><span class="dr-switch-name">RCNV logtime</span><span class="dr-switch-desc">Ghi log giờ làm qua Google Form</span></span>
                        <input type="checkbox" id="submitGoogleForm" name="submit_google_form" value="1" checked class="dr-switch-input">
                        <span class="dr-switch-track" aria-hidden="true"><span class="dr-switch-thumb"></span></span>
                    </label>
                    <label class="dr-switch-row" for="submitGoogleChat">
                        <span class="dr-switch-text"><span class="dr-switch-name">Google Chat</span><span class="dr-switch-desc">Gửi card báo cáo vào room của project</span></span>
                        <input type="checkbox" id="submitGoogleChat" name="submit_google_chat" value="1" checked class="dr-switch-input">
                        <span class="dr-switch-track" aria-hidden="true"><span class="dr-switch-thumb"></span></span>
                    </label>
                    <label class="dr-switch-row" for="submitSlack">
                        <span class="dr-switch-text"><span class="dr-switch-name">Slack</span><span class="dr-switch-desc">Gửi báo cáo dạng text vào channel Slack</span></span>
                        <input type="checkbox" id="submitSlack" name="submit_slack" value="1" checked class="dr-switch-input">
                        <span class="dr-switch-track" aria-hidden="true"><span class="dr-switch-thumb"></span></span>
                    </label>
                </div>
                </div>
            </fieldset>

            <!-- Submit -->
            <div class="sticky bottom-3 rounded-xl border border-zinc-200 bg-white/95 p-3 shadow-soft backdrop-blur">
                <div class="mb-2 flex items-center justify-between gap-2 px-0.5">
                    <span id="draftStatus" class="text-xs text-zinc-500">Bản nháp sẽ tự động lưu</span>
                    <button type="button" id="clearDraft" class="hidden text-xs font-medium text-zinc-500 hover:text-red-600">Xóa bản nháp</button>
                </div>
                <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] gap-2">
                    <button type="button" id="previewReport" class="dr-action" style="height:40px;">Xem trước</button>
                    <button type="submit" id="submitBtn" class="flex h-10 items-center justify-center gap-2 rounded-md bg-zinc-950 px-4 text-sm font-medium text-white shadow-button transition-all duration-150 hover:bg-zinc-800 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                        <span id="submitText">Gửi báo cáo</span>
                        <svg id="loadingIcon" class="ml-2 h-4 w-4 animate-spin hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                    </button>
                </div>
                <p class="mt-2 text-center text-[11px] text-zinc-400">Phím tắt: Ctrl/Cmd + Enter để gửi</p>
            </div>
        </form>
        </div>

        <div class="mt-4 text-center animate-slide-up-late">
            <a href="history.php" class="inline-flex items-center gap-1 text-sm text-zinc-500 transition-colors hover:text-zinc-950">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 14v-7h-8v7m0 1v7h6v-7m3 0h-3m3 0v-7h-6v7"></path></svg>
                Xem lịch sử báo cáo
            </a>
        </div>
    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script src="main.js?t=<?= e((string)(@filemtime(__DIR__ . '/main.js') ?: time())) ?>"></script>
</body>

</html>

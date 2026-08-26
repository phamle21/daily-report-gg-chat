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
        .animate-fade-in { animation: fadeIn 0.45s ease-out; }
        .animate-slide-up { animation: fadeIn 0.45s ease-out 0.08s both; }
        .animate-slide-up-delay { animation: fadeIn 0.45s ease-out 0.16s both; }
        .animate-slide-up-late { animation: fadeIn 0.45s ease-out 0.24s both; }
        #settingsDrawer { transform: translateX(100%); }
        #settingsDrawer.drawer-open { transform: translateX(0); }
        #settingsBackdrop[hidden] { display: none; }
        :root {
            --dr-border: #e7e5e4;
            --dr-shadow: 0 1px 2px rgba(15,23,42,.04), 0 6px 20px rgba(15,23,42,.04);
            --dr-shadow-hover: 0 2px 4px rgba(15,23,42,.04), 0 10px 26px rgba(15,23,42,.07);
        }
        #dailyReportForm > div {
            position: relative;
            overflow: visible;
            background: #fff !important;
            border: 1px solid var(--dr-border) !important;
            border-radius: 14px;
            box-shadow: var(--dr-shadow);
            transition: box-shadow .2s ease;
        }
        #dailyReportForm > div:not(:last-child) { border-color:color-mix(in srgb,var(--panel-accent,#a8a29e) 30%,#e7e5e4) !important; }
        #dailyReportForm > div:nth-of-type(1) { --panel-accent:#8b5cf6; }
        #dailyReportForm > div:nth-of-type(2) { --panel-accent:#38bdf8; }
        #dailyReportForm > div:nth-of-type(3) { --panel-accent:#818cf8; }
        #dailyReportForm > div:nth-of-type(4) { --panel-accent:#f59e0b; }
        #dailyReportForm > div:nth-of-type(5) { --panel-accent:#fb7185; }
        #dailyReportForm > div:nth-of-type(6) { --panel-accent:#34d399; }
        .settings-card {
            position:relative;
            background:#fff !important;
            border:1px solid var(--dr-border) !important;
            border-radius:12px;
            box-shadow:0 1px 3px rgba(15,23,42,.035);
        }
        .settings-card { border-color:color-mix(in srgb,var(--setting-accent,#a8a29e) 28%,#e7e5e4) !important; }
        .setting-default { --setting-accent:#8b5cf6; }
        .setting-projects { --setting-accent:#38bdf8; }
        .setting-form { --setting-accent:#34d399; }
        .setting-range { --setting-accent:#f59e0b; }
        .project-setting { border-color:#e7e5e4 !important; transition:box-shadow .18s ease; }
        .project-setting:has(.project-setting-body:not(.hidden)) { box-shadow:0 4px 14px rgba(15,23,42,.06); }
        .project-setting-body:not(.hidden), #googleFormSettingsBody:not(.hidden) { animation: revealPanel .22s ease-out; }
        @keyframes revealPanel { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
        @keyframes panelEnter { from { opacity:0; transform:translateY(10px) scale(.99); } to { opacity:1; transform:translateY(0) scale(1); } }
        #dailyReportForm > div { animation: panelEnter .38s ease-out backwards; }
        #dailyReportForm > div:nth-of-type(2) { animation-delay:.04s; }
        #dailyReportForm > div:nth-of-type(3) { animation-delay:.08s; }
        #dailyReportForm > div:nth-of-type(4) { animation-delay:.12s; }
        #dailyReportForm > div:nth-of-type(5) { animation-delay:.16s; }
        #dailyReportForm > div:nth-of-type(6) { animation-delay:.20s; }
        #dailyReportForm input:not([type="hidden"]), #dailyReportForm select, #dailyReportForm textarea,
        #settingsDrawer input:not([type="checkbox"]), #settingsDrawer select {
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
        #settingsDrawer input:not([type="checkbox"]):focus, #settingsDrawer select:focus {
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
        .dr-select-menu { position:fixed; z-index:2147483647; inset:auto; margin:0; max-height:240px; overflow:auto; border:1px solid #e7e5e4; border-radius:11px; background:#fff; padding:5px; box-shadow:0 14px 34px rgba(15,23,42,.13); animation:revealPanel .14s ease-out; }
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
        .dr-calendar { position:fixed; z-index:2147483647; inset:auto; margin:0; width:292px; border:1px solid #e7e5e4; border-radius:13px; background:#fff; padding:10px; box-shadow:0 16px 38px rgba(15,23,42,.14); animation:revealPanel .14s ease-out; }
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
                        <h3 class="font-semibold text-stone-900">Google Form chấm công</h3>
                        <ol class="mt-1 list-decimal space-y-1 pl-4">
                            <li>Mở form hiện đang dùng, chọn <strong>Preview</strong> và kiểm tra các trường Email, Bộ phận, ngày, giờ bắt đầu/kết thúc và ghi chú.</li>
                            <li>Lấy URL submit có đuôi <code>/formResponse</code>; nếu đang có link <code>/viewform</code>, thay phần cuối thành <code>/formResponse</code>.</li>
                            <li>Điền email, bộ phận và khung giờ mặc định đúng với thông tin chấm công của bạn.</li>
                        </ol>
                        <p class="mt-1 rounded-md bg-amber-50 p-2 text-amber-800">App hiện gửi theo bộ field ID của form đang dùng. Nếu đổi sang form mới, cần cập nhật các <code>entry.*</code> trong backend trước.</p>
                    </section>
                    <p class="rounded-md bg-rose-50 p-2 text-rose-700">Webhook là thông tin bí mật. Không gửi cho người khác hoặc commit URL thật lên Git.</p>
                </div>
            </details>

            <form id="settingsForm" class="space-y-2.5">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
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
                            <div class="text-[11px] text-zinc-500">Tên, webhook Google Chat, logo/avatar.</div>
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
                                    <span class="mb-1 block text-[11px] font-medium text-zinc-600">Webhook Google Chat</span>
                                    <input name="projects[webhook][]" class="project-webhook h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-xs text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" placeholder="https://chat.googleapis.com/..." value="<?= e($projectConfig['webhook'] ?? '') ?>">
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

        <form id="dailyReportForm" class="mx-auto max-w-4xl space-y-3 animate-slide-up">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- Project -->
            <div class="rounded-xl border border-violet-200 bg-violet-50/70 p-3 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-[52px_minmax(0,1fr)] sm:items-center">
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
                </div>
            </div>

            <!-- Task hôm nay -->
            <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-3 shadow-sm">
                <label class="mb-2.5 block text-sm font-medium text-zinc-900">
                    <span class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-sky-600 text-xs font-semibold text-white">✓</span>
                        Task hôm nay
                        <span class="text-xs font-normal text-zinc-500">(cần hoàn thành)</span>
                    </span>
                </label>
                <div id="tasks-today-list" class="space-y-2"></div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button" id="add-task-today" class="inline-flex h-8 items-center justify-center gap-2 rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-800 shadow-button transition-colors hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"></path></svg>
                        Thêm task
                    </button>
                    <button type="button" id="bulk-task-today" class="inline-flex h-8 items-center justify-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 text-sm font-medium text-zinc-800 shadow-button transition-colors hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h10"></path></svg>
                        Nhập nhiều dòng
                    </button>
                    <button type="button" id="ai-task-prompt" class="inline-flex h-8 items-center justify-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 text-sm font-medium text-zinc-800 shadow-button transition-colors hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.75 11 7l3.25 1.25L11 9.5l-1.25 3.25L8.5 9.5 5.25 8.25 8.5 7l1.25-3.25ZM16.5 11l.75 2 2 .75-2 .75-.75 2-.75-2-2-.75 2-.75.75-2Z"></path></svg>
                        Prompt AI
                    </button>
                </div>
            </div>

            <!-- Task ngày mai -->
            <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-3 shadow-sm">
                <label class="mb-2.5 block text-sm font-medium text-zinc-900">
                    <span class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-indigo-600 text-xs font-semibold text-white">→</span>
                        Task ngày mai
                        <span class="text-xs font-normal text-zinc-500">(lên kế hoạch)</span>
                    </span>
                </label>
                <div id="tasks-tomorrow-list" class="space-y-2"></div>
                <button type="button" id="add-task-tomorrow" class="mt-2 inline-flex h-8 items-center justify-center gap-2 rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-800 shadow-button transition-colors hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"></path></svg>
                    Thêm task
                </button>
            </div>

            <!-- Self evaluation -->
            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3 shadow-sm">
                <label class="mb-3 block text-sm font-medium text-zinc-900">
                    <span class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-amber-500 text-xs font-semibold text-white">★</span>
                        Đánh giá bản thân
                    </span>
                </label>

                <div class="space-y-3">
                    <!-- Quality -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-700">Chất lượng công việc</label>
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
                    </div>

                    <!-- Spirit -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-700">Tinh thần</label>
                        <div id="spirit-list" class="grid grid-cols-5 gap-2">
                            <button type="button" class="react-emoji flex min-h-16 flex-col items-center justify-center rounded-lg border border-zinc-200 bg-white px-1 transition-all hover:-translate-y-0.5 hover:border-rose-300 hover:bg-rose-50" data-value="1" title="Cạn pin"><span class="text-2xl" aria-hidden="true">🪫</span><small class="mt-1 text-[10px] text-zinc-500">Cạn pin</small></button>
                            <button type="button" class="react-emoji flex min-h-16 flex-col items-center justify-center rounded-lg border border-zinc-200 bg-white px-1 transition-all hover:-translate-y-0.5 hover:border-orange-300 hover:bg-orange-50" data-value="2" title="Cần cà phê"><span class="text-2xl" aria-hidden="true">☕</span><small class="mt-1 text-[10px] text-zinc-500">Cần cà phê</small></button>
                            <button type="button" class="react-emoji flex min-h-16 flex-col items-center justify-center rounded-lg border border-zinc-200 bg-white px-1 transition-all hover:-translate-y-0.5 hover:border-sky-300 hover:bg-sky-50" data-value="3" data-default="true" title="Ổn định"><span class="text-2xl" aria-hidden="true">🌤️</span><small class="mt-1 text-[10px] text-zinc-500">Ổn định</small></button>
                            <button type="button" class="react-emoji flex min-h-16 flex-col items-center justify-center rounded-lg border border-zinc-200 bg-white px-1 transition-all hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-emerald-50" data-value="4" title="Đầy năng lượng"><span class="text-2xl" aria-hidden="true">⚡</span><small class="mt-1 text-[10px] text-zinc-500">Năng lượng</small></button>
                            <button type="button" class="react-emoji flex min-h-16 flex-col items-center justify-center rounded-lg border border-zinc-200 bg-white px-1 transition-all hover:-translate-y-0.5 hover:border-amber-300 hover:bg-amber-50" data-value="5" title="Bứt phá"><span class="text-2xl" aria-hidden="true">🚀</span><small class="mt-1 text-[10px] text-zinc-500">Bứt phá</small></button>
                        </div>
                        <input type="hidden" name="spirit" id="spirit" required>
                    </div>
                </div>
            </div>

            <!-- Note -->
            <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-3 shadow-sm">
                <label class="mb-2 block text-sm font-medium text-zinc-900">
                    <span class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-rose-500 text-xs font-semibold text-white">✎</span>
                        Ghi chú
                        <span class="text-xs font-normal text-zinc-500">(tùy chọn)</span>
                    </span>
                </label>
                <textarea name="note" class="min-h-20 w-full resize-none rounded-md border border-input bg-white px-3 py-2 text-sm text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2" rows="2" placeholder="Ghi chú thêm..."></textarea>
            </div>

            <!-- Google Form toggle -->
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-3 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <label for="submitGoogleForm" class="cursor-pointer text-sm font-medium text-zinc-900">Submit kèm Google Form</label>
                        <p class="mt-0.5 text-xs text-zinc-500">Tự động điền form chấm công</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="submitGoogleForm" name="submit_google_form" value="1" <?= !empty($googleForm['enabled']) ? 'checked' : '' ?> class="sr-only peer">
                        <div class="peer h-6 w-11 rounded-full bg-zinc-200 transition-colors peer-checked:bg-zinc-950 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-zinc-950 peer-focus:ring-offset-2"></div>
                        <div class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:left-5"></div>
                    </label>
                </div>
            </div>

            <!-- Submit -->
            <div class="sticky bottom-3 rounded-xl border border-zinc-200 bg-white/95 p-3 shadow-soft backdrop-blur">
                <div class="mb-2 flex items-center justify-between gap-2 px-0.5">
                    <span id="draftStatus" class="text-xs text-zinc-500">Bản nháp sẽ tự động lưu</span>
                    <button type="button" id="clearDraft" class="hidden text-xs font-medium text-zinc-500 hover:text-red-600">Xóa bản nháp</button>
                </div>
                <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] gap-2">
                    <button type="button" id="previewReport" class="flex h-10 items-center justify-center rounded-md border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-900 shadow-button hover:bg-zinc-50">Xem trước</button>
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
    <script src="main.js"></script>
</body>

</html>

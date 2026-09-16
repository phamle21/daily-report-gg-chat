<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

$historyDir = __DIR__ . '/history';
$files = glob($historyDir . '/*.log') ?: [];
rsort($files);

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function historyTextToPlain($text)
{
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return trim($text);
}

function extractSection($text, $startMarker, array $endMarkers)
{
    $start = strpos($text, $startMarker);
    if ($start === false) {
        return '';
    }

    $start += strlen($startMarker);
    $end = strlen($text);
    foreach ($endMarkers as $marker) {
        $markerPos = strpos($text, $marker, $start);
        if ($markerPos !== false && $markerPos < $end) {
            $end = $markerPos;
        }
    }

    return trim(substr($text, $start, $end - $start));
}

function parseTaskLines($section)
{
    $tasks = [];
    foreach (preg_split('/\n+/', trim($section)) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $tasks[] = preg_replace('/^-+\s*/', '', $line);
    }

    return $tasks;
}

function extractFirstMatch($pattern, $text)
{
    return preg_match($pattern, $text, $matches) ? trim($matches[1]) : '';
}

function parseHistoryEntry($rawEntry, $file)
{
    $plain = historyTextToPlain($rawEntry);
    $headerLine = trim(strtok($plain, "\n") ?: '');
    $fileDate = basename($file, '.log');
    $project = 'Report';
    $sentAt = $fileDate;

    if (preg_match('/^\[Daily Report\]\s+(.*)\s+-\s+([A-Za-z]+,\s+\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2})\s+\(GMT\+7\)(?: \[Slack\])?$/u', $headerLine, $matches)) {
        $project = trim($matches[1]);
        $sentAt = trim($matches[2]);
    }

    // Nhãn section đổi từ "Task ngày mai" sang "Kế hoạch tiếp theo"; log cũ vẫn dùng nhãn cũ.
    $planMarkers = ['*📅 Kế hoạch tiếp theo:*', '*📅 Task ngày mai:*'];
    $planMarker = '';
    foreach ($planMarkers as $marker) {
        if (strpos($plain, $marker) !== false) {
            $planMarker = $marker;
            break;
        }
    }
    $todayTasks = parseTaskLines(extractSection($plain, '*📝 Task hôm nay:*', array_merge($planMarkers, ['*Chất lượng:*'])));
    $tomorrowTasks = $planMarker === '' ? [] : parseTaskLines(extractSection($plain, $planMarker, ['*Chất lượng:*']));
    $quality = extractFirstMatch('/^\*Chất lượng:\*\s*(.+)$/mu', $plain);
    $spirit = extractFirstMatch('/^\*Tinh thần:\*\s*(.+)$/mu', $plain);
    $note = extractFirstMatch('/^\*🗒️ Note:\*\s*(.+)$/mus', $plain);

    if (str_ends_with($headerLine, '[Slack]')) {
        preg_match_all('/^\s*• \*Công việc:\* (.+)$/mu', $plain, $taskMatches);
        $todayTasks = $taskMatches[1];
        $quality = extractFirstMatch('/^\*Đánh giá:\*\s*(.+)$/mu', $plain);
        $spirit = extractFirstMatch('/^\*Mức độ:\*\s*(.+)$/mu', $plain);
        $note = extractSection($plain, '*4. CHIA SẺ THÊM*', []);
    }

    return [
        'id' => md5($file . $rawEntry),
        'file' => basename($file),
        'file_date' => $fileDate,
        'day' => date('d', strtotime($fileDate)),
        'month' => date('m/Y', strtotime($fileDate)),
        'project' => $project,
        'sent_at' => $sentAt,
        'today_tasks' => $todayTasks,
        'tomorrow_tasks' => $tomorrowTasks,
        'quality' => $quality,
        'spirit' => $spirit,
        'note' => $note,
        'plain' => $plain,
        'task_count' => count($todayTasks) + count($tomorrowTasks),
    ];
}

// ===== Nguồn 1: bản JSON có cấu trúc (report gửi từ bản mới) =====
function recordFromJson(array $row, $file)
{
    $tasksToday = [];
    foreach ($row['tasks_today'] ?? [] as $task) {
        $tasksToday[] = [
            'issue_no' => (string)($task['issue_no'] ?? ''),
            'content' => (string)($task['content'] ?? ''),
            'work_type' => (string)($task['work_type'] ?? ''),
            'status' => (string)($task['status'] ?? ''),
            'progress' => $task['progress'] === '' || !isset($task['progress']) ? '' : (string)$task['progress'],
            'estimate' => (string)($task['estimate'] ?? ''),
        ];
    }
    $tasksNext = [];
    foreach ($row['tasks_next'] ?? [] as $task) {
        $tasksNext[] = [
            'content' => (string)($task['content'] ?? ''),
            'type' => ($task['type'] ?? 'new') === 'continue' ? 'continue' : 'new',
        ];
    }

    $reportDate = (string)($row['report_date'] ?? basename($file, '.json'));
    $channels = array_keys(array_filter($row['channels'] ?? [], fn($state) => $state === 'ok'));
    $searchable = strtolower(implode(' ', array_merge(
        [$row['project'] ?? '', $reportDate, $row['daily_result'] ?? '', $row['note'] ?? ''],
        array_map(fn($task) => $task['issue_no'] . ' ' . $task['content'] . ' ' . $task['work_type'] . ' ' . $task['status'], $tasksToday),
        array_column($tasksNext, 'content')
    )));

    return [
        'id' => md5($file . ($row['sent_at'] ?? '') . ($row['project'] ?? '')),
        'source' => 'json',
        'log_file' => basename($reportDate . '.log'),
        'file_date' => $reportDate,
        'day' => date('d', strtotime($reportDate)),
        'month' => date('m/Y', strtotime($reportDate)),
        'project' => (string)($row['project'] ?? 'Report'),
        'reporter' => (string)($row['reporter'] ?? ''),
        'sent_at' => (string)($row['sent_at'] ?? $reportDate),
        'channels' => $channels,
        'today_tasks' => $tasksToday,
        'tomorrow_tasks' => $tasksNext,
        'quality' => (int)($row['quality'] ?? 0),
        'spirit' => (int)($row['spirit'] ?? 0),
        'quality_text' => (string)($row['quality_text'] ?? ''),
        'spirit_text' => (string)($row['spirit_text'] ?? ''),
        'daily_result' => (string)($row['daily_result'] ?? ''),
        'note' => (string)($row['note'] ?? ''),
        'raw_google_chat' => historyTextToPlain((string)($row['messages']['google_chat'] ?? '')),
        'raw_slack' => (string)($row['messages']['slack'] ?? ''),
        'search' => $searchable,
        'task_count' => count($tasksToday),
    ];
}

// ===== Nguồn 2: file .log cũ — chỉ tách được nội dung task dạng một dòng =====
function recordFromLog(array $parsed, $rawEntry)
{
    $toTask = fn($line) => ['issue_no' => '', 'content' => $line, 'work_type' => '', 'status' => '', 'progress' => '', 'estimate' => ''];
    return array_merge($parsed, [
        'source' => 'log',
        'log_file' => $parsed['file'],
        'reporter' => '',
        'channels' => [],
        'today_tasks' => array_map($toTask, $parsed['today_tasks']),
        'tomorrow_tasks' => array_map(fn($line) => ['content' => $line, 'type' => 'new'], $parsed['tomorrow_tasks']),
        'quality' => 0,
        'spirit' => 0,
        'quality_text' => $parsed['quality'],
        'spirit_text' => $parsed['spirit'],
        'daily_result' => '',
        'raw_google_chat' => $parsed['plain'],
        'raw_slack' => '',
        'search' => strtolower($parsed['plain'] . ' ' . $parsed['project'] . ' ' . $parsed['file_date']),
        'task_count' => count($parsed['today_tasks']),
    ]);
}

$reports = [];
$seen = [];
foreach (glob($historyDir . '/*.json') ?: [] as $file) {
    $rows = json_decode((string)file_get_contents($file), true);
    if (!is_array($rows)) {
        continue;
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $record = recordFromJson($row, $file);
        $seen[$record['file_date'] . '|' . $record['sent_at'] . '|' . $record['project']] = true;
        $reports[] = $record;
    }
}

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }

    $entries = preg_split('/\n---\n?/', trim($content)) ?: [];
    foreach ($entries as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }

        $parsed = parseHistoryEntry($entry, $file);
        // Cùng một report đã có bản JSON thì bỏ bản .log để không hiện hai lần.
        if (isset($seen[$parsed['file_date'] . '|' . $parsed['sent_at'] . '|' . $parsed['project']])) {
            continue;
        }

        $reports[] = recordFromLog($parsed, $entry);
    }
}

usort($reports, fn($a, $b) => strcmp($b['file_date'] . $b['sent_at'], $a['file_date'] . $a['sent_at']));

$statusList = [];
$workTypeList = [];
foreach ($reports as $report) {
    foreach ($report['today_tasks'] as $task) {
        if ($task['status'] !== '') $statusList[$task['status']] = true;
        if ($task['work_type'] !== '') $workTypeList[$task['work_type']] = true;
    }
}
$statusList = array_keys($statusList);
$workTypeList = array_keys($workTypeList);
sort($statusList, SORT_NATURAL | SORT_FLAG_CASE);
sort($workTypeList, SORT_NATURAL | SORT_FLAG_CASE);

$search = trim($_GET['search'] ?? '');
$totalReports = count($reports);
$totalTasks = array_sum(array_column($reports, 'task_count'));
$projectList = array_values(array_unique(array_column($reports, 'project')));
sort($projectList, SORT_NATURAL | SORT_FLAG_CASE);
$latestReport = $reports[0]['file_date'] ?? '';

// Chuỗi xu hướng lấy từ record JSON (bản .log cũ không lưu điểm số).
$trend = [];
foreach (array_reverse($reports) as $report) {
    if ($report['quality'] > 0 || $report['spirit'] > 0) {
        $trend[] = $report;
    }
}
$trend = array_slice($trend, -14);

if (isset($_GET['export'], $_GET['file'])) {
    $exportFile = $historyDir . '/' . basename($_GET['file']);
    if (file_exists($exportFile)) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($_GET['file']) . '"');
        readfile($exportFile);
        exit;
    }
}

/** Toạ độ điểm cho đường xu hướng, thang điểm 1-5. */
function trendPoints(array $rows, $key, $width, $height)
{
    $count = count($rows);
    if ($count === 0) {
        return '';
    }

    $points = [];
    foreach (array_values($rows) as $index => $row) {
        $value = max(1, min(5, (int)$row[$key] ?: 3));
        $x = $count === 1 ? $width / 2 : round($index * ($width / ($count - 1)), 2);
        $y = round($height - (($value - 1) / 4) * $height, 2);
        $points[] = "$x,$y";
    }

    return implode(' ', $points);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử Report</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        background: '#fafafa',
                        foreground: '#18181b',
                        muted: '#f4f4f5',
                        border: '#e4e4e7',
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
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.16s ease-out; }
        /* Dùng lại đúng hệ thống card/nút/ô nhập của form báo cáo. */
        .dr-card { position:relative; overflow:hidden; border:1px solid #e4e4e7; border-left:4px solid var(--dr-accent,#a1a1aa); border-radius:12px; background:#fff; box-shadow:0 1px 2px rgba(24,24,27,.05),0 8px 22px rgba(24,24,27,.035); }
        .dr-card-head { display:flex; align-items:center; gap:10px; border-bottom:1px solid #f4f4f5; background:var(--dr-accent-soft,#fafafa); padding:10px 14px; }
        .dr-card-badge { display:flex; height:26px; width:26px; flex-shrink:0; align-items:center; justify-content:center; border-radius:8px; background:var(--dr-accent,#a1a1aa); color:#fff; font-size:12px; font-weight:700; }
        .dr-card-title { color:#18181b; font-size:14px; font-weight:650; line-height:1.2; }
        .dr-card-hint { display:block; margin-top:2px; color:#71717a; font-size:11px; font-weight:400; }
        .dr-card-body { padding:12px 14px; }
        .dr-stat { border:1px solid #e4e4e7; border-left:4px solid var(--dr-accent,#a1a1aa); border-radius:10px; background:#fff; padding:10px 12px; }
        .dr-stat-label { color:#71717a; font-size:11px; font-weight:600; }
        .dr-stat-value { margin-top:2px; color:#18181b; font-size:22px; font-weight:650; line-height:1.1; }
        .dr-stat-value.is-text { font-size:15px; }
        input, select { min-height:38px; border:1px solid #dedbd7 !important; border-radius:10px !important; background-color:#fff !important; box-shadow:0 1px 2px rgba(15,23,42,.03); font-size:13px; }
        input:focus, select:focus { outline:none; border-color:#a8a29e !important; box-shadow:0 0 0 3px rgba(120,113,108,.1) !important; }
        select { appearance:none; padding-right:30px; background-image:linear-gradient(45deg,transparent 50%,#78716c 50%),linear-gradient(135deg,#78716c 50%,transparent 50%); background-position:calc(100% - 15px) 50%,calc(100% - 10px) 50%; background-size:5px 5px,5px 5px; background-repeat:no-repeat; }
        .dr-action { display:inline-flex; height:32px; align-items:center; justify-content:center; gap:6px; border:1px solid #d4d4d8; border-radius:8px; background:#f4f4f5; padding:0 10px; color:#3f3f46; font-size:12px; font-weight:600; }
        .dr-action:hover { border-color:#a1a1aa; background:#e4e4e7; color:#18181b; }
        .dr-action-primary { border-color:#18181b; background:#18181b; color:#fff; }
        .dr-action-primary:hover { border-color:#27272a; background:#27272a; color:#fff; }
        .dr-row { display:flex; align-items:center; gap:12px; cursor:pointer; padding:10px 12px; text-align:left; width:100%; }
        .dr-row:hover { background:#fafafa; }
        .dr-daybox { display:flex; height:44px; width:44px; flex-shrink:0; flex-direction:column; align-items:center; justify-content:center; border:1px solid #e4e4e7; border-radius:9px; background:#fafafa; }
        .dr-daybox-day { color:#18181b; font-size:15px; font-weight:650; line-height:1; }
        .dr-daybox-month { margin-top:2px; color:#a1a1aa; font-size:9px; line-height:1; }
        .dr-chip { display:inline-flex; align-items:center; gap:4px; border:1px solid #e4e4e7; border-radius:999px; background:#fafafa; padding:1px 8px; color:#52525b; font-size:11px; font-weight:600; }
        .dr-chip-project { border-color:#ddd6fe; background:#f5f3ff; color:#6d28d9; }
        .dr-chip-channel { border-color:#a7f3d0; background:#f0fdf4; color:#047857; }
        .dr-chip-legacy { border-color:#fde68a; background:#fffbeb; color:#b45309; }
        .dr-tasktable { width:100%; border-collapse:collapse; font-size:12px; }
        .dr-tasktable th { border-bottom:1px solid #e4e4e7; padding:5px 8px; color:#71717a; font-size:10px; font-weight:600; text-align:left; text-transform:uppercase; letter-spacing:.02em; white-space:nowrap; }
        .dr-tasktable td { border-bottom:1px solid #f4f4f5; padding:6px 8px; color:#3f3f46; vertical-align:top; }
        .dr-tasktable tr:last-child td { border-bottom:0; }
        .dr-progress { position:relative; height:5px; width:52px; overflow:hidden; border-radius:999px; background:#e4e4e7; }
        .dr-progress span { position:absolute; inset:0 auto 0 0; border-radius:999px; background:#0284c7; }
        .dr-raw { max-height:280px; overflow:auto; border:1px solid #e4e4e7; border-radius:9px; background:#fafafa; padding:10px; color:#3f3f46; font-size:12px; line-height:1.55; white-space:pre-wrap; word-break:break-word; }
        @media (prefers-reduced-motion:reduce) { *,*::before,*::after { animation-duration:.01ms !important; transition-duration:.01ms !important; } }
    </style>
</head>

<body class="min-h-screen bg-background text-foreground antialiased">
    <main class="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(212,175,55,0.14),transparent_34%),linear-gradient(180deg,#ffffff_0%,#fafafa_42%,#f4f4f5_100%)] px-3 py-4 sm:px-4 sm:py-6">
    <div class="mx-auto w-full max-w-6xl animate-fade-in">

        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-zinc-950 sm:text-2xl">Lịch sử báo cáo</h1>
                <p class="mt-1 text-sm text-zinc-500">Tra lại công việc, kế hoạch và nội dung đã gửi mỗi ngày</p>
            </div>
            <a href="index.php" class="dr-action dr-action-primary" style="height:36px;">Report mới</a>
        </div>

        <div class="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div class="dr-stat" style="--dr-accent:#0284c7;"><div class="dr-stat-label">Báo cáo</div><div class="dr-stat-value"><?= e($totalReports) ?></div></div>
            <div class="dr-stat" style="--dr-accent:#059669;"><div class="dr-stat-label">Task đã báo cáo</div><div class="dr-stat-value"><?= e($totalTasks) ?></div></div>
            <div class="dr-stat" style="--dr-accent:#7c3aed;"><div class="dr-stat-label">Project</div><div class="dr-stat-value"><?= count($projectList) ?></div></div>
            <div class="dr-stat" style="--dr-accent:#d97706;"><div class="dr-stat-label">Gần nhất</div><div class="dr-stat-value is-text"><?= $latestReport ? e(date('d/m/Y', strtotime($latestReport))) : '—' ?></div></div>
        </div>

        <?php if (count($trend) > 1): ?>
            <div class="dr-card mb-3" style="--dr-accent:#d97706;--dr-accent-soft:#fffbeb;">
                <div class="dr-card-head">
                    <span class="dr-card-badge" aria-hidden="true">📈</span>
                    <span class="dr-card-title">Chất lượng &amp; tinh thần<span class="dr-card-hint"><?= count($trend) ?> báo cáo gần nhất — thang 1 đến 5</span></span>
                </div>
                <div class="dr-card-body">
                    <svg viewBox="0 0 600 120" preserveAspectRatio="none" class="h-28 w-full" role="img" aria-label="Biểu đồ xu hướng chất lượng và tinh thần">
                        <?php foreach ([0, 30, 60, 90, 120] as $gridY): ?>
                            <line x1="0" y1="<?= $gridY ?>" x2="600" y2="<?= $gridY ?>" stroke="#f4f4f5" stroke-width="1" vector-effect="non-scaling-stroke" />
                        <?php endforeach; ?>
                        <polyline points="<?= e(trendPoints($trend, 'quality', 600, 120)) ?>" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                        <polyline points="<?= e(trendPoints($trend, 'spirit', 600, 120)) ?>" fill="none" stroke="#0284c7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="5 4" vector-effect="non-scaling-stroke" />
                    </svg>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-zinc-500">
                        <span class="flex gap-3">
                            <span class="flex items-center gap-1"><span class="inline-block h-0.5 w-4 bg-amber-600"></span>Chất lượng</span>
                            <span class="flex items-center gap-1"><span class="inline-block h-0.5 w-4 bg-sky-600"></span>Tinh thần</span>
                        </span>
                        <span><?= e(date('d/m', strtotime($trend[0]['file_date']))) ?> → <?= e(date('d/m', strtotime($trend[count($trend) - 1]['file_date']))) ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="dr-card mb-3" style="--dr-accent:#52525b;--dr-accent-soft:#fafafa;">
            <div class="dr-card-body">
                <div id="historyFilters" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                    <input type="search" id="searchInput" placeholder="Tìm theo nội dung, issue, project..." value="<?= e($search) ?>" class="px-3 lg:col-span-2" />
                    <select id="projectFilter" class="px-3">
                        <option value="">Tất cả project</option>
                        <?php foreach ($projectList as $projectName): ?><option value="<?= e(strtolower($projectName)) ?>"><?= e($projectName) ?></option><?php endforeach; ?>
                    </select>
                    <select id="statusFilter" class="px-3">
                        <option value="">Mọi trạng thái</option>
                        <?php foreach ($statusList as $statusName): ?><option value="<?= e(strtolower($statusName)) ?>"><?= e($statusName) ?></option><?php endforeach; ?>
                    </select>
                    <select id="workTypeFilter" class="px-3">
                        <option value="">Mọi loại việc</option>
                        <?php foreach ($workTypeList as $typeName): ?><option value="<?= e(strtolower($typeName)) ?>"><?= e($typeName) ?></option><?php endforeach; ?>
                    </select>
                    <input type="month" id="monthFilter" class="px-3" />
                    <button type="button" id="clearSearch" class="dr-action hidden" style="height:38px;">Xóa lọc</button>
                </div>
                <?php if (!$statusList): ?>
                    <p class="mt-2 text-[11px] text-zinc-500">Lọc theo trạng thái và loại việc chỉ áp dụng cho báo cáo gửi từ bản mới — báo cáo cũ không lưu các trường này.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$reports): ?>
            <div class="dr-card" style="--dr-accent:#a1a1aa;"><div class="dr-card-body py-10 text-center">
                <p class="mb-1 text-sm font-medium text-zinc-900">Chưa có báo cáo nào</p>
                <p class="text-sm text-zinc-500">Báo cáo sau khi gửi sẽ xuất hiện tại đây.</p>
            </div></div>
        <?php else: ?>
            <div class="mb-2 flex items-center justify-between px-1 text-xs text-zinc-500">
                <span id="reportCount"><?= $totalReports ?> báo cáo</span>
                <span>Mới nhất trước</span>
            </div>

            <div id="reportList" class="grid gap-2">
                <?php foreach ($reports as $report): ?>
                    <?php
                    $statusTags = strtolower(implode('|', array_filter(array_column($report['today_tasks'], 'status'))));
                    $typeTags = strtolower(implode('|', array_filter(array_column($report['today_tasks'], 'work_type'))));
                    $reuse = [
                        'reportDate' => $report['file_date'],
                        'project' => $report['project'],
                        'dailyResult' => $report['daily_result'],
                        'note' => $report['note'],
                        'quality' => $report['quality'] ?: 3,
                        'spirit' => $report['spirit'] ?: 3,
                        'todayTasks' => $report['today_tasks'],
                        'tomorrowTasks' => $report['tomorrow_tasks'],
                    ];
                    ?>
                    <article class="history-report dr-card" style="--dr-accent:#0284c7;"
                        data-project="<?= e(strtolower($report['project'])) ?>"
                        data-month="<?= e(substr($report['file_date'], 0, 7)) ?>"
                        data-status="<?= e($statusTags) ?>"
                        data-worktype="<?= e($typeTags) ?>"
                        data-search="<?= e($report['search']) ?>">
                        <div class="flex items-center gap-2 pr-3">
                            <button type="button" class="dr-row toggle-content" data-report="<?= e($report['id']) ?>" aria-expanded="false">
                                <span class="dr-daybox">
                                    <span class="dr-daybox-day"><?= e($report['day']) ?></span>
                                    <span class="dr-daybox-month"><?= e($report['month']) ?></span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-1.5">
                                        <span class="dr-chip dr-chip-project"><?= e($report['project']) ?></span>
                                        <span class="dr-chip"><?= e($report['task_count']) ?> task</span>
                                        <?php foreach ($report['channels'] as $channel): ?>
                                            <span class="dr-chip dr-chip-channel"><?= e(['logtime' => 'RCNV logtime', 'google_chat' => 'Google Chat', 'slack' => 'Slack'][$channel] ?? $channel) ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($report['source'] === 'log'): ?>
                                            <span class="dr-chip dr-chip-legacy" title="Báo cáo gửi trước khi lưu dữ liệu có cấu trúc">bản cũ</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-zinc-500">
                                        <span><?= e($report['sent_at']) ?></span>
                                        <?php if ($report['quality_text']): ?><span>Chất lượng: <span class="font-medium text-zinc-700"><?= e($report['quality_text']) ?></span></span><?php endif; ?>
                                        <?php if ($report['spirit_text']): ?><span>Tinh thần: <span class="font-medium text-zinc-700"><?= e($report['spirit_text']) ?></span></span><?php endif; ?>
                                    </span>
                                </span>
                                <span class="toggle-caret flex-shrink-0 text-zinc-400" aria-hidden="true">⌄</span>
                            </button>
                        </div>

                        <div class="content-detail hidden border-t border-zinc-100 bg-zinc-50/60 px-3 py-3" data-report="<?= e($report['id']) ?>">
                            <div class="mb-3 flex flex-wrap gap-2">
                                <button type="button" class="dr-action reuse-report" data-reuse="<?= e(json_encode($reuse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">Dùng lại báo cáo này</button>
                                <button type="button" class="dr-action toggle-raw" data-report="<?= e($report['id']) ?>">Nội dung đã gửi</button>
                                <a href="history.php?export=1&amp;file=<?= urlencode($report['log_file']) ?>" class="dr-action">Tải file .log</a>
                            </div>

                            <div class="mb-3 overflow-x-auto rounded-lg border border-zinc-200 bg-white">
                                <table class="dr-tasktable">
                                    <thead><tr><th>Issue</th><th>Task</th><th>Loại</th><th>Trạng thái</th><th>Tiến độ</th><th>Dự kiến</th></tr></thead>
                                    <tbody>
                                        <?php if (!$report['today_tasks']): ?>
                                            <tr><td colspan="6" class="text-zinc-400">Không có task.</td></tr>
                                        <?php else: foreach ($report['today_tasks'] as $task): ?>
                                            <tr>
                                                <td class="font-medium text-zinc-900"><?= $task['issue_no'] !== '' ? e($task['issue_no']) : '<span class="text-zinc-300">—</span>' ?></td>
                                                <td class="whitespace-pre-wrap"><?= e($task['content']) ?></td>
                                                <td><?= $task['work_type'] !== '' ? e($task['work_type']) : '<span class="text-zinc-300">—</span>' ?></td>
                                                <td><?= $task['status'] !== '' ? e($task['status']) : '<span class="text-zinc-300">—</span>' ?></td>
                                                <td><?php if ($task['progress'] !== ''): ?>
                                                    <span class="flex items-center gap-1.5"><span class="dr-progress"><span style="width:<?= (int)$task['progress'] ?>%"></span></span><?= (int)$task['progress'] ?>%</span>
                                                <?php else: ?><span class="text-zinc-300">—</span><?php endif; ?></td>
                                                <td><?= $task['estimate'] !== '' ? e(date('d/m', strtotime($task['estimate']))) : '<span class="text-zinc-300">—</span>' ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded-lg border border-zinc-200 bg-white p-3">
                                    <h2 class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-indigo-600">→ Kế hoạch tiếp theo</h2>
                                    <?php if ($report['tomorrow_tasks']): ?>
                                        <ul class="space-y-1 text-xs text-zinc-700">
                                            <?php foreach ($report['tomorrow_tasks'] as $task): ?>
                                                <li><span class="text-zinc-400">[<?= $task['type'] === 'continue' ? 'Continue' : 'New' ?>]</span> <?= e($task['content']) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?><p class="text-xs text-zinc-400">Không có.</p><?php endif; ?>
                                </div>
                                <div class="rounded-lg border border-zinc-200 bg-white p-3">
                                    <h2 class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-amber-600">🎯 Kết quả &amp; chia sẻ</h2>
                                    <p class="whitespace-pre-wrap text-xs text-zinc-700"><?= $report['daily_result'] !== '' ? e($report['daily_result']) : '<span class="text-zinc-400">Không có tự đánh giá.</span>' ?></p>
                                    <?php if ($report['note']): ?>
                                        <p class="mt-2 whitespace-pre-wrap border-t border-zinc-100 pt-2 text-xs text-zinc-700"><span class="font-medium text-zinc-900">Chia sẻ thêm:</span> <?= e($report['note']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="raw-block mt-3 hidden" data-report="<?= e($report['id']) ?>">
                                <?php foreach ([['Google Chat', $report['raw_google_chat']], ['Slack', $report['raw_slack']]] as [$rawLabel, $rawText]): ?>
                                    <?php if (trim((string)$rawText) === '') continue; ?>
                                    <div class="mb-2">
                                        <div class="mb-1 flex items-center justify-between">
                                            <span class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= e($rawLabel) ?></span>
                                            <button type="button" class="dr-action copy-raw" style="height:26px;">Copy</button>
                                        </div>
                                        <pre class="dr-raw"><?= e($rawText) ?></pre>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div id="emptySearch" class="dr-card hidden" style="--dr-accent:#a1a1aa;"><div class="dr-card-body py-10 text-center">
                <p class="mb-1 text-sm font-medium text-zinc-900">Không tìm thấy báo cáo phù hợp</p>
                <p class="text-sm text-zinc-500">Thử từ khóa khác hoặc xóa bộ lọc.</p>
            </div></div>
        <?php endif; ?>

        <div class="mt-6 text-center">
            <a href="index.php" class="text-sm text-zinc-500 hover:text-zinc-950">← Quay lại báo cáo</a>
        </div>
    </div>
    </main>

    <script>
        $(function () {
            $('.toggle-content').on('click', function () {
                const id = $(this).data('report');
                const $detail = $('.content-detail[data-report="' + id + '"]');
                const open = $detail.hasClass('hidden');
                $detail.toggleClass('hidden', !open);
                $(this).attr('aria-expanded', open ? 'true' : 'false')
                    .find('.toggle-caret').css('transform', open ? 'rotate(180deg)' : 'none');
            });

            $('.toggle-raw').on('click', function () {
                $('.raw-block[data-report="' + $(this).data('report') + '"]').toggleClass('hidden');
            });

            $('.copy-raw').on('click', function () {
                const text = $(this).closest('div').next('pre').text();
                const $button = $(this);
                const done = () => { $button.text('Đã copy'); setTimeout(() => $button.text('Copy'), 1500); };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done).catch(() => $button.text('Copy lỗi'));
                    return;
                }
                const area = document.createElement('textarea');
                area.value = text;
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                area.remove();
                done();
            });

            // Nạp lại vào form bằng chính cơ chế bản nháp của trang báo cáo.
            $('.reuse-report').on('click', function () {
                const source = $(this).data('reuse');
                const today = new Date();
                const draft = {
                    savedAt: new Date().toISOString(),
                    submitGoogleChat: true,
                    submitSlack: true,
                    submitGoogleForm: true,
                    reportDate: [today.getFullYear(), String(today.getMonth() + 1).padStart(2, '0'), String(today.getDate()).padStart(2, '0')].join('-'),
                    dailyResult: source.dailyResult || '',
                    project: source.project || '',
                    todayTasks: (source.todayTasks || []).map(task => ({
                        work_type: task.work_type || '',
                        issue_no: task.issue_no || '',
                        content: task.content || '',
                        status: task.status || '',
                        progress: task.progress === undefined ? '' : String(task.progress),
                        estimate: task.estimate || ''
                    })),
                    tomorrowTasks: (source.tomorrowTasks || []).map(task => ({ type: task.type || 'new', content: task.content || '' })),
                    quality: String(source.quality || 3),
                    spirit: String(source.spirit || 3),
                    note: source.note || ''
                };
                try {
                    localStorage.setItem('daily-report-draft-v1', JSON.stringify(draft));
                } catch (error) {
                    alert('Trình duyệt không cho lưu bản nháp, không dùng lại được báo cáo này.');
                    return;
                }
                window.location.href = 'index.php';
            });

            function filterReports() {
                const query = $('#searchInput').val().trim().toLowerCase();
                const project = $('#projectFilter').val();
                const month = $('#monthFilter').val();
                const status = $('#statusFilter').val();
                const workType = $('#workTypeFilter').val();
                let visible = 0;

                $('.history-report').each(function () {
                    const $item = $(this);
                    const tags = value => String(value || '').split('|').filter(Boolean);
                    const match = (!query || String($item.data('search')).includes(query))
                        && (!project || $item.data('project') === project)
                        && (!month || $item.data('month') === month)
                        && (!status || tags($item.data('status')).includes(status))
                        && (!workType || tags($item.data('worktype')).includes(workType));
                    $item.toggleClass('hidden', !match);
                    if (match) visible += 1;
                });

                $('#reportCount').text(visible + ' báo cáo');
                $('#emptySearch').toggleClass('hidden', visible > 0);
                $('#clearSearch').toggleClass('hidden', !query && !project && !month && !status && !workType);
            }

            $('#searchInput').on('input', filterReports);
            $('#projectFilter, #monthFilter, #statusFilter, #workTypeFilter').on('change', filterReports);
            $('#clearSearch').on('click', function () {
                $('#searchInput').val('');
                $('#projectFilter, #monthFilter, #statusFilter, #workTypeFilter').val('');
                filterReports();
            });

            filterReports();
        });
    </script>
</body>

</html>

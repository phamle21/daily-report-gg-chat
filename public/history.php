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

    if (preg_match('/^\[Daily Report\]\s+(.*)\s+-\s+([A-Za-z]+,\s+\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2})\s+\(GMT\+7\)$/u', $headerLine, $matches)) {
        $project = trim($matches[1]);
        $sentAt = trim($matches[2]);
    }

    $todayTasks = parseTaskLines(extractSection($plain, '*📝 Task hôm nay:*', ['*📅 Task ngày mai:*', '*Chất lượng:*']));
    $tomorrowTasks = parseTaskLines(extractSection($plain, '*📅 Task ngày mai:*', ['*Chất lượng:*']));
    $quality = extractFirstMatch('/^\*Chất lượng:\*\s*(.+)$/mu', $plain);
    $spirit = extractFirstMatch('/^\*Tinh thần:\*\s*(.+)$/mu', $plain);
    $note = extractFirstMatch('/^\*🗒️ Note:\*\s*(.+)$/mus', $plain);

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

$reports = [];
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

        $reports[] = parseHistoryEntry($entry, $file);
    }
}

$search = trim($_GET['search'] ?? '');
$totalTasks = array_sum(array_column($reports, 'task_count'));
$projectList = array_values(array_unique(array_column($reports, 'project')));
sort($projectList, SORT_NATURAL | SORT_FLAG_CASE);
$latestReport = $reports[0]['file_date'] ?? '';

if (isset($_GET['export'], $_GET['file'])) {
    $exportFile = $historyDir . '/' . basename($_GET['file']);
    if (file_exists($exportFile)) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($_GET['file']) . '"');
        readfile($exportFile);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử Report</title>
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
        @keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.5s ease-out; }
        .animate-slide-up { animation: fadeIn 0.5s ease-out 0.1s both; }
        #historyStats > div, .history-report, #historyFilters { border-color:#e7e5e4 !important; box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.035) !important; }
        #historyStats > div { background:#fff !important; }
        .history-report { transition:box-shadow .18s ease; }
        #historyFilters input, #historyFilters select { border:1px solid #dedbd7 !important; border-radius:10px; background-color:#fff !important; box-shadow:0 1px 2px rgba(15,23,42,.03); }
        #historyFilters input:focus, #historyFilters select:focus { border-color:#a8a29e !important; box-shadow:0 0 0 3px rgba(120,113,108,.1) !important; outline:none; }
        .dr-select{position:relative;min-width:0}.dr-select-native{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important}.dr-select-trigger{display:flex;width:100%;height:40px;align-items:center;justify-content:space-between;gap:8px;border:1px solid #dedbd7;border-radius:10px;background:#fff;padding:0 10px 0 12px;color:#292524;font-size:14px}.dr-select-trigger:focus{outline:none;box-shadow:0 0 0 3px rgba(120,113,108,.1)}.dr-select-menu{position:absolute;z-index:50;top:46px;left:0;width:100%;max-height:240px;overflow:auto;border:1px solid #e7e5e4;border-radius:11px;background:#fff;padding:5px;box-shadow:0 14px 34px rgba(15,23,42,.13)}.dr-select-option{display:flex;width:100%;min-height:34px;align-items:center;justify-content:space-between;border-radius:7px;padding:7px 9px;color:#57534e;font-size:13px;text-align:left}.dr-select-option:hover{background:#f5f5f4}.dr-select-option.is-selected{font-weight:600;color:#0c4a6e}.dr-select-option.is-selected::after{content:"✓";color:#0284c7}
        button, a { transition:background-color .16s ease,box-shadow .16s ease,transform .12s ease; }
        button:active, a:active { transform:scale(.985); }
        @media (prefers-reduced-motion:reduce) { *,*::before,*::after { animation-duration:.01ms !important;transition-duration:.01ms !important; } }
    </style>
</head>

<body class="min-h-screen bg-background text-foreground antialiased">
    <main class="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(56,189,248,0.16),transparent_30%),radial-gradient(circle_at_top_right,rgba(139,92,246,0.12),transparent_28%),linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] px-3 py-4 sm:px-5 sm:py-8">
    <div class="mx-auto w-full max-w-6xl">

        <div class="mb-5 animate-fade-in">
            <div class="flex flex-col gap-5 rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 via-white to-sky-50 p-5 shadow-soft sm:flex-row sm:items-end sm:justify-between sm:p-6">
                <div>
                    <div class="mb-3 inline-flex items-center rounded-full border border-violet-200 bg-white px-3 py-1 text-xs font-medium text-violet-700">
                        ◷ Report Archive
                    </div>
                    <h1 class="text-3xl font-semibold tracking-tight text-zinc-950 sm:text-4xl">Lịch sử báo cáo</h1>
                    <p class="mt-2 text-sm text-zinc-500">Tìm lại công việc, kế hoạch và trạng thái mỗi ngày</p>
                </div>
                <a href="index.php" class="inline-flex h-10 items-center justify-center rounded-md bg-zinc-950 px-4 text-sm font-medium text-white shadow-button transition-colors hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                    Report mới
                </a>
            </div>
        </div>

        <div id="historyStats" class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4 animate-slide-up">
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4"><div class="text-xs font-medium text-sky-700">Báo cáo</div><div class="mt-1 text-2xl font-semibold text-sky-950"><?= count($reports) ?></div></div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><div class="text-xs font-medium text-emerald-700">Tổng task</div><div class="mt-1 text-2xl font-semibold text-emerald-950"><?= e($totalTasks) ?></div></div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-4"><div class="text-xs font-medium text-violet-700">Project</div><div class="mt-1 text-2xl font-semibold text-violet-950"><?= count($projectList) ?></div></div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><div class="text-xs font-medium text-amber-700">Gần nhất</div><div class="mt-2 text-sm font-semibold text-amber-950"><?= $latestReport ? e(date('d/m/Y', strtotime($latestReport))) : '—' ?></div></div>
        </div>

        <div class="animate-slide-up">
            <div id="historyFilters" class="mb-5 grid gap-3 rounded-xl border border-zinc-200 bg-white p-3 shadow-sm sm:grid-cols-[minmax(0,1fr)_180px_160px_auto]">
                <input type="search" id="searchInput" placeholder="Tìm theo ngày, project, nội dung..." value="<?= e($search) ?>"
                    class="h-10 flex-1 rounded-md border border-zinc-200 bg-white px-3 text-sm text-zinc-950 shadow-button placeholder:text-zinc-400 transition-colors focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" />
                <select id="projectFilter" class="h-10 rounded-md border border-violet-200 bg-violet-50 px-3 text-sm text-violet-950 focus:outline-none focus:ring-2 focus:ring-violet-500">
                    <option value="">Tất cả project</option>
                    <?php foreach ($projectList as $projectName): ?><option value="<?= e(strtolower($projectName)) ?>"><?= e($projectName) ?></option><?php endforeach; ?>
                </select>
                <input type="month" id="monthFilter" class="h-10 rounded-md border border-sky-200 bg-sky-50 px-3 text-sm text-sky-950 focus:outline-none focus:ring-2 focus:ring-sky-500">
                <button type="button" id="clearSearch" class="hidden h-10 rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Xóa lọc</button>
            </div>

            <?php if (!$reports): ?>
                <div class="rounded-xl border border-zinc-200 bg-white p-12 text-center shadow-sm">
                    <p class="mb-2 text-sm font-medium text-zinc-900">Chưa có báo cáo nào</p>
                    <p class="text-sm text-zinc-500">Báo cáo sau khi gửi sẽ xuất hiện tại đây.</p>
                </div>
            <?php else: ?>
                <div class="mb-4 flex items-center justify-between px-1 text-xs text-zinc-500">
                    <span id="reportCount"><?= count($reports) ?> báo cáo</span>
                    <span>Sắp xếp mới nhất trước</span>
                </div>

                <div id="reportList" class="grid gap-3">
                    <?php foreach ($reports as $report): ?>
                        <article class="history-report overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm" data-project="<?= e(strtolower($report['project'])) ?>" data-month="<?= e(substr($report['file_date'], 0, 7)) ?>" data-search="<?= e(strtolower($report['plain'] . ' ' . $report['project'] . ' ' . $report['file_date'])) ?>">
                            <div class="group flex items-center gap-3 px-4 py-4 sm:px-5">
                                <div class="flex h-14 w-14 flex-shrink-0 flex-col items-center justify-center rounded-lg border border-sky-200 bg-sky-50">
                                    <span class="text-lg font-semibold leading-none text-zinc-950"><?= e($report['day']) ?></span>
                                    <span class="mt-0.5 text-[10px] leading-none text-zinc-500"><?= e($report['month']) ?></span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-xs font-semibold text-violet-800"><?= e($report['project']) ?></span>
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"><?= e($report['task_count']) ?> tasks</span>
                                        <span class="text-xs text-zinc-500"><?= e($report['sent_at']) ?></span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <?php if ($report['quality']): ?>
                                            <span class="text-xs text-zinc-500">Chất lượng: <span class="font-medium text-zinc-800"><?= e($report['quality']) ?></span></span>
                                        <?php endif; ?>
                                        <?php if ($report['spirit']): ?>
                                            <span class="text-xs text-zinc-500">Tinh thần: <span class="font-medium text-zinc-800"><?= e($report['spirit']) ?></span></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex flex-shrink-0 items-center gap-1">
                                    <a href="history.php?export=1&file=<?= urlencode($report['file']) ?>" class="rounded-md p-2 text-zinc-400 transition-all hover:bg-zinc-100 hover:text-zinc-950" title="Download">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    </a>
                                    <button type="button" class="toggle-content rounded-md p-2 text-zinc-400 transition-all hover:bg-zinc-100 hover:text-zinc-950" data-report="<?= e($report['id']) ?>" title="Chi tiết">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="hidden content-detail border-t border-zinc-100 bg-slate-50 px-4 py-4 sm:px-5" data-report="<?= e($report['id']) ?>">
                                <div class="grid gap-4 text-sm text-zinc-700 md:grid-cols-2">
                                    <section class="rounded-lg border border-sky-200 bg-sky-50/70 p-3">
                                        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-sky-700">✓ Task hôm nay</h2>
                                        <?php if ($report['today_tasks']): ?>
                                            <ul class="space-y-1.5">
                                                <?php foreach ($report['today_tasks'] as $task): ?>
                                                    <li class="rounded-md border border-zinc-200 bg-white px-3 py-2"><?= e($task) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-xs text-zinc-500">Không có dữ liệu.</p>
                                        <?php endif; ?>
                                    </section>

                                    <section class="rounded-lg border border-indigo-200 bg-indigo-50/70 p-3">
                                        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-indigo-700">→ Task ngày mai</h2>
                                        <?php if ($report['tomorrow_tasks']): ?>
                                            <ul class="space-y-1.5">
                                                <?php foreach ($report['tomorrow_tasks'] as $task): ?>
                                                    <li class="rounded-md border border-zinc-200 bg-white px-3 py-2"><?= e($task) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-xs text-zinc-500">Chưa có kế hoạch.</p>
                                        <?php endif; ?>
                                    </section>
                                </div>

                                <?php if ($report['note']): ?>
                                    <div class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-zinc-700">
                                        <span class="font-medium text-zinc-900">Note:</span> <?= e($report['note']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div id="emptySearch" class="hidden rounded-xl border border-zinc-200 bg-white p-10 text-center shadow-sm">
                    <p class="mb-2 text-sm font-medium text-zinc-900">Không tìm thấy báo cáo phù hợp</p>
                    <p class="text-sm text-zinc-500">Thử từ khóa khác hoặc xóa bộ lọc.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-8 text-center">
            <a href="index.php" class="inline-flex items-center gap-1 text-sm text-zinc-500 transition-colors hover:text-zinc-950">
                ← Quay lại báo cáo
            </a>
        </div>
    </div>
    </main>

    <script>
        $(document).ready(function () {
            const $nativeProject = $('#projectFilter');
            if ($nativeProject.length) {
                const $custom = $('<div class="dr-select"><button type="button" class="dr-select-trigger" aria-expanded="false"><span class="dr-select-label"></span><span>⌄</span></button><div class="dr-select-menu hidden"></div></div>');
                $nativeProject.wrap($custom).addClass('dr-select-native');
                const $root = $nativeProject.closest('.dr-select');
                function renderProjectSelect() {
                    const selected = $nativeProject.find('option:selected').text();
                    $root.find('.dr-select-label').text(selected);
                    const $menu = $root.find('.dr-select-menu').empty();
                    $nativeProject.find('option').each(function (index) {
                        $('<button type="button" class="dr-select-option"></button>').text($(this).text()).attr('data-index', index).toggleClass('is-selected', this.selected).appendTo($menu);
                    });
                }
                renderProjectSelect();
                $root.on('click', '.dr-select-trigger', function (event) { event.stopPropagation(); $root.find('.dr-select-menu').toggleClass('hidden'); });
                $root.on('click', '.dr-select-option', function () { $nativeProject.prop('selectedIndex', Number($(this).data('index'))).trigger('change'); renderProjectSelect(); $root.find('.dr-select-menu').addClass('hidden'); });
                $(document).on('click', function () { $root.find('.dr-select-menu').addClass('hidden'); });
            }

            $('.toggle-content').click(function () {
                const target = $(this).data('report');
                const $detail = $('.content-detail[data-report="' + target + '"]');
                $detail.toggleClass('hidden');
                const $icon = $(this).find('svg');
                if ($detail.hasClass('hidden')) {
                    $icon.html('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>');
                } else {
                    $icon.html('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>');
                }
            });

            function filterReports() {
                const query = $('#searchInput').val().trim().toLowerCase();
                const project = $('#projectFilter').val();
                const month = $('#monthFilter').val();
                let visibleCount = 0;

                $('.history-report').each(function () {
                    const matchesQuery = !query || $(this).data('search').includes(query);
                    const matchesProject = !project || $(this).data('project') === project;
                    const matchesMonth = !month || $(this).data('month') === month;
                    const isMatch = matchesQuery && matchesProject && matchesMonth;
                    $(this).toggleClass('hidden', !isMatch);
                    if (isMatch) {
                        visibleCount++;
                    }
                });

                $('#reportCount').text(visibleCount + ' báo cáo');
                $('#emptySearch').toggleClass('hidden', visibleCount > 0);
                $('#reportList').toggleClass('hidden', visibleCount === 0);
                $('#clearSearch').toggleClass('hidden', !query && !project && !month);
            }

            $('#searchInput').on('input', filterReports);
            $('#projectFilter, #monthFilter').on('change', filterReports);
            $('#clearSearch').on('click', function () {
                $('#searchInput').val('');
                $('#projectFilter, #monthFilter').val('');
                filterReports();
            });

            filterReports();
        });
    </script>
</body>

</html>

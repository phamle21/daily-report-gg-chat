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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.5s ease-out; }
        .animate-slide-up { animation: fadeIn 0.5s ease-out 0.1s both; }
    </style>
</head>

<body class="min-h-screen bg-background text-foreground antialiased">
    <main class="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(212,175,55,0.14),transparent_34%),linear-gradient(180deg,#ffffff_0%,#fafafa_42%,#f4f4f5_100%)] px-4 py-8 sm:py-12">
    <div class="w-full max-w-4xl mx-auto">

        <div class="mb-8 animate-fade-in">
            <div class="flex flex-col gap-5 rounded-2xl border border-zinc-200/80 bg-white/85 p-6 shadow-soft backdrop-blur sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="mb-3 inline-flex items-center rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">
                        Report Archive
                    </div>
                    <h1 class="text-3xl font-semibold tracking-tight text-zinc-950 sm:text-4xl">Lịch sử báo cáo</h1>
                    <p class="mt-2 text-sm text-zinc-500">Tổng quan các báo cáo đã gửi</p>
                </div>
                <a href="index.php" class="inline-flex h-10 items-center justify-center rounded-md bg-zinc-950 px-4 text-sm font-medium text-white shadow-button transition-colors hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                    Report mới
                </a>
            </div>
        </div>

        <div class="animate-slide-up">
            <div class="mb-6 flex gap-3">
                <input type="search" id="searchInput" placeholder="Tìm theo ngày, project, nội dung..." value="<?= e($search) ?>"
                    class="h-10 flex-1 rounded-md border border-zinc-200 bg-white px-3 text-sm text-zinc-950 shadow-button placeholder:text-zinc-400 transition-colors focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" />
                <a href="index.php" class="hidden h-10 items-center justify-center whitespace-nowrap rounded-md border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-800 shadow-button transition-colors hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 sm:inline-flex">
                    Tạo mới
                </a>
            </div>

            <?php if (!$reports): ?>
                <div class="rounded-xl border border-zinc-200 bg-white p-12 text-center shadow-sm">
                    <p class="mb-2 text-sm font-medium text-zinc-900">Chưa có báo cáo nào</p>
                    <p class="text-sm text-zinc-500">Báo cáo sau khi gửi sẽ xuất hiện tại đây.</p>
                </div>
            <?php else: ?>
                <div class="mb-4 flex items-center justify-between px-1 text-xs text-zinc-500">
                    <span id="reportCount"><?= count($reports) ?> báo cáo</span>
                    <button type="button" id="clearSearch" class="hidden font-medium text-zinc-900 transition-colors hover:text-zinc-600">Bỏ bộ lọc</button>
                </div>

                <div id="reportList" class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
                    <?php foreach ($reports as $report): ?>
                        <div class="history-report border-b border-zinc-100 last:border-b-0" data-search="<?= e(strtolower($report['plain'] . ' ' . $report['project'] . ' ' . $report['file_date'])) ?>">
                            <div class="group flex items-center gap-3 px-5 py-4 transition-colors hover:bg-zinc-50">
                                <div class="flex h-12 w-12 flex-shrink-0 flex-col items-center justify-center rounded-md border border-zinc-200 bg-zinc-50">
                                    <span class="text-lg font-semibold leading-none text-zinc-950"><?= e($report['day']) ?></span>
                                    <span class="mt-0.5 text-[10px] leading-none text-zinc-500"><?= e($report['month']) ?></span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border border-zinc-200 bg-white px-2 py-0.5 text-xs font-medium text-zinc-800"><?= e($report['project']) ?></span>
                                        <span class="text-sm font-medium text-zinc-900"><?= e($report['task_count']) ?> tasks</span>
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

                            <div class="hidden content-detail border-t border-zinc-100 bg-zinc-50 px-5 py-4" data-report="<?= e($report['id']) ?>">
                                <div class="grid gap-4 text-sm text-zinc-700 md:grid-cols-2">
                                    <section>
                                        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Task hôm nay</h2>
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

                                    <section>
                                        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Task ngày mai</h2>
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
                                    <div class="mt-4 rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700">
                                        <span class="font-medium text-zinc-900">Note:</span> <?= e($report['note']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
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
                let visibleCount = 0;

                $('.history-report').each(function () {
                    const isMatch = !query || $(this).data('search').includes(query);
                    $(this).toggleClass('hidden', !isMatch);
                    if (isMatch) {
                        visibleCount++;
                    }
                });

                $('#reportCount').text(visibleCount + ' báo cáo');
                $('#emptySearch').toggleClass('hidden', visibleCount > 0);
                $('#reportList').toggleClass('hidden', visibleCount === 0);
                $('#clearSearch').toggleClass('hidden', !query);
            }

            $('#searchInput').on('input', filterReports);
            $('#clearSearch').on('click', function () {
                $('#searchInput').val('');
                filterReports();
            });

            filterReports();
        });
    </script>
</body>

</html>

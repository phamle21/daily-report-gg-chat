<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
$historyDir = __DIR__ . '/history';
$files = glob($historyDir . '/*.log');
rsort($files);

// Search/filter
$search = $_GET['search'] ?? '';
if ($search) {
    $filtered = [];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (stripos($content, $search) !== false) {
            $filtered[] = $file;
        }
    }
    $files = $filtered;
}

// Export
if (isset($_GET['export']) && isset($_GET['file'])) {
    $exportFile = __DIR__ . '/history/' . basename($_GET['file']);
    if (file_exists($exportFile)) {
        header('Content-Type: text/plain');
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
    <div class="w-full max-w-3xl mx-auto">

        <!-- Header -->
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
            <!-- Search + New -->
            <div class="mb-6 flex gap-3">
                <input type="search" id="searchInput" placeholder="Tìm theo ngày, nội dung..." value="<?= htmlspecialchars($search) ?>"
                    class="h-10 flex-1 rounded-md border border-zinc-200 bg-white px-3 text-sm text-zinc-950 shadow-button placeholder:text-zinc-400 transition-colors focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" />
                <a href="index.php" class="hidden h-10 items-center justify-center whitespace-nowrap rounded-md border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-800 shadow-button transition-colors hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 sm:inline-flex">
                    Tạo mới
                </a>
            </div>

            <?php if (!$files): ?>
                <div class="rounded-xl border border-zinc-200 bg-white p-12 text-center shadow-sm">
                    <p class="mb-2 text-sm font-medium text-zinc-900">Chưa có báo cáo nào</p>
                    <p class="text-sm text-zinc-500">Báo cáo sau khi gửi sẽ xuất hiện tại đây.</p>
                </div>
            <?php else: ?>
                <div class="mb-4 flex items-center justify-between px-1 text-xs text-zinc-500">
                    <span><?= count($files) ?> báo cáo</span>
                    <?php if ($search): ?>
                        <a href="history.php" class="font-medium text-zinc-900 transition-colors hover:text-zinc-600">Bỏ bộ lọc</a>
                    <?php endif; ?>
                </div>

                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
                    <?php foreach ($files as $file):
                        $dateStr = basename($file, '.log');
                        $content = file_get_contents($file);
                        // Parse date info for display
                        $lines = explode("\n", trim($content));
                        $project = '';
                        $quality = '';
                        $spirit = '';
                        $taskCount = 0;
                        foreach ($lines as $line) {
                            if (strpos($line, 'Project:') === 0) $project = trim(explode(':', $line)[1]);
                            if (strpos($line, 'Quality:') === 0) $quality = trim(explode(':', $line)[1]);
                            if (strpos($line, 'Spirit:') === 0) $spirit = trim(explode(':', $line)[1]);
                            if (strpos($line, '- ') === 0) $taskCount++;
                        }
                        $spiritEmoji = $spirit === '5' ? '🔥' : ($spirit === '4' ? '😃' : ($spirit === '3' ? '😊' : ($spirit === '2' ? '🤒' : '😵')));
                    ?>
                        <div class="border-b border-zinc-100 last:border-b-0">
                            <div class="group flex items-center gap-3 px-5 py-4 transition-colors hover:bg-zinc-50">
                                <!-- Date icon -->
                                <div class="flex h-12 w-12 flex-shrink-0 flex-col items-center justify-center rounded-md border border-zinc-200 bg-zinc-50">
                                    <span class="text-lg font-semibold leading-none text-zinc-950"><?= substr($dateStr, 8) ?></span>
                                    <span class="mt-0.5 text-[10px] leading-none text-zinc-500"><?= substr($dateStr, 5, 3) ?></span>
                                </div>

                                <!-- Info -->
                                <div class="min-w-0 flex-1">
                                    <div class="mb-1 flex items-center gap-2">
                                        <span class="rounded-full border border-zinc-200 bg-white px-2 py-0.5 text-xs font-medium text-zinc-800"><?= htmlspecialchars($project) ?></span>
                                        <span class="text-sm font-medium text-zinc-900"><?= $taskCount ?> tasks</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-zinc-500">Chất lượng: <span class="font-medium text-zinc-800"><?= $quality ?></span></span>
                                        <span class="text-sm"><?= $spiritEmoji ?></span>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex flex-shrink-0 items-center gap-1">
                                    <a href="history.php?export=1&file=<?= urlencode(basename($file)) ?>" class="rounded-md p-2 text-zinc-400 transition-all hover:bg-zinc-100 hover:text-zinc-950" title="Download">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    </a>
                                    <span class="toggle-content cursor-pointer rounded-md p-2 text-zinc-400 transition-all hover:bg-zinc-100 hover:text-zinc-950" data-file="<?= htmlspecialchars($dateStr) ?>" title="Chi tiết">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </span>
                                </div>
                            </div>
                            <div class="hidden content-detail" data-file="<?= htmlspecialchars($dateStr) ?>">
                                <pre class="max-h-80 overflow-auto whitespace-pre-wrap break-words border-t border-zinc-100 bg-zinc-50 px-5 py-4 font-mono text-xs leading-relaxed text-zinc-600"><?= htmlspecialchars($content) ?></pre>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
            // Toggle content expand/collapse
            $('.toggle-content').click(function () {
                const target = $(this).data('file');
                const $detail = $('.content-detail[data-file="' + target + '"]');
                $detail.toggleClass('hidden');
                const $icon = $(this).find('svg');
                if ($detail.hasClass('hidden')) {
                    $icon.html('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>');
                } else {
                    $icon.html('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>');
                }
            });

            // Search/filter
            let debounceTimer;
            $('#searchInput').on('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    const query = $(this).val().trim();
                    if (query) {
                        window.location.href = 'history.php?search=' + encodeURIComponent(query);
                    } else {
                        window.location.href = 'history.php';
                    }
                }.bind(this), 300);
            });
        });
    </script>
</body>

</html>

<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$today = date('l, d/m/Y');
if (!file_exists(__DIR__ . '/history/app-config.php')) {
    header('Location: settings.php');
    exit;
}
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
    <?php require __DIR__ . '/partials/head-assets.php'; ?>
</head>

<body class="min-h-screen bg-background text-foreground antialiased dr-wepro-off">
    <main class="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(212,175,55,0.16),transparent_34%),linear-gradient(180deg,#d4d4d8_0%,#c4c4ca_45%,#b4b4bb_100%)] px-3 py-4 sm:px-4 sm:py-6">
    <div class="w-full max-w-5xl mx-auto animate-fade-in dr-page">

        <!-- Header -->
        <div class="mb-5 animate-slide-up">
            <div class="flex flex-col gap-3 rounded-xl border border-zinc-300/80 bg-white/90 p-4 shadow-soft backdrop-blur sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 sm:text-3xl">Daily Report</h1>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars($today) ?></p>
                </div>
                <div class="flex gap-2">
                    <button type="button" id="googleFormRangeBtn" data-start-time="<?= e(sprintf('%02d:%02d', (int)($googleForm['start_hour'] ?? 8), (int)($googleForm['start_minute'] ?? 0))) ?>" data-end-time="<?= e(sprintf('%02d:%02d', (int)($googleForm['end_hour'] ?? 17), (int)($googleForm['end_minute'] ?? 0))) ?>" class="inline-flex h-9 items-center justify-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-3 text-sm font-medium text-amber-800 shadow-button transition-colors hover:bg-amber-100" title="Nộp logtime RCVN (Google Form) cho các ngày đã quên">🗓 Logtime RCVN bù</button>
                    <a href="tasks.php" class="inline-flex h-9 items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 text-sm font-medium text-indigo-800 shadow-button transition-colors hover:bg-indigo-100" title="Tạo và sửa task trên WePRO">☰ Task WePRO</a>
                    <a href="history.php" class="inline-flex h-9 items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-800 shadow-button transition-colors hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">Lịch sử</a>
                    <span id="weproStatus" class="dr-status wait" title="Đang kiểm tra cookie WePRO"><span class="dr-status-dot"></span><span class="dr-status-text">WePRO: đang kiểm tra...</span></span>
                    <a href="settings.php" class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-zinc-950 px-3 text-sm font-medium text-white shadow-button transition-colors hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                        <span aria-hidden="true">⚙</span> Thiết lập
                    </a>
                </div>
            </div>
        </div>

        <div>

        <form data-reporter="<?= e($config['reporter'] ?? '') ?>" id="dailyReportForm" class="mx-auto max-w-5xl space-y-5 animate-slide-up">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <nav class="dr-progress" id="formProgress" aria-label="Các bước">
                <button type="button" class="dr-progress-step" data-target="card-tasks"><span class="dr-progress-num">1</span>Task hôm nay</button>
                <button type="button" class="dr-progress-step" data-target="card-review"><span class="dr-progress-num">2</span>Tự đánh giá</button>
                <button type="button" class="dr-progress-step" data-target="card-send"><span class="dr-progress-num">3</span>Gửi</button>
            </nav>

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
            <div class="dr-card" id="card-tasks" style="--dr-accent:#0284c7;--dr-accent-soft:#f0f9ff;">
                <div class="dr-card-head">
                    <span class="dr-card-badge" aria-hidden="true">1</span>
                    <span class="dr-card-title">Công việc trong ngày<span class="dr-card-hint">Mỗi task ghi rõ issue, activity và tiến độ — để trống Issue nếu không có</span></span>
                </div>
                <div class="dr-card-body">
                <div class="dr-colhead" aria-hidden="true">
                    <span style="flex:0 0 20px;"></span>
                    <span class="dr-field-label dr-col-issue">Issue</span>
                    <span class="dr-field-label dr-col-title">Task</span>
                    <span class="dr-field-label dr-col-type">Activity</span>
                    <span class="dr-field-label dr-col-progress">Tiến độ</span>
                    <span class="dr-field-label dr-col-hours" title="Chỉ dùng để điền sẵn khi logtime WePRO">Giờ log</span>
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
                    <button type="button" id="wepro-pick-task" class="dr-action dr-action-indigo hidden" title="Chọn task từ WePRO và điền vào report">
                        ▤ Chọn task WePRO
                    </button>
                    <button type="button" id="wepro-log-bulk" class="dr-action dr-action-sky hidden" title="Logtime lên WePRO cho mọi task đã gắn WePRO">⏱ Logtime WePRO</button>
                    <button type="button" id="wepro-progress-bulk" class="dr-action dr-action-indigo hidden" title="Cập nhật % và trạng thái các task WePRO trong report">⇪ Cập nhật task WePRO</button>
                    <a id="weproLoggedTotal" class="dr-wlog-badge hidden" href="#" target="_blank" rel="noopener noreferrer" title="Mở trang log giờ trên WePRO"></a>
                    <button type="button" id="clear-tasks-today" class="dr-action dr-action-danger" title="Xóa hết task hôm nay để nhập lại">🗑 Xóa hết</button>
                </div>
                </div>
            </div>

            <!-- Self evaluation -->
            <div class="dr-card" id="card-review" style="--dr-accent:#d97706;--dr-accent-soft:#fffbeb;">
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
                            <button type="button" class="quality-btn min-h-20 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-2 text-center text-sm text-zinc-600 transition-all hover:border-zinc-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2" data-value="3">
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
                            <div class="dr-callout-row"><label for="dailyResult" class="dr-callout-label">Tự đánh giá ngắn gọn<span class="dr-callout-tag">tùy chọn</span></label><button type="button" class="dr-tips-btn" aria-controls="resultTips" aria-expanded="false" title="Bấm để xem gợi ý nên viết gì">💡 <span>Xem gợi ý</span></button></div>
                            <p class="dr-callout-hint">Một câu về kết quả và chất lượng công việc hôm nay — phần này hiện ngay trong báo cáo gửi đi.</p>
                            <ul class="dr-tips" id="resultTips" hidden>
                                <li>Hôm nay hoàn thành được gì, tới mức nào? (VD: xong màn đơn hàng, đã test trên local)</li>
                                <li>Chất lượng ra sao: đã tự test, đã review chưa, còn lỗi nào đã biết?</li>
                                <li>Có việc nào trễ so với dự kiến? Vì sao?</li>
                                <li>Việc tiếp theo là gì, đang chờ ai xác nhận?</li>
                            </ul>
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

            <div class="dr-card dr-optional hidden" id="card-share" style="--dr-accent:#e11d48;--dr-accent-soft:#fff1f2;">
                <div class="dr-card-head">
                    <span class="dr-card-badge" aria-hidden="true">+</span>
                    <span class="dr-card-title">Chia sẻ thêm <span class="font-normal text-zinc-500">(nếu có)</span> <button type="button" class="dr-tips-btn" aria-controls="shareTips" aria-expanded="false" title="Bấm để xem gợi ý nên viết gì">💡 <span>Xem gợi ý</span></button><span class="dr-card-hint">Hãy chia sẻ ngắn gọn điều ảnh hưởng đến cảm xúc hoặc tinh thần và chất lượng công việc của bạn hôm nay.</span></span>
                </div>
                <div class="dr-card-body">
                <ul class="dr-tips" id="shareTips" hidden>
                    <li>Hôm nay có gì vướng? (thiếu thông tin, chờ người khác, môi trường lỗi…)</li>
                    <li>Cần ai hỗ trợ, hoặc cần quyết định gì?</li>
                    <li>Điều gì làm bạn vui hoặc mệt hôm nay?</li>
                    <li>Có đề xuất gì về quy trình, công cụ, cách làm việc?</li>
                </ul>
                <textarea name="note" class="min-h-20 w-full resize-none rounded-md border border-input bg-white px-3 py-2 text-sm text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2" rows="3" placeholder="Viết ngắn gọn. Chưa biết viết gì? Bấm 💡 Xem gợi ý ở tiêu đề."></textarea>
                </div>
            </div>

            <div class="dr-optional-bar" id="optionalBar">
                <span class="dr-optional-label">Thêm phần tuỳ chọn:</span>
                <button type="button" class="dr-chip" data-optional="card-share">＋ Chia sẻ thêm</button>
            </div>

        </form>
        <!-- Send panel: outside the form so its entry animation (transform) cannot trap position:fixed -->
            <div class="dr-sendbar dr-card" id="card-send" style="--dr-accent:#059669;--dr-accent-soft:#ecfdf5;">
                <div class="dr-card-head">
                    <span class="dr-card-badge" aria-hidden="true">↗</span>
                    <span class="dr-card-title">Gửi đến<span class="dr-card-hint">Bật nơi nhận rồi bấm Gửi. Có thể gửi nhiều nơi một lần.</span></span>
                </div>
                <div class="dr-sendbar-inner">
                    <div class="dr-dest-list">
                        <label class="dr-dest" for="submitGoogleForm">
                            <span class="dr-dest-icon" style="--c:#d97706;background:#fffbeb;">🗓</span>
                            <span class="dr-dest-text"><span class="dr-dest-name">RCNV logtime</span><span class="dr-dest-desc">Nộp giờ làm qua Google Form</span></span>
                            <input type="checkbox" form="dailyReportForm" id="submitGoogleForm" name="submit_google_form" value="1" checked class="dr-switch-input">
                            <span class="dr-switch-track" aria-hidden="true"><span class="dr-switch-thumb"></span></span>
                        </label>
                        <label class="dr-dest" for="submitGoogleChat">
                            <span class="dr-dest-icon" style="--c:#0284c7;background:#f0f9ff;">💬</span>
                            <span class="dr-dest-text"><span class="dr-dest-name">Google Chat</span><span class="dr-dest-desc">Card báo cáo vào room project</span></span>
                            <input type="checkbox" form="dailyReportForm" id="submitGoogleChat" name="submit_google_chat" value="1" class="dr-switch-input">
                            <span class="dr-switch-track" aria-hidden="true"><span class="dr-switch-thumb"></span></span>
                        </label>
                        <label class="dr-dest" for="submitSlack">
                            <span class="dr-dest-icon" style="--c:#7c3aed;background:#f5f3ff;">#</span>
                            <span class="dr-dest-text"><span class="dr-dest-name">Slack</span><span class="dr-dest-desc">Text vào channel Slack</span></span>
                            <input type="checkbox" form="dailyReportForm" id="submitSlack" name="submit_slack" value="1" checked class="dr-switch-input">
                            <span class="dr-switch-track" aria-hidden="true"><span class="dr-switch-thumb"></span></span>
                        </label>
                        <label class="dr-dest dr-dest-muted" for="historyOnly">
                            <span class="dr-dest-icon" style="--c:#4f46e5;background:#eef2ff;">🗂</span>
                            <span class="dr-dest-text"><span class="dr-dest-name">Chỉ lưu lịch sử</span><span class="dr-dest-desc">Không gửi, chỉ lưu để tra cứu</span></span>
                            <input type="checkbox" form="dailyReportForm" id="historyOnly" name="history_only" value="1" class="dr-switch-input">
                            <span class="dr-switch-track" aria-hidden="true"><span class="dr-switch-thumb"></span></span>
                        </label>
                    </div>
                    <div class="dr-sendbar-actions">
                        <button type="submit" form="dailyReportForm" id="submitBtn" title="Phím tắt: Ctrl/Cmd + Enter" class="dr-send-btn">
                            <span id="submitText">Gửi báo cáo</span>
                            <svg id="loadingIcon" class="ml-2 h-4 w-4 animate-spin hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        </button>
                        <button type="button" id="previewReport" class="dr-action dr-preview-btn">👁 Xem trước</button>
                    </div>
                    <div class="dr-sendbar-draft"><span id="draftStatus">Bản nháp sẽ tự động lưu</span> <button type="button" id="clearDraft" class="hidden font-medium hover:text-red-600">· Xóa bản nháp</button></div>
                </div>
            </div>
        </div>

    </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script src="wepro-create.js?t=<?= e((string)(@filemtime(__DIR__ . '/wepro-create.js') ?: time())) ?>"></script>
    <script src="main.js?t=<?= e((string)(@filemtime(__DIR__ . '/main.js') ?: time())) ?>"></script>
</body>

</html>

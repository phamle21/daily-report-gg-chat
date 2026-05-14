<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.45s ease-out; }
        .animate-slide-up { animation: fadeIn 0.45s ease-out 0.08s both; }
        .animate-slide-up-delay { animation: fadeIn 0.45s ease-out 0.16s both; }
        .animate-slide-up-late { animation: fadeIn 0.45s ease-out 0.24s both; }
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
                <a href="history.php" class="inline-flex h-9 items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-800 shadow-button transition-colors hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                    Lịch sử
                </a>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[330px_minmax(0,1fr)] lg:items-start">
        <aside class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm lg:sticky lg:top-4">
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-zinc-950">Thiết lập</h2>
                <p class="mt-1 text-xs text-zinc-500">Lưu vào file config trong source</p>
            </div>

            <form id="settingsForm" class="space-y-2.5">
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-700">Project mặc định khi mở form</label>
                    <select name="default_project" id="defaultProjectSelect" class="h-8 w-full rounded-md border border-zinc-200 bg-white px-2 text-sm text-zinc-950 shadow-button focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                        <?php foreach ($projectNames as $projectName): ?>
                            <option value="<?= e($projectName) ?>" <?= $defaultProject === $projectName ? 'selected' : '' ?>><?= e($projectName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rounded-lg border border-zinc-200 p-2.5">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <div>
                            <div class="text-xs font-semibold text-zinc-900">Danh sách project</div>
                            <div class="text-[11px] text-zinc-500">Tên, webhook Google Chat, logo/avatar.</div>
                        </div>
                        <button type="button" id="addProject" class="h-7 rounded-md border border-zinc-200 bg-white px-2 text-xs font-medium text-zinc-800 hover:bg-zinc-50">Thêm</button>
                    </div>
                    <div id="projectSettingsList" class="space-y-2">
                        <?php foreach ($projects as $projectName => $projectConfig): ?>
                            <div class="project-setting rounded-md border border-zinc-200 bg-zinc-50 p-2" data-project="<?= e($projectName) ?>">
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
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rounded-lg border border-zinc-200 p-2.5">
                    <label class="mb-2 flex items-center justify-between gap-3 text-xs font-semibold text-zinc-900">
                        Bật gửi kèm Google Form
                        <input type="checkbox" name="google_form_enabled" value="1" <?= !empty($googleForm['enabled']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-zinc-300 text-zinc-950 focus:ring-zinc-950">
                    </label>
                    <div class="space-y-2">
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
        </aside>

        <form id="dailyReportForm" class="space-y-3 animate-slide-up">

            <!-- Project -->
            <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm">
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
            <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm">
                <label class="mb-2.5 block text-sm font-medium text-zinc-900">
                    <span class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-zinc-950 text-xs font-semibold text-white">2</span>
                        Task hôm nay
                        <span class="text-xs font-normal text-zinc-500">(cần hoàn thành)</span>
                    </span>
                </label>
                <div id="tasks-today-list" class="space-y-2"></div>
                <button type="button" id="add-task-today" class="mt-2 inline-flex h-8 items-center justify-center gap-2 rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-800 shadow-button transition-colors hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"></path></svg>
                    Thêm task
                </button>
            </div>

            <!-- Task ngày mai -->
            <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm">
                <label class="mb-2.5 block text-sm font-medium text-zinc-900">
                    <span class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-zinc-950 text-xs font-semibold text-white">3</span>
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
            <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm">
                <label class="mb-3 block text-sm font-medium text-zinc-900">
                    <span class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-zinc-950 text-xs font-semibold text-white">4</span>
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
                        <div id="spirit-list" class="flex flex-wrap gap-2">
                            <span class="react-emoji flex h-12 w-12 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 text-xl transition-all hover:border-zinc-300 hover:bg-white" data-value="1">😵‍💫</span>
                            <span class="react-emoji flex h-12 w-12 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 text-xl transition-all hover:border-zinc-300 hover:bg-white" data-value="2">🤒</span>
                            <span class="react-emoji flex h-12 w-12 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 text-xl transition-all hover:border-zinc-300 hover:bg-white" data-value="3" data-default="true">😊</span>
                            <span class="react-emoji flex h-12 w-12 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 text-xl transition-all hover:border-zinc-300 hover:bg-white" data-value="4">😃</span>
                            <span class="react-emoji flex h-12 w-12 cursor-pointer items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 text-xl transition-all hover:border-zinc-300 hover:bg-white" data-value="5">🔥</span>
                        </div>
                        <input type="hidden" name="spirit" id="spirit" required>
                    </div>
                </div>
            </div>

            <!-- Note -->
            <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm">
                <label class="mb-2 block text-sm font-medium text-zinc-900">
                    <span class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-zinc-950 text-xs font-semibold text-white">5</span>
                        Ghi chú
                        <span class="text-xs font-normal text-zinc-500">(tùy chọn)</span>
                    </span>
                </label>
                <textarea name="note" class="min-h-20 w-full resize-none rounded-md border border-input bg-white px-3 py-2 text-sm text-zinc-950 shadow-button placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2" rows="2" placeholder="Ghi chú thêm..."></textarea>
            </div>

            <!-- Google Form toggle -->
            <div class="rounded-xl border border-zinc-200 bg-white p-3 shadow-sm">
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
            <button type="submit" id="submitBtn" class="flex h-10 w-full items-center justify-center gap-2 rounded-md bg-zinc-950 px-4 text-sm font-medium text-white shadow-button transition-all duration-150 hover:bg-zinc-800 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2">
                <span id="submitText">Gửi báo cáo</span>
                <svg id="loadingIcon" class="ml-2 h-4 w-4 animate-spin hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
            </button>
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

    <script src="main.js"></script>
</body>

</html>

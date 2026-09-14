<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Set response type to JSON
header('Content-Type: application/json; charset=utf-8');
// Log errors to file, NOT to output (prevents HTML breaking JSON response)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

// ===== LOG HELPER =====
function writeLog($message)
{
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    $file = $logDir . '/' . date('Y-m-d') . '.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($file, "[$time] $message\n", FILE_APPEND);
}

// Catch any unexpected errors/warnings and log them instead of outputting
set_error_handler(function ($severity, $message, $file, $line) {
    writeLog("PHP Error [$severity]: $message in $file:$line");
    return true; // Don't execute PHP's internal error handler
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.']);
    exit;
}

// ===== App config =====
$configFile = file_exists(__DIR__ . '/history/app-config.php')
    ? __DIR__ . '/history/app-config.php'
    : __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$projects = $config['projects'] ?? [];
$googleFormConfig = $config['google_form'] ?? [];

$reportDate = trim((string)($_POST['report_date'] ?? date('Y-m-d')));
$reportDay = DateTime::createFromFormat('!Y-m-d', $reportDate);
if (!$reportDay || $reportDay->format('Y-m-d') !== $reportDate) {
    echo json_encode(['success' => false, 'message' => 'Ngày báo cáo không hợp lệ']);
    exit;
}

$project = $_POST['project'] ?? ($config['default_project'] ?? 'JRR');
$projectConfig = $projects[$project] ?? null;
$sendGoogleChat = !empty($_POST['submit_google_chat']);
$sendSlack = !empty($_POST['submit_slack']);
$submitGoogleForm = !empty($_POST['submit_google_form']);
if (!$sendGoogleChat && !$sendSlack && !$submitGoogleForm) {
    echo json_encode(['success' => false, 'message' => 'Chọn ít nhất một mục trong Submit kèm']);
    exit;
}
$webhooks = [];
foreach (['google_chat' => $sendGoogleChat, 'slack' => $sendSlack] as $channel => $selected) {
    if (!$selected) continue;
    $label = $channel === 'slack' ? 'Slack' : 'Google Chat';
    $url = trim($projectConfig[$channel === 'slack' ? 'slack_webhook' : 'webhook'] ?? '');
    if (!$projectConfig || !$url) {
        echo json_encode(['success' => false, 'message' => "Project chưa cấu hình webhook $label"]);
        exit;
    }
    if ($channel === 'slack' && !preg_match('~^https://hooks\.slack\.com/services/[A-Za-z0-9/_-]+$~D', $url)) {
        echo json_encode(['success' => false, 'message' => 'Webhook Slack không hợp lệ']);
        exit;
    }
    $webhooks[$channel] = $url;
}
if ($submitGoogleForm && (empty($googleFormConfig['enabled']) || empty($googleFormConfig['url']))) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng bật và cấu hình RCNV logtime trong Thiết lập']);
    exit;
}

// ===== Parse tasks =====
function parseTasks($arr, $withType = false)
{
    $tasks = [];
    if (is_array($arr)) {
        foreach ($arr as $t) {
            if (!empty($t['content'])) {
                $content = trim((string)$t['content']);
                if (mb_strlen($content) > 500) {
                    continue;
                }
                $progress = isset($t['progress']) && $t['progress'] !== '' ? intval($t['progress']) : '';
                if ($progress !== '' && ($progress < 0 || $progress > 100)) {
                    $progress = '';
                }
                $estimate = trim($t['estimate'] ?? '');
                if ($estimate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $estimate)) {
                    $estimate = '';
                }
                if ($progress === 100) {
                    $estimate = '';
                }

                $task = [
                    'content' => $content,
                    'issue' => preg_match('/^#([A-Za-z0-9_-]+)(?:\s|$)/u', $content, $issueMatch) ? $issueMatch[1] : '',
                    'work_type' => in_array($t['work_type'] ?? '', ['Coding', 'Fix bug', 'Feature', 'Testing', 'Review', 'Research', 'Discussion'], true) ? $t['work_type'] : 'Coding',
                    'progress' => $progress,
                    'estimate' => $estimate
                ];
                if ($withType) {
                    $type = $t['type'] ?? 'new';
                    $task['type'] = $type === 'continue' ? 'continue' : 'new';
                }

                $tasks[] = $task;
            }
        }
    }
    return $tasks;
}

$tasks_today = parseTasks($_POST['tasks_today'] ?? []);
$tasks_tomorrow = parseTasks($_POST['tasks_tomorrow'] ?? [], true);
$quality = intval($_POST['quality'] ?? 3); // default 3
$spirit = intval($_POST['spirit'] ?? 3);   // default 3
$reporter = mb_substr(trim($config['reporter'] ?? ''), 0, 100);
$dailyResult = mb_substr(trim($_POST['daily_result'] ?? ''), 0, 2000);
if ($sendSlack && $reporter === '') {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập và lưu người báo cáo trong Thiết lập']);
    exit;
}
$note = mb_substr(trim($_POST['note'] ?? ''), 0, 2000);

if (($sendGoogleChat || $sendSlack) && count($tasks_today) == 0) {
    echo json_encode(['success' => false, 'message' => 'Cần ít nhất 1 task hôm nay']);
    exit;
}

foreach (($sendGoogleChat || $sendSlack) ? $tasks_today : [] as $task) {
    if ($task['progress'] !== '' && $task['progress'] < 100 && $task['estimate'] === '') {
        echo json_encode(['success' => false, 'message' => 'Task hôm nay chưa đạt 100% phải có ngày dự kiến']);
        exit;
    }
    if ($task['estimate'] !== '' && $task['estimate'] < $reportDate) {
        echo json_encode(['success' => false, 'message' => 'Ngày dự kiến hoàn thành không được trước ngày báo cáo']);
        exit;
    }
}


// ===== Map quality and spirit with icons =====
$qualityMap = [
    1 => '❌ Kém – Không hoàn thành',
    2 => '⚠️ Trung bình – Hoàn thành nhưng còn lỗi',
    3 => '✅ Khá – Hoàn thành đúng yêu cầu',
    4 => '🌟 Tốt – Hoàn thành vượt yêu cầu',
    5 => '🏆 Xuất sắc – Kết quả nổi bật'
];
$qualityText = $qualityMap[$quality] ?? '✅ Khá – Hoàn thành đúng yêu cầu';

$spiritMap = [
    1 => '🪫 Cạn pin',
    2 => '☕ Cần cà phê',
    3 => '🌤️ Ổn định',
    4 => '⚡ Đầy năng lượng',
    5 => '🚀 Bứt phá'
];
$spiritText = $spiritMap[$spirit] ?? '🌤️ Ổn định';

// ===== Format tasks =====
function formatTasks($tasks, $showType = false)
{
    $out = [];
    $typeLabels = [
        'new' => '[New]',
        'continue' => '[Continue]',
    ];
    foreach ($tasks as $t) {
        $prefix = '';
        if ($showType && !empty($t['type'])) {
            $prefix = ($typeLabels[$t['type']] ?? '[New]') . ' ';
        }

        $content = htmlspecialchars($t['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $workType = htmlspecialchars($t['work_type'] ?? 'Coding', ENT_QUOTES, 'UTF-8');
        $line = "- {$prefix}" . (!$showType ? "[{$workType}] " : '') . $content;
        if ($t['progress'] !== '') $line .= " (<b style='color:yellow'>{$t['progress']}%</b>)";
        if ($t['estimate']) $line .= " - Dự kiến: {$t['estimate']}";
        $out[] = $line;
    }
    return implode("<br>", $out);
}


// Set timezone to GMT+7 (Asia/Ho_Chi_Minh)
date_default_timezone_set('Asia/Ho_Chi_Minh');
$todayStr = formatTasks($tasks_today);
$tomorrowStr = formatTasks($tasks_tomorrow, true);
// Format date with weekday, day/month/year hour:minute
$date = $reportDay->format('l, d/m/Y') . ' ' . date('H:i');
$avatar = $projectConfig['avatar'] ?: 'https://www.jrr.jp/wp-content/uploads/2026/04/favicon.png';



// ===== Prepare Google Chat cardV2 payload with icons and order =====
$cardV2 = [
    "cardsV2" => [[
        "card" => [
            "header" => [
                "title"     => "📋 Daily Report - $project",
                "subtitle"  => $date . ' (GMT+7)',
                "imageUrl"  => $avatar,
                "imageType" => "CIRCLE",
            ],
            "sections" => [
                [
                    "widgets" => [
                        [
                            "textParagraph" => [
                                "text" => "<b>📝 Task hôm nay:</b><br>" . (nl2br($todayStr))
                            ]
                        ]
                    ]
                ],
                ($tomorrowStr ? [
                    "widgets" => [
                        [
                            "textParagraph" => [
                                "text" => "<b>📅 Task ngày mai:</b><br>" . (nl2br($tomorrowStr))
                            ]
                        ]
                    ]
                ] : null),
                [
                    "widgets" => [
                        [
                            "decoratedText" => [
                                "topLabel" => "Chất lượng (Performance)",
                                "text" => $qualityText
                            ]
                        ],
                        [
                            "decoratedText" => [
                                "topLabel" => "Tinh thần",
                                "text" => $spiritText
                            ]
                        ]
                    ]
                ],
                ($note ? [
                    "widgets" => [
                        [
                            "textParagraph" => [
                                "text" => "<b>🗒️ Note:</b> " . nl2br(htmlspecialchars($note))
                            ]
                        ]
                    ]
                ] : null)
            ]
        ]
    ]]
];
// Remove null sections (if no tomorrow/note)
$cardV2["cardsV2"][0]["card"]["sections"] = array_values(array_filter($cardV2["cardsV2"][0]["card"]["sections"]));

// Slack uses mrkdwn rather than Google Chat card HTML.
$slackText = '';
if ($sendSlack) {
    require_once __DIR__ . '/slack-report.php';
    $slackText = formatSlackReport($reporter, $reportDay->format('Y/m/d'), $tasks_today, $dailyResult, $quality, $spirit, $note);
    if (mb_strlen($slackText) > 39000) {
        echo json_encode(['success' => false, 'message' => 'Báo cáo Slack quá dài. Vui lòng giảm nội dung.']);
        exit;
    }
}

// Send every selected channel and retain each result independently.
$statuses = ['logtime' => 'skipped', 'google_chat' => 'skipped', 'slack' => 'skipped'];
foreach ($webhooks as $channel => $webhook) {
    $payload = json_encode($channel === 'slack' ? ['text' => $slackText, 'unfurl_links' => false, 'unfurl_media' => false] : $cardV2, JSON_UNESCAPED_UNICODE);
    $ch = curl_init($webhook);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = $resp !== false && $httpcode >= 200 && $httpcode < 300 && ($channel !== 'slack' || trim($resp) === 'ok');
    $statuses[$channel] = $ok ? 'ok' : 'failed';
    writeLog("$channel HTTP: $httpcode; status: {$statuses[$channel]}");
}

// ===== Send Google Form (conditional) =====
$formOk = true; // default to true if skipped
$formHttpCode = 0;

if ($submitGoogleForm && !empty($googleFormConfig['url'])) {
    writeLog("=== SEND GOOGLE FORM ===");
    $now = clone $reportDay;

    // Build task summary for form fields
    $formTaskToday = $todayStr ?: 'N/A';
    $formTaskTomorrow = $tomorrowStr ?: 'Chưa có kế hoạch';
    $formQuality = $qualityText;
    $formSpirit = $spiritText;
    $formNote = $note ?: 'Không có ghi chú';

    $formData = [
        'entry.1568765630' => $googleFormConfig['email'] ?? '',
        'entry.603488309' => $googleFormConfig['department'] ?? '',
        'entry.90373077_hour' => $googleFormConfig['start_hour'] ?? '08',
        'entry.90373077_minute' => $googleFormConfig['start_minute'] ?? '00',
        'entry.1722653064_hour' => $googleFormConfig['end_hour'] ?? '17',
        'entry.1722653064_minute' => $googleFormConfig['end_minute'] ?? '00',
        'entry.1510561303' => 'NoBody',
        'entry.1748841788' => '↓↓↓↓↓',
        'entry.1562962609' => 'Working',
        'entry.616500863' => 'Nothing',

        // DATE
        'entry.2095511431_year'  => $now->format('Y'),
        'entry.2095511431_month' => (int)$now->format('m'),
        'entry.2095511431_day'   => (int)$now->format('d'),

        'entry.116047374' => $googleFormConfig['report_note'] ?? '',

        'fvv' => '1',
        'pageHistory' => '0',
        'fbzx' => rand(1000000000, 9999999999),
    ];
    $formUrl = $googleFormConfig['url'];
    writeLog("Payload: " . json_encode($formData));

    $chForm = curl_init($formUrl);
    curl_setopt_array($chForm, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($formData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; DailyReport/1.0)',
    ]);
    $formResp = curl_exec($chForm);
    $formHttpCode = curl_getinfo($chForm, CURLINFO_HTTP_CODE);
    $formError = curl_error($chForm);
    curl_close($chForm);

    writeLog("Google Form HTTP: $formHttpCode");
    if ($formError) writeLog("Google Form cURL Error: $formError");
    if ($formHttpCode == 200 || $formHttpCode == 302) {
        writeLog("✔ Google Form submitted successfully");
    } else {
        writeLog("❌ Google Form failed (HTTP $formHttpCode)");
    }
    $formOk = ($formHttpCode == 200 || $formHttpCode == 302);
} else {
    writeLog("=== SKIP GOOGLE FORM (user opted out) ===");
}

$statuses['logtime'] = $submitGoogleForm ? ($formOk ? 'ok' : 'failed') : 'skipped';
$anyOk = in_array('ok', $statuses, true);

// ===== Save history to file =====
$msg = "[Daily Report] {$project} - {$date} (GMT+7)\n";
$msg .= "\n*📝 Task hôm nay:*\n$todayStr\n";
if ($tomorrowStr) $msg .= "\n*📅 Task ngày mai:*\n$tomorrowStr\n";
$msg .= "\n*Chất lượng:* $qualityText\n*Tinh thần:* $spiritText\n";
if ($note) $msg .= "\n*🗒️ Note:* $note\n";

if ($statuses['slack'] === 'ok') $msg = "[Daily Report] {$project} - {$date} (GMT+7) [Slack]\n" . $slackText;

$historyDir = __DIR__ . '/history';
if (!is_dir($historyDir)) {
    // Attempt to create history directory
    if (!mkdir($historyDir, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'Không tạo được thư mục history. Vui lòng kiểm tra quyền ghi thư mục!']);
        exit;
    }
}
$historyFile = $historyDir . '/' . $reportDate . '.log';
if ($anyOk && @file_put_contents($historyFile, $msg . "\n---\n", FILE_APPEND | LOCK_EX) === false) {
    echo json_encode(['success' => false, 'message' => 'Không ghi được file lịch sử. Vui lòng kiểm tra quyền ghi thư mục history!']);
    exit;
}

// ===== Response =====
writeLog("=== DONE ===");
$success = !in_array('failed', $statuses, true);
echo json_encode([
    'success' => $success,
    'partial' => !$success && $anyOk,
    'statuses' => $statuses,
    'message' => $success ? 'Đã gửi các mục đã chọn' : 'Có mục gửi thất bại',
]);

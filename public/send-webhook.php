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
const TASK_STATUSES = ['Chưa bắt đầu', 'Đang thực hiện', 'Chờ review', 'Tạm hoãn', 'Hoàn thành'];
const DEFAULT_TASK_STATUS = 'Đang thực hiện';

// The leading [..] groups of a task title hold its issue/feature reference.
function splitIssueFromTitle($value)
{
    $rest = trim((string)$value);
    $parts = [];
    while (preg_match('/^\[([^\]]*)\]\s*/u', $rest, $matches)) {
        $label = trim($matches[1]);
        if ($label !== '') {
            $parts[] = $label;
        }
        $rest = mb_substr($rest, mb_strlen($matches[0]));
    }

    $rest = trim($rest);
    if ($rest === '') {
        return ['issue' => '', 'content' => trim((string)$value)];
    }

    return ['issue' => mb_substr(implode(' ', $parts), 0, 50), 'content' => $rest];
}

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
                // Issue now has its own field; the bracket prefix stays supported for legacy drafts.
                $split = splitIssueFromTitle($content);
                $issueNo = mb_substr(trim((string)($t['issue_no'] ?? '')), 0, 50);
                if ($issueNo !== '') {
                    $split['issue'] = $issueNo;
                }
                $workType = mb_substr(trim((string)($t['work_type'] ?? '')), 0, 50);
                $status = trim((string)($t['status'] ?? ''));
                if (!in_array($status, TASK_STATUSES, true)) {
                    $status = DEFAULT_TASK_STATUS;
                }

                $task = [
                    'content' => $split['content'],
                    'issue_no' => $split['issue'],
                    'work_type' => $workType !== '' ? $workType : 'Coding',
                    'status' => $status,
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
    1 => '😣 Rất không tốt',
    2 => '😕 Không tốt',
    3 => '😐 Bình thường',
    4 => '🙂 Tốt',
    5 => '😄 Rất tốt'
];
$spiritText = $spiritMap[$spirit] ?? '😐 Bình thường';

// ===== Format tasks =====
function formatTasks($tasks, $showType = false)
{
    $out = [];
    $escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    foreach ($tasks as $t) {
        // Task nhiều dòng: dòng sau thụt vào cho thẳng hàng với nội dung bullet.
        $content = str_replace("\n", "<br>&nbsp;&nbsp;", str_replace("\r\n", "\n", $escape($t['content'])));

        if ($showType) {
            $type = ($t['type'] ?? 'new') === 'continue' ? 'Continue' : 'New';
            $out[] = "- <b>[{$type}]</b> {$content}";
            continue;
        }

        $issueNo = $escape($t['issue_no'] ?? '');
        $issuePrefix = $issueNo !== '' ? "<b>[{$issueNo}]</b> " : '';
        // Phần bổ nghĩa gom vào một cụm xám để mắt bám vào nội dung task trước.
        $meta = [$escape($t['work_type'] ?? 'Coding'), $escape($t['status'] ?? DEFAULT_TASK_STATUS)];
        $meta[] = $t['progress'] === '' ? 'chưa cập nhật' : $t['progress'] . '%';
        if (!empty($t['estimate'])) {
            $meta[] = 'dự kiến ' . date('d/m', strtotime($t['estimate']));
        }
        $out[] = "- {$issuePrefix}{$content} <font color=\"#80868b\">· " . implode(' · ', $meta) . "</font>";
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
$cardSubtitle = $date . ' (GMT+7)' . ($reporter !== '' ? ' · ' . $reporter : '');
$cardV2 = [
    "cardsV2" => [[
        "card" => [
            "header" => [
                "title"     => "📋 Daily Report · $project",
                "subtitle"  => $cardSubtitle,
                "imageUrl"  => $avatar,
                "imageType" => "CIRCLE",
            ],
            "sections" => [
                [
                    "header" => "📝 Task hôm nay",
                    "widgets" => [["textParagraph" => ["text" => $todayStr]]]
                ],
                ($tomorrowStr ? [
                    "header" => "📅 Kế hoạch tiếp theo",
                    "widgets" => [["textParagraph" => ["text" => $tomorrowStr]]]
                ] : null),
                ($dailyResult ? [
                    "header" => "🎯 Kết quả hôm nay",
                    "widgets" => [["textParagraph" => ["text" => nl2br(htmlspecialchars($dailyResult, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))]]]
                ] : null),
                [
                    "widgets" => [
                        ["decoratedText" => ["topLabel" => "Chất lượng", "text" => $qualityText]],
                        ["decoratedText" => ["topLabel" => "Tinh thần", "text" => $spiritText]]
                    ]
                ],
                ($note ? [
                    "header" => "🗒️ Chia sẻ thêm",
                    "widgets" => [["textParagraph" => ["text" => nl2br(htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))]]]
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
// Bản .log giữ nguyên dạng cũ để các record trước đây vẫn đọc được. Bản Google Chat
// luôn được ghi vì nó là bản duy nhất chứa đủ mọi phần (Slack không gửi kế hoạch tiếp theo).
$msg = "[Daily Report] {$project} - {$date} (GMT+7)\n";
$msg .= "\n*📝 Task hôm nay:*\n$todayStr\n";
if ($tomorrowStr) $msg .= "\n*📅 Kế hoạch tiếp theo:*\n$tomorrowStr\n";
if ($dailyResult) $msg .= "\n*🎯 Kết quả hôm nay:* $dailyResult\n";
$msg .= "\n*Chất lượng:* $qualityText\n*Tinh thần:* $spiritText\n";
if ($note) $msg .= "\n*🗒️ Note:* $note\n";

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

// Bản JSON là nguồn dữ liệu chính của trang lịch sử: giữ nguyên từng trường thay vì
// phải parse ngược từ message đã gửi đi.
if ($anyOk) {
    $record = [
        'sent_at' => $date,
        'report_date' => $reportDate,
        'project' => $project,
        'reporter' => $reporter,
        'channels' => $statuses,
        'quality' => $quality,
        'quality_text' => $qualityText,
        'spirit' => $spirit,
        'spirit_text' => $spiritText,
        'daily_result' => $dailyResult,
        'note' => $note,
        'tasks_today' => $tasks_today,
        'tasks_next' => $tasks_tomorrow,
        'messages' => [
            'google_chat' => $msg,
            'slack' => $slackText,
        ],
    ];
    $jsonFile = $historyDir . '/' . $reportDate . '.json';
    $existing = [];
    if (is_file($jsonFile)) {
        $decoded = json_decode((string)file_get_contents($jsonFile), true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }
    $existing[] = $record;
    @file_put_contents($jsonFile, json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
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

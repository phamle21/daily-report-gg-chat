<?php

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

// ===== App config =====
$configFile = file_exists(__DIR__ . '/history/app-config.php')
    ? __DIR__ . '/history/app-config.php'
    : __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$projects = $config['projects'] ?? [];
$googleFormConfig = $config['google_form'] ?? [];

$project = $_POST['project'] ?? ($config['default_project'] ?? 'JRR');
$projectConfig = $projects[$project] ?? null;
$webhook = trim($projectConfig['webhook'] ?? '');
if (!$projectConfig || !$webhook) {
    echo json_encode(['success' => false, 'message' => 'Project không hợp lệ']);
    exit;
}

// ===== Parse tasks =====
function parseTasks($arr)
{
    $tasks = [];
    if (is_array($arr)) {
        foreach ($arr as $t) {
            if (!empty($t['content'])) {
                $tasks[] = [
                    'content' => trim($t['content']),
                    'progress' => isset($t['progress']) ? intval($t['progress']) : '',
                    'estimate' => $t['estimate'] ?? ''
                ];
            }
        }
    }
    return $tasks;
}

$tasks_today = parseTasks($_POST['tasks_today'] ?? []);
$tasks_tomorrow = parseTasks($_POST['tasks_tomorrow'] ?? []);
$quality = intval($_POST['quality'] ?? 3); // default 3
$spirit = intval($_POST['spirit'] ?? 3);   // default 3
$note = trim($_POST['note'] ?? '');

if (count($tasks_today) == 0) {
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
    1 => '😵‍💫 Kém',
    2 => '🤒 Trung bình',
    3 => '😊 Khá',
    4 => '😃👍Tốt',
    5 => '🤩 Rất tốt 🔥'
];
$spiritText = $spiritMap[$spirit] ?? '🙂 Khá';

// ===== Format tasks =====
function formatTasks($tasks)
{
    $out = [];
    foreach ($tasks as $t) {
        $line = "- {$t['content']}";
        if ($t['progress']) $line .= " (<b style='color:yellow'>{$t['progress']}%</b>)";
        if ($t['estimate']) $line .= " - Dự kiến: {$t['estimate']}";
        $out[] = $line;
    }
    return implode("<br>", $out);
}


// Set timezone to GMT+7 (Asia/Ho_Chi_Minh)
date_default_timezone_set('Asia/Ho_Chi_Minh');
$todayStr = formatTasks($tasks_today);
$tomorrowStr = formatTasks($tasks_tomorrow);
// Format date with weekday, day/month/year hour:minute
$date = date('l, d/m/Y H:i');
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

// Send to Google Chat using cURL
writeLog("=== SEND GOOGLE CHAT ===");
writeLog("Project: $project");
$payload = json_encode($cardV2, JSON_UNESCAPED_UNICODE);
$ch = curl_init($webhook);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

writeLog("Google Chat HTTP: $httpcode");
if ($curl_error) writeLog("Google Chat cURL Error: $curl_error");
if ($httpcode >= 200 && $httpcode < 300) {
    writeLog("✔ Google Chat sent successfully");
} else {
    writeLog("❌ Google Chat failed - Response: $resp");
}


// ===== Send Google Form (conditional) =====
$submitGoogleForm = !empty($_POST['submit_google_form']) && !empty($googleFormConfig['enabled']);
$formOk = true; // default to true if skipped
$formHttpCode = 0;

if ($submitGoogleForm && !empty($googleFormConfig['url'])) {
    writeLog("=== SEND GOOGLE FORM ===");
    $now = new DateTime();

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
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
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

// ===== Save history to file =====
$msg = "[Daily Report] {$project} - {$date} (GMT+7)\n";
$msg .= "\n*📝 Task hôm nay:*\n$todayStr\n";
if ($tomorrowStr) $msg .= "\n*📅 Task ngày mai:*\n$tomorrowStr\n";
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
$historyFile = $historyDir . '/' . date('Y-m-d') . '.log';
if (@file_put_contents($historyFile, $msg . "\n---\n", FILE_APPEND) === false) {
    echo json_encode(['success' => false, 'message' => 'Không ghi được file lịch sử. Vui lòng kiểm tra quyền ghi thư mục history!']);
    exit;
}

// ===== Response =====
writeLog("=== DONE ===");
$chatOk = ($httpcode >= 200 && $httpcode < 300);
$formOk = !$submitGoogleForm || ($formHttpCode == 200 || $formHttpCode == 302);

if ($chatOk) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gửi Google Chat thất bại',
        'httpcode' => $httpcode,
        'curl_error' => $curl_error,
        'response' => $resp,
        'form_status' => $formOk ? 'ok' : 'failed (HTTP ' . $formHttpCode . ')'
    ]);
}

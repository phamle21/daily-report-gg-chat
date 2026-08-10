<?php

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php-errors.log');

function respond($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function writeGoogleFormLog($message)
{
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    file_put_contents($logDir . '/' . date('Y-m-d') . '.log', $line, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request');
}

$configFile = file_exists(__DIR__ . '/history/app-config.php')
    ? __DIR__ . '/history/app-config.php'
    : __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$googleForm = $config['google_form'] ?? [];

if (empty($googleForm['enabled'])) {
    respond(false, 'Google Form chưa được bật trong phần thiết lập');
}

$formUrl = trim($googleForm['url'] ?? '');
if ($formUrl === '') {
    respond(false, 'Chưa cấu hình Google Form response URL');
}

$dateValue = trim($_POST['date'] ?? '');
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
$dateErrors = DateTimeImmutable::getLastErrors();
if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $dateValue) {
    respond(false, 'Ngày submit không hợp lệ');
}

$formData = [
    'entry.1568765630' => $googleForm['email'] ?? '',
    'entry.603488309' => $googleForm['department'] ?? '',
    'entry.90373077_hour' => $googleForm['start_hour'] ?? '08',
    'entry.90373077_minute' => $googleForm['start_minute'] ?? '00',
    'entry.1722653064_hour' => $googleForm['end_hour'] ?? '17',
    'entry.1722653064_minute' => $googleForm['end_minute'] ?? '00',
    'entry.1510561303' => 'NoBody',
    'entry.1748841788' => '↓↓↓↓↓',
    'entry.1562962609' => 'Working',
    'entry.616500863' => 'Nothing',
    'entry.2095511431_year' => $date->format('Y'),
    'entry.2095511431_month' => (int)$date->format('m'),
    'entry.2095511431_day' => (int)$date->format('d'),
    'entry.116047374' => $googleForm['report_note'] ?? '',
    'fvv' => '1',
    'pageHistory' => '0',
    'fbzx' => random_int(1000000000, 9999999999),
];

writeGoogleFormLog("=== SUBMIT GOOGLE FORM ONLY: {$dateValue} ===");

$ch = curl_init($formUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($formData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; DailyReport/1.0)',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$success = in_array($httpCode, [200, 302], true);
writeGoogleFormLog(sprintf(
    'Google Form date %s: HTTP %d%s',
    $dateValue,
    $httpCode,
    $curlError ? " - cURL error: {$curlError}" : ''
));

if (!$success) {
    respond(false, "Submit ngày {$date->format('d/m/Y')} thất bại", [
        'date' => $dateValue,
        'httpcode' => $httpCode,
        'curl_error' => $curlError,
    ]);
}

respond(true, "Đã submit ngày {$date->format('d/m/Y')}", ['date' => $dateValue]);

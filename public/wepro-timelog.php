<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

function fail($message, $code = 200)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request');
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
    fail('Phiên làm việc không hợp lệ. Vui lòng tải lại trang.', 403);
}

$configFile = file_exists(__DIR__ . '/history/app-config.php')
    ? __DIR__ . '/history/app-config.php'
    : __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$wepro = $config['wepro'] ?? [];

if (empty($wepro['enabled'])) {
    fail('Vui lòng bật và cấu hình WePRO trong Thiết lập');
}

$baseUrl = rtrim((string)($wepro['base_url'] ?? ''), '/');
$cookie = trim((string)($wepro['remember_cookie'] ?? ''));
if ($baseUrl === '' || $cookie === '') {
    fail('Thiếu Base URL hoặc cookie remember trong Thiết lập WePRO');
}

$action = (string)($_POST['action'] ?? '');
$taskIds = array_values(array_filter(array_map('intval', (array)($_POST['task_ids'] ?? [])), fn ($id) => $id > 0));
if (!$taskIds) {
    fail('Thiếu task id WePRO.');
}

// The remember cookie logs us in; the jar then carries the session + XSRF cookies into the POST,
// where Laravel checks the _token scraped from the form against that same session.
$jar = tempnam(sys_get_temp_dir(), 'wepro');

function weproRequest($url, $opts, $wepro, $cookie, $jar)
{
    $ch = curl_init($url);
    $base = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIE => $cookie,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; DailyReport/1.0)',
    ];
    if (($wepro['basic_user'] ?? '') !== '') {
        $base[CURLOPT_USERPWD] = $wepro['basic_user'] . ':' . ($wepro['basic_pass'] ?? '');
        $base[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
    }
    curl_setopt_array($ch, $base + $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$body, $code, $err];
}

$firstId = $taskIds[0];
[$body, $code, $err] = weproRequest(
    $baseUrl . '/account/timelogs/create-timelog-simple/' . $firstId,
    [CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Requested-With: XMLHttpRequest']],
    $wepro, $cookie, $jar
);
if ($body === false) {
    @unlink($jar);
    fail('Không gọi được WePRO: ' . $err);
}
if ($code === 401) {
    @unlink($jar);
    fail('WePRO trả về 401: cookie remember sai hoặc đã hết hạn.');
}
$page = json_decode($body, true);
$html = (string)($page['html'] ?? '');
if ($code !== 200 || $html === '') {
    @unlink($jar);
    fail('WePRO không trả form log giờ (HTTP ' . $code . '). Cookie remember có thể đã hết hạn.');
}

$token = preg_match('/name="_token"[^>]*value="([^"]+)"/', $html, $m) ? $m[1] : '';
$userId = preg_match('/name="user_id"[^>]*value="(\d+)"/', $html, $m) ? $m[1] : '';
$projectId = preg_match('/name="project_id_check\[\]"[^>]*value="(\d+)"/', $html, $m) ? $m[1] : '';
$activities = [];
if (preg_match('/<select[^>]*activity_1[^>]*>(.*?)<\/select>/s', $html, $m)
    && preg_match_all('/<option[^>]*value="([^"]*)"[^>]*>([^<]*)</', $m[1], $o, PREG_SET_ORDER)) {
    foreach ($o as $opt) {
        $activities[] = ['value' => $opt[1], 'label' => trim(html_entity_decode($opt[2], ENT_QUOTES, 'UTF-8'), " \t\n[]")];
    }
}
if ($token === '' || $userId === '') {
    @unlink($jar);
    fail('Không đọc được token/user từ form WePRO. Có thể WePRO đã đổi giao diện.');
}

if ($action === 'form') {
    @unlink($jar);
    echo json_encode(['success' => true, 'user_id' => (int)$userId, 'project_id' => (int)$projectId, 'activities' => $activities]);
    exit;
}
if ($action !== 'store') {
    @unlink($jar);
    fail('Action không hợp lệ.');
}

$date = (string)($_POST['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fail('Ngày log không hợp lệ.');
}
$memos = (array)($_POST['memos'] ?? []);
$hours = (array)($_POST['hours'] ?? []);
$acts = (array)($_POST['activities'] ?? []);

$fields = [
    'f_email' => '', 'f_slack_username' => '', 'redirect_url' => '',
    '_token' => $token, 'user_id' => $userId, 'start_date' => $date,
];
foreach ($taskIds as $i => $id) {
    $h = (float)($hours[$i] ?? 0);
    if ($h <= 0) {
        @unlink($jar);
        fail('Task ' . $id . ' chưa có số giờ.');
    }
    $fields["project_id_check[$i]"] = $projectId;
    $fields["task_id[$i]"] = $id;
    $fields["custom_fields_data[activity_1][$i]"] = (string)($acts[$i] ?? '4');
    $fields["memo[$i]"] = (string)($memos[$i] ?? '');
    $fields["wp_task_time[$i]"] = rtrim(rtrim(number_format($h, 2, '.', ''), '0'), '.');
}

[$body, $code, $err] = weproRequest(
    $baseUrl . '/account/timelogs/multi_store_simple',
    [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'X-Requested-With: XMLHttpRequest',
            'Origin: ' . $baseUrl,
            'Referer: ' . $baseUrl . '/account/timelogs/create-timelog-simple/' . $firstId,
        ],
    ],
    $wepro, $cookie, $jar
);
@unlink($jar);

if ($body === false) {
    fail('Không gửi được log lên WePRO: ' . $err);
}
$res = json_decode($body, true);
if ($code !== 200 || !is_array($res)) {
    fail('WePRO trả về HTTP ' . $code . ' khi lưu log.');
}
if (($res['status'] ?? '') !== 'success') {
    $msg = (string)($res['message'] ?? '');
    if (!empty($res['errors']) && is_array($res['errors'])) {
        $msg .= ' ' . implode(' ', array_map(fn ($e) => implode(' ', (array)$e), $res['errors']));
    }
    fail('WePRO từ chối: ' . trim($msg));
}
echo json_encode(['success' => true, 'message' => (string)($res['message'] ?? 'Đã log giờ.'), 'count' => count($taskIds)]);

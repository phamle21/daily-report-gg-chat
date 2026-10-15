<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/partials/wepro-browser.php';

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
$projectName = trim((string)($_POST['project'] ?? ''));
$projectId = trim((string)($config['projects'][$projectName]['wepro_project_id'] ?? ''));
if ($projectId === '') {
    fail('Project “' . $projectName . '” chưa có WePRO Project ID.');
}

// Same dance as the timelog: the create form carries the _token bound to the session the
// remember cookie opens, so the jar must carry that session into every POST.
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
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0',
    ];
    if (($wepro['basic_user'] ?? '') !== '') {
        $base[CURLOPT_USERPWD] = $wepro['basic_user'] . ':' . ($wepro['basic_pass'] ?? '');
        $base[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
    }
    curl_setopt_array($ch, weproBrowserOpts($base + $opts, rtrim((string)($wepro['base_url'] ?? ''), '/')));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$body, $code, $err];
}

function selectOptions($html, $name)
{
    if (!preg_match('/<select[^>]*name="' . preg_quote($name, '/') . '"[^>]*>(.*?)<\/select>/s', $html, $m)) {
        return [];
    }
    preg_match_all('/<option[^>]*value="([^"]*)"[^>]*>([^<]*)</', $m[1], $o, PREG_SET_ORDER);
    $out = [];
    foreach ($o as $opt) {
        $label = trim(html_entity_decode($opt[2], ENT_QUOTES, 'UTF-8'));
        if ($opt[1] === '' && ($label === '--' || $label === '')) {
            continue;
        }
        $out[] = ['value' => $opt[1], 'label' => $label];
    }
    return $out;
}

$createUrl = $baseUrl . '/account/tasks/create?task_project_id=' . rawurlencode($projectId);
[$body, $code, $err] = weproRequest($createUrl, [CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Requested-With: XMLHttpRequest']], $wepro, $cookie, $jar);
if ($body === false) {
    @unlink($jar);
    fail('Không gọi được WePRO: ' . $err);
}
if ($code === 401) {
    @unlink($jar);
    fail('WePRO trả về 401: cookie remember sai hoặc đã hết hạn.');
}
$page = json_decode($body, true);
$html = is_array($page) && isset($page['html']) ? (string)$page['html'] : (string)$body;
unset($body);
if ($code !== 200 || $html === '') {
    @unlink($jar);
    fail('WePRO không trả form tạo task (HTTP ' . $code . ').');
}

$token = preg_match('/name="_token"[^>]*value="([^"]+)"/', $html, $m) ? $m[1] : '';
$me = preg_match("/#selectAssignee'\)\.val\('(\d+)'\)/", $html, $m) ? (int)$m[1] : 0;
$projectLabel = '';
if (preg_match('/<input[^>]*name="projectName"[^>]*>/', $html, $m) && preg_match('/value="([^"]*)"/', $m[0], $v)) {
    $projectLabel = html_entity_decode($v[1], ENT_QUOTES, 'UTF-8');
}
$statuses = selectOptions($html, 'custom_fields_data[status_2]');
$priorities = selectOptions($html, 'priority');
$sprints = selectOptions($html, 'sprint_id');
$categories = selectOptions($html, 'category_id');
unset($html);
if ($token === '') {
    @unlink($jar);
    fail('Không đọc được token từ form WePRO. Có thể WePRO đã đổi giao diện.');
}

$action = (string)($_POST['action'] ?? '');
if ($action === 'form') {
    @unlink($jar);
    echo json_encode([
        'success' => true,
        'me' => $me,
        'project_id' => (int)$projectId,
        'project_label' => $projectLabel,
        'statuses' => $statuses,
        'priorities' => $priorities,
        'sprints' => $sprints,
        'categories' => $categories,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action !== 'store') {
    @unlink($jar);
    fail('Action không hợp lệ.');
}

$items = $_POST['tasks'] ?? [];
if (!is_array($items) || !$items) {
    @unlink($jar);
    fail('Chưa có task nào để tạo.');
}
$common = [
    'parent_id' => (int)($_POST['parent_id'] ?? 0),
    'priority' => (string)($_POST['priority'] ?? 'medium'),
    'status' => (string)($_POST['status'] ?? ''),
    'sprint_id' => (string)($_POST['sprint_id'] ?? ''),
    'start_date' => (string)($_POST['start_date'] ?? date('Y-m-d')),
    'due_date' => (string)($_POST['due_date'] ?? date('Y-m-d')),
    'user_id' => (int)($_POST['user_id'] ?? $me),
];
foreach (['start_date', 'due_date'] as $k) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $common[$k])) {
        @unlink($jar);
        fail('Ngày không hợp lệ.');
    }
}

$results = [];
foreach (array_values($items) as $i => $item) {
    $heading = trim((string)($item['heading'] ?? ''));
    if ($heading === '') {
        $results[] = ['index' => $i, 'success' => false, 'message' => 'Thiếu tên task'];
        continue;
    }
    $en = trim((string)($item['en_name'] ?? ''));
    $fields = [
        'f_email' => '', 'f_slack_username' => '', 'redirect_url' => '',
        '_token' => $token,
        'project_id' => $projectId,
        'task_project_id' => $projectId,
        'projectName' => $projectLabel,
        'parent_id' => $common['parent_id'] > 0 ? (string)$common['parent_id'] : '',
        'category_id' => '',
        'heading' => $heading,
        'en_name' => $en,
        'priority' => $common['priority'],
        'storypoint' => (string)max(0, (int)($item['storypoint'] ?? 1)),
        'start_date' => $common['start_date'],
        'due_date' => $common['due_date'],
        'user_id[0]' => (string)$common['user_id'],
        'description' => '<p><br></p>',
        'note' => '<p><br></p>',
        'client_id' => '', 'milestone_id' => '', 'sprint_id' => $common['sprint_id'], 'pcl' => '',
        'tester_date' => '', 'client_test_date' => '', 'tester_tested_date' => '', 'client_tested_date' => '',
        'estimate_hours' => (string)max(0, (int)($item['estimate_hours'] ?? 0)),
        'estimate_minutes' => '0',
        'mention_user_ids' => '',
        'custom_fields_data[status_2]' => $common['status'],
    ];
    [$body, $code, $err] = weproRequest(
        $baseUrl . '/account/tasks?task_template_id=&taskId=',
        [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/javascript, */*; q=0.01',
                'X-Requested-With: XMLHttpRequest',
                'Origin: ' . $baseUrl,
                'Referer: ' . $createUrl,
            ],
        ],
        $wepro, $cookie, $jar
    );
    $res = $body !== false ? json_decode($body, true) : null;
    if (is_array($res) && ($res['status'] ?? '') === 'success' && !empty($res['taskID'])) {
        $id = (int)$res['taskID'];
        $results[] = [
            'index' => $i, 'success' => true, 'id' => $id, 'heading' => $heading,
            'url' => $baseUrl . '/account/tasks/' . $id,
            'timelog_url' => $baseUrl . '/account/timelogs/create-timelog-simple/' . $id,
        ];
        continue;
    }
    $msg = $body === false ? $err : (is_array($res) ? (string)($res['message'] ?? '') : 'HTTP ' . $code);
    if (is_array($res) && !empty($res['errors']) && is_array($res['errors'])) {
        $msg .= ' ' . implode(' ', array_map(fn ($e) => implode(' ', (array)$e), $res['errors']));
    }
    $results[] = ['index' => $i, 'success' => false, 'heading' => $heading, 'message' => trim($msg) ?: 'WePRO từ chối'];
}
@unlink($jar);

$created = count(array_filter($results, fn ($r) => $r['success']));
echo json_encode(['success' => $created > 0, 'created' => $created, 'total' => count($results), 'results' => $results], JSON_UNESCAPED_UNICODE);

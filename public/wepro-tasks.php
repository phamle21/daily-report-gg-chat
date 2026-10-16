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

// Auth is shared; the WePRO project id belongs to the report project being written.
$projectName = trim((string)($_POST['project'] ?? ''));
$projectId = trim((string)($config['projects'][$projectName]['wepro_project_id'] ?? ''));

if ($baseUrl === '' || $cookie === '') {
    fail('Thiếu Base URL hoặc cookie remember trong Thiết lập WePRO');
}
if ($projectId === '') {
    fail('Project “' . $projectName . '” chưa có WePRO Project ID. Thiết lập → Danh sách project → điền WePRO Project ID.');
}

// DataTables server-side endpoint: same page URL, JSON when asked as XHR.
$query = http_build_query([
    'tab' => 'tasks',
    'draw' => 1,
    'start' => 0,
    'length' => 500,
    'projectId' => $projectId,
    'status' => 'not finished',
    'trashedData' => 'false',
    'milestone_id' => 'all',
    'project_admin' => 0,
    'assignedBY' => 'all',
    'label' => 'all',
    'category_id' => 'all',
    'pinned' => 'all',
    'date_filter_on' => 'start_date',
    'storyPoint' => 'all',
    'stage' => 'all',
    'show_childs' => !empty($wepro['include_subtasks']) ? 1 : 0,
    'department' => 'notSelected',
]);
$url = $baseUrl . '/account/projects/' . rawurlencode($projectId) . '?' . $query;

$headers = [
    'Accept: application/json, text/javascript, */*; q=0.01',
    'X-Requested-With: XMLHttpRequest',
    'Referer: ' . $baseUrl . '/account/projects/' . rawurlencode($projectId) . '?tab=tasks',
];

$ch = curl_init($url);
$options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_COOKIE => $cookie,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0',
];
if (($wepro['basic_user'] ?? '') !== '') {
    $options[CURLOPT_USERPWD] = $wepro['basic_user'] . ':' . ($wepro['basic_pass'] ?? '');
    $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
}
curl_setopt_array($ch, weproBrowserOpts($options, $baseUrl, $baseUrl . '/account/projects/' . rawurlencode($projectId) . '?tab=tasks'));

$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Never log or echo the raw body: it carries member emails and company secrets.
if ($body === false) {
    fail('Không gọi được WePRO: ' . $curlError);
}
if ($httpCode === 401) {
    // nginx answers with WWW-Authenticate; Laravel answers 401 for an unauthenticated XHR.
    $basicGate = stripos($body, 'Authorization Required') !== false;
    fail($basicGate
        ? 'WePRO trả về 401 ở cổng Basic auth. Kiểm tra lại user/password.'
        : 'WePRO trả về 401: chưa đăng nhập. Cookie remember sai hoặc đã hết hạn — nhớ dán cả tên cookie, dạng remember_web_xxx=giá_trị.');
}
if ($httpCode !== 200) {
    fail('WePRO trả về HTTP ' . $httpCode . '. Cookie remember có thể đã hết hạn.');
}

$data = json_decode($body, true);
unset($body);

if (!is_array($data) || !isset($data['data'])) {
    fail('WePRO không trả JSON task. Cookie remember nhiều khả năng đã hết hạn, hãy lấy lại.');
}

function stripTags($value)
{
    return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES, 'UTF-8'));
}

// The task titles only exist inside the rendered `heading` cell.
function taskTitles($heading)
{
    $titles = [];
    if (preg_match_all('/<span[^>]*class="[^"]*f-13[^"]*"[^>]*style="max-width:\s*450px[^"]*"[^>]*>(.*?)<\/span>/s', (string)$heading, $m)) {
        foreach ($m[1] as $raw) {
            $title = stripTags($raw);
            if ($title !== '') {
                $titles[] = $title;
            }
        }
    }
    return $titles;
}

// The gantt feed of the project lists every task with its `parent`, in one request:
// that is the only place WePRO states the parent → child relation.
function fetchGantt($baseUrl, $projectId, $cookie, $basicUser, $basicPass)
{
    $ch = curl_init($baseUrl . '/account/projects/' . rawurlencode($projectId) . '?tab=gantt&json=true');
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'],
        CURLOPT_COOKIE => $cookie,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0',
    ];
    if ($basicUser !== '') {
        $opts[CURLOPT_USERPWD] = $basicUser . ':' . $basicPass;
        $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
    }
    curl_setopt_array($ch, weproBrowserOpts($opts, $baseUrl, $baseUrl . '/account/projects/' . rawurlencode($projectId) . '?tab=gantt'));
    $body = curl_exec($ch);
    $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    $json = $ok && $body !== false ? json_decode($body, true) : null;
    unset($body);
    $out = [];
    foreach ((array)($json['data'] ?? []) as $row) {
        if (!is_array($row) || empty($row['id'])) {
            continue;
        }
        $out[(int)$row['id']] = [
            'parent' => (int)($row['parent'] ?? 0),
            'status' => (string)($row['status'] ?? ''),
        ];
    }
    return $out;
}

// Who "me" is: the create form preselects the current user as assignee. One extra small request.
function fetchMe($baseUrl, $projectId, $cookie, $basicUser, $basicPass)
{
    $ch = curl_init($baseUrl . '/account/tasks/create?task_project_id=' . rawurlencode($projectId));
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'],
        CURLOPT_COOKIE => $cookie,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36 Edg/155.0.0.0',
    ];
    if ($basicUser !== '') {
        $opts[CURLOPT_USERPWD] = $basicUser . ':' . $basicPass;
        $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
    }
    curl_setopt_array($ch, weproBrowserOpts($opts, $baseUrl));
    $body = curl_exec($ch);
    curl_close($ch);
    $json = $body !== false ? json_decode($body, true) : null;
    $html = is_array($json) && isset($json['html']) ? (string)$json['html'] : (string)$body;
    return preg_match("/#selectAssignee'\)\.val\('(\d+)'\)/", $html, $m) ? (int)$m[1] : 0;
}

// Assignees only exist as avatar links in the `users` HTML: /account/employees/<id> + tooltip name.
function taskAssignees($html)
{
    $out = [];
    if (preg_match_all('#employees/(\d+)"[^>]*>\s*<img[^>]*data-original-title="([^"]*)"#', (string)$html, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $out[] = ['id' => (int)$x[1], 'name' => html_entity_decode($x[2], ENT_QUOTES, 'UTF-8')];
        }
    }
    return $out;
}

$tasks = [];
foreach ($data['data'] as $row) {
    if (!is_array($row)) {
        continue;
    }
    $titles = taskTitles($row['heading'] ?? '');
    $shortCode = trim((string)($row['task_short_code'] ?? ''));
    $projectCode = trim((string)($row['project_short_code'] ?? ''));

    $tasks[] = [
        'id' => (int)($row['id'] ?? 0),
        'short_code' => $shortCode,
        'full_code' => $projectCode !== '' && $shortCode !== '' ? $projectCode . '.' . $shortCode : $shortCode,
        'title' => $titles[0] ?? stripTags($row['en_name'] ?? ''),
        'title_en' => $titles[1] ?? '',
        'percent' => (int)($row['task_percent'] ?? 0),
        'start_date' => trim((string)($row['create_on'] ?? '')),
        'due_date' => trim((string)($row['due_on'] ?? '')),
        'subtask_count' => (int)($row['subtasks_count'] ?? 0),
        'assignees' => taskAssignees($row['users'] ?? ''),
        'estimate_hours' => round(((int)($row['total_estimate_minutes'] ?? 0)) / 60, 1),
        'logged_hours' => round(((float)($row['total_log_time'] ?? 0)) / 60, 1),
        'parent_id' => 0,
        'status' => '',
        'url' => $baseUrl . '/account/tasks/' . (int)($row['id'] ?? 0),
        'timelog_url' => $baseUrl . '/account/timelogs/create-timelog-simple/' . (int)($row['id'] ?? 0),
    ];
}

$byId = [];
foreach ($tasks as $i => $task) {
    $byId[$task['id']] = $i;
}
$gantt = fetchGantt($baseUrl, $projectId, $cookie, (string)($wepro['basic_user'] ?? ''), (string)($wepro['basic_pass'] ?? ''));
$mapped = 0;
foreach ($tasks as $i => $task) {
    $g = $gantt[$task['id']] ?? null;
    if (!$g) {
        continue;
    }
    $tasks[$i]['status'] = $g['status'];
    if ($g['parent'] > 0 && isset($byId[$g['parent']])) {
        $tasks[$i]['parent_id'] = $g['parent'];
        $mapped++;
    }
}

$me = fetchMe($baseUrl, $projectId, $cookie, (string)($wepro['basic_user'] ?? ''), (string)($wepro['basic_pass'] ?? ''));
echo json_encode([
    'success' => true,
    'me' => $me,
    'mapped_children' => $mapped,
    'fetched_at' => date('c'),
    'total' => (int)($data['recordsFiltered'] ?? count($tasks)),
    'tasks' => $tasks,
], JSON_UNESCAPED_UNICODE);

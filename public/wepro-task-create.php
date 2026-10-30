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
// Editing existing tasks goes through WePRO's inline-edit endpoint, which takes any subset
// of fields. `tasks[i][id]` plus the fields to change; a field left out stays untouched.
if ($action === 'update') {
    $items = $_POST['tasks'] ?? [];
    if (!is_array($items) || !$items) {
        @unlink($jar);
        fail('Chưa có task nào để sửa.');
    }
    $results = [];
    foreach (array_values($items) as $item) {
        $id = (int)($item['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $fields = [];
        if (isset($item['heading'])) {
            $fields['heading'] = trim((string)$item['heading']);
            if ($fields['heading'] === '') {
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Tên task không được trống'];
                continue;
            }
        }
        if (isset($item['en_name'])) {
            $fields['en_name'] = trim((string)$item['en_name']);
        }
        if (isset($item['description'])) {
            $descText = trim((string)$item['description']);
            $fields['description'] = $descText === ''
                ? '<p><br></p>'
                : implode('', array_map(fn ($line) => '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>', preg_split('/\r\n|\r|\n/', $descText)));
        }
        if (isset($item['task_percent']) && $item['task_percent'] !== '') {
            $fields['task_percent'] = (string)max(0, min(100, (int)$item['task_percent']));
        }
        // Same split as on create: WePRO's own inline estimate edit posts hours + minutes.
        if (isset($item['estimate_hours']) && $item['estimate_hours'] !== '') {
            $est = max(0, (float)$item['estimate_hours']);
            $fields['estimate_hours'] = (string)(int)floor($est);
            $fields['estimate_minutes'] = (string)(int)round(($est - floor($est)) * 60);
        }
        // Status is a board column; the gantt feed reports the same four as new/doing/on_hold/completed.
        // Completing goes through change-status with the AI-usage survey WePRO demands; the other
        // three statuses are plain board columns.
        $complete = null;
        if (isset($item['status']) && $item['status'] !== '') {
            $columns = ['new' => '9', 'doing' => '10', 'on_hold' => '6'];
            if ($item['status'] === 'completed') {
                $pct = (string)($item['ai_percentage'] ?? '');
                $purposes = array_values(array_filter(array_map('strval', (array)($item['ai_purpose'] ?? []))));
                // WePRO asks for purposes only when AI helped (> 0%).
                if (!in_array($pct, ['0', '20', '40', '80', '100'], true) || ($pct !== '0' && !$purposes)) {
                    $results[] = ['id' => $id, 'success' => false, 'message' => 'Hoàn thành cần khảo sát AI: mức %, và ít nhất một mục đích khi AI > 0%'];
                    continue;
                }
                $complete = ['taskId' => (string)$id, 'status' => 'completed', 'percentage' => $pct, 'notes' => trim((string)($item['ai_notes'] ?? '')), '_token' => $token];
                foreach ($purposes as $k => $pu) {
                    $complete['purpose[' . $k . ']'] = $pu;
                }
            } elseif (isset($columns[$item['status']])) {
                $fields['board_column_id'] = $columns[$item['status']];
            } else {
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Trạng thái không hợp lệ'];
                continue;
            }
        }
        if (isset($item['parent_id'])) {
            $parentId = (int)$item['parent_id'];
            if ($parentId === $id) {
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Task không thể là cha của chính nó'];
                continue;
            }
            $fields['parent_id'] = $parentId > 0 ? (string)$parentId : '';
        }
        if (!$fields && !$complete) {
            $results[] = ['id' => $id, 'success' => false, 'message' => 'Không có gì để sửa'];
            continue;
        }
        if ($complete) {
            [$body, $code, $err] = weproRequest(
                $baseUrl . '/account/tasks/change-status',
                [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $complete,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'X-Requested-With: XMLHttpRequest',
                        'X-CSRF-TOKEN: ' . $token,
                        'Origin: ' . $baseUrl,
                        'Referer: ' . $baseUrl . '/account/tasks/' . $id,
                    ],
                ],
                $wepro, $cookie, $jar
            );
            $res = $body !== false ? json_decode($body, true) : null;
            if (!is_array($res) || ($res['status'] ?? '') !== 'success') {
                $msg = $body === false ? $err : (is_array($res) ? (string)($res['message'] ?? '') : 'HTTP ' . $code);
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Hoàn thành thất bại: ' . (trim($msg) ?: 'WePRO từ chối')];
                continue;
            }
            if (!$fields) {
                $results[] = ['id' => $id, 'success' => true, 'fields' => ['status']];
                continue;
            }
        }
        [$body, $code, $err] = weproRequest(
            $baseUrl . '/account/tasks/update-fields/' . $id,
            [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ['_token' => $token] + $fields,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json, text/javascript, */*; q=0.01',
                    'X-Requested-With: XMLHttpRequest',
                    'X-CSRF-TOKEN: ' . $token,
                    'Origin: ' . $baseUrl,
                    'Referer: ' . $baseUrl . '/account/tasks/' . $id,
                ],
            ],
            $wepro, $cookie, $jar
        );
        $res = $body !== false ? json_decode($body, true) : null;
        if (is_array($res) && ($res['status'] ?? '') === 'success') {
            $results[] = ['id' => $id, 'success' => true, 'fields' => array_merge(array_keys($fields), $complete ? ['status'] : [])];
            continue;
        }
        $msg = $body === false ? $err : (is_array($res) ? (string)($res['message'] ?? '') : 'HTTP ' . $code);
        if (is_array($res) && !empty($res['errors']) && is_array($res['errors'])) {
            $msg .= ' ' . implode(' ', array_map(fn ($e) => implode(' ', (array)$e), $res['errors']));
        }
        $results[] = ['id' => $id, 'success' => false, 'message' => trim($msg) ?: 'WePRO từ chối'];
    }
    @unlink($jar);
    $updated = count(array_filter($results, fn ($r) => $r['success']));
    echo json_encode(['success' => $updated > 0, 'updated' => $updated, 'total' => count($results), 'results' => $results], JSON_UNESCAPED_UNICODE);
    exit;
}
// The list has no description: the detail page does, as rich text in #taskDes-<id>.
if ($action === 'detail') {
    $id = (int)($_POST['task_id'] ?? 0);
    if ($id <= 0) {
        @unlink($jar);
        fail('Thiếu task id.');
    }
    [$body, $code, $err] = weproRequest(
        $baseUrl . '/account/tasks/' . $id,
        [CURLOPT_HTTPHEADER => ['Accept: application/json, text/javascript, */*; q=0.01', 'X-Requested-With: XMLHttpRequest']],
        $wepro, $cookie, $jar
    );
    @unlink($jar);
    if ($body === false || $code !== 200) {
        fail('Không lấy được chi tiết task (' . ($body === false ? $err : 'HTTP ' . $code) . ').');
    }
    $page = json_decode($body, true);
    $html = is_array($page) && isset($page['html']) ? (string)$page['html'] : (string)$body;
    unset($body);
    $desc = '';
    if (preg_match('/<textarea name="description" id="descriptionText-' . $id . '"[^>]*>(.*?)<\/textarea>/s', $html, $m)) {
        $desc = preg_replace('/<\/p>\s*<p>/', "\n", trim($m[1]));
        $desc = preg_replace('/<br\s*\/?>/i', "\n", $desc);
        $desc = trim(html_entity_decode(strip_tags($desc), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    $heading = preg_match('/name="heading" value="([^"]*)"/', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
    $en = preg_match('/name="en_name" value="([^"]*)"/', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
    echo json_encode(['success' => true, 'id' => $id, 'heading' => $heading, 'en_name' => $en, 'description' => $desc], JSON_UNESCAPED_UNICODE);
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
    // WePRO stores a rich-text description; plain text goes in as escaped paragraphs.
    $descText = trim((string)($item['description'] ?? ''));
    $description = $descText === ''
        ? '<p><br></p>'
        : implode('', array_map(fn ($line) => '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>', preg_split('/\r\n|\r|\n/', $descText)));
    // Hours may be fractional (1.5): WePRO wants hours + minutes as two fields.
    $estTotal = max(0, (float)($item['estimate_hours'] ?? 0));
    $estHours = (int)floor($estTotal);
    $estMinutes = (int)round(($estTotal - $estHours) * 60);
    // A row may carry its own dates; otherwise the common ones apply.
    $startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($item['start_date'] ?? '')) ? $item['start_date'] : $common['start_date'];
    $dueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($item['due_date'] ?? '')) ? $item['due_date'] : $common['due_date'];
    if ($startDate > $dueDate) {
        $results[] = ['index' => $i, 'success' => false, 'heading' => $heading, 'message' => 'Ngày bắt đầu sau ngày hạn'];
        continue;
    }
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
        'start_date' => $startDate,
        'due_date' => $dueDate,
        'user_id[0]' => (string)$common['user_id'],
        'description' => $description,
        'note' => '<p><br></p>',
        'client_id' => '', 'milestone_id' => '', 'sprint_id' => (string)($item['sprint_id'] ?? '') !== '' ? (string)$item['sprint_id'] : $common['sprint_id'], 'pcl' => '',
        'tester_date' => '', 'client_test_date' => '', 'tester_tested_date' => '', 'client_tested_date' => '',
        'estimate_hours' => (string)$estHours,
        'estimate_minutes' => (string)$estMinutes,
        'mention_user_ids' => '',
        'custom_fields_data[status_2]' => (string)($item['status'] ?? '') !== '' ? (string)$item['status'] : $common['status'],
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
            'start_date' => $startDate, 'due_date' => $dueDate,
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

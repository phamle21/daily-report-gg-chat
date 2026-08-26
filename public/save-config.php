<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.']);
    exit;
}

function clean($value)
{
    return trim((string)($value ?? ''));
}

$projects = [];
$postedProjects = $_POST['projects'] ?? [];
$names = $postedProjects['name'] ?? [];
$webhooks = $postedProjects['webhook'] ?? [];
$avatars = $postedProjects['avatar'] ?? [];

foreach ($names as $index => $name) {
    $projectName = clean($name);
    if ($projectName === '') {
        continue;
    }

    $projects[$projectName] = [
        'webhook' => clean($webhooks[$index] ?? ''),
        'avatar' => clean($avatars[$index] ?? ''),
    ];
}

if (!$projects) {
    $projects['JRR'] = ['webhook' => '', 'avatar' => ''];
}

$defaultProject = clean($_POST['default_project'] ?? 'JRR');
if (!isset($projects[$defaultProject])) {
    $defaultProject = array_key_first($projects);
}

$config = [
    'default_project' => $defaultProject,
    'projects' => $projects,
    'google_form' => [
        'enabled' => !empty($_POST['google_form_enabled']),
        'url' => clean($_POST['google_form_url'] ?? ''),
        'email' => clean($_POST['google_form_email'] ?? ''),
        'department' => clean($_POST['google_form_department'] ?? ''),
        'start_hour' => clean($_POST['start_hour'] ?? '08'),
        'start_minute' => clean($_POST['start_minute'] ?? '00'),
        'end_hour' => clean($_POST['end_hour'] ?? '17'),
        'end_minute' => clean($_POST['end_minute'] ?? '00'),
        'report_note' => clean($_POST['report_note'] ?? ''),
    ],
];

$content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
$runtimeDir = __DIR__ . '/history';
$file = $runtimeDir . '/app-config.php';

if (!is_dir($runtimeDir) && !mkdir($runtimeDir, 0775, true)) {
    echo json_encode(['success' => false, 'message' => 'Không tạo được thư mục history để lưu config']);
    exit;
}

if (@file_put_contents($file, $content, LOCK_EX) === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Không ghi được file config runtime. Hãy kiểm tra volume history-data và quyền ghi của PHP.',
    ]);
    exit;
}

echo json_encode(['success' => true, 'file' => basename($file)]);

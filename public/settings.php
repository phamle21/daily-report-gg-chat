<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$runtimeConfig = __DIR__ . '/history/app-config.php';
$firstRun = !file_exists($runtimeConfig);
$configFile = $firstRun ? __DIR__ . '/config.php' : $runtimeConfig;
$config = file_exists($configFile) ? require $configFile : [];
$projects = $config['projects'] ?? [];
if (!$projects) {
    $projects = ['JRR' => ['webhook' => '', 'avatar' => '']];
}
$googleForm = $config['google_form'] ?? [];
$wepro = $config['wepro'] ?? [];
$defaultProject = $config['default_project'] ?? array_key_first($projects);
$projectNames = array_keys($projects);
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
// Step state drives the ticks in the stepper; a step is "done" when its must-have fields are saved.
$doneYou = trim((string)($config['reporter'] ?? '')) !== '';
$doneProjects = !$firstRun && count(array_filter($projectNames, fn ($n) => trim($n) !== '')) > 0;
$doneWepro = !$firstRun && (empty($wepro['enabled']) || trim((string)($wepro['remember_cookie'] ?? '')) !== '');
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thiết lập · Daily Report</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <?php require __DIR__ . '/partials/head-assets.php'; ?>
    <style>
        .st-layout { display:grid; grid-template-columns:minmax(0,1fr) 300px; gap:18px; align-items:start; }
        .st-side { position:sticky; top:16px; }
        .st-side .st-card { padding:14px 16px; }
        @media (max-width:900px) { .st-layout { grid-template-columns:1fr; } .st-side { position:static; } }
        .st-input { height:40px; width:100%; border:1px solid #d4d4d8; border-radius:10px; background:#fff; padding:0 12px; font-size:14px; color:#18181b; box-shadow:0 1px 2px rgba(24,24,27,.05); }
        .st-input:focus { outline:none; border-color:#18181b; box-shadow:0 0 0 3px rgba(24,24,27,.12); }
        textarea.st-input { height:auto; padding:10px 12px; line-height:1.45; }
        .st-label { display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:#3f3f46; }
        .st-help { margin-top:6px; font-size:12px; color:#71717a; line-height:1.5; }
        .st-field { margin-bottom:16px; }
        .st-stepper { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:18px; }
        .st-step { display:flex; align-items:center; gap:10px; border:1px solid #d4d4d8; border-radius:12px; background:#fff; padding:10px 12px; text-align:left; cursor:pointer; }
        .st-step.is-active { border-color:#18181b; box-shadow:0 0 0 2px rgba(24,24,27,.12); }
        .st-step-num { display:flex; height:30px; width:30px; flex-shrink:0; align-items:center; justify-content:center; border-radius:999px; background:#e4e4e7; color:#3f3f46; font-size:13px; font-weight:700; }
        .st-step.is-done .st-step-num { background:#059669; color:#fff; }
        .st-step.is-active .st-step-num { background:#18181b; color:#fff; }
        .st-step-title { display:block; font-size:13px; font-weight:700; color:#18181b; }
        .st-step-sub { display:block; font-size:11px; color:#71717a; }
        .st-cookie { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.4fr); gap:10px; }
        @media (max-width:700px) { .st-cookie { grid-template-columns:1fr; } }
        .st-sublabel { display:block; margin-bottom:4px; font-size:11.5px; font-weight:600; color:#71717a; }
        .st-mono { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12.5px; }
        .st-copywrap { position:relative; }
        .st-copywrap .st-input { padding-right:62px; }
        .st-copy { position:absolute; right:6px; top:50%; transform:translateY(-50%); height:28px; border:1px solid #d4d4d8; border-radius:7px; background:#fafafa; padding:0 9px; font-size:11.5px; font-weight:600; color:#3f3f46; cursor:pointer; }
        .st-copy:hover { border-color:#18181b; color:#18181b; }
        .st-saved { margin-left:6px; border-radius:999px; background:#d1fae5; color:#065f46; padding:1px 8px; font-size:10.5px; font-weight:700; }
        .st-howto { margin-top:8px; border:1px dashed #d4d4d8; border-radius:10px; padding:8px 12px; background:#fafafa; font-size:12.5px; color:#3f3f46; }
        .st-howto summary { cursor:pointer; font-weight:600; }
        .st-howto ol { margin:8px 0 0 18px; padding:0; display:flex; flex-direction:column; gap:4px; }
        .st-howto p { margin:8px 0 0; color:#71717a; }
        .st-test { margin-top:10px; border:1px solid #e4e4e7; border-radius:10px; padding:10px 12px; font-size:13px; display:flex; flex-direction:column; gap:6px; }
        .st-test .row { display:flex; align-items:center; gap:8px; }
        .st-test .row i { height:10px; width:10px; border-radius:999px; background:#d4d4d8; flex:none; }
        .st-test .row.ok i { background:#059669; } .st-test .row.err i { background:#e11d48; } .st-test .row.wait i { background:#f59e0b; animation:drPulse 1s infinite; }
        .st-test .row.err { color:#be123c; }
        #weproFields.is-off { opacity:.45; pointer-events:none; }
        .st-panel { display:none; }
        .st-panel.is-active { display:block; }
        .st-card { border:1px solid #d4d4d8; border-radius:14px; background:#fff; padding:18px; box-shadow:0 1px 2px rgba(24,24,27,.08),0 10px 28px rgba(24,24,27,.10); }
        .st-card + .st-card { margin-top:14px; }
        .st-card-title { margin-bottom:4px; font-size:17px; font-weight:700; color:#18181b; }
        .st-card-lead { margin-bottom:16px; font-size:13px; color:#71717a; line-height:1.5; }
        .st-nav { display:flex; justify-content:space-between; gap:8px; margin-top:18px; }
        .st-btn { display:inline-flex; height:42px; align-items:center; justify-content:center; gap:6px; border:1px solid #d4d4d8; border-radius:10px; background:#f4f4f5; padding:0 18px; font-size:14px; font-weight:600; color:#3f3f46; cursor:pointer; }
        .st-btn:hover { background:#e4e4e7; color:#18181b; }
        .st-btn-primary { border-color:#18181b; background:#18181b; color:#fff; }
        .st-btn-primary:hover { background:#27272a; color:#fff; }
        .st-btn:disabled { opacity:.6; cursor:not-allowed; }
        .st-toggle { display:flex; align-items:center; justify-content:space-between; gap:12px; border:1px solid #d4d4d8; border-radius:12px; background:#fafafa; padding:12px 14px; cursor:pointer; margin-bottom:16px; }
        .st-toggle:has(input:checked) { border-color:#a7f3d0; background:#f0fdf4; }
        .st-toggle input { height:20px; width:20px; accent-color:#059669; }
        .st-toggle > span:first-child { display:flex; flex-direction:column; gap:2px; }
        .st-status { margin-top:10px; border-radius:10px; padding:10px 12px; font-size:13px; line-height:1.5; }
        .st-status.ok { background:#ecfdf5; color:#047857; }
        .st-status.err { background:#fff1f2; color:#be123c; }
        .st-status.wait { background:#f4f4f5; color:#52525b; }
        .st-project { border:1px solid #bae6fd; border-radius:12px; background:#fff; overflow:hidden; margin-bottom:10px; }
        .st-project-head { display:flex; width:100%; align-items:center; justify-content:space-between; gap:8px; background:#f0f9ff; padding:10px 14px; text-align:left; font-size:14px; font-weight:700; color:#0c4a6e; cursor:pointer; }
        .st-project-body { padding:14px; }
        .st-project-body.hidden { display:none; }
        .st-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .st-guide summary { cursor:pointer; font-size:13px; font-weight:600; color:#3f3f46; }
        .st-guide ol, .st-guide p { font-size:12.5px; color:#52525b; line-height:1.6; }
        .st-guide ol { padding-left:18px; list-style:decimal; }
        .st-guide h3 { margin:10px 0 2px; font-size:13px; font-weight:700; color:#18181b; }
        @media (max-width:640px) { .st-stepper { grid-template-columns:1fr; } .st-grid-2 { grid-template-columns:1fr; } .st-step-sub { display:none; } }
    </style>
</head>

<body class="min-h-screen bg-background text-foreground antialiased">
    <main class="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(212,175,55,0.16),transparent_34%),linear-gradient(180deg,#d4d4d8_0%,#c4c4ca_45%,#b4b4bb_100%)] px-3 py-4 sm:px-4 sm:py-6">
    <div class="w-full max-w-5xl mx-auto animate-fade-in st-layout">
    <div class="st-main">

        <div class="mb-5">
            <div class="flex flex-col gap-3 rounded-xl border border-zinc-300/80 bg-white/90 p-4 shadow-soft backdrop-blur sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 sm:text-3xl">Thiết lập</h1>
                    <p class="mt-1 text-sm text-zinc-500"><?= $firstRun ? 'Lần đầu dùng: điền 3 tab, tab nào xong bấm Lưu tab đó.' : 'Mỗi tab có nút Lưu riêng, lưu ngay không cần qua các tab khác.' ?></p>
                </div>
                <a href="index.php" class="inline-flex h-9 items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-800 shadow-button hover:bg-zinc-50">← Về trang báo cáo</a>
            </div>
        </div>

        <div class="st-stepper" id="stepper">
            <button type="button" class="st-step <?= $doneYou ? 'is-done' : '' ?>" data-step="1"><span class="st-step-num"><?= $doneYou ? '✓' : '1' ?></span><span><span class="st-step-title">Bạn là ai</span><span class="st-step-sub">Tên và chấm công</span></span></button>
            <button type="button" class="st-step <?= $doneProjects ? 'is-done' : '' ?>" data-step="2"><span class="st-step-num"><?= $doneProjects ? '✓' : '2' ?></span><span><span class="st-step-title">Project</span><span class="st-step-sub">Nơi nhận báo cáo</span></span></button>
            <button type="button" class="st-step <?= $doneWepro ? 'is-done' : '' ?>" data-step="3"><span class="st-step-num"><?= $doneWepro ? '✓' : '3' ?></span><span><span class="st-step-title">Kết nối WePRO</span><span class="st-step-sub">Lấy task và logtime</span></span></button>
        </div>

        <form id="settingsForm" data-first-run="<?= $firstRun ? '1' : '0' ?>">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- Step 1 -->
            <section class="st-panel" data-panel="1">
                <div class="st-card">
                    <div class="st-card-title">Bạn là ai</div>
                    <p class="st-card-lead">Tên này hiện trong báo cáo gửi đi. Phần chấm công dùng để tự điền Google Form mỗi ngày.</p>
                    <div class="st-field">
                        <label for="reporterSetting" class="st-label">Tên người báo cáo</label>
                        <input name="reporter" id="reporterSetting" maxlength="100" value="<?= e($config['reporter'] ?? '') ?>" class="st-input" placeholder="VD: vuong.toan">
                    </div>
                </div>
                <div class="st-card">
                    <div class="st-card-title">Chấm công qua Google Form</div>
                    <p class="st-card-lead">Bật nếu công ty yêu cầu nộp giờ làm qua Google Form. Tắt thì bỏ qua cả phần này.</p>
                    <label class="st-toggle"><span><b>Bật chấm công Google Form</b><span class="st-help" style="margin:0">Khi gửi báo cáo sẽ nộp form kèm theo</span></span><input type="checkbox" name="google_form_enabled" value="1" <?= !empty($googleForm['enabled']) ? 'checked' : '' ?>></label>
                    <div class="st-field">
                        <label class="st-label">Link Google Form (formResponse)</label>
                        <input name="google_form_url" class="st-input" placeholder="https://docs.google.com/forms/.../formResponse" value="<?= e($googleForm['url'] ?? '') ?>">
                    </div>
                    <div class="st-grid-2">
                        <div class="st-field">
                            <label class="st-label">Email chấm công</label>
                            <input name="google_form_email" class="st-input" placeholder="name@example.com" value="<?= e($googleForm['email'] ?? '') ?>">
                        </div>
                        <div class="st-field">
                            <label class="st-label">Bộ phận / nhóm</label>
                            <input name="google_form_department" class="st-input" placeholder="VD: Dev team" value="<?= e($googleForm['department'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="st-grid-2">
                        <div class="st-field">
                            <label class="st-label">Giờ bắt đầu</label>
                            <div class="flex gap-2"><input name="start_hour" class="st-input" placeholder="08" value="<?= e($googleForm['start_hour'] ?? '08') ?>"><input name="start_minute" class="st-input" placeholder="00" value="<?= e($googleForm['start_minute'] ?? '00') ?>"></div>
                            <p class="st-help">Giờ : Phút</p>
                        </div>
                        <div class="st-field">
                            <label class="st-label">Giờ kết thúc</label>
                            <div class="flex gap-2"><input name="end_hour" class="st-input" placeholder="17" value="<?= e($googleForm['end_hour'] ?? '17') ?>"><input name="end_minute" class="st-input" placeholder="00" value="<?= e($googleForm['end_minute'] ?? '00') ?>"></div>
                            <p class="st-help">Giờ : Phút</p>
                        </div>
                    </div>
                    <div class="st-field">
                        <label class="st-label">Ghi chú gửi kèm form</label>
                        <input name="report_note" class="st-input" placeholder="VD: Đã nhập đầy đủ" value="<?= e($googleForm['report_note'] ?? '') ?>">
                    </div>
                </div>
                <div class="st-nav"><span></span><button type="submit" class="st-btn st-btn-primary save-step">Lưu</button></div>
            </section>

            <!-- Step 2 -->
            <section class="st-panel" data-panel="2">
                <div class="st-card">
                    <div class="st-card-title">Project nhận báo cáo</div>
                    <p class="st-card-lead">Mỗi project là một nơi nhận. Chỉ cần <b>tên</b>; các link webhook thêm sau cũng được.</p>
                    <div id="projectSettingsList">
                        <?php foreach ($projects as $projectName => $projectConfig): ?>
                            <div class="st-project project-setting" data-project="<?= e($projectName) ?>">
                                <button type="button" class="st-project-head toggle-project" aria-expanded="false">
                                    <span class="project-summary truncate">📁 <?= e($projectName) ?></span>
                                    <span class="project-chevron transition-transform">⌄</span>
                                </button>
                                <div class="st-project-body project-setting-body hidden">
                                    <div class="st-grid-2">
                                        <div class="st-field">
                                            <label class="st-label">Tên project</label>
                                            <input name="projects[name][]" class="project-name st-input" placeholder="VD: JRR" value="<?= e($projectName) ?>">
                                        </div>
                                        <div class="st-field">
                                            <label class="st-label">WePRO Project ID</label>
                                            <input name="projects[wepro_project_id][]" class="project-wepro-id st-input" placeholder="VD: 366" value="<?= e($projectConfig['wepro_project_id'] ?? '') ?>">
                                            <p class="st-help">Số trong link WePRO: /account/projects/<b>366</b></p>
                                        </div>
                                    </div>
                                    <div class="st-field">
                                        <label class="st-label">Webhook Google Chat</label>
                                        <input name="projects[webhook][]" class="project-webhook st-input" placeholder="https://chat.googleapis.com/..." value="<?= e($projectConfig['webhook'] ?? '') ?>">
                                    </div>
                                    <div class="st-field">
                                        <label class="st-label">Webhook Slack</label>
                                        <input name="projects[slack_webhook][]" class="project-slack-webhook st-input" placeholder="https://hooks.slack.com/services/..." value="<?= e($projectConfig['slack_webhook'] ?? '') ?>">
                                    </div>
                                    <div class="st-field">
                                        <label class="st-label">Logo (link ảnh)</label>
                                        <input name="projects[avatar][]" class="project-avatar st-input" placeholder="https://..." value="<?= e($projectConfig['avatar'] ?? '') ?>">
                                    </div>
                                    <button type="button" class="st-btn remove-project" style="height:36px;color:#be123c;">Xóa project này</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="addProject" class="st-btn" style="width:100%;">＋ Thêm project</button>
                    <div class="st-field" style="margin-top:16px;">
                        <label class="st-label">Project mở sẵn khi vào trang báo cáo</label>
                        <select name="default_project" id="defaultProjectSelect" class="st-input">
                            <?php foreach ($projectNames as $projectName): ?>
                                <option value="<?= e($projectName) ?>" <?= $defaultProject === $projectName ? 'selected' : '' ?>><?= e($projectName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="st-nav"><span></span><button type="submit" class="st-btn st-btn-primary save-step">Lưu</button></div>
            </section>

            <!-- Step 3 -->
            <section class="st-panel" data-panel="3">
                <div class="st-card">
                    <div class="st-card-title">Kết nối WePRO</div>
                    <p class="st-card-lead">Dùng để lấy task, tạo/sửa task và logtime. Không dùng WePRO thì tắt công tắc và bấm Lưu.</p>
                    <label class="st-toggle"><span><b>Dùng WePRO</b><span class="st-help" style="margin:0">Bật các nút WePRO ở trang báo cáo và trang Task</span></span><input type="checkbox" name="wepro_enabled" value="1" <?= !empty($wepro['enabled']) ? 'checked' : '' ?>></label>
                    <div id="weproFields">
                    <div class="st-field">
                        <label class="st-label">Địa chỉ WePRO</label>
                        <input name="wepro_base_url" class="st-input" placeholder="https://wepro.rcvn.work" value="<?= e($wepro['base_url'] ?? 'https://wepro.rcvn.work') ?>">
                    </div>
                    <div class="st-grid-2">
                        <div class="st-field">
                            <label class="st-label">User cổng Basic auth</label>
                            <input name="wepro_basic_user" autocomplete="off" class="st-input" placeholder="user" value="<?= e($wepro['basic_user'] ?? '') ?>">
                            <p class="st-help">Hộp đăng nhập của trình duyệt hiện ra trước khi vào WePRO.</p>
                        </div>
                        <div class="st-field">
                            <label class="st-label">Mật khẩu cổng Basic auth <?= !empty($wepro['basic_pass']) ? '<span class="st-saved">đã lưu</span>' : '' ?></label>
                            <input type="password" name="wepro_basic_pass" autocomplete="new-password" class="st-input" placeholder="<?= !empty($wepro['basic_pass']) ? 'Để trống nếu giữ nguyên' : 'password' ?>" value="">
                        </div>
                    </div>
                    <?php $cookieName = preg_replace('/=.*/s', '', (string)($wepro['remember_cookie'] ?? '')); ?>
                    <div class="st-field">
                        <label class="st-label">Cookie đăng nhập WePRO <?= !empty($wepro['remember_cookie']) ? '<span class="st-saved">đã lưu</span>' : '' ?></label>
                        <div class="st-cookie">
                            <div>
                                <span class="st-sublabel">Key (Name)</span>
                                <div class="st-copywrap">
                                    <input name="wepro_cookie_name" id="weproCookieName" autocomplete="off" spellcheck="false" class="st-input st-mono" placeholder="remember_web_…" value="<?= e($cookieName) ?>">
                                    <button type="button" class="st-copy" data-copy="#weproCookieName" title="Copy key">Copy</button>
                                </div>
                            </div>
                            <div>
                                <span class="st-sublabel">Value</span>
                                <textarea name="wepro_cookie_value" id="weproCookie" rows="2" autocomplete="off" spellcheck="false" class="st-input st-mono" placeholder="<?= !empty($wepro['remember_cookie']) ? 'Để trống nếu giữ nguyên. Dán value mới vào đây để thay.' : 'Dán value: eyJpdiI6...' ?>"></textarea>
                            </div>
                        </div>
                        <p class="st-help" id="weproCookieHint">Copy từng cột <b>Name</b> và <b>Value</b> trong DevTools vào hai ô. Dán nguyên <code>remember_web_…=…</code> hoặc cả dòng <b>Cookie:</b> vào ô Value cũng được, app tự tách.</p>
                        <details class="st-howto">
                            <summary>Cách lấy cookie (30 giây)</summary>
                            <ol>
                                <li>Mở WePRO trong trình duyệt, đã đăng nhập (có tick "Ghi nhớ").</li>
                                <li>Bấm <b>F12</b> → tab <b>Application</b> (Edge/Chrome) → <b>Cookies</b> → chọn <code>https://wepro.rcvn.work</code>.</li>
                                <li>Tìm dòng tên bắt đầu bằng <b>remember_web_</b>.</li>
                                <li>Nháy đúp ô <b>Name</b>, Ctrl+C, dán vào ô <b>Key</b>. Nháy đúp ô <b>Value</b>, Ctrl+A, Ctrl+C, dán vào ô <b>Value</b>.</li>
                            </ol>
                            <p>Cookie này sống nhiều tháng. Chỉ phải lấy lại khi bạn bấm Đăng xuất trên WePRO hoặc đổi mật khẩu.</p>
                        </details>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-zinc-700" style="margin-bottom:12px;"><input type="checkbox" name="wepro_include_subtasks" value="1" <?= !empty($wepro['include_subtasks']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-zinc-300"> Lấy cả task con khi tải danh sách (chậm hơn, khoảng 10 giây)</label>
                    <div class="flex gap-2 items-center flex-wrap">
                        <button type="button" id="weproTestBtn" class="st-btn">Lưu và kiểm tra kết nối</button>
                        <span class="st-help" style="margin:0">Lưu các ô trên rồi thử đăng nhập và đọc task của project mặc định.</span>
                    </div>
                    <div id="weproTestResult" class="st-test hidden"></div>
                    </div>
                </div>
                <div class="st-nav"><span></span><button type="submit" class="st-btn st-btn-primary save-step">Lưu</button></div>
            </section>
        </form>

    </div>
    <aside class="st-side">
        <details class="st-card st-guide" open>
            <summary>❓ Lấy các link webhook ở đâu?</summary>
            <h3>Webhook Google Chat</h3>
            <ol>
                <li>Mở Google Chat, vào Space nhận daily report.</li>
                <li>Bấm tên Space → <b>Apps & integrations</b> → <b>Webhooks</b>.</li>
                <li>Tạo webhook, đặt tên theo project, copy link bắt đầu bằng <code>https://chat.googleapis.com/</code>.</li>
            </ol>
            <h3>Webhook Slack</h3>
            <p>Trong Slack App, bật Incoming Webhooks → Add New Webhook to Workspace → chọn channel → copy link.</p>
            <h3>Logo project</h3>
            <p>Dùng link ảnh HTTPS mở được khi chưa đăng nhập. Ảnh vuông hiển thị đẹp nhất.</p>
            <p style="margin-top:8px;color:#be123c;">Webhook là thông tin bí mật, không gửi cho người khác hay đưa lên Git.</p>
        </details>
    </aside>
    </div>
    </main>

    <script src="settings.js?t=<?= e((string)(@filemtime(__DIR__ . '/settings.js') ?: time())) ?>"></script>
</body>

</html>

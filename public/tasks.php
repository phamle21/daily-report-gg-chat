<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$runtimeConfig = __DIR__ . '/history/app-config.php';
if (!file_exists($runtimeConfig)) {
    header('Location: settings.php');
    exit;
}
$config = require $runtimeConfig;
$projects = $config['projects'] ?? [];
$defaultProject = $config['default_project'] ?? array_key_first($projects);
$wepro = $config['wepro'] ?? [];
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task WePRO</title>
    <?php require __DIR__ . '/partials/head-assets.php'; ?>
    <style>
        :root { --tk-ink:#18181b; --tk-mute:#71717a; --tk-line:#e4e4e7; --tk-soft:#f4f4f5; --tk-brand:#4f46e5; --tk-brand-soft:#eef2ff; --tk-brand-line:#c7d2fe; --tk-top:56px; }
        html, body { height:100%; }
        body { background:#ececef; overflow:hidden; }
        .hidden { display:none !important; }
        /* ---- app frame: fixed top bar, two scrolling columns, docked bars ---- */
        .tk-app { display:grid; grid-template-rows:var(--tk-top) minmax(0,1fr) auto; height:100vh; }
        .tk-top { display:flex; align-items:center; gap:10px; padding:0 16px; background:#fff; border-bottom:1px solid var(--tk-line); }
        .tk-top h1 { font-size:15px; font-weight:700; letter-spacing:-.01em; color:var(--tk-ink); margin:0 6px 0 0; display:flex; align-items:center; gap:8px; }
        .tk-top h1 i { display:inline-flex; height:26px; width:26px; border-radius:7px; background:var(--tk-brand); color:#fff; font-style:normal; font-size:13px; align-items:center; justify-content:center; }
        .tk-grow { flex:1; }
        .tk-btn { display:inline-flex; align-items:center; gap:6px; height:38px; padding:0 12px; border-radius:8px; border:1px solid var(--tk-line); background:#fff; color:#3f3f46; font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap; transition:background .12s, border-color .12s; text-decoration:none; }
        .tk-btn:hover { background:var(--tk-soft); border-color:#d4d4d8; }
        .tk-btn:disabled { opacity:.5; cursor:not-allowed; }
        .tk-btn.primary { background:var(--tk-ink); border-color:var(--tk-ink); color:#fff; }
        .tk-btn.primary:hover { background:#27272a; }
        .tk-btn.brand { background:var(--tk-brand); border-color:var(--tk-brand); color:#fff; }
        .tk-btn.brand:hover { background:#4338ca; }
        .tk-btn.ghost { border-color:transparent; background:transparent; }
        .tk-btn.danger { background:#dc2626; border-color:#dc2626; color:#fff; }
        .tk-btn.danger:hover { background:#b91c1c; border-color:#b91c1c; }
        .dr-modal-confirm.tk-danger-confirm { background:#dc2626 !important; border-color:#dc2626 !important; color:#fff !important; }
        .dr-modal-confirm.tk-danger-confirm:hover { background:#b91c1c !important; }
        .tk-btn.sm { height:34px; padding:0 12px; font-size:12px; border-radius:7px; }
        .tk-select { height:38px; border:1px solid var(--tk-line); border-radius:8px; background:#fff; padding:0 30px 0 10px; font-size:13px; font-weight:600; color:var(--tk-ink); appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2371717a' stroke-width='2.5'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 9px center; }
        .tk-kbd { font-family:ui-monospace, Menlo, monospace; font-size:10.5px; border:1px solid var(--tk-line); border-bottom-width:2px; border-radius:4px; padding:0 4px; color:var(--tk-mute); background:#fff; }

        .tk-main { --aside:400px; display:grid; grid-template-columns:minmax(0,1fr) 10px var(--aside); min-height:0; gap:0; padding:14px; }
        /* Drag handle between the list and the editor; width is remembered per browser. */
        .tk-split { cursor:col-resize; position:relative; margin:0 2px; border-radius:6px; }
        .tk-split::before { content:""; position:absolute; left:3px; top:50%; height:40px; width:4px; margin-top:-20px; border-radius:999px; background:#d4d4d8; transition:background .12s; }
        .tk-split:hover::before, .tk-split.is-dragging::before { background:var(--tk-brand); }
        .tk-split.is-dragging { background:var(--tk-brand-soft); }
        body.is-resizing { cursor:col-resize; user-select:none; }
        body.is-resizing iframe, body.is-resizing .tk-list { pointer-events:none; }
        .tk-box { background:#fff; border:1px solid var(--tk-line); border-radius:14px; box-shadow:0 1px 2px rgba(24,24,27,.05), 0 8px 24px rgba(24,24,27,.07); overflow:hidden; }
        @media (max-width:1024px) { .tk-main { grid-template-columns:1fr; } .tk-split { display:none; } .tk-aside { display:none; } .tk-main.show-aside .tk-aside { display:flex; } .tk-main.show-aside .tk-listwrap { display:none; } }
        .tk-listwrap { display:flex; flex-direction:column; min-height:0; }
        .tk-stats { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; padding:12px 14px 4px; background:#fafafa; }
        .tk-stat { position:relative; overflow:hidden; display:flex; flex-direction:column; align-items:flex-start; gap:2px; min-height:64px; padding:10px 12px; border:0; border-radius:12px; color:#fff; cursor:pointer; text-align:left; box-shadow:0 1px 2px rgba(24,24,27,.08); transition:transform .12s, box-shadow .12s; }
        .tk-stat:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(24,24,27,.14); }
        .tk-stat span { font-size:11.5px; font-weight:600; opacity:.9; }
        .tk-stat b { font-size:22px; font-weight:800; line-height:1.1; font-variant-numeric:tabular-nums; }
        .tk-stat i { position:absolute; right:10px; top:50%; transform:translateY(-50%); height:34px; width:34px; border-radius:999px; background:rgba(255,255,255,.22); display:flex; align-items:center; justify-content:center; font-style:normal; font-size:14px; }
        .tk-stat.all { background:#52525b; } .tk-stat.doing { background:#2563eb; } .tk-stat.new { background:#4f46e5; } .tk-stat.hold { background:#f59e0b; } .tk-stat.done { background:#10b981; }
        .tk-stat { opacity:.78; }
        .tk-stat.is-active { opacity:1; box-shadow:0 0 0 3px #fff, 0 0 0 5px currentColor, 0 8px 18px rgba(24,24,27,.16); }
        .tk-stat.is-active::after { content:"✓ đang lọc"; position:absolute; right:52px; bottom:8px; font-size:10px; font-weight:700; opacity:.85; }
        .tk-stat.all.is-active::after { content:""; }
        @media (max-width:900px) { .tk-stats { grid-template-columns:repeat(3,minmax(0,1fr)); } }
        .tk-tools { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:10px 14px; border-bottom:1px solid var(--tk-line); background:#fafafa; }
        .tk-search { flex:1 1 220px; position:relative; }
        .tk-search input { width:100%; height:38px; border:1px solid var(--tk-line); border-radius:8px; background:#fff; padding:0 12px 0 32px; font-size:13px; }
        .tk-search input:focus { outline:none; border-color:var(--tk-ink); box-shadow:0 0 0 3px rgba(24,24,27,.08); }
        .tk-search::before { content:"⌕"; position:absolute; left:11px; top:5px; font-size:17px; color:var(--tk-mute); }
        .tk-seg { display:inline-flex; background:#fff; border:1px solid var(--tk-line); border-radius:8px; padding:3px; gap:2px; }
        .tk-seg button { height:26px; padding:0 9px; border:0; border-radius:5px; background:transparent; font-size:12px; font-weight:600; color:#52525b; cursor:pointer; }
        .tk-seg button.is-active { background:var(--tk-ink); color:#fff; }
        .tk-meta { font-size:12px; color:var(--tk-mute); white-space:nowrap; }
        .tk-list { flex:1; min-height:0; overflow:auto; }
        /* rows */
        .tk-row { display:grid; grid-template-columns:34px 112px minmax(0,1fr) 150px 86px 30px; align-items:center; gap:10px; min-height:46px; padding:5px 14px 5px 6px; border-bottom:1px solid var(--tk-soft); cursor:pointer; position:relative; user-select:none; }
        .tk-row:hover { background:#fafafa; }
        .tk-row.is-active { background:var(--tk-brand-soft); }
        .tk-row.is-active::before { content:""; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--tk-brand); }
        .tk-row input[type=checkbox] { height:15px; width:15px; accent-color:var(--tk-brand); margin-left:10px; cursor:pointer; }
        .tk-code { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:11.5px; font-weight:600; color:var(--tk-mute); white-space:nowrap; display:flex; align-items:center; gap:4px; }
        .tk-title { min-width:0; }
        .tk-title b { display:block; font-weight:600; font-size:13.5px; color:var(--tk-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .tk-title span { display:block; font-size:12px; color:var(--tk-mute); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        /* Unsaved rows: amber band + border, with chips naming the changed fields. */
        .tk-row.is-dirty { background:#fff7db; box-shadow:inset 4px 0 0 #f59e0b, inset 0 0 0 1px #fcd34d; }
        .tk-row.is-dirty:hover { background:#fff1c2; }
        .tk-row.is-dirty.is-active { background:#ffedb5; }
        .tk-row.is-dirty .tk-code { color:#92400e; }
        .tk-chips { display:flex; flex-wrap:wrap; gap:4px; margin-top:3px; }
        .tk-chips i { font-style:normal; font-size:10px; font-weight:700; line-height:1; padding:3px 6px; border-radius:999px; background:#f59e0b; color:#fff; }
        .tk-chips i::before { content:"✎ "; }
        .tk-row.is-saved { background:#ecfdf5; box-shadow:inset 4px 0 0 #10b981; }
        .tk-row.is-bad { background:#fff1f2; box-shadow:inset 4px 0 0 #e11d48; }
        /* AI usage survey */
        .tk-ai { text-align:left; display:flex; flex-direction:column; gap:8px; }
        .tk-ai-note { margin:0 0 4px; font-size:13px; color:#52525b; }
        .tk-ai-label { font-size:12.5px; font-weight:700; color:#18181b; margin-top:6px; display:flex; justify-content:space-between; }
        .tk-ai-label b { color:#e11d48; } .tk-ai-label small { font-weight:500; color:#71717a; }
        .tk-ai-pct { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; }
        .tk-ai-pct button { border:1px solid #d4d4d8; border-radius:10px; background:#fff; padding:10px 6px; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:2px; }
        .tk-ai-pct button b { font-size:14px; color:#18181b; } .tk-ai-pct button span { font-size:11px; color:#71717a; }
        .tk-ai-pct button.is-active, .tk-ai-purpose button.is-active { border-color:#4f46e5; background:#eef2ff; box-shadow:0 0 0 2px rgba(79,70,229,.25); }
        .tk-ai-purpose { display:flex; flex-wrap:wrap; gap:8px; }
        .tk-ai-purpose button { border:1px solid #d4d4d8; border-radius:9px; background:#fff; padding:8px 12px; font-size:13px; font-weight:600; color:#3f3f46; cursor:pointer; }
        .tk-ai textarea { width:100%; border:1px solid #d4d4d8; border-radius:9px; padding:9px 11px; font-size:13px; resize:vertical; }
        .tk-ai textarea:focus { outline:none; border-color:#18181b; box-shadow:0 0 0 3px rgba(24,24,27,.08); }
        .tk-meta-row { display:flex; gap:14px; flex-wrap:wrap; margin-top:3px; }
        .tk-meta-row em + em { padding-left:14px; border-left:1px solid #e4e4e7; }
        .tk-meta-row em { font-style:normal; font-size:11px; color:#71717a; font-variant-numeric:tabular-nums; display:inline-flex; align-items:center; gap:3px; white-space:nowrap; }
        .tk-due { display:inline-flex; align-items:center; justify-content:center; gap:0; font-weight:700; border-radius:999px; padding:3px 10px 3px 9px; border:1px solid transparent; line-height:1; }
        .tk-due i { display:inline-flex; align-items:center; justify-content:center; height:14px; width:14px; margin:0 8px; flex:none; border-radius:999px; color:#fff; font-style:normal; font-size:10px; font-weight:900; line-height:1; }
        .tk-due.late { color:#b91c1c; background:#fef2f2; border-color:#fecaca; } .tk-due.late i { background:#dc2626; }
        .tk-due.today { color:#b45309; background:#fffbeb; border-color:#fde68a; } .tk-due.today i { background:#f59e0b; }
        .tk-due::after { display:inline-flex; align-items:center; align-self:center; font-size:10px; font-weight:600; opacity:.8; line-height:1; }
        .tk-due.late::after { content:"quá hạn"; } .tk-due.today::after { content:"hôm nay"; }
        .tk-row.due-late { box-shadow:inset 4px 0 0 #fca5a5; }
        .tk-row.due-today { box-shadow:inset 4px 0 0 #fcd34d; }
        .tk-row.is-dirty.due-late, .tk-row.is-dirty.due-today { box-shadow:inset 4px 0 0 #f59e0b, inset 0 0 0 1px #fcd34d; }
        .tk-who { display:inline-flex; align-items:center; gap:4px; }
        .tk-who b { display:inline-flex; height:16px; min-width:16px; padding:0 4px; border-radius:999px; background:#e4e4e7; color:#3f3f46; font-size:9.5px; font-weight:800; align-items:center; justify-content:center; }
        .tk-who b.is-me { background:var(--tk-brand); color:#fff; }
        .tk-meta-row u { text-decoration:none; font-size:9.5px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#a1a1aa; margin-right:4px; }
        .tk-diff-wrap { text-align:left; max-height:60vh; overflow:auto; display:flex; flex-direction:column; gap:10px; }
        .tk-diff { border:1px solid var(--tk-line); border-radius:10px; overflow:hidden; }
        .tk-diff-head { padding:8px 12px; background:#fafafa; border-bottom:1px solid var(--tk-line); font-size:13px; }
        .tk-diff-head b { font-family:ui-monospace, Menlo, monospace; color:#71717a; margin-right:6px; }
        .tk-diff table { width:100%; border-collapse:collapse; font-size:13px; }
        .tk-diff th { width:90px; padding:7px 12px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#52525b; vertical-align:top; }
        .tk-diff td { padding:7px 8px; vertical-align:top; word-break:break-word; }
        .tk-diff td.old { color:#9f1239; background:#fff1f2; text-decoration:line-through; width:38%; }
        .tk-diff td.arrow { width:20px; color:#a1a1aa; text-align:center; }
        .tk-diff td.new { color:#065f46; background:#ecfdf5; width:38%; }
        .tk-diff td i { color:#a1a1aa; }
        /* Inline edit on the list: title, percent and status flip into controls in place. */
        .tk-title b[contenteditable] { outline:none; border-radius:6px; padding:1px 4px; margin:-1px -4px; }
        .tk-title b[contenteditable]:hover { background:#fff; box-shadow:0 0 0 1px var(--tk-line); }
        .tk-title b[contenteditable]:focus { background:#fff; box-shadow:0 0 0 2px var(--tk-brand); white-space:normal; }
        .tk-pct output { cursor:text; border-radius:5px; }
        .tk-pct output:hover { background:#fff; box-shadow:0 0 0 1px var(--tk-line); }
        .tk-pct input.tk-pct-in { width:48px; height:24px; border:1px solid var(--tk-brand); border-radius:5px; padding:0 4px; font-size:12px; font-weight:700; text-align:right; }
        .tk-st { cursor:pointer; }
        .tk-st:hover { box-shadow:0 0 0 2px rgba(24,24,27,.12); }
        select.tk-st-in { height:26px; border:1px solid var(--tk-brand); border-radius:6px; font-size:11.5px; font-weight:700; padding:0 4px; background:#fff; }
        .tk-clock { height:28px; width:28px; border:1px solid var(--tk-line); border-radius:8px; background:#fff; color:#0369a1; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; font-size:14px; opacity:.55; }
        .tk-row:hover .tk-clock, .tk-clock:hover { opacity:1; }
        .tk-clock:hover { background:#e0f2fe; border-color:#bae6fd; }
        .tk-dot { display:none; margin-left:6px; height:8px; width:8px; border-radius:999px; background:#f59e0b; vertical-align:middle; }
        .tk-row.is-dirty .tk-dot { display:inline-block; }
        .tk-row.is-saved .tk-dot { display:inline-block; background:#10b981; }
        .tk-row.is-bad .tk-dot { display:inline-block; background:#e11d48; }
        .tk-pct { display:flex; align-items:center; gap:8px; }
        .tk-bar { flex:1; height:6px; border-radius:999px; background:var(--tk-soft); overflow:hidden; }
        .tk-bar i { display:block; height:100%; background:var(--tk-brand); border-radius:999px; transition:width .2s; }
        .tk-bar.done i { background:#10b981; }
        .tk-pct output { width:36px; text-align:right; font-size:12px; font-weight:700; color:#3f3f46; font-variant-numeric:tabular-nums; }
        .tk-st { display:inline-block; border-radius:6px; padding:3px 8px; font-size:11px; font-weight:700; text-align:center; }
        .tk-st.st-new { background:#e0e7ff; color:#3730a3; }
        .tk-st.st-doing { background:#dbeafe; color:#1d4ed8; }
        .tk-st.st-hold { background:#fef3c7; color:#92400e; }
        .tk-st.st-done { background:#d1fae5; color:#065f46; }
        /* groups */
        .tk-group { border-bottom:1px solid var(--tk-line); }
        .tk-group > .tk-row .tk-title b { font-weight:700; }
        .tk-fold { height:22px; width:22px; border:1px solid var(--tk-line); border-radius:6px; background:#fff; font-size:13px; color:#52525b; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:transform .15s; flex:none; }
        .tk-group.is-folded .tk-fold { transform:rotate(-90deg); }
        .tk-kids { margin-left:4px; border-radius:999px; background:var(--tk-brand-soft); color:#3730a3; padding:1px 7px; font-size:10.5px; font-weight:700; }
        .tk-children { margin-left:26px; border-left:2px solid var(--tk-brand-line); background:#fcfcfd; }
        .tk-group.is-folded .tk-children { display:none; }
        .tk-children .tk-row { grid-template-columns:34px 100px minmax(0,1fr) 150px 86px 30px; min-height:42px; }
        .tk-children .tk-row .tk-title b { font-weight:500; font-size:13px; }
        .tk-children .tk-code::before { content:""; width:10px; border-top:2px solid var(--tk-brand-line); margin-right:2px; }
        /* Section colour system: each zone has one hue, reused for its header, left bar and tints. */
        .tk-section { --zone:#4f46e5; --zone-soft:#eef2ff; --zone-line:#c7d2fe; position:sticky; top:0; z-index:2; display:flex; align-items:center; gap:8px; padding:8px 14px 8px 12px; font-size:11px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--zone); background:var(--zone-soft); border-bottom:1px solid var(--zone-line); border-left:4px solid var(--zone); }
        .tk-section::before { content:""; height:8px; width:8px; border-radius:999px; background:var(--zone); }
        .tk-section span { color:var(--zone); opacity:.75; font-weight:600; letter-spacing:0; text-transform:none; }
        .tk-section.zone-loose { --zone:#64748b; --zone-soft:#f1f5f9; --zone-line:#cbd5e1; }
        .tk-group { border-left:4px solid var(--tk-brand-line); }
        .tk-group > .tk-row { background:#fafaff; }
        .tk-group:hover { border-left-color:var(--tk-brand); }
        .tk-loose .tk-row { border-left:4px solid #e2e8f0; }
        .tk-loose .tk-row .tk-code { color:#64748b; }
        .tk-aside-head { border-top:3px solid var(--tk-brand); }
        .tk-aside-head h2::before { content:"✎"; display:inline-flex; height:22px; width:22px; margin-right:8px; border-radius:6px; background:var(--tk-brand); color:#fff; font-size:12px; align-items:center; justify-content:center; }
        .tk-dock-bar.bulk { border-left:5px solid var(--tk-brand); }
        .tk-dock-bar.save { border-left:5px solid #f59e0b; }
        .tk-section button { margin-left:auto; }
        .tk-empty { padding:48px 20px; text-align:center; color:var(--tk-mute); font-size:13px; }

        /* ---- editor ---- */
        .tk-aside { display:flex; flex-direction:column; min-height:0; background:#fff; }
        .tk-aside-head { display:flex; align-items:center; gap:8px; padding:0 16px; height:48px; border-bottom:1px solid var(--tk-line); background:#fafafa; }
        .tk-aside-head h2 { margin:0; font-size:13px; font-weight:700; color:var(--tk-ink); }
        .tk-aside-body { flex:1; min-height:0; overflow:auto; padding:14px 16px 18px; display:flex; flex-direction:column; gap:12px; background:#e3e3e7; }
        .tk-field { background:#fff; border:1px solid var(--tk-line); border-radius:10px; padding:10px 12px; }
        .tk-info { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
        .tk-info div { background:#fff; border:1px solid var(--tk-line); border-radius:10px; padding:8px 10px; }
        .tk-info span { display:block; font-size:10px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--tk-mute); }
        .tk-info b { display:block; font-size:14px; font-weight:700; color:var(--tk-ink); font-variant-numeric:tabular-nums; margin-top:2px; }
        .tk-info b small { font-size:11px; font-weight:600; color:var(--tk-mute); }
        .tk-info .tk-due { font-size:12px; }
        .tk-aside-empty { margin:auto; text-align:center; color:var(--tk-mute); font-size:13px; line-height:1.6; max-width:260px; }
        .tk-aside-empty b { display:block; color:var(--tk-ink); font-size:14px; margin-bottom:4px; }
        .tk-field { display:flex; flex-direction:column; gap:6px; }
        .tk-field > label { font-size:11px; font-weight:700; color:#52525b; text-transform:uppercase; letter-spacing:.05em; display:flex; justify-content:space-between; align-items:center; }
        .tk-field > label small { font-weight:500; text-transform:none; letter-spacing:0; color:var(--tk-mute); }
        .tk-field > label .tk-undo { display:none; height:20px; padding:0 7px; border:1px solid #fde68a; border-radius:5px; background:#fffbeb; color:#92400e; font-size:10.5px; font-weight:700; text-transform:none; letter-spacing:0; cursor:pointer; }
        .tk-field.is-changed > label .tk-undo { display:inline-flex; align-items:center; }
        .tk-field input[type=text], .tk-field textarea { width:100%; border:1px solid var(--tk-line); border-radius:8px; padding:9px 11px; font-size:13.5px; color:var(--tk-ink); background:#fff; }
        .tk-field textarea { min-height:120px; resize:vertical; line-height:1.5; font-size:13px; }
        .tk-field input:focus, .tk-field textarea:focus { outline:none; border-color:var(--tk-ink); box-shadow:0 0 0 3px rgba(24,24,27,.08); }
        .tk-field.is-changed input, .tk-field.is-changed textarea, .tk-field.is-changed .tk-pbtn { border-color:#f59e0b; background:#fffbeb; }
        .tk-range { display:grid; grid-template-columns:1fr 64px; gap:10px; align-items:center; }
        .tk-range input[type=range] { width:100%; accent-color:var(--tk-brand); }
        .tk-range input[type=number] { height:36px; border:1px solid var(--tk-line); border-radius:8px; padding:0 6px; font-size:13px; font-weight:700; text-align:center; }
        .tk-ticks { display:flex; gap:4px; }
        .tk-ticks button { flex:1; height:24px; border:1px solid var(--tk-line); border-radius:6px; background:#fff; font-size:11px; font-weight:600; color:#52525b; cursor:pointer; }
        .tk-ticks button:hover { border-color:var(--tk-brand); color:var(--tk-brand); }
        .tk-stpick button.is-active { border-color:var(--tk-ink); background:var(--tk-ink); color:#fff; }
        .tk-field.is-changed .tk-stpick button.is-active { background:#f59e0b; border-color:#f59e0b; }
        .tk-combo { position:relative; }
        .tk-combo input { padding-right:30px; }
        .tk-combo-clear { position:absolute; right:6px; top:7px; height:24px; width:24px; border:0; border-radius:6px; background:transparent; color:var(--tk-mute); font-size:16px; cursor:pointer; }
        .tk-combo-clear:hover { background:var(--tk-soft); color:var(--tk-ink); }
        .tk-combo-list { position:absolute; left:0; right:0; top:calc(100% + 4px); z-index:20; max-height:240px; overflow:auto; background:#fff; border:1px solid var(--tk-line); border-radius:9px; box-shadow:0 12px 28px rgba(24,24,27,.14); padding:4px; }
        .tk-combo-list button { display:block; width:100%; text-align:left; border:0; border-radius:6px; background:transparent; padding:7px 9px; font-size:12.5px; color:var(--tk-ink); cursor:pointer; }
        .tk-combo-list button b { font-family:ui-monospace, Menlo, monospace; color:var(--tk-mute); font-weight:650; margin-right:6px; }
        .tk-combo-list button:hover, .tk-combo-list button.is-hot { background:var(--tk-brand-soft); }
        .tk-combo-list .tk-combo-empty { padding:8px; font-size:12px; color:var(--tk-mute); }
        .tk-pbtn { display:flex; align-items:center; justify-content:space-between; gap:8px; width:100%; min-height:38px; border:1px solid var(--tk-line); border-radius:8px; background:#fff; padding:7px 11px; font-size:13px; color:var(--tk-ink); text-align:left; cursor:pointer; }
        .tk-pbtn:hover { border-color:#a1a1aa; }
        .tk-pbtn.is-root { color:var(--tk-mute); }
        .tk-pbtn::after { content:"⇅"; color:var(--tk-mute); flex:none; }
        .tk-aside-foot { display:flex; gap:8px; align-items:center; padding:12px 16px; border-top:1px solid var(--tk-line); background:#fafafa; }
        .tk-aside-foot a { font-size:12px; font-weight:600; color:var(--tk-brand); text-decoration:none; }
        .tk-aside-foot a:hover { text-decoration:underline; }
        .tk-msg { font-size:12px; color:#be123c; }

        /* ---- docked bars ---- */
        .tk-dock { display:flex; flex-direction:column; gap:8px; padding:0 14px 14px; }
        .tk-dock:empty { display:none; }
        .tk-dock-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:10px 16px; border:1px solid var(--tk-line); border-radius:12px; background:#fff; box-shadow:0 8px 24px rgba(24,24,27,.12); }
        .tk-dock-bar b { font-size:13px; color:var(--tk-ink); }
        .tk-dock-bar .tk-note { font-size:12px; color:var(--tk-mute); }
        .tk-dock-bar.bulk { background:var(--tk-brand-soft); border-color:var(--tk-brand-line); }
        .tk-dock-bar.save { background:#fffbeb; border-color:#fde68a; }
        .tk-dock-bar.save .tk-note { color:#92400e; }
    </style>
</head>
<body class="antialiased">
<div class="tk-app">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <header class="tk-top">
        <h1><i>☰</i> Task WePRO</h1>
        <select id="tkProject" class="tk-select" title="Project">
        <?php foreach (array_keys($projects) as $name): ?>
            <option value="<?= e($name) ?>" <?= $defaultProject === $name ? 'selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
        </select>
        <span class="tk-meta" id="tkNote">Đang tải...</span>
        <span class="tk-grow"></span>
        <a href="index.php" class="tk-btn ghost">← Daily report</a>
        <button type="button" id="tkRefresh" class="tk-btn" title="Tải lại danh sách từ WePRO">⟳ Làm mới</button>
        <button type="button" id="tkCreate" class="tk-btn brand" title="Tạo một hoặc nhiều task">＋ Tạo task</button>
    </header>

    <?php if (empty($wepro['enabled'])): ?>
    <div style="padding:24px">WePRO chưa bật. Vào <a class="underline" href="settings.php">Thiết lập</a> để bật và dán cookie.</div>
    <div></div>
    <?php else: ?>
    <div class="tk-main" id="tkMain">
        <section class="tk-listwrap tk-box">
            <div class="tk-stats" id="tkStats">
                <button type="button" class="tk-stat all is-active" data-status="all"><span>Tất cả</span><b>0</b><i>☰</i></button>
                <button type="button" class="tk-stat doing" data-status="doing"><span>Đang làm</span><b>0</b><i>▶</i></button>
                <button type="button" class="tk-stat new" data-status="new"><span>Mới</span><b>0</b><i>✦</i></button>
                <button type="button" class="tk-stat hold" data-status="on_hold"><span>Tạm hoãn</span><b>0</b><i>❚❚</i></button>
                <button type="button" class="tk-stat done" data-status="completed"><span>Xong</span><b>0</b><i>✓</i></button>
            </div>
            <div class="tk-tools">
                <label class="tk-search"><input type="text" id="tkSearch" placeholder="Tìm theo mã hoặc tên task   /" autocomplete="off"></label>
                <div class="tk-seg" id="tkWho" title="Lọc theo người được giao">
                    <button type="button" class="is-active" data-who="all">Mọi người</button>
                    <button type="button" data-who="me" title="Task giao cho tôi, kể cả task cha; task cha của tôi hiện cả các task con">Của tôi</button>
                    <button type="button" data-who="me-child" title="Chỉ task con / task lẻ giao cho tôi, bỏ qua việc task cha có giao cho tôi hay không">Của tôi · chỉ task con</button>
                </div>
                <div class="tk-seg" id="tkWhen" title="Lọc theo thời gian làm (bắt đầu → hạn)">
                    <button type="button" class="is-active" data-when="all">Mọi lúc</button>
                    <button type="button" data-when="today">Hôm nay</button>
                    <button type="button" data-when="week">Tuần này</button>
                </div>
                <label class="tk-btn sm" title="Tick tất cả đang hiện"><input type="checkbox" id="tkCheckAll" style="accent-color:var(--tk-brand)"> Tick hết</label>
            </div>
            <div class="tk-list" id="tkList"><div class="tk-empty">Đang tải task từ WePRO, khoảng 10 giây...</div></div>
        </section>

        <div class="tk-split" id="tkSplit" title="Kéo để đổi độ rộng, nháy đúp để về mặc định"></div>
        <aside class="tk-aside tk-box">
            <div class="tk-aside-head">
                <button type="button" id="edBack" class="tk-btn sm ghost hidden">←</button>
                <h2>Chi tiết task</h2>
                <span class="tk-code" id="edCode"></span>
                <span class="tk-grow"></span>
                <span class="tk-meta"><span class="tk-kbd">↑</span> <span class="tk-kbd">↓</span> chuyển task</span>
            </div>
            <div class="tk-aside-body" id="edEmpty"><div class="tk-aside-empty"><b>Chưa chọn task</b>Bấm vào một dòng bên trái để sửa tên, mô tả, tiến độ và task cha. Sửa xong nhiều task rồi bấm Lưu một lần.</div></div>
            <div class="tk-aside-body hidden" id="edBody">
                <div class="tk-info" id="edInfo"></div>
                <div class="tk-field" data-f="heading"><label>Tên VN <button type="button" class="tk-undo">↶ hoàn tác</button></label><input type="text" id="edVn" placeholder="Tên task (VN)"></div>
                <div class="tk-field" data-f="en_name"><label>Tên EN <button type="button" class="tk-undo">↶ hoàn tác</button></label><input type="text" id="edEn" placeholder="Tên task (EN), tùy chọn"></div>
                <div class="tk-field" data-f="estimate_hours"><label>Ước lượng (giờ) <button type="button" class="tk-undo">↶ hoàn tác</button></label><input type="number" id="edEst" min="0" step="0.5" placeholder="Nhập số giờ"></div>
                <div class="tk-field" data-f="task_percent">
                    <label>Tiến độ <button type="button" class="tk-undo">↶ hoàn tác</button></label>
                    <div class="tk-range"><input type="range" id="edRange" min="0" max="100" step="5"><input type="number" id="edPct" min="0" max="100" step="5"></div>
                    <div class="tk-ticks"><button type="button" data-v="0">0%</button><button type="button" data-v="25">25%</button><button type="button" data-v="50">50%</button><button type="button" data-v="75">75%</button><button type="button" data-v="100">100%</button></div>
                </div>
                <div class="tk-field" data-f="status"><label>Trạng thái <button type="button" class="tk-undo">↶ hoàn tác</button></label>
                    <div class="tk-ticks tk-stpick"><button type="button" data-v="new">Mới</button><button type="button" data-v="doing">Đang làm</button><button type="button" data-v="on_hold">Tạm hoãn</button><button type="button" data-v="completed">Xong</button></div>
                </div>
                <div class="tk-field" data-f="parent_id"><label>Task cha <button type="button" class="tk-undo">↶ hoàn tác</button></label><div class="tk-combo" id="edParentBox"><input type="text" id="edParent" placeholder="Gõ mã hoặc tên task cha… (trống = task gốc)" autocomplete="off"><button type="button" class="tk-combo-clear" id="edParentClear" title="Bỏ task cha">×</button><div class="tk-combo-list hidden" id="edParentList"></div></div></div>
                <div class="tk-field" data-f="description"><label>Mô tả <small id="edDescHint"></small><button type="button" class="tk-undo">↶ hoàn tác</button></label><textarea id="edDesc" placeholder="Mô tả task..."></textarea><div class="tk-msg hidden" id="edMsg"></div></div>
            </div>
            <div class="tk-aside-foot hidden" id="edFoot">
                <a id="edOpen" href="#" target="_blank" rel="noopener noreferrer">Mở trên WePRO ↗</a>
                <button type="button" id="edLog" class="tk-btn sm" style="color:#0369a1;border-color:#bae6fd;background:#e0f2fe"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Logtime</button>
                <span class="tk-grow"></span>
                <button type="button" id="edPreview" class="tk-btn sm hidden">👁 Xem thay đổi</button>
                <button type="button" id="edRevert" class="tk-btn sm danger hidden">Hủy sửa task này</button>
            </div>
        </aside>
    </div>

    <div class="tk-dock">
        <div id="tkBulk" class="tk-dock-bar bulk hidden">
            <b id="tkBulkCount">0 task đã tick</b>
            <button type="button" id="tkBulkParent" class="tk-btn sm">⤷ Gán task cha</button>
            <select id="tkBulkPercent" class="tk-select" style="height:30px;font-size:12px"><option value="">Đặt tiến độ…</option><?php for ($p = 0; $p <= 100; $p += 5): ?><option value="<?= $p ?>"><?= $p ?>%</option><?php endfor; ?></select>
            <select id="tkBulkStatus" class="tk-select" style="height:30px;font-size:12px"><option value="">Đặt trạng thái…</option><option value="new">Mới</option><option value="doing">Đang làm</option><option value="on_hold">Tạm hoãn</option><option value="completed">Xong</option></select>
            <button type="button" id="tkBulkLog" class="tk-btn sm" style="color:#0369a1;border-color:#bae6fd;background:#e0f2fe"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Logtime</button>
            <span class="tk-grow"></span>
            <button type="button" id="tkBulkNone" class="tk-btn sm ghost">Bỏ tick</button>
        </div>
        <div id="tkSaveBar" class="tk-dock-bar save hidden">
            <b id="tkDirtyCount">0 task có thay đổi</b><span class="tk-note">Chưa gửi lên WePRO. Dòng có chấm vàng là đang sửa dở.</span>
            <span class="tk-grow"></span>
            <button type="button" id="tkPreview" class="tk-btn sm">👁 Xem tất cả thay đổi</button>
            <button type="button" id="tkDiscard" class="tk-btn sm danger">Hủy tất cả thay đổi</button>
            <button type="button" id="tkSave" class="tk-btn primary">Lưu lên WePRO <span class="tk-kbd" style="color:#d4d4d8;border-color:#52525b;background:transparent">Ctrl+S</span></button>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="wepro-create.js?t=<?= e((string)(@filemtime(__DIR__ . '/wepro-create.js') ?: time())) ?>"></script>
<script src="wepro-timelog.js?t=<?= e((string)(@filemtime(__DIR__ . '/wepro-timelog.js') ?: time())) ?>"></script>
<script src="tasks.js?t=<?= e((string)(@filemtime(__DIR__ . '/tasks.js') ?: time())) ?>"></script>
</body>
</html>

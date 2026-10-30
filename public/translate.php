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

function getJson($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200 && $body !== false ? json_decode($body, true) : null;
}

// Both are free and keyless. Google's web endpoint (client=gtx) is unofficial and answers 429
// when it throttles an IP, so MyMemory (5000 chars/day anonymous) takes over.
function translateViToEn($text)
{
    $json = getJson('https://translate.googleapis.com/translate_a/single?' . http_build_query([
        'client' => 'gtx', 'sl' => 'vi', 'tl' => 'en', 'dt' => 't', 'q' => $text,
    ]));
    if (is_array($json) && is_array($json[0] ?? null)) {
        return trim(implode('', array_map(fn ($seg) => (string)($seg[0] ?? ''), $json[0])));
    }
    $json = getJson('https://api.mymemory.translated.net/get?' . http_build_query(['langpair' => 'vi|en', 'q' => $text]));
    if (is_array($json) && (int)($json['responseStatus'] ?? 0) === 200 && empty($json['quotaFinished'])) {
        // Its top hit can be a crowd memory entry ("filterByStatus"); the machine one reads cleaner.
        $text = (string)($json['responseData']['translatedText'] ?? '');
        foreach ((array)($json['matches'] ?? []) as $match) {
            if (($match['created-by'] ?? '') === 'MT!' && trim((string)($match['translation'] ?? '')) !== '') {
                $text = (string)$match['translation'];
                break;
            }
        }
        return trim(html_entity_decode($text, ENT_QUOTES, 'UTF-8')) ?: null;
    }
    return null;
}

$texts = array_slice((array)($_POST['texts'] ?? []), 0, 50);
$out = [];
foreach ($texts as $text) {
    $text = mb_substr(trim((string)$text), 0, 300);
    // "[Backoffice] [API] ..." prefixes are tags, not words: keep them as typed.
    preg_match('/^((?:\[[^\]]*\]\s*)*)(.*)$/us', $text, $m);
    $prefix = $m[1] ?? '';
    $rest = trim($m[2] ?? $text);
    if ($rest === '') {
        $out[] = $text;
        continue;
    }
    $en = translateViToEn($rest);
    if ($en === null) {
        fail('Không dịch được (Google và MyMemory đều lỗi hoặc hết lượt).');
    }
    $out[] = $prefix . $en;
}

echo json_encode(['success' => true, 'texts' => $out], JSON_UNESCAPED_UNICODE);

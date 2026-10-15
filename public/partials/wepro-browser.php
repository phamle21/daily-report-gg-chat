<?php
// Headers an Edge tab sends on an XHR to WePRO, so the app's requests look like the browser's.
// The User-Agent is set separately via CURLOPT_USERAGENT.
function weproBrowserHeaders($baseUrl, $referer = '')
{
    return [
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Accept-Language: en-US,en;q=0.9,ja;q=0.8',
        'X-Requested-With: XMLHttpRequest',
        'sec-ch-ua: "Microsoft Edge";v="155", "Chromium";v="155", "Not(A:Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'Referer: ' . ($referer !== '' ? $referer : $baseUrl . '/account/dashboard'),
    ];
}

// Merge browser headers into a curl option set; explicit headers in $opts win.
function weproBrowserOpts(array $opts, $baseUrl, $referer = '')
{
    $own = $opts[CURLOPT_HTTPHEADER] ?? [];
    $ownNames = array_map(fn ($h) => strtolower(trim(strstr($h, ':', true))), $own);
    $merged = array_filter(weproBrowserHeaders($baseUrl, $referer), fn ($h) => !in_array(strtolower(trim(strstr($h, ':', true))), $ownNames, true));
    $opts[CURLOPT_HTTPHEADER] = array_merge(array_values($merged), $own);
    $opts[CURLOPT_ENCODING] = '';
    return $opts;
}

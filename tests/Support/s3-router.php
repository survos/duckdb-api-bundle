<?php

declare(strict_types=1);

// Minimal path-style S3 transport fixture: HEAD and ranged GET, no AWS service.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!in_array($path, ['/bucket/test.duckdb', '/bucket/test.duckdb.wal'], true)) {
    http_response_code(404);

    return;
}
$file = getcwd().$path;
if (!is_file($file)) {
    http_response_code(404);

    return;
}
if (!str_starts_with($_SERVER['HTTP_AUTHORIZATION'] ?? '', 'AWS4-HMAC-SHA256')) {
    http_response_code(403);

    return;
}
$size = filesize($file);
$start = 0;
$end = $size - 1;
header('Accept-Ranges: bytes');
header('ETag: "duckdb-test-snapshot"');
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d+)-(\d*)$/', $_SERVER['HTTP_RANGE'], $matches)) {
    $start = (int) $matches[1];
    $end = '' === $matches[2] ? $end : min($end, (int) $matches[2]);
    if ($start > $end) {
        http_response_code(416);

        return;
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Type: application/octet-stream');
header('Content-Length: '.($end - $start + 1));
if ('HEAD' !== $_SERVER['REQUEST_METHOD']) {
    $stream = fopen($file, 'rb');
    fseek($stream, $start);
    echo fread($stream, $end - $start + 1);
    fclose($stream);
}

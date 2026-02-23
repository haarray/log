<?php

/**
 * Shared hosting / PHP built-in server bootstrap.
 *
 * Supports:
 * - php -S 127.0.0.1:8000 server.php
 * - Root path access: /, /dashboard, ...
 * - Sub-directory style access: /harray-core, /haaray-core, /haaray
 */

$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = urldecode((string) (parse_url($requestUri, PHP_URL_PATH) ?? '/'));
$query = (string) (parse_url($requestUri, PHP_URL_QUERY) ?? '');
$publicPath = __DIR__ . '/public';
$publicReal = realpath($publicPath) ?: $publicPath;

$candidates = array_values(array_unique(array_filter([
    '/' . trim((string) basename(__DIR__), '/'),
    '/harray-core',
    '/haaray-core',
    '/haaray',
])));

$basePath = '';
foreach ($candidates as $candidate) {
    if ($path === $candidate || str_starts_with($path, $candidate . '/')) {
        $basePath = $candidate;
        break;
    }
}

if ($basePath !== '') {
    $path = substr($path, strlen($basePath)) ?: '/';
}

$resolved = realpath($publicPath . $path);
if ($path !== '/' && $resolved && str_starts_with($resolved, $publicReal) && is_file($resolved)) {
    $extension = strtolower((string) pathinfo($resolved, PATHINFO_EXTENSION));
    $mimeMap = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'mjs' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'map' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'txt' => 'text/plain; charset=UTF-8',
        'html' => 'text/html; charset=UTF-8',
        'xml' => 'application/xml; charset=UTF-8',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4',
    ];

    $mime = $mimeMap[$extension] ?? '';
    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string) (mime_content_type($resolved) ?: '');
    }
    if ($mime !== '') {
        header('Content-Type: ' . $mime);
    }
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . (string) filesize($resolved));
    readfile($resolved);
    exit;
}

if ($basePath !== '') {
    $_SERVER['SCRIPT_NAME'] = $basePath . '/index.php';
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    $_SERVER['REQUEST_URI'] = $path . ($query !== '' ? '?' . $query : '');
}

require_once $publicPath . '/index.php';

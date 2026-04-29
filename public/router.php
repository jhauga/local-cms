<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$publicPath = __DIR__ . ($requestPath === false ? '/' : $requestPath);

if (($requestPath ?? '/') !== '/' && is_file($publicPath)) {
    $extension = strtolower((string) pathinfo($publicPath, PATHINFO_EXTENSION));
    $contentType = match ($extension) {
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };

    header('Content-Type: ' . $contentType);
    readfile($publicPath);

    return true;
}

require __DIR__ . '/index.php';

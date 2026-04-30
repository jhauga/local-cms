<?php
declare(strict_types=1);

use Cms\Core\Env;

$rootPath = dirname(__DIR__);
$jsonConfig = [];
$jsonConfigPath = $rootPath . '/config.json';

if (is_file($jsonConfigPath)) {
    $decoded = json_decode((string) file_get_contents($jsonConfigPath), true);

    if (is_array($decoded)) {
        $jsonConfig = $decoded;
    }
}

$themeConfig = is_array($jsonConfig['theme'] ?? null) ? $jsonConfig['theme'] : [];
$applicationConfig = is_array($jsonConfig['application'] ?? null) ? $jsonConfig['application'] : [];

$normalizeUploadDatePath = static function (mixed $value): array {
    if (!is_array($value)) {
        return ['YYYY', 'MM'];
    }

    $normalized = [];

    foreach ($value as $segment) {
        $rawSegment = trim((string) $segment);

        if ($rawSegment === '') {
            continue;
        }

        if (strtoupper($rawSegment) === 'YYYY' || $rawSegment === 'Y') {
            $normalized[] = 'YYYY';
            continue;
        }

        if (strtoupper($rawSegment) === 'MM' || $rawSegment === 'm') {
            $normalized[] = 'MM';
        }
    }

    return $normalized;
};

$configuredTheme = trim((string) Env::get('APP_THEME', (string) ($themeConfig['name'] ?? 'default')));
$themeName = $configuredTheme !== '' && is_dir($rootPath . '/themes/' . $configuredTheme)
    ? $configuredTheme
    : 'default';

$configuredMediaDirectory = trim((string) ($themeConfig['media'] ?? 'img'));
$themeMediaDirectory = preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $configuredMediaDirectory) === 1
    ? $configuredMediaDirectory
    : 'img';

$uploadDatePath = $normalizeUploadDatePath($applicationConfig['uploads']['datePath'] ?? ['YYYY', 'MM']);

return [
    'name' => (string) Env::get('APP_NAME', 'Local CMS'),
    'tagline' => (string) Env::get('APP_TAGLINE', 'A simple content studio with WordPress-shaped theme templates.'),
    'environment' => (string) Env::get('APP_ENV', 'local'),
    'debug' => filter_var((string) Env::get('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
    'theme' => [
        'name' => $themeName,
        'media' => $themeMediaDirectory,
    ],
    'application' => array_replace_recursive([
        'mode' => 'local',
        'admin' => [
            'title' => 'Local CMS Studio',
            'chrome' => 'editorial',
        ],
        'content' => [
            'defaultFormat' => 'markdown',
            'previews' => true,
            'clientConverter' => false,
        ],
        'uploads' => [
            'datePath' => ['YYYY', 'MM'],
        ],
    ], $applicationConfig, [
        'uploads' => [
            'datePath' => $uploadDatePath,
        ],
    ]),
];

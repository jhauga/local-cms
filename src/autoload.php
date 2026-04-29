<?php
declare(strict_types=1);

spl_autoload_register(static function (string $className): void {
    $prefix = 'Cms\\';

    if (strncmp($className, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $filePath = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($filePath)) {
        require $filePath;
    }
});

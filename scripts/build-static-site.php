<?php
declare(strict_types=1);

use Cms\Build\StaticSiteBuilder;
use Cms\Core\Database;
use Cms\Core\Env;
use Cms\Repositories\ContentRepository;

$rootPath = dirname(__DIR__);

chdir($rootPath);
require $rootPath . '/src/autoload.php';

$app = require $rootPath . '/bootstrap/app.php';
$exportPath = $rootPath . '/export';
$appConfig = require $rootPath . '/config/app.php';
$sitePath = parse_url((string) ($appConfig['url'] ?? Env::get('APP_URL', '/')), PHP_URL_PATH) ?: '/';
$builder = new StaticSiteBuilder($app, new ContentRepository(Database::connection()), $exportPath, $sitePath);

try {
    $writtenFiles = $builder->build();

    fwrite(STDOUT, 'Static export complete: ' . count($writtenFiles) . ' files written to export/' . PHP_EOL);

    foreach ($writtenFiles as $file) {
        fwrite(STDOUT, ' - ' . $file . PHP_EOL);
    }

    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Static export failed: ' . $throwable->getMessage() . PHP_EOL);

    exit(1);
}
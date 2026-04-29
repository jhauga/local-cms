<?php
declare(strict_types=1);

use Cms\Core\Env;

return [
    'connection' => (string) Env::get('DB_CONNECTION', 'sqlite'),
    'database' => (string) Env::get('DB_DATABASE', 'storage/database/cms.sqlite'),
    'root_path' => dirname(__DIR__),
    'schema' => dirname(__DIR__) . '/database/schema.sql',
    'migrations_path' => dirname(__DIR__) . '/database/migrations',
];

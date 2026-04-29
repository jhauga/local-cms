<?php
declare(strict_types=1);

define('CMS_ROOT', dirname(__DIR__));

require CMS_ROOT . '/src/autoload.php';

$app = require CMS_ROOT . '/bootstrap/app.php';
$app->run();

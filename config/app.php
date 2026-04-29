<?php
declare(strict_types=1);

use Cms\Core\Env;

return [
    'name' => (string) Env::get('APP_NAME', 'Local CMS'),
    'tagline' => (string) Env::get('APP_TAGLINE', 'A simple content studio with WordPress-shaped theme templates.'),
    'environment' => (string) Env::get('APP_ENV', 'local'),
    'debug' => filter_var((string) Env::get('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
    'theme' => (string) Env::get('APP_THEME', 'default'),
];

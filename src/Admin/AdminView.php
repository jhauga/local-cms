<?php
declare(strict_types=1);

namespace Cms\Admin;

use RuntimeException;

final class AdminView
{
    private string $viewPath;

    public function __construct(string $rootPath)
    {
        $this->viewPath = rtrim($rootPath, '/\\') . '/views/admin';

        if (!is_dir($this->viewPath)) {
            throw new RuntimeException('Admin view directory does not exist.');
        }
    }

    public function render(string $template, array $data = []): string
    {
        $contentTemplate = $this->resolveTemplate($template);

        $viewData = array_merge([
            'pageTitle' => 'Admin',
            'siteName' => 'Local CMS',
            'stylesheets' => ['/assets/admin.css'],
        ], $data);

        ob_start();
        extract($viewData, EXTR_SKIP);

        require $this->viewPath . '/layout.php';

        return (string) ob_get_clean();
    }

    private function resolveTemplate(string $template): string
    {
        $templatePath = $this->viewPath . '/' . $template . '.php';

        if (!is_file($templatePath)) {
            throw new RuntimeException('Admin template not found: ' . $template);
        }

        return $templatePath;
    }
}

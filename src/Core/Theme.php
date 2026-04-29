<?php
declare(strict_types=1);

namespace Cms\Core;

use Cms\Http\Response;
use RuntimeException;
use Throwable;

final class Theme
{
    private string $themePath;

    public function __construct(private string $rootPath, private string $themeName)
    {
        $this->themePath = rtrim($this->rootPath, '/\\') . '/themes/' . $this->themeName;

        if (!is_dir($this->themePath)) {
            throw new RuntimeException('The configured theme directory does not exist.');
        }

        require_once $this->rootPath . '/src/Support/WordPressCompat.php';

        $functionsPath = $this->themePath . '/functions.php';

        if (is_file($functionsPath)) {
            require_once $functionsPath;
        }
    }

    public function render(string $template, array $data = [], array $meta = []): string
    {
        $viewData = array_merge(
            [
                'pageTitle' => (string) ($meta['pageTitle'] ?? $data['siteName'] ?? 'Local CMS'),
                'pageDescription' => (string) ($meta['pageDescription'] ?? $data['siteTagline'] ?? ''),
                'siteName' => (string) ($data['siteName'] ?? 'Local CMS'),
                'siteTagline' => (string) ($data['siteTagline'] ?? ''),
                'navigation' => $data['navigation'] ?? [],
                'currentPath' => (string) ($data['currentPath'] ?? '/'),
                'stylesheetUrl' => (string) ($data['stylesheetUrl'] ?? $this->stylesheetUrl()),
            ],
            $data
        );

        ThemeRuntime::boot($this->themePath, $template, $viewData);
        ob_start();

        try {
            $this->includeTemplate($this->resolveTemplate($template), $viewData);

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();

            throw $exception;
        } finally {
            ThemeRuntime::clear();
        }
    }

    public function assetResponse(string $assetName): Response
    {
        if (preg_match('/^[A-Za-z0-9._-]+$/', $assetName) !== 1) {
            return new Response('Asset not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $assetPath = $this->themePath . '/' . $assetName;

        if (!is_file($assetPath)) {
            return new Response('Asset not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $asset = file_get_contents($assetPath);

        if ($asset === false) {
            return new Response('Asset could not be read.', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return new Response(
            $asset,
            200,
            ['Content-Type' => $this->detectContentType($assetPath) . '; charset=UTF-8']
        );
    }

    public function stylesheetUrl(): string
    {
        return '/theme/style.css';
    }

    private function resolveTemplate(string $template): string
    {
        $templatePath = $this->themePath . '/' . $template . '.php';

        if (!is_file($templatePath)) {
            throw new RuntimeException('The requested theme template does not exist: ' . $template);
        }

        return $templatePath;
    }

    private function includeTemplate(string $filePath, array $data): void
    {
        extract($data, EXTR_SKIP);
        $theme = $this;

        require $filePath;
    }

    private function detectContentType(string $filePath): string
    {
        return match (pathinfo($filePath, PATHINFO_EXTENSION)) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'svg' => 'image/svg+xml',
            default => 'text/plain',
        };
    }
}

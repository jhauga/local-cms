<?php
declare(strict_types=1);

namespace Cms\Core;

use Cms\Http\Response;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class Theme
{
    private const PUBLIC_ASSET_EXTENSIONS = ['css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'avif'];

    private string $themePath;

    public function __construct(private string $rootPath, private string $themeName, private string $mediaDirectory = 'img')
    {
        $this->themePath = rtrim($this->rootPath, '/\\') . '/themes/' . $this->themeName;
        $this->mediaDirectory = trim($this->mediaDirectory, '/\\');
        $this->mediaDirectory = $this->mediaDirectory !== '' ? $this->mediaDirectory : 'img';

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
        $normalizedAssetName = trim(str_replace('\\', '/', $assetName), '/');

        if ($normalizedAssetName === '') {
            return new Response('Asset not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if (preg_match('#(^|/)\.\.?(/|$)#', $normalizedAssetName) === 1 || preg_match('#(^|/)\.[^/]+#', $normalizedAssetName) === 1) {
            return new Response('Asset not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if (preg_match('#^[A-Za-z0-9._/-]+$#', $normalizedAssetName) !== 1) {
            return new Response('Asset not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $assetPath = $this->themePath . '/' . $normalizedAssetName;

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
        return $this->assetUrl('style.css');
    }

    public function assetUrl(string $path = ''): string
    {
        if ($path === '') {
            return '/theme';
        }

        return '/theme/' . ltrim($path, '/');
    }

    public function mediaDirectory(): string
    {
        return $this->mediaDirectory;
    }

    public function mediaUrl(string $path = ''): string
    {
        if ($path === '') {
            return $this->assetUrl($this->mediaDirectory);
        }

        return $this->assetUrl($this->mediaDirectory . '/' . ltrim($path, '/'));
    }

    public function publicAssetPaths(): array
    {
        $assets = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->themePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($this->themePath) + 1));

            if ($relativePath === '' || preg_match('#(^|/)\.[^/]+#', $relativePath) === 1) {
                continue;
            }

            $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

            if (!in_array($extension, self::PUBLIC_ASSET_EXTENSIONS, true)) {
                continue;
            }

            $assets[] = $relativePath;
        }

        sort($assets);

        return $assets;
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
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'avif' => 'image/avif',
            default => 'text/plain',
        };
    }
}

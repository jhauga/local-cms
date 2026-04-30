<?php
declare(strict_types=1);

namespace Cms\Build;

use Cms\Core\App;
use Cms\Http\Request;
use Cms\Repositories\ContentRepository;
use Cms\Support\Uploads;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class StaticSiteBuilder
{
    private const GENERATED_DIRECTORIES = [
        'post',
        'posts',
        'page',
        'category',
        'tag',
        'theme',
        'uploads',
        'assets',
    ];

    private const GENERATED_FILES = [
        'index.html',
        'build-manifest.json',
    ];

    public function __construct(
        private App $app,
        private ContentRepository $contentRepository,
        private string $exportPath,
        private string $sitePath = '/',
    ) {
        $this->sitePath = $this->normalizeSitePath($this->sitePath);
    }

    public function build(): array
    {
        $this->prepareExportRoot();

        $routes = $this->routesToExport();
        $writtenFiles = [];

        foreach ($routes as $route) {
            $writtenFiles[] = $this->renderRouteToFile($route['route'], $route['target'], $route['type']);
        }

        $writtenFiles = array_merge($writtenFiles, $this->copyPublicDirectory(Uploads::publicDirectoryPath(), 'uploads'));

        $this->writeManifest($routes, $writtenFiles);

        return $writtenFiles;
    }

    private function prepareExportRoot(): void
    {
        if (!is_dir($this->exportPath) && !mkdir($concurrentDirectory = $this->exportPath, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Unable to create export directory at ' . $this->exportPath);
        }

        foreach (self::GENERATED_DIRECTORIES as $directory) {
            $path = $this->exportPath . DIRECTORY_SEPARATOR . $directory;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            }
        }

        foreach (self::GENERATED_FILES as $fileName) {
            $path = $this->exportPath . DIRECTORY_SEPARATOR . $fileName;

            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('Unable to remove previous export file at ' . $path);
            }
        }
    }

    private function routesToExport(): array
    {
        $routes = [
            ['route' => '/', 'target' => 'index.html', 'type' => 'page'],
            ['route' => '/posts', 'target' => 'posts/index.html', 'type' => 'archive'],
        ];

        foreach ($this->app->theme()->publicAssetPaths() as $assetPath) {
            $routes[] = [
                'route' => '/theme/' . $assetPath,
                'target' => 'theme/' . $assetPath,
                'type' => 'asset',
            ];
        }

        foreach ($this->publicAssetPaths() as $assetPath) {
            $routes[] = [
                'route' => '/assets/' . $assetPath,
                'target' => 'assets/' . $assetPath,
                'type' => 'asset',
            ];
        }

        foreach ($this->contentRepository->publishedPages() as $page) {
            $slug = (string) ($page['slug'] ?? '');

            if ($slug === '' || $slug === 'home') {
                continue;
            }

            $routes[] = [
                'route' => '/page/' . $slug,
                'target' => 'page/' . $slug . '/index.html',
                'type' => 'page',
            ];
        }

        foreach ($this->contentRepository->allPosts() as $post) {
            $slug = (string) ($post['slug'] ?? '');

            if ($slug === '') {
                continue;
            }

            $routes[] = [
                'route' => '/post/' . $slug,
                'target' => 'post/' . $slug . '/index.html',
                'type' => 'post',
            ];
        }

        foreach ($this->contentRepository->termsByTaxonomy('category') as $term) {
            $slug = (string) ($term['slug'] ?? '');

            if ($slug === '') {
                continue;
            }

            $routes[] = [
                'route' => '/category/' . $slug,
                'target' => 'category/' . $slug . '/index.html',
                'type' => 'category',
            ];
        }

        foreach ($this->contentRepository->termsByTaxonomy('tag') as $term) {
            $slug = (string) ($term['slug'] ?? '');

            if ($slug === '') {
                continue;
            }

            $routes[] = [
                'route' => '/tag/' . $slug,
                'target' => 'tag/' . $slug . '/index.html',
                'type' => 'tag',
            ];
        }

        return $this->uniqueRoutes($routes);
    }

    private function uniqueRoutes(array $routes): array
    {
        $unique = [];
        $seen = [];

        foreach ($routes as $route) {
            $key = (string) ($route['target'] ?? '');

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $route;
        }

        return $unique;
    }

    private function renderRouteToFile(string $route, string $relativeTargetPath, string $type): string
    {
        $response = $this->app->handle(new Request('GET', $route));
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new RuntimeException(sprintf('Failed to build route "%s". Expected 200, got %d.', $route, $statusCode));
        }

        $targetPath = $this->exportPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeTargetPath);
        $directory = dirname($targetPath);

        if (!is_dir($directory) && !mkdir($concurrentDirectory = $directory, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Unable to create export directory at ' . $directory);
        }

        $content = $response->getContent();

        if ($type !== 'asset') {
            $content = $this->rewriteHtmlForSitePath($content);
        }

        if (file_put_contents($targetPath, $content) === false) {
            throw new RuntimeException('Unable to write export file at ' . $targetPath);
        }

        return str_replace('\\', '/', ltrim(substr($targetPath, strlen($this->exportPath)), '/\\'));
    }

    private function writeManifest(array $routes, array $writtenFiles): void
    {
        $manifestPath = $this->exportPath . DIRECTORY_SEPARATOR . 'build-manifest.json';
        $manifest = [
            'generated_at' => gmdate('c'),
            'routes' => array_values($routes),
            'files' => array_values($writtenFiles),
        ];

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents($manifestPath, $json . PHP_EOL) === false) {
            throw new RuntimeException('Unable to write export manifest.');
        }
    }

    private function copyPublicDirectory(string $sourcePath, string $targetRelativePath): array
    {
        if (!is_dir($sourcePath)) {
            return [];
        }

        $targetRoot = $this->exportPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($targetRelativePath, '/'));
        $writtenFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($sourcePath) + 1));

            if ($relativePath === '' || preg_match('#(^|/)\.[^/]+#', $relativePath) === 1) {
                continue;
            }

            $destinationPath = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $destinationDirectory = dirname($destinationPath);

            if (!is_dir($destinationDirectory) && !mkdir($concurrentDirectory = $destinationDirectory, 0777, true) && !is_dir($concurrentDirectory)) {
                throw new RuntimeException('Unable to create export directory at ' . $destinationDirectory);
            }

            if (!copy($file->getPathname(), $destinationPath)) {
                throw new RuntimeException('Unable to copy uploaded asset to ' . $destinationPath);
            }

            $writtenFiles[] = trim($targetRelativePath, '/') . '/' . $relativePath;
        }

        return $writtenFiles;
    }

    private function publicAssetPaths(): array
    {
        $assetsDir = dirname($this->exportPath) . '/public/assets';

        if (!is_dir($assetsDir)) {
            return [];
        }

        $extensions = ['css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'avif'];
        $assets = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($assetsDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($assetsDir) + 1));

            if ($relativePath === '' || preg_match('#(^|/)\.[^/]+#', $relativePath) === 1) {
                continue;
            }

            if (!in_array(strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)), $extensions, true)) {
                continue;
            }

            $assets[] = $relativePath;
        }

        sort($assets);

        return $assets;
    }

    private function removeDirectory(string $directory): void
    {
        $items = scandir($directory);

        if ($items === false) {
            throw new RuntimeException('Unable to read export directory at ' . $directory);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            if (!unlink($path)) {
                throw new RuntimeException('Unable to remove export file at ' . $path);
            }
        }

        if (!rmdir($directory)) {
            throw new RuntimeException('Unable to remove export directory at ' . $directory);
        }
    }

    private function rewriteHtmlForSitePath(string $content): string
    {
        if ($this->sitePath === '/') {
            return $content;
        }

        return preg_replace_callback(
            '/\b(href|src|action|poster)=(["\'])\/(?!\/)([^"\']*)\2/i',
            function (array $matches): string {
                $attribute = $matches[1];
                $quote = $matches[2];
                $path = $matches[3];
                $siteRelativePath = ltrim($this->sitePath, '/');

                if ($siteRelativePath !== '' && ($path === $siteRelativePath || str_starts_with($path, $siteRelativePath . '/'))) {
                    return $matches[0];
                }

                $prefixedPath = $this->sitePath;

                if ($path !== '') {
                    $prefixedPath .= '/' . ltrim($path, '/');
                } else {
                    $prefixedPath .= '/';
                }

                return sprintf('%s=%s%s%s', $attribute, $quote, $prefixedPath, $quote);
            },
            $content,
        ) ?? $content;
    }

    private function normalizeSitePath(string $sitePath): string
    {
        $sitePath = trim($sitePath);

        if ($sitePath === '' || $sitePath === '/') {
            return '/';
        }

        return '/' . trim($sitePath, '/');
    }
}
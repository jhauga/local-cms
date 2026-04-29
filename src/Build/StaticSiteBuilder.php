<?php
declare(strict_types=1);

namespace Cms\Build;

use Cms\Core\App;
use Cms\Http\Request;
use Cms\Repositories\ContentRepository;
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
            ['route' => '/theme/style.css', 'target' => 'theme/style.css', 'type' => 'asset'],
        ];

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
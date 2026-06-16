<?php
declare(strict_types=1);

namespace Cms\Core;

use Cms\Http\Response;
use Cms\Support\ThemeFallbackRegistry;
use Cms\Support\ThemeFunctionBridge;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class Theme
{
    private const PUBLIC_ASSET_EXTENSIONS = ['css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'avif'];

    private string $themePath;

    /** Whether functions.php has already been required for this theme instance. */
    private bool $functionsLoaded = false;

    public function __construct(private string $rootPath, private string $themeName, private string $mediaDirectory = 'img')
    {
        $this->themePath = rtrim($this->rootPath, '/\\') . '/themes/' . $this->themeName;
        $this->mediaDirectory = trim($this->mediaDirectory, '/\\');
        $this->mediaDirectory = $this->mediaDirectory !== '' ? $this->mediaDirectory : 'img';

        if (!is_dir($this->themePath)) {
            throw new RuntimeException('The configured theme directory does not exist.');
        }

        // Load the compatibility shim definitions here so the function symbols are
        // available globally as soon as the theme object exists.  functions.php is
        // loaded lazily in render() instead: loading it here would run it at
        // bootstrap time (before any routing), meaning an uncatchable exit()/die()
        // in a ported theme would terminate the whole request — including admin
        // pages — before a single route is evaluated.
        require_once $this->rootPath . '/src/Support/WordPressCompat.php';
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

        $this->loadFunctions();

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

    /**
     * Require the theme's functions.php exactly once per Theme instance.
     *
     * Called immediately before rendering so that a theme whose functions.php
     * contains an uncatchable exit()/die() (e.g. an un-neutralized WordPress
     * access guard) only terminates the site render, not admin routes, which
     * never call render().
     */
    private function loadFunctions(): void
    {
        if ($this->functionsLoaded) {
            return;
        }

        $this->functionsLoaded = true;

        // Seed the theme location globals for compatibility shims
        // (get_template_directory(), wp_get_theme(), etc.) right before
        // functions.php executes so they are correct for this theme.
        $GLOBALS['localcms_theme_directory'] = $this->themePath;
        $GLOBALS['localcms_theme_uri'] = '/theme';

        // Detect whether this theme was authored for Local CMS ("native") or
        // ported from WordPress.  Ported themes have a LICENSE_NOTE.txt created
        // by the port-cms adapter, or lack the strict_types marker.
        $native = $this->detectNativeTheme();
        $GLOBALS['localcms_theme_native'] = $native;

        // For ported WordPress themes, stand up the function bridge: shim the
        // external dependencies the theme calls but never defines, so its
        // functions.php can load fully (recovering its own real helpers) instead
        // of aborting on the first undefined call and leaving later helpers — the
        // ones its templates need — undefined. Native themes are clean and skip
        // this entirely.
        $bridgeActive = false;

        if (!$native) {
            try {
                ThemeFunctionBridge::activate(ThemeFallbackRegistry::load($this->rootPath));
                ThemeFunctionBridge::preload($this->themePath, $this->rootPath);
                $bridgeActive = true;
            } catch (Throwable) {
            }
        }

        $functionsPath = $this->themePath . '/functions.php';

        if (is_file($functionsPath)) {
            // Catch PHP errors and exceptions from unshimmed symbols.  Note:
            // exit()/die() calls are NOT catchable and will still terminate
            // the process, but because this is deferred to render time the
            // admin routes are never affected.
            try {
                require_once $functionsPath;
            } catch (Throwable) {
            }
        }

        // Last safety net: shim any function the theme's templates call that is
        // still undefined after functions.php ran (e.g. a helper whose defining
        // file never loaded), so the render cannot fatal on an undefined call.
        if ($bridgeActive) {
            try {
                ThemeFunctionBridge::finalize($this->themePath, $this->rootPath);
            } catch (Throwable) {
            }
        }
    }

    /**
     * Detect whether this theme is a native Local CMS theme or a WordPress port.
     *
     * Returns true for native themes (authored for this runtime), false for
     * ported WordPress themes.  The detection order is:
     *  1. style.css "Local CMS Runtime: native" header → native
     *  2. style.css "Local CMS Origin: local-cms|native|local" header → native
     *  3. LICENSE_NOTE.txt present (written by port-cms adapter) → ported
     *  4. functions.php opens with declare(strict_types=1) → native
     *  5. Default → ported (conservative fallback)
     */
    private function detectNativeTheme(): bool
    {
        $styleCss = $this->themePath . '/style.css';
        if (is_file($styleCss)) {
            $head = (string) substr((string) file_get_contents($styleCss), 0, 2048);
            if (preg_match('/^Local CMS Runtime\s*:\s*native/mi', $head)) {
                return true;
            }
            if (preg_match('/^Local CMS Origin\s*:\s*(local-?cms|native|local)\b/mi', $head)) {
                return true;
            }
        }
        if (is_file($this->themePath . '/LICENSE_NOTE.txt')) {
            return false;
        }
        $functionsPath = $this->themePath . '/functions.php';
        if (is_file($functionsPath)) {
            $head = (string) substr((string) file_get_contents($functionsPath), 0, 1024);
            if (str_contains($head, 'declare(strict_types=1)')) {
                return true;
            }
        }
        return false;
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
        // Build a WordPress-style template hierarchy for each template type.
        // This allows ported themes that don't have index.php to still render
        // using an equivalent template (home.php, front-page.php, etc.).
        $candidates = $this->templateHierarchy($template);

        foreach ($candidates as $candidate) {
            $path = $this->themePath . '/' . $candidate . '.php';
            if (is_file($path)) {
                return $path;
            }
        }

        // Wider search: WordPress themes sometimes keep templates in sub-folders.
        $widerCandidates = [
            'page-template/front-page',
            'page-template/home',
            'page-templates/front-page',
            'page-templates/home',
        ];
        foreach ($widerCandidates as $candidate) {
            $path = $this->themePath . '/' . $candidate . '.php';
            if (is_file($path)) {
                return $path;
            }
        }

        // If the theme has a functions.php but no renderable template, generate
        // a graceful stub in memory via an output-buffer include of a minimal
        // WordPress-shaped template that shows the site name and a notice.
        // This avoids a crash and lets the admin diagnose the situation.
        $stubPath = $this->rootPath . '/storage/tmp/missing-template-stub.php';
        if (!is_file($stubPath)) {
            file_put_contents($stubPath, implode("\n", [
                '<?php get_header(); ?>',
                '<main style="padding:2rem;font-family:sans-serif">',
                '  <h1><?php bloginfo("name"); ?></h1>',
                '  <p><em>This theme does not have a <code>' . $template . '.php</code> template.',
                '  Run the port-cms adapter to generate template stubs, or add one manually.</em></p>',
                '</main>',
                '<?php get_footer(); ?>',
            ]));
        }
        return $stubPath;
    }

    /**
     * Returns an ordered list of template file names (without .php extension)
     * to try for the given logical template name, mirroring WordPress hierarchy.
     *
     * @return string[]
     */
    private function templateHierarchy(string $template): array
    {
        // Common fallbacks shared by all templates
        $base = ['index'];

        return match ($template) {
            'index'   => ['front-page', 'home', 'index'],
            'home'    => ['front-page', 'home', 'index'],
            'single'  => ['single-post', 'single', 'singular', 'index'],
            'page'    => ['page', 'singular', 'index'],
            'archive' => ['archive', 'index'],
            'search'  => ['search', 'index'],
            '404'     => ['404', 'index'],
            default   => [$template, ...$base],
        };
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

<?php
declare(strict_types=1);

use Cms\Admin\AdminController;
use Cms\Admin\AdminView;
use Cms\Core\App;
use Cms\Core\Config;
use Cms\Core\Database;
use Cms\Core\Env;
use Cms\Core\Theme;
use Cms\Http\Request;
use Cms\Http\Response;
use Cms\Http\Router;
use Cms\Repositories\AdminRepository;
use Cms\Repositories\ContentRepository;
use Cms\Security\Auth;
use Cms\Security\Session;
use Cms\Support\MarkdownTemplateCatalog;
use Cms\Support\PluginCatalog;
use Cms\Support\ThemeCatalog;
use Cms\Support\WordPressThemeDirectory;

$rootPath = dirname(__DIR__);

Env::load($rootPath . '/.env');
Session::start();

$config = Config::fromArray([
    'app' => require $rootPath . '/config/app.php',
    'database' => require $rootPath . '/config/database.php',
]);

Database::bootstrap($config->get('database'));

$contentRepository = new ContentRepository(Database::connection());
$adminRepository = new AdminRepository(Database::connection());
$auth = new Auth($adminRepository);
$adminView = new AdminView($rootPath);
$themeCatalog = new ThemeCatalog($rootPath);
$wordPressThemeDirectory = new WordPressThemeDirectory($rootPath);
$pluginCatalog = new PluginCatalog($rootPath);
$adminController = new AdminController($config, $adminView, $adminRepository, $auth, $themeCatalog, $wordPressThemeDirectory, $pluginCatalog, $rootPath);
$configuredTheme = (string) $config->get('app.theme.name', 'default');
$themeMedia = (string) $config->get('app.theme.media', 'img');

// Constructing a theme loads its functions.php. A foreign WordPress theme (for
// example one installed from the WordPress.org directory) may call exit()/die()
// when WordPress is absent, terminating the process uncatchably, or fatal on an
// undefined WordPress-core API. Neither can be guarded with try/catch around the
// construction. So the configured theme is gated on the static style.css runtime
// marker first, and only constructed when it is marked compatible; the default
// theme is always compatible. A try/catch remains as a secondary net for a
// marked theme that still fails with a catchable error. On any fallback, when
// the selection came from config.json (not the APP_THEME override), heal it back
// to the default so the site stays reachable and the failure does not repeat.
$effectiveTheme = ($configuredTheme === 'default' || $themeCatalog->isLocalRuntimeCompatible($configuredTheme))
    ? $configuredTheme
    : 'default';
$themeFellBack = $effectiveTheme !== $configuredTheme;

try {
    $theme = new Theme($rootPath, $effectiveTheme, $themeMedia);
} catch (Throwable $themeError) {
    $theme = new Theme($rootPath, 'default', $themeMedia);
    $themeFellBack = $themeFellBack || $effectiveTheme !== 'default';
}

if ($themeFellBack && $configuredTheme !== 'default' && trim((string) Env::get('APP_THEME', '')) === '') {
    try {
        $themeCatalog->activate('default');
    } catch (Throwable) {
        // If config.json cannot be rewritten, the runtime fallback above is still
        // in effect, so the site keeps working with the default theme.
    }

    Session::flash('error', sprintf(
        'The "%s" theme is not compatible with the local runtime and was reset to the default theme. WordPress.org themes are available for export and porting, not for rendering in the local runtime.',
        $configuredTheme
    ));
}
$router = new Router();

$sharedData = static function (string $currentPath) use ($contentRepository, $config, $theme): array {
    $settings = $contentRepository->getSettings();
    $siteName = (string) ($settings['site_name'] ?? $config->get('app.name', 'Local CMS'));
    $siteTagline = (string) ($settings['site_tagline'] ?? $config->get('app.tagline', 'A simple content studio with WordPress-shaped theme templates.'));
    $pages = $contentRepository->publishedPages();

    $navigation = [
        [
            'label' => 'Home',
            'url' => '/',
            'active' => $currentPath === '/',
        ],
        [
            'label' => 'Posts',
            'url' => '/posts',
            'active' => $currentPath === '/posts' || str_starts_with($currentPath, '/post/'),
        ],
    ];

    foreach ($pages as $page) {
        $slug = (string) $page['slug'];

        if ($slug === 'home') {
            continue;
        }

        $url = '/page/' . $slug;

        $navigation[] = [
            'label' => (string) $page['title'],
            'url' => $url,
            'active' => $currentPath === $url,
        ];
    }

    return [
        'siteName' => $siteName,
        'siteTagline' => $siteTagline,
        'markdownTemplateMap' => MarkdownTemplateCatalog::mapFromSettings($settings),
        'homeUrl' => parse_url((string) $config->get('app.url', '/'), PHP_URL_PATH) ?: '/',
        'navigation' => $navigation,
        'currentPath' => $currentPath,
        'stylesheetUrl' => $theme->stylesheetUrl(),
        'themeAssetBaseUrl' => $theme->assetUrl(),
        'themeMediaDirectory' => $theme->mediaDirectory(),
        'themeMediaUrl' => $theme->mediaUrl(),
        'categories' => $contentRepository->termsByTaxonomy('category'),
        'clientConverterEnabled' => (bool) $config->get('app.application.content.clientConverter', false),
    ];
};

$missingPage = static function (string $path, string $title, string $message) use ($sharedData, $theme): Response {
    $body = $theme->render(
        'page',
        array_merge(
            $sharedData($path),
            [
                'page' => [
                    'title' => $title,
                    'excerpt' => $message,
                    'body_html' => '<p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
                ],
            ]
        ),
        ['pageTitle' => $title]
    );

    return new Response($body, 404);
};

$router->get('/theme/{assetPath*}', static function (Request $request, array $parameters) use ($theme): Response {
    return $theme->assetResponse((string) ($parameters['assetPath'] ?? ''));
});

$publicAssetsPath = $rootPath . '/public/assets';

$router->get('/assets/{assetPath*}', static function (Request $request, array $parameters) use ($publicAssetsPath): Response {
    $assetName = trim(str_replace('\\', '/', (string) ($parameters['assetPath'] ?? '')), '/');

    if (
        $assetName === '' ||
        preg_match('#(^|/)\.\.?(/|$)#', $assetName) === 1 ||
        preg_match('#(^|/)\.[^/]+#', $assetName) === 1 ||
        preg_match('#^[A-Za-z0-9._/-]+$#', $assetName) !== 1
    ) {
        return new Response('Asset not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    $filePath = $publicAssetsPath . '/' . $assetName;

    if (!is_file($filePath)) {
        return new Response('Asset not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    $content = file_get_contents($filePath);

    if ($content === false) {
        return new Response('Asset could not be read.', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    $contentType = match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
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

    return new Response($content, 200, ['Content-Type' => $contentType . '; charset=UTF-8']);
});

$router->get('/admin', static function (Request $request) use ($adminController): Response {
    return $adminController->home($request);
});

$router->get('/admin/login', static function (Request $request) use ($adminController): Response {
    return $adminController->showLogin($request);
});

$router->post('/admin/login', static function (Request $request) use ($adminController): Response {
    return $adminController->login($request);
});

$router->post('/admin/logout', static function (Request $request) use ($adminController): Response {
    return $adminController->logout($request);
});

$router->post('/admin/preview/markdown', static function (Request $request) use ($adminController): Response {
    return $adminController->previewMarkdown($request);
});

$router->get('/admin/pages', static function (Request $request) use ($adminController): Response {
    return $adminController->contentIndex($request, 'page');
});

$router->get('/admin/pages/create', static function (Request $request) use ($adminController): Response {
    return $adminController->createContentForm($request, 'page');
});

$router->post('/admin/pages/create', static function (Request $request) use ($adminController): Response {
    return $adminController->storeContent($request, 'page');
});

$router->get('/admin/pages/{id}', static function (Request $request, array $parameters) use ($adminController): Response {
    return $adminController->editContentForm($request, 'page', (int) ($parameters['id'] ?? 0));
});

$router->post('/admin/pages/{id}', static function (Request $request, array $parameters) use ($adminController): Response {
    return $adminController->updateContent($request, 'page', (int) ($parameters['id'] ?? 0));
});

$router->post('/admin/pages/{id}/delete', static function (Request $request, array $parameters) use ($adminController): Response {
    return $adminController->deleteContent($request, 'page', (int) ($parameters['id'] ?? 0));
});

$router->get('/admin/posts', static function (Request $request) use ($adminController): Response {
    return $adminController->contentIndex($request, 'post');
});

$router->get('/admin/posts/create', static function (Request $request) use ($adminController): Response {
    return $adminController->createContentForm($request, 'post');
});

$router->post('/admin/posts/create', static function (Request $request) use ($adminController): Response {
    return $adminController->storeContent($request, 'post');
});

$router->get('/admin/posts/{id}', static function (Request $request, array $parameters) use ($adminController): Response {
    return $adminController->editContentForm($request, 'post', (int) ($parameters['id'] ?? 0));
});

$router->post('/admin/posts/{id}', static function (Request $request, array $parameters) use ($adminController): Response {
    return $adminController->updateContent($request, 'post', (int) ($parameters['id'] ?? 0));
});

$router->post('/admin/posts/{id}/delete', static function (Request $request, array $parameters) use ($adminController): Response {
    return $adminController->deleteContent($request, 'post', (int) ($parameters['id'] ?? 0));
});

$router->get('/admin/import', static function (Request $request) use ($adminController): Response {
    return $adminController->importForm($request);
});

$router->post('/admin/import', static function (Request $request) use ($adminController): Response {
    return $adminController->runImport($request);
});

$router->get('/admin/taxonomies', static function (Request $request) use ($adminController): Response {
    return $adminController->taxonomyIndex($request);
});

$router->get('/admin/taxonomies/create', static function (Request $request) use ($adminController): Response {
    return $adminController->createTermForm($request);
});

$router->post('/admin/taxonomies/create', static function (Request $request) use ($adminController): Response {
    return $adminController->storeTerm($request);
});

$router->get('/admin/taxonomies/{id}', static function (Request $request, array $parameters) use ($adminController): Response {
    return $adminController->editTermForm($request, (int) ($parameters['id'] ?? 0));
});

$router->post('/admin/taxonomies/{id}', static function (Request $request, array $parameters) use ($adminController): Response {
    return $adminController->updateTerm($request, (int) ($parameters['id'] ?? 0));
});

$router->post('/admin/taxonomies/{id}/delete', static function (Request $request, array $parameters) use ($adminController): Response {
    return $adminController->deleteTerm($request, (int) ($parameters['id'] ?? 0));
});

$router->get('/admin/settings', static function (Request $request) use ($adminController): Response {
    return $adminController->settingsForm($request);
});

$router->post('/admin/settings', static function (Request $request) use ($adminController): Response {
    return $adminController->updateSettings($request);
});

$router->get('/admin/templating', static function (Request $request) use ($adminController): Response {
    return $adminController->templatingForm($request);
});

$router->post('/admin/templating', static function (Request $request) use ($adminController): Response {
    return $adminController->updateTemplating($request);
});

$router->get('/admin/themes', static function (Request $request) use ($adminController): Response {
    return $adminController->themesForm($request);
});

$router->get('/admin/themes/browse.json', static function (Request $request) use ($adminController): Response {
    return $adminController->themesBrowseJson($request);
});

$router->post('/admin/themes/activate', static function (Request $request) use ($adminController): Response {
    return $adminController->activateTheme($request);
});

$router->post('/admin/themes/install', static function (Request $request) use ($adminController): Response {
    return $adminController->installTheme($request);
});

$router->get('/admin/themes/{slug}/screenshot', static function (Request $request, array $parameters) use ($adminController): Response {
    return $adminController->themeScreenshot($request, (string) ($parameters['slug'] ?? ''));
});

// Live preview of an installed-but-inactive theme. Renders the front page with
// the requested theme without activating it. The theme function bridge keeps
// even a foreign WordPress theme from fataling, so a preview is always safe to
// attempt; its own assets are served through the preview-asset route below so
// its styling renders rather than the active theme's.
$router->get('/admin/themes/{slug}/preview', static function (Request $request, array $parameters) use ($auth, $rootPath, $themeMedia, $contentRepository, $config, $themeCatalog): Response {
    if (!$auth->check()) {
        return Response::redirect('/admin');
    }

    $slug = (string) ($parameters['slug'] ?? '');

    if (!$themeCatalog->exists($slug)) {
        return new Response('That theme is not installed.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    try {
        $previewTheme = new Theme($rootPath, $slug, $themeMedia);
    } catch (Throwable) {
        return new Response('This theme could not be loaded for preview.', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    $assetBase = '/admin/themes/' . rawurlencode($slug) . '/preview-asset';
    $settings = $contentRepository->getSettings();
    $siteName = (string) ($settings['site_name'] ?? $config->get('app.name', 'Local CMS'));
    $siteTagline = (string) ($settings['site_tagline'] ?? $config->get('app.tagline', ''));
    $homePage = $contentRepository->findPageBySlug('home');

    $data = [
        'siteName' => $siteName,
        'siteTagline' => $siteTagline,
        'homeUrl' => '/',
        'navigation' => [
            ['label' => 'Home', 'url' => '/', 'active' => true],
            ['label' => 'Posts', 'url' => '/posts', 'active' => false],
        ],
        'currentPath' => '/',
        'stylesheetUrl' => $assetBase . '/style.css',
        'themeAssetBaseUrl' => $assetBase,
        'themeMediaDirectory' => $themeMedia,
        'categories' => $contentRepository->termsByTaxonomy('category'),
        'heroPage' => $homePage,
        'posts' => $contentRepository->latestPosts(3),
        'intro' => (string) ($homePage['excerpt'] ?? ''),
        'archiveTitle' => 'Posts',
        'archiveDescription' => '',
    ];

    try {
        $body = $previewTheme->render('index', $data, ['pageTitle' => $siteName . ' — theme preview']);
    } catch (Throwable) {
        return new Response('This theme could not be rendered for preview.', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    $banner = '<div style="position:fixed;left:0;right:0;bottom:0;z-index:2147483647;background:#111;color:#fff;'
        . 'font:600 13px/1.4 system-ui,sans-serif;padding:10px 16px;display:flex;gap:12px;align-items:center;justify-content:center">'
        . 'Previewing &ldquo;' . htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '&rdquo; — not the active theme.'
        . ' <a href="/admin/themes" style="color:#7fd1ff">Back to Themes</a></div>';

    return new Response($body . $banner);
});

// Serve a previewed theme's own assets (CSS, images, JS) so its preview renders
// with its real styling instead of the active theme's.
$router->get('/admin/themes/{slug}/preview-asset/{assetPath*}', static function (Request $request, array $parameters) use ($auth, $rootPath, $themeMedia, $themeCatalog): Response {
    if (!$auth->check()) {
        return new Response('Forbidden.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    $slug = (string) ($parameters['slug'] ?? '');

    if (!$themeCatalog->exists($slug)) {
        return new Response('Asset not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    try {
        $previewTheme = new Theme($rootPath, $slug, $themeMedia);
    } catch (Throwable) {
        return new Response('Asset not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    return $previewTheme->assetResponse((string) ($parameters['assetPath'] ?? ''));
});

$router->post('/admin/themes/{slug}/delete', static function (Request $request, array $parameters) use ($adminController): Response {
    return $adminController->deleteTheme($request, (string) ($parameters['slug'] ?? ''));
});

$router->get('/admin/plugins', static function (Request $request) use ($adminController): Response {
    return $adminController->pluginsForm($request);
});

$router->get('/admin/theme-bridge', static function (Request $request) use ($adminController): Response {
    return $adminController->themeBridgeForm($request);
});

$router->post('/admin/theme-bridge', static function (Request $request) use ($adminController): Response {
    return $adminController->updateThemeFallbacks($request);
});

$router->get('/', static function (Request $request) use ($contentRepository, $sharedData, $theme, $config): Response {
    $homePage = $contentRepository->findPageBySlug('home');

    $body = $theme->render(
        'index',
        array_merge(
            $sharedData($request->path()),
            [
                'heroPage' => $homePage,
                'posts' => $contentRepository->latestPosts(3),
                'intro' => (string) ($homePage['excerpt'] ?? 'A minimal PHP CMS scaffold with theme templates designed to migrate cleanly into WordPress.'),
            ]
        ),
        [
            'pageTitle' => $homePage !== null
                ? (string) ($homePage['meta_title'] ?? $homePage['title'])
                : (string) $config->get('app.name', 'Local CMS'),
            'pageDescription' => (string) ($homePage['meta_description'] ?? $config->get('app.tagline', 'A simple content studio with WordPress-shaped theme templates.')),
        ]
    );

    return new Response($body);
});

$router->get('/posts', static function (Request $request) use ($contentRepository, $sharedData, $theme): Response {
    $body = $theme->render(
        'archive',
        array_merge(
            $sharedData($request->path()),
            [
                'archiveTitle' => 'Post Archive',
                'archiveDescription' => 'Browse posts by date, category, and tag.',
                'archiveTerm' => null,
                'posts' => $contentRepository->allPosts(),
            ]
        ),
        [
            'pageTitle' => 'Posts',
            'pageDescription' => 'Browse posts by date, category, and tag.',
        ]
    );

    return new Response($body);
});

$router->get('/category/{slug}', static function (Request $request, array $parameters) use ($contentRepository, $sharedData, $theme, $missingPage): Response {
    $term = $contentRepository->findTerm('category', (string) ($parameters['slug'] ?? ''));

    if ($term === null) {
        return $missingPage($request->path(), 'Category not found', 'The requested category does not exist yet.');
    }

    $body = $theme->render(
        'archive',
        array_merge(
            $sharedData($request->path()),
            [
                'archiveTitle' => (string) $term['name'],
                'archiveDescription' => (string) ($term['description'] ?: 'Posts filed under this category.'),
                'archiveTerm' => $term,
                'posts' => $contentRepository->findPostsByTerm('category', (string) $term['slug']),
            ]
        ),
        [
            'pageTitle' => (string) $term['name'],
            'pageDescription' => (string) ($term['description'] ?: 'Posts filed under this category.'),
        ]
    );

    return new Response($body);
});

$router->get('/tag/{slug}', static function (Request $request, array $parameters) use ($contentRepository, $sharedData, $theme, $missingPage): Response {
    $term = $contentRepository->findTerm('tag', (string) ($parameters['slug'] ?? ''));

    if ($term === null) {
        return $missingPage($request->path(), 'Tag not found', 'The requested tag does not exist yet.');
    }

    $body = $theme->render(
        'archive',
        array_merge(
            $sharedData($request->path()),
            [
                'archiveTitle' => '#' . (string) $term['name'],
                'archiveDescription' => (string) ($term['description'] ?: 'Posts filed under this tag.'),
                'archiveTerm' => $term,
                'posts' => $contentRepository->findPostsByTerm('tag', (string) $term['slug']),
            ]
        ),
        [
            'pageTitle' => '#' . (string) $term['name'],
            'pageDescription' => (string) ($term['description'] ?: 'Posts filed under this tag.'),
        ]
    );

    return new Response($body);
});

$router->get('/post/{slug}', static function (Request $request, array $parameters) use ($contentRepository, $sharedData, $theme, $missingPage): Response {
    $post = $contentRepository->findPostBySlug((string) ($parameters['slug'] ?? ''));

    if ($post === null) {
        return $missingPage($request->path(), 'Post not found', 'The requested post does not exist yet.');
    }

    $body = $theme->render(
        'single',
        array_merge(
            $sharedData($request->path()),
            [
                'post' => $post,
            ]
        ),
        [
            'pageTitle' => (string) ($post['meta_title'] ?? $post['title']),
            'pageDescription' => (string) ($post['meta_description'] ?? $post['excerpt'] ?? ''),
        ]
    );

    return new Response($body);
});

$router->get('/page/{slug}', static function (Request $request, array $parameters) use ($contentRepository, $sharedData, $theme, $missingPage): Response {
    $page = $contentRepository->findPageBySlug((string) ($parameters['slug'] ?? ''));

    if ($page === null) {
        return $missingPage($request->path(), 'Page not found', 'The requested page does not exist yet.');
    }

    $body = $theme->render(
        'page',
        array_merge(
            $sharedData($request->path()),
            [
                'page' => $page,
            ]
        ),
        [
            'pageTitle' => (string) ($page['meta_title'] ?? $page['title']),
            'pageDescription' => (string) ($page['meta_description'] ?? $page['excerpt'] ?? ''),
        ]
    );

    return new Response($body);
});

return new App($config, $router, $theme);

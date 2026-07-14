<?php
declare(strict_types=1);

namespace Cms\Admin;

use Cms\Core\Config;
use Cms\Core\Env;
use Cms\Http\Request;
use Cms\Http\Response;
use Cms\Repositories\AdminRepository;
use Cms\Security\Auth;
use Cms\Security\Csrf;
use Cms\Security\Session;
use Cms\Support\Markdown;
use Cms\Support\MarkdownTemplateCatalog;
use Cms\Support\PluginCatalog;
use Cms\Support\RunningMarkdown;
use Cms\Support\RunningMarkdownImporter;
use Cms\Support\ThemeCatalog;
use Cms\Support\ThemeFallbackRegistry;
use Cms\Support\ThemeFunctionBridge;
use Cms\Support\Uploads;
use Cms\Support\WordPressThemeDirectory;
use DateTimeImmutable;

final class AdminController
{
    public function __construct(
        private Config $config,
        private AdminView $view,
        private AdminRepository $repository,
        private Auth $auth,
        private ThemeCatalog $themes,
        private WordPressThemeDirectory $themeDirectory,
        private PluginCatalog $plugins,
        private string $rootPath = '',
    ) {
    }

    public function home(Request $request): Response
    {
        return $this->dashboard($request);
    }

    public function showLogin(Request $request): Response
    {
        Session::flash('notice', 'Local admin mode does not require signing in.');

        return Response::redirect('/admin');
    }

    public function login(Request $request): Response
    {
        Session::flash('notice', 'Local admin mode does not require signing in.');

        return Response::redirect('/admin');
    }

    public function logout(Request $request): Response
    {
        Session::flash('notice', 'Local admin mode stays available without signing in.');

        return Response::redirect('/admin');
    }

    public function previewMarkdown(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return new Response('<p class="preview-status is-error">Admin access is unavailable.</p>', 500);
        }

        if (!$this->validateCsrf($request)) {
            return new Response('<p class="preview-status is-error">Preview token expired. Refresh the editor and try again.</p>', 419);
        }

        $markdown = trim((string) $request->post('body_markdown', ''));

        if ($markdown === '') {
            return new Response('<p class="preview-empty">Nothing to preview yet.</p>');
        }

        return new Response(Markdown::toHtml($markdown));
    }

    public function dashboard(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $body = $this->view->render('dashboard', $this->baseData([
            'pageTitle' => 'Admin Dashboard',
            'currentSection' => 'dashboard',
            'summary' => $this->repository->dashboardSummary(),
            'recentPages' => array_slice($this->repository->listContent('page', 5), 0, 5),
            'recentPosts' => array_slice($this->repository->listContent('post', 5), 0, 5),
        ]));

        return new Response($body);
    }

    public function contentIndex(Request $request, string $type): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $label = $this->labelForContent($type, true);

        $body = $this->view->render('content-list', $this->baseData([
            'pageTitle' => $label,
            'currentSection' => $this->sectionForContent($type),
            'contentType' => $type,
            'contentLabel' => $label,
            'items' => $this->repository->listContent($type),
        ]));

        return new Response($body);
    }

    public function createContentForm(Request $request, string $type): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        return $this->renderContentForm($type, $this->contentDefaults($type), [], true);
    }

    public function storeContent(Request $request, string $type): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        if (!$this->validateCsrf($request)) {
            return $this->renderContentForm($type, $this->normalizeContentInput($request, $type), ['Invalid security token.'], true, 419);
        }

        $payload = $this->normalizeContentInput($request, $type);
        $errors = $this->validateContentPayload($payload, $type, null);

        if ($errors !== []) {
            return $this->renderContentForm($type, $payload, $errors, true, 422);
        }

        if (($uploadError = $this->applyFeaturedImageUpload($request, $payload)) !== null) {
            return $this->renderContentForm($type, $payload, [$uploadError], true, 422);
        }

        $payload['author_id'] = (int) ($this->auth->user()['id'] ?? 0);
        $contentId = $this->repository->saveContent($type, $payload, $payload['term_ids']);
        Session::flash('notice', $this->labelForContent($type) . ' created.');

        return Response::redirect('/admin/' . $this->sectionForContent($type) . '/' . $contentId);
    }

    public function editContentForm(Request $request, string $type, int $id): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $item = $this->repository->findContent($type, $id);

        if ($item === null) {
            return $this->messageResponse('Content not found', 'The requested admin record could not be found.', 404, $this->sectionForContent($type));
        }

        return $this->renderContentForm($type, $item, [], false);
    }

    public function updateContent(Request $request, string $type, int $id): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $existing = $this->repository->findContent($type, $id);

        if ($existing === null) {
            return $this->messageResponse('Content not found', 'The requested admin record could not be found.', 404, $this->sectionForContent($type));
        }

        $payload = $this->normalizeContentInput($request, $type);

        if (!$this->validateCsrf($request)) {
            return $this->renderContentForm($type, array_merge($existing, $payload), ['Invalid security token.'], false, 419);
        }

        $errors = $this->validateContentPayload($payload, $type, $id);

        if ($errors !== []) {
            return $this->renderContentForm($type, array_merge($existing, $payload), $errors, false, 422);
        }

        if (($uploadError = $this->applyFeaturedImageUpload($request, $payload)) !== null) {
            return $this->renderContentForm($type, array_merge($existing, $payload), [$uploadError], false, 422);
        }

        $payload['author_id'] = (int) ($this->auth->user()['id'] ?? 0);
        $this->repository->saveContent($type, $payload, $payload['term_ids'], $id);
        Session::flash('notice', $this->labelForContent($type) . ' updated.');

        return Response::redirect('/admin/' . $this->sectionForContent($type) . '/' . $id);
    }

    public function deleteContent(Request $request, string $type, int $id): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        if (!$this->validateCsrf($request)) {
            Session::flash('error', 'Invalid security token.');

            return Response::redirect('/admin/' . $this->sectionForContent($type));
        }

        $this->repository->deleteContent($type, $id);
        Session::flash('notice', $this->labelForContent($type) . ' deleted.');

        return Response::redirect('/admin/' . $this->sectionForContent($type));
    }

    public function taxonomyIndex(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $body = $this->view->render('taxonomy-list', $this->baseData([
            'pageTitle' => 'Taxonomies',
            'currentSection' => 'taxonomies',
            'groupedTerms' => $this->repository->listTermsGrouped(),
        ]));

        return new Response($body);
    }

    public function createTermForm(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $taxonomy = (string) $request->query('taxonomy', 'category');

        return $this->renderTermForm([
            'taxonomy' => in_array($taxonomy, ['category', 'tag'], true) ? $taxonomy : 'category',
            'name' => '',
            'slug' => '',
            'description' => '',
        ], [], true);
    }

    public function storeTerm(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $payload = $this->normalizeTermInput($request);

        if (!$this->validateCsrf($request)) {
            return $this->renderTermForm($payload, ['Invalid security token.'], true, 419);
        }

        $errors = $this->validateTermPayload($payload, null);

        if ($errors !== []) {
            return $this->renderTermForm($payload, $errors, true, 422);
        }

        $termId = $this->repository->saveTerm($payload);
        Session::flash('notice', 'Term created.');

        return Response::redirect('/admin/taxonomies/' . $termId);
    }

    public function editTermForm(Request $request, int $id): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $term = $this->repository->findTermById($id);

        if ($term === null) {
            return $this->messageResponse('Term not found', 'The requested taxonomy term does not exist.', 404, 'taxonomies');
        }

        return $this->renderTermForm($term, [], false);
    }

    public function updateTerm(Request $request, int $id): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $term = $this->repository->findTermById($id);

        if ($term === null) {
            return $this->messageResponse('Term not found', 'The requested taxonomy term does not exist.', 404, 'taxonomies');
        }

        $payload = $this->normalizeTermInput($request);

        if (!$this->validateCsrf($request)) {
            return $this->renderTermForm(array_merge($term, $payload), ['Invalid security token.'], false, 419);
        }

        $errors = $this->validateTermPayload($payload, $id);

        if ($errors !== []) {
            return $this->renderTermForm(array_merge($term, $payload), $errors, false, 422);
        }

        $this->repository->saveTerm($payload, $id);
        Session::flash('notice', 'Term updated.');

        return Response::redirect('/admin/taxonomies/' . $id);
    }

    public function deleteTerm(Request $request, int $id): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        if (!$this->validateCsrf($request)) {
            Session::flash('error', 'Invalid security token.');

            return Response::redirect('/admin/taxonomies');
        }

        $this->repository->deleteTerm($id);
        Session::flash('notice', 'Term deleted.');

        return Response::redirect('/admin/taxonomies');
    }

    public function settingsForm(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $settings = $this->repository->getSettings();
        $body = $this->view->render('settings', $this->baseData([
            'pageTitle' => 'Settings',
            'currentSection' => 'settings',
            'settings' => [
                'site_name' => (string) ($settings['site_name'] ?? $this->config->get('app.name', 'Local CMS')),
                'site_tagline' => (string) ($settings['site_tagline'] ?? $this->config->get('app.tagline', 'A simple content studio with WordPress-shaped theme templates.')),
                'default_content_type' => $this->defaultContentType($settings),
            ],
            'errors' => [],
        ]));

        return new Response($body);
    }

    public function updateSettings(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $settings = [
            'site_name' => trim((string) $request->post('site_name', '')),
            'site_tagline' => trim((string) $request->post('site_tagline', '')),
            'default_content_type' => trim((string) $request->post('default_content_type', 'post')),
        ];

        $errors = [];

        if (!$this->validateCsrf($request)) {
            $errors[] = 'Invalid security token.';
        }

        if ($settings['site_name'] === '') {
            $errors[] = 'Site name is required.';
        }

        if (!in_array($settings['default_content_type'], ['page', 'post'], true)) {
            $errors[] = 'Default content type must be page or post.';
        }

        if ($errors !== []) {
            $body = $this->view->render('settings', $this->baseData([
                'pageTitle' => 'Settings',
                'currentSection' => 'settings',
                'settings' => $settings,
                'errors' => $errors,
            ]));

            return new Response($body, 422);
        }

        $this->repository->updateSettings($settings);
        Session::flash('notice', 'Settings updated.');

        return Response::redirect('/admin/settings');
    }

    public function importForm(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        return new Response($this->view->render('import', $this->importViewData('', [], null, false)));
    }

    public function runImport(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $source = trim((string) $request->post('running_markdown', ''));
        $dryRun = $request->post('dry_run', '0') === '1';
        $errors = [];

        if (!$this->validateCsrf($request)) {
            return new Response($this->view->render('import', $this->importViewData($source, ['Invalid security token.'], null, $dryRun)), 419);
        }

        if ($request->hasFile('running_markdown_file')) {
            $file = (array) $request->file('running_markdown_file');
            $tmpPath = (string) ($file['tmp_name'] ?? '');
            $contents = $tmpPath !== '' && is_uploaded_file($tmpPath) ? file_get_contents($tmpPath) : false;

            if ($contents === false) {
                $errors[] = 'The uploaded file could not be read.';
            } else {
                $source = $contents;
            }
        }

        if ($errors === [] && trim($source) === '') {
            $errors[] = 'Paste a running markdown document or choose a markdown file to import.';
        }

        if ($errors !== []) {
            return new Response($this->view->render('import', $this->importViewData($source, $errors, null, $dryRun)), 422);
        }

        $settings = $this->repository->getSettings();
        $parsed = RunningMarkdown::parse($source, $this->defaultContentType($settings));

        if ($parsed['errors'] !== []) {
            return new Response($this->view->render('import', $this->importViewData($source, $parsed['errors'], null, $dryRun, $parsed['warnings'])), 422);
        }

        if ($dryRun) {
            $preview = [
                'created' => 0,
                'updated' => 0,
                'items' => [],
                'warnings' => $parsed['warnings'],
            ];

            foreach ($parsed['items'] as $item) {
                $slug = $this->slugify((string) $item['title']);
                $action = $this->repository->slugExists((string) $item['type'], $slug) ? 'updated' : 'created';
                $preview[$action] += 1;
                $preview['items'][] = [
                    'title' => (string) $item['title'],
                    'slug' => $slug,
                    'type' => (string) $item['type'],
                    'status' => (string) $item['status'],
                    'tags' => (array) $item['tags'],
                    'categories' => (array) $item['categories'],
                    'keywords' => (array) $item['keywords'],
                    'action' => $action,
                ];
            }

            return new Response($this->view->render('import', $this->importViewData($source, [], $preview, true)));
        }

        $importer = new RunningMarkdownImporter($this->repository);
        $report = $importer->import($parsed, (int) ($this->auth->user()['id'] ?? 0));

        Session::flash('notice', sprintf(
            'Running markdown imported: %d created, %d updated.',
            $report['created'],
            $report['updated']
        ));

        return new Response($this->view->render('import', $this->importViewData('', [], $report, false)));
    }

    public function templatingForm(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $settings = $this->repository->getSettings();
        $templates = MarkdownTemplateCatalog::decode((string) ($settings[MarkdownTemplateCatalog::SETTINGS_KEY] ?? ''));
        $body = $this->view->render('templating', $this->templatingViewData($templates, []));

        return new Response($body);
    }

    public function updateTemplating(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        [$templates, $errors] = $this->normalizeMarkdownTemplates($request);

        if (!$this->validateCsrf($request)) {
            $errors[] = 'Invalid security token.';
        }

        if ($errors !== []) {
            $body = $this->view->render('templating', $this->templatingViewData($templates, $errors));

            return new Response($body, 422);
        }

        $this->repository->updateSettings([
            MarkdownTemplateCatalog::SETTINGS_KEY => MarkdownTemplateCatalog::encode($templates),
        ]);

        Session::flash('notice', 'Templating updated.');

        return Response::redirect('/admin/templating');
    }

    public function themesForm(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $activeTheme = (string) $this->config->get('app.theme.name', 'default');
        $envOverride = trim((string) Env::get('APP_THEME', ''));

        $browse = trim((string) $request->query('browse', ''));
        $directory = [
            'available' => $this->themeDirectory->isAvailable(),
            'requested' => false,
            'browse' => $browse !== '' ? $browse : 'popular',
            'themes' => [],
            'hasMore' => false,
            'error' => null,
        ];

        if ($browse !== '') {
            $result = $this->themeDirectory->browse($browse);
            $directory['requested'] = true;
            $directory['themes'] = $result['themes'];
            $directory['hasMore'] = !empty($result['hasMore']);
            $directory['error'] = $result['error'];
        }

        // Flag each installed theme with whether it is compatible with the local
        // runtime, so the view offers Activate only for those and never invites a
        // click that would just be declined. The check is the static style.css
        // marker; theme code is never executed here.
        $installed = $this->themes->installed();

        foreach ($installed as &$installedTheme) {
            $installedTheme['compatible'] = $this->themes->isLocalRuntimeCompatible((string) $installedTheme['slug']);
            $installedTheme['protected']  = $this->themes->isProtected((string) $installedTheme['slug']);
        }
        unset($installedTheme);

        $body = $this->view->render('themes', $this->baseData([
            'pageTitle' => 'Themes',
            'currentSection' => 'themes',
            'themes' => $installed,
            'activeTheme' => $activeTheme,
            'envLocked' => $envOverride !== '',
            'envOverride' => $envOverride,
            'directory' => $directory,
        ]));

        return new Response($body);
    }

    public function activateTheme(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        if (!$this->validateCsrf($request)) {
            Session::flash('error', 'Invalid security token.');

            return Response::redirect('/admin/themes');
        }

        $slug = trim((string) $request->post('theme', ''));

        $compatibilityError = $this->themes->compatibilityError($slug);

        if ($compatibilityError !== null) {
            $theme = $this->themes->find($slug);
            $name = $theme['name'] ?? $slug;
            Session::flash('error', sprintf(
                'The "%s" theme cannot be activated: it is not compatible with the local runtime (%s). Themes installed from the WordPress.org directory need the WordPress runtime to render and are available here for export and porting only.',
                $name,
                $compatibilityError
            ));

            return Response::redirect('/admin/themes');
        }

        try {
            $this->themes->activate($slug);
            $theme = $this->themes->find($slug);
            Session::flash('notice', ($theme['name'] ?? $slug) . ' is now the active theme.');
        } catch (\RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Response::redirect('/admin/themes');
    }

    public function installTheme(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $browse = trim((string) $request->post('browse', 'popular'));
        $redirectTo = '/admin/themes?browse=' . rawurlencode($browse !== '' ? $browse : 'popular');

        if (!$this->validateCsrf($request)) {
            Session::flash('error', 'Invalid security token.');

            return Response::redirect($redirectTo);
        }

        $slug = trim((string) $request->post('slug', ''));

        try {
            $installed = $this->themeDirectory->install($slug);
            Session::flash('notice', 'Installed "' . $installed . '" into themes/. Activate it from the installed themes below.');
        } catch (\RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Response::redirect($redirectTo);
    }

    /**
     * JSON endpoint used by the admin Themes screen to lazy-load additional
     * pages of WordPress.org directory results as the user scrolls.
     */
    public function themesBrowseJson(Request $request): Response
    {
        if (!$this->auth->check()) {
            return new Response(
                json_encode(['error' => 'Authentication required.'], JSON_UNESCAPED_SLASHES),
                401,
                ['Content-Type' => 'application/json; charset=UTF-8']
            );
        }

        $browse = trim((string) $request->query('browse', 'popular'));
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = max(1, min(30, (int) $request->query('per_page', '12')));

        if (!$this->themeDirectory->isAvailable()) {
            $payload = [
                'themes' => [],
                'page' => $page,
                'perPage' => $perPage,
                'hasMore' => false,
                'error' => 'Outbound HTTP is unavailable in this PHP build.',
            ];

            return new Response(
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                200,
                ['Content-Type' => 'application/json; charset=UTF-8']
            );
        }

        $result = $this->themeDirectory->browse($browse, $perPage, $page);

        // Annotate each remote theme with whether it is already in themes/, so
        // the client can render an "Already installed" label instead of an
        // Install button without making a second round-trip.
        $installedSlugs = array_map(
            static fn (array $theme): string => strtolower((string) ($theme['slug'] ?? '')),
            $this->themes->installed()
        );
        $installedLookup = array_fill_keys($installedSlugs, true);

        foreach ($result['themes'] as &$theme) {
            $slug = strtolower((string) ($theme['slug'] ?? ''));
            $theme['installed'] = isset($installedLookup[$slug]);
        }
        unset($theme);

        return new Response(
            json_encode($result, JSON_UNESCAPED_SLASHES),
            200,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public function deleteTheme(Request $request, string $slug): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        if (!$this->validateCsrf($request)) {
            Session::flash('error', 'Invalid security token.');

            return Response::redirect('/admin/themes');
        }

        $activeTheme = (string) $this->config->get('app.theme.name', 'default');

        try {
            $theme = $this->themes->find($slug);
            $name  = $theme !== null ? (string) $theme['name'] : $slug;
            $this->themes->delete($slug, $activeTheme);
            Session::flash('notice', '"' . $name . '" has been deleted.');
        } catch (\RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Response::redirect('/admin/themes');
    }

    public function themeBridgeForm(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $activeTheme = (string) $this->config->get('app.theme.name', 'default');
        $registry = ThemeFallbackRegistry::load($this->rootPath);
        $themePath = rtrim($this->rootPath, '/\\') . '/themes/' . $activeTheme;

        $detected = is_dir($themePath)
            ? ThemeFunctionBridge::report($themePath, $this->rootPath, $registry)
            : [];

        $body = $this->view->render('theme-bridge', $this->baseData([
            'pageTitle'     => 'Theme Bridge',
            'currentSection' => 'theme-bridge',
            'activeTheme'   => $activeTheme,
            'detected'      => $detected,
            'overrides'     => $registry->overrides(),
            'kinds'         => ThemeFallbackRegistry::KINDS,
        ]));

        return new Response($body);
    }

    public function updateThemeFallbacks(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        if (!$this->validateCsrf($request)) {
            Session::flash('error', 'Invalid security token.');

            return Response::redirect('/admin/theme-bridge');
        }

        $names = $request->post('fn_names', []);
        $kinds = $request->post('fn_kinds', []);
        $names = is_array($names) ? $names : [];
        $kinds = is_array($kinds) ? $kinds : [];

        $registry = ThemeFallbackRegistry::load($this->rootPath);

        foreach ($names as $index => $name) {
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            $kind = (string) ($kinds[$index] ?? '');

            // An empty kind means "use the smart default" — drop any override.
            if ($kind === '') {
                $registry->removeOverride($name);
            } elseif (in_array($kind, ThemeFallbackRegistry::KINDS, true)) {
                $registry->setOverride($name, ['kind' => $kind]);
            }
        }

        $saved = $registry->save($this->rootPath);

        Session::flash(
            $saved ? 'notice' : 'error',
            $saved ? 'Theme fallback overrides saved.' : 'Could not write theme-fallbacks.json.'
        );

        return Response::redirect('/admin/theme-bridge');
    }

    public function pluginsForm(Request $request): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $body = $this->view->render('plugins', $this->baseData([
            'pageTitle'      => 'Plugins',
            'currentSection' => 'plugins',
            'plugins'        => $this->plugins->installed(),
        ]));

        return new Response($body);
    }

    public function themeScreenshot(Request $request, string $slug): Response
    {
        if (($redirect = $this->requireAuth()) !== null) {
            return $redirect;
        }

        $path = $this->themes->screenshotPath($slug);

        if ($path === null || !is_file($path)) {
            return new Response('Screenshot not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $bytes = file_get_contents($path);

        if ($bytes === false) {
            return new Response('Screenshot could not be read.', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $contentType = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };

        return new Response($bytes, 200, ['Content-Type' => $contentType]);
    }

    private function requireAuth(): ?Response
    {
        if ($this->auth->check()) {
            return null;
        }

        return $this->messageResponse(
            'Admin user unavailable',
            'The local admin seed could not be resolved. Repair the users table or rerun database bootstrap.',
            500,
            'dashboard'
        );
    }

    private function validateCsrf(Request $request): bool
    {
        return Csrf::validate($request->post('_token'));
    }

    private function renderContentForm(string $type, array $item, array $errors, bool $isNew, int $statusCode = 200): Response
    {
        $label = $this->labelForContent($type);
        $body = $this->view->render('content-form', $this->baseData([
            'pageTitle' => ($isNew ? 'Create ' : 'Edit ') . $label,
            'currentSection' => $this->sectionForContent($type),
            'contentType' => $type,
            'contentLabel' => $label,
            'markdownPreviewEnabled' => true,
            'item' => $item,
            'errors' => $errors,
            'isNew' => $isNew,
            'termGroups' => $this->repository->listTermsGrouped(),
        ]));

        return new Response($body, $statusCode);
    }

    private function renderTermForm(array $term, array $errors, bool $isNew, int $statusCode = 200): Response
    {
        $body = $this->view->render('taxonomy-form', $this->baseData([
            'pageTitle' => $isNew ? 'Create Term' : 'Edit Term',
            'currentSection' => 'taxonomies',
            'term' => $term,
            'errors' => $errors,
            'isNew' => $isNew,
        ]));

        return new Response($body, $statusCode);
    }

    private function messageResponse(string $title, string $message, int $statusCode, string $section): Response
    {
        $body = $this->view->render('message', $this->baseData([
            'pageTitle' => $title,
            'currentSection' => $section,
            'messageTitle' => $title,
            'messageBody' => $message,
        ]));

        return new Response($body, $statusCode);
    }

    private function normalizeContentInput(Request $request, string $type): array
    {
        $title = trim((string) $request->post('title', ''));
        $slug = trim((string) $request->post('slug', ''));

        if ($slug === '' && $title !== '') {
            $slug = $this->slugify($title);
        }

        $publishedAt = trim((string) $request->post('published_at', ''));

        return [
            'title' => $title,
            'slug' => $this->slugify($slug),
            'excerpt' => trim((string) $request->post('excerpt', '')),
            'body_markdown' => trim((string) $request->post('body_markdown', '')),
            'markdown_math' => $request->post('markdown_math', '0') === '1',
            'use_marked' => $request->post('use_marked', '0') === '1',
            'status' => in_array((string) $request->post('status', 'draft'), ['draft', 'published'], true) ? (string) $request->post('status', 'draft') : 'draft',
            'published_at' => $this->normalizeDateTime($publishedAt),
            'meta_title' => trim((string) $request->post('meta_title', '')),
            'meta_description' => trim((string) $request->post('meta_description', '')),
            'featured_image' => trim((string) $request->post('featured_image', '')),
            'template' => $type === 'page' ? trim((string) $request->post('template', 'page')) : 'single',
            'sort_order' => $type === 'page' ? max(0, (int) $request->post('sort_order', 0)) : 0,
            'term_ids' => is_array($request->post('term_ids', [])) ? array_map(static fn (mixed $value): int => (int) $value, $request->post('term_ids', [])) : [],
        ];
    }

    private function validateContentPayload(array &$payload, string $type, ?int $id): array
    {
        $errors = [];

        if ($payload['title'] === '') {
            $errors[] = $this->labelForContent($type) . ' title is required.';
        }

        if ($payload['slug'] === '') {
            $errors[] = $this->labelForContent($type) . ' slug is required.';
        } elseif ($this->repository->slugExists($type, $payload['slug'], $id)) {
            $errors[] = 'That slug is already in use.';
        }

        if ($payload['body_markdown'] === '') {
            $errors[] = 'Body content is required.';
        }

        if ($type === 'page' && $payload['template'] === '') {
            $errors[] = 'Template is required for pages.';
        }

        if ($payload['status'] === 'published' && $payload['published_at'] === null) {
            $payload['published_at'] = gmdate('Y-m-d H:i:s');
        }

        if ($payload['status'] === 'draft') {
            $payload['published_at'] = $payload['published_at'];
        }

        if ($payload['meta_title'] === '') {
            $payload['meta_title'] = $payload['title'];
        }

        if ($payload['meta_description'] === '') {
            $payload['meta_description'] = $payload['excerpt'];
        }

        $payload['term_ids'] = $this->repository->filterExistingTermIds($payload['term_ids']);

        return $errors;
    }

    private function contentDefaults(string $type): array
    {
        return [
            'title' => '',
            'slug' => '',
            'excerpt' => '',
            'body_markdown' => '',
            'markdown_math' => false,
            'use_marked' => false,
            'status' => 'draft',
            'published_at' => null,
            'meta_title' => '',
            'meta_description' => '',
            'featured_image' => '',
            'template' => $type === 'page' ? 'page' : 'single',
            'sort_order' => 0,
            'term_ids' => [],
            'categories' => [],
            'tags' => [],
        ];
    }

    private function applyFeaturedImageUpload(Request $request, array &$payload): ?string
    {
        if (!$request->hasFile('featured_image_upload')) {
            return null;
        }

        try {
            $payload['featured_image'] = Uploads::storeImage(
                $request->file('featured_image_upload'),
                (array) $this->config->get('app.application.uploads', [])
            );

            return null;
        } catch (\RuntimeException $exception) {
            return $exception->getMessage();
        }
    }

    private function normalizeTermInput(Request $request): array
    {
        $name = trim((string) $request->post('name', ''));
        $slug = trim((string) $request->post('slug', ''));

        if ($slug === '' && $name !== '') {
            $slug = $this->slugify($name);
        }

        return [
            'taxonomy' => (string) $request->post('taxonomy', 'category'),
            'name' => $name,
            'slug' => $this->slugify($slug),
            'description' => trim((string) $request->post('description', '')),
        ];
    }

    private function validateTermPayload(array &$payload, ?int $id): array
    {
        $errors = [];

        if (!in_array($payload['taxonomy'], ['category', 'tag'], true)) {
            $errors[] = 'Taxonomy must be category or tag.';
        }

        if ($payload['name'] === '') {
            $errors[] = 'Name is required.';
        }

        if ($payload['slug'] === '') {
            $errors[] = 'Slug is required.';
        } elseif ($this->repository->termSlugExists($payload['taxonomy'], $payload['slug'], $id)) {
            $errors[] = 'That taxonomy slug is already in use.';
        }

        return $errors;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;

        return trim($value, '-');
    }

    private function normalizeDateTime(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $normalizedValue = str_replace('T', ' ', $value);

        try {
            $date = new DateTimeImmutable($normalizedValue);
        } catch (\Throwable) {
            return null;
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function sectionForContent(string $type): string
    {
        return $type === 'page' ? 'pages' : 'posts';
    }

    private function labelForContent(string $type, bool $plural = false): string
    {
        if ($type === 'page') {
            return $plural ? 'Pages' : 'Page';
        }

        return $plural ? 'Posts' : 'Post';
    }

    private function baseData(array $data): array
    {
        $settings = $this->repository->getSettings();

        return array_merge([
            'siteName' => (string) ($settings['site_name'] ?? $this->config->get('app.name', 'Local CMS')),
            'siteTagline' => (string) ($settings['site_tagline'] ?? $this->config->get('app.tagline', 'A simple content studio with WordPress-shaped theme templates.')),
            'markdownTemplateMap' => MarkdownTemplateCatalog::mapFromSettings($settings),
            'authUser' => $this->auth->user(),
            'notice' => Session::pullFlash('notice'),
            'errorNotice' => Session::pullFlash('error'),
            'csrfToken' => Csrf::token(),
            'clientConverterEnabled' => (bool) $this->config->get('app.application.content.clientConverter', false),
        ], $data);
    }

    private function defaultContentType(array $settings): string
    {
        $type = (string) ($settings['default_content_type'] ?? 'post');

        return in_array($type, ['page', 'post'], true) ? $type : 'post';
    }

    private function importViewData(string $source, array $errors, ?array $report, bool $dryRun, array $warnings = []): array
    {
        if ($report !== null) {
            $warnings = array_merge($warnings, $report['warnings']);
        }

        return $this->baseData([
            'pageTitle' => 'Import',
            'currentSection' => 'import',
            'source' => $source,
            'errors' => $errors,
            'warnings' => array_values(array_unique($warnings)),
            'report' => $report,
            'dryRun' => $dryRun,
        ]);
    }

    private function templatingViewData(array $templates, array $errors): array
    {
        return $this->baseData([
            'pageTitle' => 'Templating',
            'currentSection' => 'templating',
            'templates' => array_merge($templates, [['name' => '', 'markup' => '']]),
            'errors' => $errors,
        ]);
    }

    private function normalizeMarkdownTemplates(Request $request): array
    {
        $names = $request->post('template_names', []);
        $markups = $request->post('template_markups', []);
        $templates = [];
        $errors = [];
        $seenNames = [];

        if (!is_array($names)) {
            $names = [];
        }

        if (!is_array($markups)) {
            $markups = [];
        }

        $rowCount = max(count($names), count($markups));

        for ($index = 0; $index < $rowCount; $index += 1) {
            $name = trim((string) ($names[$index] ?? ''));
            $markup = trim((string) ($markups[$index] ?? ''));
            $rowLabel = 'Template ' . ($index + 1);

            if ($name === '' && $markup === '') {
                continue;
            }

            if ($name === '') {
                $errors[] = $rowLabel . ' is missing a name.';
            }

            if ($markup === '') {
                $errors[] = $rowLabel . ' is missing its HTML snippet.';
            }

            if ($name !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
                $errors[] = $rowLabel . ' must use only letters, numbers, underscores, or hyphens.';
            }

            if ($name !== '' && isset($seenNames[$name])) {
                $errors[] = $rowLabel . ' duplicates the template name "' . $name . '".';
            }

            if ($markup !== '' && strpos($markup, '{__markdown__}') === false) {
                $errors[] = $rowLabel . ' must include the {__markdown__} placeholder.';
            }

            if ($name !== '' && $markup !== '' && !isset($seenNames[$name]) && preg_match('/^[A-Za-z0-9_-]+$/', $name) === 1) {
                $seenNames[$name] = true;
                $templates[] = [
                    'name' => $name,
                    'markup' => $markup,
                ];
            }
        }

        return [$templates, $errors];
    }
}

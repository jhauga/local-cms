<?php
declare(strict_types=1);

namespace Cms\Admin;

use Cms\Core\Config;
use Cms\Http\Request;
use Cms\Http\Response;
use Cms\Repositories\AdminRepository;
use Cms\Security\Auth;
use Cms\Security\Csrf;
use Cms\Security\Session;
use DateTimeImmutable;

final class AdminController
{
    public function __construct(
        private Config $config,
        private AdminView $view,
        private AdminRepository $repository,
        private Auth $auth,
    ) {
    }

    public function home(Request $request): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/admin/login');
        }

        return $this->dashboard($request);
    }

    public function showLogin(Request $request): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/admin');
        }

        return $this->renderLogin();
    }

    public function login(Request $request): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/admin');
        }

        if (!$this->validateCsrf($request)) {
            return $this->renderLogin(['Invalid security token.'], ['email' => (string) $request->post('email', '')], 419);
        }

        $email = trim((string) $request->post('email', ''));
        $password = (string) $request->post('password', '');
        $errors = [];

        if ($email === '') {
            $errors[] = 'Email is required.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if ($errors === [] && !$this->auth->attempt($email, $password)) {
            $errors[] = 'The provided credentials are not valid.';
        }

        if ($errors !== []) {
            return $this->renderLogin($errors, ['email' => $email], 422);
        }

        Session::flash('notice', 'Signed in successfully.');

        return Response::redirect('/admin');
    }

    public function logout(Request $request): Response
    {
        if (!$this->validateCsrf($request)) {
            Session::flash('error', 'Your session token expired. Sign in again.');

            return Response::redirect('/admin/login');
        }

        $this->auth->logout();
        Session::flash('notice', 'Signed out.');

        return Response::redirect('/admin/login');
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
        ];

        $errors = [];

        if (!$this->validateCsrf($request)) {
            $errors[] = 'Invalid security token.';
        }

        if ($settings['site_name'] === '') {
            $errors[] = 'Site name is required.';
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

    private function requireAuth(): ?Response
    {
        if ($this->auth->check()) {
            return null;
        }

        Session::flash('error', 'Sign in to access the admin area.');

        return Response::redirect('/admin/login');
    }

    private function validateCsrf(Request $request): bool
    {
        return Csrf::validate($request->post('_token'));
    }

    private function renderLogin(array $errors = [], array $values = [], int $statusCode = 200): Response
    {
        $body = $this->view->render('login', $this->baseData([
            'pageTitle' => 'Admin Login',
            'currentSection' => 'login',
            'errors' => $errors,
            'values' => array_merge(['email' => 'admin@example.com'], $values),
            'isGuestScreen' => true,
        ]));

        return new Response($body, $statusCode);
    }

    private function renderContentForm(string $type, array $item, array $errors, bool $isNew, int $statusCode = 200): Response
    {
        $label = $this->labelForContent($type);
        $body = $this->view->render('content-form', $this->baseData([
            'pageTitle' => ($isNew ? 'Create ' : 'Edit ') . $label,
            'currentSection' => $this->sectionForContent($type),
            'contentType' => $type,
            'contentLabel' => $label,
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
            'authUser' => $this->auth->user(),
            'notice' => Session::pullFlash('notice'),
            'errorNotice' => Session::pullFlash('error'),
            'csrfToken' => Csrf::token(),
        ], $data);
    }
}

<?php
declare(strict_types=1);

namespace Cms\Core;

use Cms\Http\Request;
use Cms\Http\Response;
use Cms\Http\Router;
use Throwable;

final class App
{
    public function __construct(
        private Config $config,
        private Router $router,
        private Theme $theme,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->router->dispatch($request);

            if ($response !== null) {
                return $response;
            }

            return $this->notFound($request);
        } catch (Throwable $throwable) {
            return $this->serverError($throwable, $request);
        }
    }

    public function run(): void
    {
        $this->handle(Request::capture())->send();
    }

    public function theme(): Theme
    {
        return $this->theme;
    }

    private function notFound(Request $request): Response
    {
        $content = $this->theme->render(
            'page',
            [
                'siteName' => (string) $this->config->get('app.name', 'Local CMS'),
                'siteTagline' => (string) $this->config->get('app.tagline', 'A simple content studio with WordPress-shaped theme templates.'),
                'navigation' => [
                    ['label' => 'Home', 'url' => '/'],
                    ['label' => 'Posts', 'url' => '/posts'],
                ],
                'currentPath' => $request->path(),
                'page' => [
                    'title' => 'Page not found',
                    'excerpt' => 'The requested content does not exist yet.',
                    'body_html' => '<p>Try the home page, the post archive, or seed a new page in the database.</p>',
                ],
            ],
            ['pageTitle' => 'Not Found']
        );

        return new Response($content, 404);
    }

    private function serverError(Throwable $throwable, Request $request): Response
    {
        $message = (bool) $this->config->get('app.debug', false)
            ? htmlspecialchars($throwable->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : 'An unexpected error occurred while booting the CMS.';

        $content = $this->theme->render(
            'page',
            [
                'siteName' => (string) $this->config->get('app.name', 'Local CMS'),
                'siteTagline' => (string) $this->config->get('app.tagline', 'A simple content studio with WordPress-shaped theme templates.'),
                'navigation' => [
                    ['label' => 'Home', 'url' => '/'],
                    ['label' => 'Posts', 'url' => '/posts'],
                ],
                'currentPath' => $request->path(),
                'page' => [
                    'title' => 'Application error',
                    'excerpt' => 'The CMS could not complete the request.',
                    'body_html' => '<p>' . $message . '</p>',
                ],
            ],
            ['pageTitle' => 'Application error']
        );

        return new Response($content, 500);
    }
}

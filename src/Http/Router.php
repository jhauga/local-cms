<?php
declare(strict_types=1);

namespace Cms\Http;

final class Router
{
    /** @var array<string, array<int, array{pattern: string, regex: string, handler: callable}>> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    public function dispatch(Request $request): ?Response
    {
        foreach ($this->routes[$request->method()] ?? [] as $route) {
            if (preg_match($route['regex'], $request->path(), $matches) !== 1) {
                continue;
            }

            $parameters = [];

            foreach ($matches as $name => $value) {
                if (!is_int($name)) {
                    $parameters[$name] = $value;
                }
            }

            return ($route['handler'])($request, $parameters);
        }

        return null;
    }

    private function addRoute(string $method, string $pattern, callable $handler): void
    {
        $this->routes[$method][] = [
            'pattern' => $pattern,
            'regex' => $this->compilePattern($pattern),
            'handler' => $handler,
        ];
    }

    private function compilePattern(string $pattern): string
    {
        $normalized = $pattern === '/' ? '/' : '/' . trim($pattern, '/');
        $segments = array_values(array_filter(explode('/', trim($normalized, '/')), static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return '#^/$#';
        }

        $compiledSegments = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\*\}$/', $segment, $matches) === 1) {
                $compiledSegments[] = '(?P<' . $matches[1] . '>.+)';
                continue;
            }

            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $matches) === 1) {
                $compiledSegments[] = '(?P<' . $matches[1] . '>[^/]+)';
                continue;
            }

            $compiledSegments[] = preg_quote($segment, '#');
        }

        return '#^/' . implode('/', $compiledSegments) . '$#';
    }
}

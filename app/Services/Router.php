<?php
declare(strict_types=1);

namespace App\Services;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, bool $auth = true): void { $this->add('GET', $path, $handler, $auth, false); }
    public function post(string $path, callable|array $handler, bool $auth = true, bool $csrf = true): void { $this->add('POST', $path, $handler, $auth, $csrf); }

    private function add(string $method, string $path, callable|array $handler, bool $auth, bool $csrf): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[0-9]+)', $path);
        $this->routes[] = compact('method', 'path', 'pattern', 'handler', 'auth', 'csrf');
    }

    public function dispatch(): void
    {
        $method = strtoupper($_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/.');
        if (str_ends_with($scriptDir, '/public')) {
            $scriptDir = substr($scriptDir, 0, -7);
        }
        if ($scriptDir && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir)) ?: '/';
        }
        $uri = '/' . trim(rawurldecode($uri), '/');
        if ($uri === '//') $uri = '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || !preg_match('#^' . $route['pattern'] . '/?$#', $uri, $matches)) continue;
            if ($route['auth']) {
                if (!Auth::check()) {
                    flash('warning', 'Faça login para continuar.');
                    redirect('login');
                }
                if (!Auth::isSuperAdmin()) {
                    Auth::logout();
                    http_response_code(403);
                    View::render('errors/403', [], 'guest');
                    return;
                }
            }
            if ($method !== 'GET' && $route['csrf'] && !Csrf::verify($_POST['_token'] ?? null)) {
                http_response_code(419);
                View::render('errors/419', [], Auth::check() ? 'admin' : 'guest');
                return;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            if (is_array($route['handler'])) {
                [$class, $action] = $route['handler'];
                (new $class())->{$action}(...array_values($params));
            } else {
                ($route['handler'])(...array_values($params));
            }
            return;
        }
        http_response_code(404);
        View::render('errors/404', [], Auth::check() ? 'admin' : 'guest');
    }
}

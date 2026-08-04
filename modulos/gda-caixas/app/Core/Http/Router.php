<?php
declare(strict_types=1);
namespace Gda\Core\Http;

final class Router
{
    private array $routes = [];

    public function add(string $name, callable $handler, bool $public = false, array $methods = ['GET', 'POST']): self
    {
        $this->routes[$name] = compact('handler', 'public', 'methods');
        return $this;
    }

    public function dispatch(string $name, string $method, callable $authorize): void
    {
        $route = $this->routes[$name] ?? null;
        if (!$route) { http_response_code(404); echo 'Página não encontrada.'; return; }
        if (!in_array(strtoupper($method), $route['methods'], true)) {
            http_response_code(405); header('Allow: ' . implode(', ', $route['methods'])); echo 'Método não permitido.'; return;
        }
        if (!$route['public']) $authorize();
        ($route['handler'])();
    }
}

<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Deliberately simple exact-path router (no regex/param placeholders
 * needed): every route the app exposes today is a fixed path, e.g.
 * /products.php, /cart.php, /login.php. Query strings (e.g.
 * ?slug=...) are handled by the controller reading $_GET, same as
 * before — the router only matches on the path portion of the URL,
 * which is exactly what preserves every existing link and form action
 * in the app unchanged.
 */
final class Router
{
    /** @var array<string, array<string, array{0:string,1:string}>> */
    private $routes = [];

    public function get(string $path, string $controller, string $method): void
    {
        $this->addRoute('GET', $path, $controller, $method);
    }

    public function post(string $path, string $controller, string $method): void
    {
        $this->addRoute('POST', $path, $controller, $method);
    }

    public function any(string $path, string $controller, string $method): void
    {
        $this->addRoute('GET', $path, $controller, $method);
        $this->addRoute('POST', $path, $controller, $method);
    }

    private function addRoute(string $httpMethod, string $path, string $controller, string $method): void
    {
        $this->routes[$httpMethod][$path] = [$controller, $method];
    }

    /**
     * @return array{0:string,1:string}|null
     */
    public function match(string $httpMethod, string $path): ?array
    {
        $path = '/' . ltrim($path, '/');

        return $this->routes[$httpMethod][$path] ?? null;
    }
}

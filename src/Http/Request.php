<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Thin wrapper around the incoming request. Deliberately minimal —
 * existing controller logic keeps reading $_POST/$_GET via filter_input()
 * exactly as before (that's validation logic, not routing, and goal is
 * not to redesign it). This class only exists so the Router/Kernel have
 * a clean way to know the HTTP method and path being requested.
 */
final class Request
{
    /** @var string */
    private $method;

    /** @var string */
    private $path;

    public function __construct(string $method, string $path)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') {
            $path = '/';
        }

        return new self($method, $path);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }
}

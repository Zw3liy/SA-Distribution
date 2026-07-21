<?php
declare(strict_types=1);

namespace App\Container;

use Closure;
use RuntimeException;

/**
 * Minimal dependency-injection container. Not a full IoC framework —
 * just a single place to register how each class is built (replacing the
 * manual "new X(new Y($db))" chains that used to be repeated at the top
 * of every page) and to share singleton instances (DB connection,
 * config, services) across a request.
 */
final class Container
{
    /** @var array<string, Closure> */
    private $factories = [];

    /** @var array<string, mixed> */
    private $instances = [];

    /** @var array<string, bool> */
    private $shared = [];

    public function set(string $id, Closure $factory, bool $shared = true): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = $shared;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    public function get(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException(sprintf('No binding registered for "%s".', $id));
        }

        $value = ($this->factories[$id])($this);

        if ($this->shared[$id] ?? true) {
            $this->instances[$id] = $value;
        }

        return $value;
    }
}

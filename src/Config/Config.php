<?php
declare(strict_types=1);

namespace App\Config;

/**
 * Centralized configuration accessor. Wraps the existing config/app.php
 * array (unchanged in shape) so it is loaded exactly once per request
 * instead of being require()'d independently by every page, and exposes
 * a small dot-notation accessor for convenience.
 */
final class Config
{
    /** @var array */
    private $items;

    public function __construct(string $configFile)
    {
        $this->items = require $configFile;
    }

    public function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Returns the full underlying config array, matching what
     * `require config/app.php` used to return directly. Kept so existing
     * view templates can keep using `$appConfig['name']` etc. unchanged.
     */
    public function all(): array
    {
        return $this->items;
    }
}

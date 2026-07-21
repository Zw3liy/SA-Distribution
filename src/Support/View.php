<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Tiny view-rendering helper. Templates live under views/pages/*.php and
 * are plain PHP files exactly like the old page bodies were — this just
 * captures their output as a string instead of echoing directly, so a
 * Controller can return it inside a Response.
 */
final class View
{
    /** @var string */
    private static $basePath;

    public static function setBasePath(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/');
    }

    public static function render(string $template, array $data = []): string
    {
        $file = self::$basePath . '/' . $template . '.php';

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}

<?php
declare(strict_types=1);

/**
 * Front controller. This is the single entry point for the whole
 * application. Every request — whether it looks like /products.php,
 * /login.php, /cart-api.php, or /, dynamic or a static asset — passes
 * through here first.
 *
 * For local development:
 *   php -S localhost:8000 public/index.php
 * (this file also acts as the router script; see the static-file
 * passthrough below.)
 *
 * For production (Apache): point the document root at public/ and use
 * public/.htaccess to rewrite everything to this file.
 */

// When running under the PHP built-in development server, let real static
// assets under public/ (css, js, images, fonts) be served directly instead
// of being routed through the application. Deliberately checked against
// REQUEST_URI + __DIR__ rather than $_SERVER['SCRIPT_FILENAME']: the
// built-in server sets SCRIPT_FILENAME inconsistently -- an absolute path
// when a request resolves to this router directly (e.g. "/"), but the
// literal relative path passed on the command line for every other
// routed request -- which made a __FILE__ comparison unreliable and
// caused the built-in server's own 404 to shadow every non-root route.
// .php requests are deliberately excluded from this passthrough (even
// though public/index.php itself "is_file"): every .php-suffixed legacy
// URL must always flow through the Kernel/router below, never be executed
// as a standalone script.
if (PHP_SAPI === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($uri !== null && $uri !== '' && $uri !== '/' && !str_ends_with($uri, '.php')) {
        $candidate = __DIR__ . urldecode($uri);
        if (is_file($candidate)) {
            return false;
        }
    }
}

define('APP_BASE_PATH', dirname(__DIR__));

require APP_BASE_PATH . '/vendor/autoload.php';

use App\Http\Kernel;
use App\Http\Request;

$kernel = new Kernel(APP_BASE_PATH);
$request = Request::fromGlobals();
$response = $kernel->handle($request);
$response->send();

<?php
declare(strict_types=1);

namespace App\Domains\Customers\Controllers;

use App\Config\Config;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;

/**
 * Direct port of the original wishlist.php, which had no dedicated
 * controller class of its own — it operated purely on the session
 * wishlist via global helper functions. Wrapped in a thin controller
 * here purely for structural consistency with the rest of the app; no
 * new behavior was introduced. Wishlist remains session-array-backed,
 * not promoted to a database table in this phase
 * (docs/specs/04-customers.md §4/§19/§20 -- a real, flagged gap, not
 * silently dropped).
 */
class WishlistController
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function page(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $slug = trim((string) filter_input(INPUT_POST, 'slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

            if ($action === 'remove' && $slug !== '') {
                removeFromWishlist($slug);
                setFlashMessage('Item removed from wishlist.');
            }

            return Response::redirect('/wishlist.php');
        }

        $html = View::render('pages/wishlist', [
            'appConfig' => $this->config->all(),
            'flashMessage' => getFlashMessage(),
            'wishlistItems' => getWishlistItems(),
        ]);

        return Response::html($html);
    }
}

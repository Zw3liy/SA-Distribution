<?php
declare(strict_types=1);

namespace App\Domains\Administration\Controllers;

use App\Config\Config;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;

class AdminDashboardController
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Route action for GET /admin — the admin portal landing page/shell.
     * Reads from other domains for summary widgets, writes nothing
     * (docs/specs/02-administration.md §7).
     */
    public function index(Request $request): Response
    {
        $html = View::render('pages/admin/dashboard', [
            'appConfig' => $this->config->all(),
        ]);

        return Response::html($html);
    }
}

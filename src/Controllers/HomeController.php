<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;

class HomeController
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function index(Request $request): Response
    {
        $html = View::render('pages/home', [
            'appConfig' => $this->config->all(),
        ]);

        return Response::html($html);
    }
}

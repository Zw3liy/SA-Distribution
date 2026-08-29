<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Domains\Catalog\Services\ProductServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;

final class HomeController
{
    private Config $config;
    private ?ProductServiceInterface $products;

    public function __construct(Config $config, ?ProductServiceInterface $products = null)
    {
        $this->config = $config;
        $this->products = $products;
    }

    public function index(Request $request): Response
    {
        return Response::html(View::render('pages/home', [
            'appConfig' => $this->config->all(),
            'featuredProducts' => $this->products !== null
                ? $this->products->getProducts([], 'featured', 8, 0)
                : [],
            'categories' => $this->products !== null
                ? $this->products->getCategories()
                : [],
        ]));
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Product.php';
require_once __DIR__ . '/models/CartItem.php';
require_once __DIR__ . '/repositories/ProductRepository.php';
require_once __DIR__ . '/repositories/CartRepository.php';
require_once __DIR__ . '/services/ProductService.php';
require_once __DIR__ . '/services/CartService.php';
require_once __DIR__ . '/controllers/CartController.php';

header('Content-Type: application/json; charset=utf-8');

$appConfig = require __DIR__ . '/config/app.php';
$productService = new ProductService(new ProductRepository($db));
$cartService = new CartService(new ProductRepository($db), new CartRepository($db));
$cartController = new CartController($cartService, $productService);

$sessionId = session_id();
if ($sessionId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Session unavailable.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';

try {
    if ($method === 'GET' && $action === 'summary') {
        $data = $cartController->getCartPageData($sessionId);
        echo json_encode([
            'items' => array_map(function (CartItem $item) {
                return [
                    'product_id' => $item->productId,
                    'slug' => $item->slug,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'price' => $item->price,
                    'sale_price' => $item->salePrice,
                    'quantity' => $item->quantity,
                    'stock' => $item->stock,
                    'thumbnail' => $item->thumbnail,
                    'unit_price' => $item->getUnitPrice(),
                    'line_total' => $item->getLineTotal(),
                ];
            }, $data['items']),
            'summary' => $data['summary'],
        ]);
        exit;
    }

    if ($method === 'POST' && in_array($action, ['add', 'update', 'remove'], true)) {
        $_POST['action'] = $action;
        $cartController->handleCartAction($sessionId);
        echo json_encode(['success' => true, 'message' => getFlashMessage()]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to process cart action.', 'detail' => $exception->getMessage()]);
}

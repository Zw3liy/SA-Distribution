<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Product.php';
require_once __DIR__ . '/models/CartItem.php';
require_once __DIR__ . '/models/QuoteItem.php';
require_once __DIR__ . '/models/Quote.php';
require_once __DIR__ . '/repositories/QuoteRepository.php';
require_once __DIR__ . '/repositories/CartRepository.php';
require_once __DIR__ . '/repositories/ProductRepository.php';
require_once __DIR__ . '/services/CartService.php';
require_once __DIR__ . '/services/QuoteService.php';
require_once __DIR__ . '/controllers/QuoteController.php';

header('Content-Type: application/json; charset=utf-8');

$quoteService = new QuoteService(new QuoteRepository($db));
$cartService = new CartService(new ProductRepository($db), new CartRepository($db));
$quoteController = new QuoteController($quoteService, $cartService);

$sessionId = session_id();
if ($sessionId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Session unavailable.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $quoteId = $quoteController->handleQuoteSubmit($sessionId);
        echo json_encode(['success' => true, 'quote_id' => $quoteId]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to process quote request.', 'detail' => $exception->getMessage()]);
}

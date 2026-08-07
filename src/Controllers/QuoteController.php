<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Domains\Orders\Models\CartItem;
use App\Domains\Orders\Services\CartServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Services\QuoteService;
use InvalidArgumentException;
use Throwable;

class QuoteController
{
    /** @var QuoteService */
    private $quoteService;

    /** @var CartServiceInterface */
    private $cartService;

    /** @var Config */
    private $config;

    public function __construct(QuoteService $quoteService, CartServiceInterface $cartService, Config $config)
    {
        $this->quoteService = $quoteService;
        $this->cartService = $cartService;
        $this->config = $config;
    }

    /**
     * Original business logic, unchanged. Submits the database-backed
     * quote built from the DB-backed cart (see CartController::api()).
     */
    public function handleQuoteSubmit(string $sessionId): int
    {
        $companyName = trim((string) filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $contactName = trim((string) filter_input(INPUT_POST, 'contact_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $email = trim((string) filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '');
        $phone = trim((string) filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $registrationNumber = trim((string) filter_input(INPUT_POST, 'registration_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $vatNumber = trim((string) filter_input(INPUT_POST, 'vat_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $notes = trim((string) filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

        if ($companyName === '' || $contactName === '' || $email === '' || $phone === '') {
            throw new InvalidArgumentException('Missing required quote request fields.');
        }

        $items = $this->cartService->getCartItems($sessionId);
        if (empty($items)) {
            throw new InvalidArgumentException('Your quote cart is empty. Add products before submitting a quote request.');
        }

        $formattedItems = array_map(function (CartItem $item) {
            return [
                'product_id' => $item->productId,
                'slug' => $item->slug,
                'name' => $item->name,
                'sku' => $item->sku,
                'price' => $item->price,
                'sale_price' => $item->salePrice,
                'quantity' => $item->quantity,
            ];
        }, $items);

        $quoteId = $this->quoteService->createQuote($sessionId, [
            'company_name' => $companyName,
            'contact_name' => $contactName,
            'email' => $email,
            'phone' => $phone,
            'registration_number' => $registrationNumber,
            'vat_number' => $vatNumber,
            'notes' => $notes,
        ], $formattedItems);

        return $quoteId;
    }

    /**
     * Original business logic, unchanged.
     */
    public function getQuotePageData(string $sessionId): array
    {
        return [
            'history' => $this->quoteService->getQuoteHistory($sessionId),
        ];
    }

    /**
     * Route action for POST /quote-api.php — the database-backed quote
     * submission JSON API, a direct port of the original quote-api.php.
     */
    public function api(Request $request): Response
    {
        $sessionId = session_id();
        if ($sessionId === '') {
            return Response::json(['error' => 'Session unavailable.'], 400);
        }

        try {
            $quoteId = $this->handleQuoteSubmit($sessionId);

            return Response::json(['success' => true, 'quote_id' => $quoteId]);
        } catch (Throwable $exception) {
            return Response::json([
                'error' => 'Unable to process quote request.',
                'detail' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Route action for POST /quote-request.php — the simple session-based
     * quote capture used from the product detail page's quote form. This
     * is a direct port of the original quote-request.php and, like the
     * original, is unrelated to QuoteService/the database-backed quote
     * above: it only ever writes into $_SESSION['quote_requests']. This
     * pre-existing inconsistency is preserved deliberately, not fixed.
     */
    public function sessionRequest(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $productSlug = trim((string) filter_input(INPUT_POST, 'product_slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
            $productName = trim((string) filter_input(INPUT_POST, 'product_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
            $name = trim((string) filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
            $company = trim((string) filter_input(INPUT_POST, 'company', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
            $email = trim((string) filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '');
            $phone = trim((string) filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
            $message = trim((string) filter_input(INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

            if ($name !== '' && $email !== '' && $productSlug !== '') {
                $_SESSION['quote_requests'][] = [
                    'product_slug' => $productSlug,
                    'product_name' => $productName,
                    'name' => $name,
                    'company' => $company,
                    'email' => $email,
                    'phone' => $phone,
                    'message' => $message,
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                setFlashMessage('Quote request sent successfully. Our team will contact you shortly.');
            } else {
                setFlashMessage('Please provide your name, email, and product details.');
            }

            return Response::redirect('/product-details.php?slug=' . urlencode($productSlug));
        }

        return Response::redirect('/products.php');
    }
}

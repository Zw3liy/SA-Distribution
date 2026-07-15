<?php
declare(strict_types=1);

class QuoteController
{
    /** @var QuoteService */
    private $quoteService;

    /** @var CartService */
    private $cartService;

    public function __construct(QuoteService $quoteService, CartService $cartService)
    {
        $this->quoteService = $quoteService;
        $this->cartService = $cartService;
    }

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

    public function getQuotePageData(string $sessionId): array
    {
        return [
            'history' => $this->quoteService->getQuoteHistory($sessionId),
        ];
    }
}

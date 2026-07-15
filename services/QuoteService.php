<?php
declare(strict_types=1);

class QuoteService
{
    /** @var QuoteRepository */
    private $quoteRepository;

    public function __construct(QuoteRepository $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }

    public function generateQuoteNumber(): string
    {
        $prefix = 'SDQ';
        $timestamp = date('YmdHis');
        $random = random_int(100, 999);
        return sprintf('%s-%s-%s', $prefix, $timestamp, $random);
    }

    public function createQuote(string $sessionId, array $quoteData, array $items): int
    {
        $summary = $this->calculateQuoteSummary($items);

        $payload = [
            'quote_number' => $this->generateQuoteNumber(),
            'session_id' => $sessionId,
            'status' => 'pending',
            'company_name' => trim((string) $quoteData['company_name']),
            'contact_name' => trim((string) $quoteData['contact_name']),
            'email' => trim((string) $quoteData['email']),
            'phone' => trim((string) $quoteData['phone']),
            'registration_number' => trim((string) ($quoteData['registration_number'] ?? '')) ?: null,
            'vat_number' => trim((string) ($quoteData['vat_number'] ?? '')) ?: null,
            'notes' => trim((string) ($quoteData['notes'] ?? '')) ?: null,
            'subtotal' => $summary['subtotal'],
            'vat_amount' => $summary['vat'],
            'grand_total' => $summary['grand_total'],
        ];

        return $this->quoteRepository->saveQuote($payload, $items);
    }

    public function getQuote(int $quoteId): ?Quote
    {
        $quoteRow = $this->quoteRepository->getQuoteById($quoteId);
        if ($quoteRow === null) {
            return null;
        }

        $items = $this->quoteRepository->getQuoteItems($quoteId);
        return new Quote($quoteRow, $items);
    }

    public function getQuoteHistory(string $sessionId): array
    {
        return $this->quoteRepository->getQuotesBySession($sessionId);
    }

    private function calculateQuoteSummary(array $items): array
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $unitPrice = $item['sale_price'] !== null ? (float) $item['sale_price'] : (float) $item['price'];
            $subtotal += $unitPrice * (int) $item['quantity'];
        }

        $vat = round($subtotal * 0.15, 2);
        $grandTotal = round($subtotal + $vat, 2);

        return [
            'subtotal' => $subtotal,
            'vat' => $vat,
            'grand_total' => $grandTotal,
        ];
    }
}

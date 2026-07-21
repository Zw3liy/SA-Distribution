<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

class QuoteRepository
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function saveQuote(array $quoteData, array $items): int
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO quotes (quote_number, session_id, status, company_name, contact_name, email, phone, registration_number, vat_number, notes, subtotal, vat_amount, grand_total) VALUES (:quote_number, :session_id, :status, :company_name, :contact_name, :email, :phone, :registration_number, :vat_number, :notes, :subtotal, :vat_amount, :grand_total)'
            );

            $stmt->bindValue(':quote_number', $quoteData['quote_number'], PDO::PARAM_STR);
            $stmt->bindValue(':session_id', $quoteData['session_id'], PDO::PARAM_STR);
            $stmt->bindValue(':status', $quoteData['status'], PDO::PARAM_STR);
            $stmt->bindValue(':company_name', $quoteData['company_name'], PDO::PARAM_STR);
            $stmt->bindValue(':contact_name', $quoteData['contact_name'], PDO::PARAM_STR);
            $stmt->bindValue(':email', $quoteData['email'], PDO::PARAM_STR);
            $stmt->bindValue(':phone', $quoteData['phone'], PDO::PARAM_STR);
            $stmt->bindValue(':registration_number', $quoteData['registration_number'], PDO::PARAM_STR);
            $stmt->bindValue(':vat_number', $quoteData['vat_number'], PDO::PARAM_STR);
            $stmt->bindValue(':notes', $quoteData['notes'], PDO::PARAM_STR);
            $stmt->bindValue(':subtotal', number_format($quoteData['subtotal'], 2, '.', ''), PDO::PARAM_STR);
            $stmt->bindValue(':vat_amount', number_format($quoteData['vat_amount'], 2, '.', ''), PDO::PARAM_STR);
            $stmt->bindValue(':grand_total', number_format($quoteData['grand_total'], 2, '.', ''), PDO::PARAM_STR);
            $stmt->execute();

            $quoteId = (int) $this->db->lastInsertId();

            $insertItem = $this->db->prepare(
                'INSERT INTO quote_items (quote_id, product_id, product_slug, product_name, product_sku, unit_price, sale_price, quantity, line_total) VALUES (:quote_id, :product_id, :product_slug, :product_name, :product_sku, :unit_price, :sale_price, :quantity, :line_total)'
            );

            foreach ($items as $item) {
                $insertItem->bindValue(':quote_id', $quoteId, PDO::PARAM_INT);
                $insertItem->bindValue(':product_id', $item['product_id'], PDO::PARAM_INT);
                $insertItem->bindValue(':product_slug', $item['slug'], PDO::PARAM_STR);
                $insertItem->bindValue(':product_name', $item['name'], PDO::PARAM_STR);
                $insertItem->bindValue(':product_sku', $item['sku'], PDO::PARAM_STR);
                $insertItem->bindValue(':unit_price', number_format((float) $item['price'], 2, '.', ''), PDO::PARAM_STR);
                $insertItem->bindValue(':sale_price', $item['sale_price'] !== null ? number_format((float) $item['sale_price'], 2, '.', '') : null, PDO::PARAM_STR);
                $insertItem->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
                $insertItem->bindValue(':line_total', number_format((float) $item['quantity'] * ((float) ($item['sale_price'] ?? $item['price'])), 2, '.', ''), PDO::PARAM_STR);
                $insertItem->execute();
            }

            $this->db->commit();
            return $quoteId;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function getQuoteById(int $quoteId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM quotes WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $quoteId, PDO::PARAM_INT);
        $stmt->execute();

        $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        return $quote === false ? null : $quote;
    }

    public function getQuoteItems(int $quoteId): array
    {
        $stmt = $this->db->prepare('SELECT product_id, product_slug AS slug, product_name AS name, product_sku AS sku, unit_price AS price, sale_price, quantity, line_total FROM quote_items WHERE quote_id = :quote_id ORDER BY id ASC');
        $stmt->bindValue(':quote_id', $quoteId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getQuotesBySession(string $sessionId): array
    {
        $stmt = $this->db->prepare('SELECT id, quote_number, status, company_name, contact_name, email, phone, created_at, grand_total FROM quotes WHERE session_id = :session_id ORDER BY created_at DESC');
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

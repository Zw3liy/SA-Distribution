<?php
declare(strict_types=1);

namespace App\Domains\Orders\Services;

/**
 * Temporary home for a contract that will eventually belong to Finance
 * (domain #10) -- see TaxCalculator's own docblock for the full
 * explanation. Signature matches docs/specs/10-finance.md §5 exactly:
 * interface TaxCalculatorInterface { public function calculate(array
 * $lineItems, string $region = 'ZA'): TaxCalculationResult; }
 */
interface TaxCalculatorInterface
{
    /**
     * @param array<int, array{unit_price: float, quantity: int}> $lineItems
     */
    public function calculate(array $lineItems, string $region = 'ZA'): TaxCalculationResult;
}

<?php
declare(strict_types=1);

namespace App\Domains\Orders\Services;

/**
 * Matches the exact shape Finance's own TaxCalculatorInterface will use
 * (docs/specs/10-finance.md §5: "{ subtotal, taxTotal, grandTotal }"),
 * so that when TaxCalculator relocates to Finance (see TaxCalculator's
 * own docblock), this DTO moves with it unchanged.
 */
final class TaxCalculationResult
{
    /** @var float */
    public $subtotal;

    /** @var float */
    public $taxTotal;

    /** @var float */
    public $grandTotal;

    public function __construct(float $subtotal, float $taxTotal, float $grandTotal)
    {
        $this->subtotal = $subtotal;
        $this->taxTotal = $taxTotal;
        $this->grandTotal = $grandTotal;
    }
}

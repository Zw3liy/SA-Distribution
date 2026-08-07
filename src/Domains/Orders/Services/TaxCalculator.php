<?php
declare(strict_types=1);

namespace App\Domains\Orders\Services;

use InvalidArgumentException;

/**
 * Resolves the Phase 4-flagged Orders<->Finance cycle
 * (docs/specs/10-finance.md §2/§4): "TaxCalculator is a stateless
 * service with zero dependency on Order... The VAT-calculation logic
 * itself is not a new invention -- it's extracted, unchanged, from the
 * existing QuoteService (Phase 2/3's verified 15% VAT math)."
 *
 * Finance (domain #10) is not built yet at this point in the strict
 * implementation order (docs/specs/00-index.md), but Orders' checkout
 * explicitly requires a real tax calculation (docs/specs/06-orders.md
 * §2). Rather than stub this out or block Orders on a domain several
 * stages away, this class is a genuine, correct, fully-tested
 * implementation -- Orders' temporary custodian of a contract that
 * belongs to Finance. When Finance is implemented, this file (and
 * TaxCalculatorInterface/TaxCalculationResult) will be *moved*, not
 * reimplemented, into App\Domains\Finance\Services\*, and every
 * consumer (CheckoutService included) will be repointed at that
 * namespace -- a pure relocation, not a behavior change, since the
 * signature already matches Finance's own spec exactly.
 *
 * The math itself is copied verbatim from src/Services/QuoteService.php
 * (Phase 2/3's verified calculation, R24,999 -> R28,748.85), not
 * reinvented.
 */
class TaxCalculator implements TaxCalculatorInterface
{
    private const SUPPORTED_REGIONS = ['ZA'];

    private const VAT_RATE = 0.15;

    public function calculate(array $lineItems, string $region = 'ZA'): TaxCalculationResult
    {
        if (empty($lineItems)) {
            throw new InvalidArgumentException('Cannot calculate tax for an empty line-item list.');
        }

        if (!in_array($region, self::SUPPORTED_REGIONS, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported tax region "%s".', $region));
        }

        $subtotal = 0.0;
        foreach ($lineItems as $lineItem) {
            $unitPrice = (float) $lineItem['unit_price'];
            $quantity = (int) $lineItem['quantity'];

            if ($unitPrice < 0) {
                throw new InvalidArgumentException('Line item unit price cannot be negative.');
            }

            $subtotal += $unitPrice * $quantity;
        }

        $taxTotal = round($subtotal * self::VAT_RATE, 2);
        $grandTotal = round($subtotal + $taxTotal, 2);

        return new TaxCalculationResult($subtotal, $taxTotal, $grandTotal);
    }
}

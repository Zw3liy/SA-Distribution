<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Domains\Orders\Services\TaxCalculationResult;
use App\Domains\Orders\Services\TaxCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * TaxCalculator is Orders' temporary custodian of Finance's contract
 * (docs/specs/06-orders.md §2, docs/specs/10-finance.md §5): the math is
 * extracted verbatim from Phase 2/3's verified QuoteService 15% VAT
 * logic (R24,999 -> R28,748.85).
 */
final class TaxCalculatorTest extends TestCase
{
    private TaxCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TaxCalculator();
    }

    /**
     * The exact regression case carried over from QuoteService: a single
     * R24,999 line at 15% VAT must produce R28,748.85.
     */
    public function testCalculatesVatAtFifteenPercentForSingleLine(): void
    {
        $result = $this->calculator->calculate([
            ['unit_price' => 24999.0, 'quantity' => 1],
        ]);

        $this->assertInstanceOf(TaxCalculationResult::class, $result);
        $this->assertSame(24999.0, $result->subtotal);
        $this->assertSame(3749.85, $result->taxTotal);
        $this->assertSame(28748.85, $result->grandTotal);
    }

    public function testAccumulatesSubtotalAcrossMultipleLineItems(): void
    {
        $result = $this->calculator->calculate([
            ['unit_price' => 100.0, 'quantity' => 2],
            ['unit_price' => 250.5, 'quantity' => 3],
            ['unit_price' => 9.99, 'quantity' => 1],
        ]);

        $this->assertSame(200.0 + 751.5 + 9.99, $result->subtotal);
        $this->assertSame(round(961.49 * 0.15, 2), $result->taxTotal);
        $this->assertSame(round(961.49 * 1.15, 2), $result->grandTotal);
    }

    public function testRoundsTaxToTwoDecimalPlaces(): void
    {
        // 3 x 9.99 = 29.97; 15% = 4.4955 -> must round to 4.50.
        $result = $this->calculator->calculate([
            ['unit_price' => 9.99, 'quantity' => 3],
        ]);

        $this->assertSame(29.97, $result->subtotal);
        $this->assertSame(4.5, $result->taxTotal);
        $this->assertSame(34.47, $result->grandTotal);
    }

    public function testDefaultsToSouthAfricanRegion(): void
    {
        $result = $this->calculator->calculate([['unit_price' => 100.0, 'quantity' => 1]]);

        $this->assertSame(15.0, $result->taxTotal);
    }

    public function testZeroQuantityLineContributesNothing(): void
    {
        $result = $this->calculator->calculate([
            ['unit_price' => 100.0, 'quantity' => 0],
            ['unit_price' => 50.0, 'quantity' => 2],
        ]);

        $this->assertSame(100.0, $result->subtotal);
        $this->assertSame(15.0, $result->taxTotal);
    }

    public function testEmptyLineItemsThrowInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty line-item list');

        $this->calculator->calculate([]);
    }

    public function testUnsupportedRegionThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported tax region "US"');

        $this->calculator->calculate([['unit_price' => 100.0, 'quantity' => 1]], 'US');
    }

    public function testNegativeUnitPriceThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        $this->calculator->calculate([['unit_price' => -5.0, 'quantity' => 1]]);
    }

    public function testResultIsAnImmutableValueObjectWithPublicFields(): void
    {
        $result = new TaxCalculationResult(100.0, 15.0, 115.0);

        $this->assertSame(100.0, $result->subtotal);
        $this->assertSame(15.0, $result->taxTotal);
        $this->assertSame(115.0, $result->grandTotal);
    }
}

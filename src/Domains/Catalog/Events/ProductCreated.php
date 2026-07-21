<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Events;

/**
 * Public event contract per docs/specs/03-catalog.md §9. Not dispatched
 * anywhere yet in this phase -- there is no event bus in the platform
 * yet, and none of this event's real consumers (Inventory, Analytics,
 * AI) exist as domains yet either. Defined now so the contract is
 * settled; wiring actual dispatch is deferred to when the first real
 * consumer (Inventory, domain #5) is built, per the same
 * ahead-of-need-at-the-schema-only-level pattern already used for
 * ProductVariant and Identity's ApiCredential.
 */
final class ProductCreated
{
    /** @var int */
    public $productId;

    /** @var string */
    public $sku;

    public function __construct(int $productId, string $sku)
    {
        $this->productId = $productId;
        $this->sku = $sku;
    }
}

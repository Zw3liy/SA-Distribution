<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Models;

/**
 * Minimal entity matching the product_variants schema
 * (docs/specs/03-catalog.md §4/§19). No repository or service logic
 * populates or reads this yet in this phase -- it exists purely so the
 * shape is settled ahead of real variant requirements, the same way
 * Identity's ApiCredential model was built ahead of the API Platform
 * domain.
 */
class ProductVariant
{
    /** @var int */
    public $id;

    /** @var int */
    public $productId;

    /** @var string */
    public $sku;

    /** @var array */
    public $attributesJson;

    /** @var float|null */
    public $priceOverride;

    /** @var bool */
    public $isActive;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->productId = (int) $data['product_id'];
        $this->sku = (string) $data['sku'];
        $rawAttributes = $data['attributes_json'] ?? null;
        $this->attributesJson = $rawAttributes ? (is_string($rawAttributes) ? (json_decode($rawAttributes, true) ?: []) : (array) $rawAttributes) : [];
        $this->priceOverride = isset($data['price_override']) ? (float) $data['price_override'] : null;
        $this->isActive = (bool) $data['is_active'];
    }
}

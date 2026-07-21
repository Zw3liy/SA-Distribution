<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Models;

class Product
{
    /** @var int */
    public $id;

    /** @var string */
    public $name;

    /** @var string */
    public $slug;

    /** @var string */
    public $sku;

    /** @var float */
    public $price;

    /** @var float|null */
    public $salePrice;

    /** @var int */
    public $stock;

    /** @var bool */
    public $isFeatured;

    /** @var bool */
    public $isNew;

    /** @var bool */
    public $isOnSale;

    /** @var string */
    public $shortDescription;

    /** @var string */
    public $description;

    /** @var int */
    public $categoryId;

    /** @var string */
    public $categoryName;

    /** @var int */
    public $brandId;

    /** @var string */
    public $brandName;

    /** @var string|null */
    public $thumbnail;

    /** @var string */
    public $createdAt;

    /**
     * @var array
     * New in this domain (docs/specs/03-catalog.md §2/§4) -- JSON
     * attribute bag, chosen over a full EAV schema until real
     * variant/attribute requirements exist.
     */
    public $attributesJson;

    /** @var int|null */
    public $taxClassId;

    /**
     * New: the storefront read paths never needed this (they only ever
     * see active products), but the admin listing does -- staff must be
     * able to see deactivated products (docs/specs/03-catalog.md §2).
     * Defaults to true when the column isn't present in a given result
     * set (the storefront queries all still filter is_active=1 in SQL,
     * so any row that reaches this model there is, by definition, active).
     *
     * @var bool
     */
    public $isActive;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->slug = (string) $data['slug'];
        $this->sku = (string) $data['sku'];
        $this->price = (float) $data['price'];
        $this->salePrice = $data['sale_price'] !== null ? (float) $data['sale_price'] : null;
        $this->stock = (int) $data['stock'];
        $this->isFeatured = (bool) $data['is_featured'];
        $this->isNew = (bool) $data['is_new'];
        $this->isOnSale = (bool) $data['is_on_sale'];
        $this->shortDescription = (string) $data['short_description'];
        $this->description = (string) $data['description'];
        $this->categoryId = (int) $data['category_id'];
        $this->categoryName = (string) $data['category_name'];
        $this->brandId = (int) $data['brand_id'];
        $this->brandName = (string) $data['brand_name'];
        $this->thumbnail = $data['thumbnail'] ? sprintf('images/%s', $data['thumbnail']) : null;
        $this->createdAt = (string) $data['created_at'];
        $this->attributesJson = $this->decodeAttributes($data['attributes_json'] ?? null);
        $this->taxClassId = isset($data['tax_class_id']) ? (int) $data['tax_class_id'] : null;
        $this->isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;
    }

    private function decodeAttributes($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return is_string($value) ? (json_decode($value, true) ?: []) : (array) $value;
    }
}

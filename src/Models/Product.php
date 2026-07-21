<?php
declare(strict_types=1);

namespace App\Models;

class Product
{
    public $id;
    public $name;
    public $slug;
    public $sku;
    public $price;
    public $salePrice;
    public $stock;
    public $isFeatured;
    public $isNew;
    public $isOnSale;
    public $shortDescription;
    public $description;
    public $categoryId;
    public $categoryName;
    public $brandId;
    public $brandName;
    public $thumbnail;
    public $createdAt;

    public function __construct(
        $id,
        $name,
        $slug,
        $sku,
        $price,
        $salePrice,
        $stock,
        $isFeatured,
        $isNew,
        $isOnSale,
        $shortDescription,
        $description,
        $categoryId,
        $categoryName,
        $brandId,
        $brandName,
        $thumbnail,
        $createdAt
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->sku = $sku;
        $this->price = $price;
        $this->salePrice = $salePrice;
        $this->stock = $stock;
        $this->isFeatured = $isFeatured;
        $this->isNew = $isNew;
        $this->isOnSale = $isOnSale;
        $this->shortDescription = $shortDescription;
        $this->description = $description;
        $this->categoryId = $categoryId;
        $this->categoryName = $categoryName;
        $this->brandId = $brandId;
        $this->brandName = $brandName;
        $this->thumbnail = $thumbnail;
        $this->createdAt = $createdAt;
    }
}

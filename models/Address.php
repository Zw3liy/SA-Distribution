<?php
declare(strict_types=1);

class Address
{
    /** @var int */
    public $id;

    /** @var int */
    public $userId;

    /** @var string */
    public $label;

    /** @var bool */
    public $isDefault;

    /** @var string */
    public $addressLine1;

    /** @var string|null */
    public $addressLine2;

    /** @var string */
    public $city;

    /** @var string */
    public $region;

    /** @var string */
    public $postalCode;

    /** @var string */
    public $country;

    /** @var string */
    public $type;

    /** @var string */
    public $createdAt;

    /** @var string */
    public $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->userId = (int) $data['user_id'];
        $this->label = (string) $data['label'];
        $this->isDefault = (bool) $data['is_default'];
        $this->addressLine1 = (string) $data['address_line_1'];
        $this->addressLine2 = $data['address_line_2'] ?? null;
        $this->city = (string) $data['city'];
        $this->region = (string) $data['region'];
        $this->postalCode = (string) $data['postal_code'];
        $this->country = (string) $data['country'];
        $this->type = (string) $data['type'];
        $this->createdAt = (string) $data['created_at'];
        $this->updatedAt = (string) $data['updated_at'];
    }
}

<?php
declare(strict_types=1);

namespace App\Domains\Customers\Models;

class Customer
{
    /** @var int */
    public $id;

    /** @var string */
    public $accountType;

    /** @var string|null */
    public $companyName;

    /** @var int|null */
    public $parentCustomerId;

    /** @var string|null */
    public $creditTerms;

    /** @var string */
    public $createdAt;

    /** @var string */
    public $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->accountType = (string) $data['account_type'];
        $this->companyName = $data['company_name'] ?? null;
        $this->parentCustomerId = isset($data['parent_customer_id']) ? (int) $data['parent_customer_id'] : null;
        $this->creditTerms = $data['credit_terms'] ?? null;
        $this->createdAt = (string) $data['created_at'];
        $this->updatedAt = (string) $data['updated_at'];
    }

    public function isB2b(): bool
    {
        return $this->accountType === 'b2b';
    }
}

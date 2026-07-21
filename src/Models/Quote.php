<?php
declare(strict_types=1);

namespace App\Models;

class Quote
{
    /** @var int */
    public $id;

    /** @var string */
    public $quoteNumber;

    /** @var string */
    public $sessionId;

    /** @var string */
    public $status;

    /** @var string */
    public $companyName;

    /** @var string */
    public $contactName;

    /** @var string */
    public $email;

    /** @var string */
    public $phone;

    /** @var string|null */
    public $registrationNumber;

    /** @var string|null */
    public $vatNumber;

    /** @var string|null */
    public $notes;

    /** @var float */
    public $subtotal;

    /** @var float */
    public $vatAmount;

    /** @var float */
    public $grandTotal;

    /** @var string */
    public $createdAt;

    /** @var array */
    public $items;

    public function __construct(array $data, array $items = [])
    {
        $this->id = (int) $data['id'];
        $this->quoteNumber = (string) $data['quote_number'];
        $this->sessionId = (string) $data['session_id'];
        $this->status = (string) $data['status'];
        $this->companyName = (string) $data['company_name'];
        $this->contactName = (string) $data['contact_name'];
        $this->email = (string) $data['email'];
        $this->phone = (string) $data['phone'];
        $this->registrationNumber = $data['registration_number'] ?? null;
        $this->vatNumber = $data['vat_number'] ?? null;
        $this->notes = $data['notes'] ?? null;
        $this->subtotal = (float) $data['subtotal'];
        $this->vatAmount = (float) $data['vat_amount'];
        $this->grandTotal = (float) $data['grand_total'];
        $this->createdAt = (string) $data['created_at'];
        $this->items = $items;
    }
}

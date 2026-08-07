<?php
declare(strict_types=1);

namespace App\Domains\Orders\Models;

/**
 * An orchestration record only (docs/specs/06-orders.md §16) -- no
 * card/payment-instrument data is ever stored here or anywhere in this
 * domain. Only the gateway's own reference ID and a status are kept,
 * keeping the platform outside PCI-DSS card-data scope entirely.
 */
class Payment
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    /** @var int */
    public $id;

    /** @var int */
    public $orderId;

    /** @var string */
    public $method;

    /** @var string */
    public $status;

    /** @var float */
    public $amount;

    /** @var string|null */
    public $gatewayReference;

    /** @var string */
    public $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->orderId = (int) $data['order_id'];
        $this->method = (string) $data['method'];
        $this->status = (string) $data['status'];
        $this->amount = (float) $data['amount'];
        $this->gatewayReference = $data['gateway_reference'] ?? null;
        $this->createdAt = (string) $data['created_at'];
    }
}

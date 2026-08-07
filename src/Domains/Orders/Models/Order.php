<?php
declare(strict_types=1);

namespace App\Domains\Orders\Models;

class Order
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_FULFILLING = 'fulfilling';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETURNED = 'returned';

    /** @var int */
    public $id;

    /** @var string */
    public $orderNumber;

    /** @var int */
    public $customerId;

    /** @var int */
    public $userId;

    /** @var string */
    public $status;

    /** @var float */
    public $subtotal;

    /** @var float */
    public $taxTotal;

    /** @var float */
    public $grandTotal;

    /** @var int */
    public $shippingAddressId;

    /** @var int */
    public $billingAddressId;

    /** @var string */
    public $placedAt;

    /** @var string */
    public $updatedAt;

    /** @var OrderItem[] */
    public $items = [];

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->orderNumber = (string) $data['order_number'];
        $this->customerId = (int) $data['customer_id'];
        $this->userId = (int) $data['user_id'];
        $this->status = (string) $data['status'];
        $this->subtotal = (float) $data['subtotal'];
        $this->taxTotal = (float) $data['tax_total'];
        $this->grandTotal = (float) $data['grand_total'];
        $this->shippingAddressId = (int) $data['shipping_address_id'];
        $this->billingAddressId = (int) $data['billing_address_id'];
        $this->placedAt = (string) $data['placed_at'];
        $this->updatedAt = (string) $data['updated_at'];
    }
}

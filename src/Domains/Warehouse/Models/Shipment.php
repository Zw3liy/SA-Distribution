<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Models;

/**
 * Orchestration record for the outbound leg. Carrier/tracking only --
 * no live carrier API integration in this phase (docs/specs/07-warehouse.md
 * §19, explicitly out of scope; vendor decision pending).
 */
class Shipment
{
    /** @var int */
    public $id;

    /** @var int */
    public $orderId;

    /** @var int */
    public $pickListId;

    /** @var string */
    public $carrier;

    /** @var string|null */
    public $trackingNumber;

    /** @var int */
    public $createdByUserId;

    /** @var string */
    public $shippedAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->orderId = (int) $data['order_id'];
        $this->pickListId = (int) $data['pick_list_id'];
        $this->carrier = (string) $data['carrier'];
        $this->trackingNumber = $data['tracking_number'] ?? null;
        $this->createdByUserId = (int) $data['created_by_user_id'];
        $this->shippedAt = (string) $data['shipped_at'];
    }
}

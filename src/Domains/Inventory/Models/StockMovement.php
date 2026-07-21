<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Models;

/**
 * The durable audit trail for every change to quantity_on_hand
 * (docs/specs/05-inventory.md §2/§15) -- this record itself satisfies
 * the domain's logging requirement; no separate log entry duplicates
 * it.
 */
class StockMovement
{
    /** @var int */
    public $id;

    /** @var int */
    public $inventoryItemId;

    /** @var int */
    public $delta;

    /** @var string */
    public $reason;

    /** @var string|null */
    public $referenceType;

    /** @var string|null */
    public $referenceId;

    /** @var int|null */
    public $actorUserId;

    /** @var string */
    public $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->inventoryItemId = (int) $data['inventory_item_id'];
        $this->delta = (int) $data['delta'];
        $this->reason = (string) $data['reason'];
        $this->referenceType = $data['reference_type'] ?? null;
        $this->referenceId = $data['reference_id'] ?? null;
        $this->actorUserId = isset($data['actor_user_id']) ? (int) $data['actor_user_id'] : null;
        $this->createdAt = (string) $data['created_at'];
    }
}

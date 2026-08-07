<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Models;

/**
 * One per pick list (docs/specs/07-warehouse.md §2: a shipment cannot be
 * created until every line on its pick list is picked AND packed).
 */
class PackingSlip
{
    /** @var int */
    public $id;

    /** @var int */
    public $pickListId;

    /** @var int */
    public $packedByUserId;

    /** @var string */
    public $packedAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->pickListId = (int) $data['pick_list_id'];
        $this->packedByUserId = (int) $data['packed_by_user_id'];
        $this->packedAt = (string) $data['packed_at'];
    }
}

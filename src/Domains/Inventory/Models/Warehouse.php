<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Models;

/**
 * Intentionally minimal per docs/specs/05-inventory.md §19 -- just
 * enough to scope a quantity to a location. The Warehouse domain
 * (#7) owns the richer location model (zones/bins) and will either
 * extend this table or reference it when it is actually implemented.
 */
class Warehouse
{
    /** @var int */
    public $id;

    /** @var string */
    public $name;

    /** @var string */
    public $code;

    /** @var bool */
    public $isActive;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->code = (string) $data['code'];
        $this->isActive = (bool) $data['is_active'];
    }
}

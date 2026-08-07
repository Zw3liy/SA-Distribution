<?php
declare(strict_types=1);

namespace App\Domains\Orders\Models;

/**
 * Catalog price at time of order -- prices are snapshotted onto this
 * model at checkout and never recalculated later from live Catalog data
 * (docs/specs/06-orders.md §2), so a subsequent price change never
 * retroactively alters a placed order.
 */
class OrderItem
{
    /** @var int */
    public $id;

    /** @var int */
    public $orderId;

    /** @var int */
    public $productId;

    /** @var string */
    public $sku;

    /** @var string */
    public $nameSnapshot;

    /** @var int */
    public $quantity;

    /** @var float */
    public $unitPriceSnapshot;

    /** @var float */
    public $lineTotal;

    /**
     * Not in the spec's literal §3 column list, but a necessary addition:
     * links this line item to the Inventory reservation created for it
     * at checkout (docs/specs/05-inventory.md's reserve()/
     * consumeReservation()/releaseReservation() need a reservation id to
     * act on), so OrderService::transition() can consume or release the
     * correct reservation when the order moves to 'fulfilling' or is
     * cancelled (docs/specs/06-orders.md §2's stock lifecycle rule).
     *
     * @var int|null
     */
    public $inventoryReservationId;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->orderId = (int) $data['order_id'];
        $this->productId = (int) $data['product_id'];
        $this->sku = (string) $data['sku'];
        $this->nameSnapshot = (string) $data['name_snapshot'];
        $this->quantity = (int) $data['quantity'];
        $this->unitPriceSnapshot = (float) $data['unit_price_snapshot'];
        $this->lineTotal = (float) $data['line_total'];
        $this->inventoryReservationId = isset($data['inventory_reservation_id']) ? (int) $data['inventory_reservation_id'] : null;
    }
}

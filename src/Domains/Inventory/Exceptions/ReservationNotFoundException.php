<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use RuntimeException;

/**
 * Not one of the three exception types explicitly listed in
 * docs/specs/05-inventory.md §14 (InsufficientStockException,
 * InventoryItemNotFoundException, ReservationExpiredException), but a
 * real, distinct failure mode that consumeReservation()/
 * releaseReservation() must be able to report: the reservation id
 * simply does not exist (bad input / already garbage-collected), which
 * is a different situation from "exists but expired". Added as a
 * deliberate, documented extension beyond the spec's minimal list, not
 * a silent scope change.
 */
class ReservationNotFoundException extends RuntimeException
{
}

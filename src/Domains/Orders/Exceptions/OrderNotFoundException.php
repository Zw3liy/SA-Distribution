<?php
declare(strict_types=1);

namespace App\Domains\Orders\Exceptions;

use RuntimeException;

/**
 * Not one of the four exception types explicitly listed in
 * docs/specs/06-orders.md §14, but a real, distinct failure mode
 * OrderService::transition()/cancel() must be able to report: the order
 * id simply does not exist. Added as a deliberate, documented extension,
 * matching the same precedent as Inventory's ReservationNotFoundException.
 */
class OrderNotFoundException extends RuntimeException
{
}

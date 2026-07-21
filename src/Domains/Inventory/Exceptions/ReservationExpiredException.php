<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use RuntimeException;

/**
 * A real race condition worth a named exception rather than a generic
 * failure (docs/specs/05-inventory.md §14): attempting to consume a
 * reservation that has already auto-expired. Orders' future checkout
 * flow needs to handle this distinctly -- re-check availability and
 * either re-reserve or fail the checkout with a clear message -- rather
 * than treating it the same as "reservation not found".
 */
class ReservationExpiredException extends RuntimeException
{
}

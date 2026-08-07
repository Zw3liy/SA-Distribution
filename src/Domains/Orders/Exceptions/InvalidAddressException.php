<?php
declare(strict_types=1);

namespace App\Domains\Orders\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a shipping/billing address is missing or does not belong
 * to the checking-out customer (docs/specs/06-orders.md §8) -- a defense
 * in depth re-check of Customers domain's own ownership rule, not a
 * replacement for it.
 */
class InvalidAddressException extends InvalidArgumentException
{
}

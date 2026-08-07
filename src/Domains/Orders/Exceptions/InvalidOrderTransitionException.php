<?php
declare(strict_types=1);

namespace App\Domains\Orders\Exceptions;

use RuntimeException;

/**
 * Thrown when a requested status transition is not one of the exact
 * from->to pairs in the state machine (docs/specs/06-orders.md §2/§8):
 * pending_payment -> paid -> fulfilling -> shipped -> delivered,
 * cancelled reachable only from pending_payment/paid, returned only
 * from delivered. No skip, no backward move, except those explicit
 * cancel/return paths.
 */
class InvalidOrderTransitionException extends RuntimeException
{
}

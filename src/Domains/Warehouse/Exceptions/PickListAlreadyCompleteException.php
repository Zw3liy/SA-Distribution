<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Exceptions;

use RuntimeException;

/**
 * Thrown when a pick action (markPicked/markPacked) is attempted on a
 * pick list that is already fully picked, packed, shipped, or cancelled
 * (docs/specs/07-warehouse.md §8).
 */
class PickListAlreadyCompleteException extends RuntimeException
{
}

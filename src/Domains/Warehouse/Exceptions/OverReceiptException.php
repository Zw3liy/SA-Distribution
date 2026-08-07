<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Exceptions;

use RuntimeException;

/**
 * Thrown when a goods receipt line exceeds the quantity ordered on the
 * referenced purchase order without the explicit staff override flag
 * (docs/specs/07-warehouse.md §8).
 */
class OverReceiptException extends RuntimeException
{
}

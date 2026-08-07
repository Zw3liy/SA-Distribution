<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Exceptions;

use RuntimeException;

/**
 * Thrown when StockTransferService::complete() is called on a transfer
 * that is not in 'in_transit' (already completed or cancelled) --
 * completing the same transfer twice would apply the atomic stock pair
 * twice (docs/specs/07-warehouse.md §14).
 */
class TransferAlreadyCompletedException extends RuntimeException
{
}

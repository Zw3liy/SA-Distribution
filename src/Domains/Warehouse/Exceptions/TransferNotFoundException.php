<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Exceptions;

use RuntimeException;

/**
 * Documented extension: StockTransferService::complete() cannot act on a
 * transfer id that does not exist.
 */
class TransferNotFoundException extends RuntimeException
{
}

<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Exceptions;

use RuntimeException;

/**
 * Documented extension (Orders' OrderNotFoundException precedent): a
 * shipment id or pick-list-without-shipment lookup that cannot be
 * satisfied must be reported distinctly, not as a generic exception.
 */
class ShipmentNotFoundException extends RuntimeException
{
}

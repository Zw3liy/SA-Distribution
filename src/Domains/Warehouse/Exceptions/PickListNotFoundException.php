<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Exceptions;

use RuntimeException;

/**
 * Not in the spec's explicit §14 list, but a real, distinct failure mode
 * the Warehouse services must report: a pick list, shipment, or transfer
 * id that does not exist. Same documented-extension precedent as
 * Orders' OrderNotFoundException.
 */
class PickListNotFoundException extends RuntimeException
{
}

<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Exceptions;

use RuntimeException;

/**
 * Thrown by InventoryService::reserve() when quantity > quantity_available
 * (never silently clamped), and reused by adjust() when a negative delta
 * would take quantity_on_hand below zero without an explicit
 * allowNegative override (docs/specs/05-inventory.md §8) -- both are the
 * same underlying business concern ("this would leave insufficient
 * stock"), so one exception type covers both call sites.
 */
class InsufficientStockException extends RuntimeException
{
}

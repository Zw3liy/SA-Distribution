<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Exceptions;

use RuntimeException;

/**
 * Thrown when attempting to change the slug of a product that has
 * already shipped in a customer-facing order/quote (docs/specs/03-catalog.md
 * §2) -- changing it would break historical references and external
 * links.
 */
class SlugImmutableException extends RuntimeException
{
}

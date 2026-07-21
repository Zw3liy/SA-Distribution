<?php
declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

/**
 * Thrown when login is rejected due to too many recent failed attempts
 * for the given email+IP (docs/specs/01-identity.md §16).
 */
class AccountLockedException extends RuntimeException
{
}

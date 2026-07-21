<?php
declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

/**
 * Thrown when credentials are correct but the account is is_active = 0.
 */
class AccountInactiveException extends RuntimeException
{
}

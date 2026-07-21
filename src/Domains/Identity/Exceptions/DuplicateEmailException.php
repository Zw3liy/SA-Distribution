<?php
declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

/**
 * Thrown on registration when the email is already in use.
 */
class DuplicateEmailException extends RuntimeException
{
}

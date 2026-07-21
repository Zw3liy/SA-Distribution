<?php
declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

/**
 * Thrown when an email/password pair does not match. Deliberately never
 * distinguishes "no such email" from "wrong password" in its message --
 * that distinction must never leak to the caller/UI.
 */
class InvalidCredentialsException extends RuntimeException
{
}

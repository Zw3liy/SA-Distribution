<?php
declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Exceptions\AccountInactiveException;
use App\Domains\Identity\Exceptions\AccountLockedException;
use App\Domains\Identity\Exceptions\DuplicateEmailException;
use App\Domains\Identity\Exceptions\InvalidCredentialsException;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Repositories\UserRepositoryInterface;
use RuntimeException;

class AuthService implements AuthServiceInterface
{
    /**
     * Lock out after this many failed attempts for the same email+IP
     * within the window below (docs/specs/01-identity.md §2/§16 -- this
     * closes a real Phase 3 gap: the login_attempts table existed but
     * nothing enforced it).
     */
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_WINDOW_SECONDS = 900; // 15 minutes

    /** @var UserRepositoryInterface */
    private $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function register(array $data): int
    {
        $existingUser = $this->userRepository->findByEmail($data['email']);
        if ($existingUser !== null) {
            throw new DuplicateEmailException('A user with that email already exists.');
        }

        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Unable to hash password.');
        }

        return $this->userRepository->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password_hash' => $passwordHash,
            'is_active' => 1,
            'is_verified' => 0,
            'notifications_marketing' => $data['notifications_marketing'] ?? 0,
            'notifications_updates' => $data['notifications_updates'] ?? 1,
            'account_kind' => $data['account_kind'] ?? 'customer',
        ]);
    }

    public function authenticate(string $email, string $password, string $ip): User
    {
        // Rate-limit check runs before password verification, so a
        // locked-out account never leaks whether the password would
        // have been correct (docs/specs/01-identity.md §8).
        $recentFailures = $this->userRepository->recentFailedAttempts($email, $ip, self::LOCKOUT_WINDOW_SECONDS);
        if ($recentFailures >= self::MAX_FAILED_ATTEMPTS) {
            throw new AccountLockedException('Too many failed login attempts. Please try again later.');
        }

        $user = $this->userRepository->findByEmail($email);
        if ($user === null || !password_verify($password, $user->passwordHash)) {
            $this->userRepository->recordLoginAttempt($email, $ip, false);
            throw new InvalidCredentialsException('Invalid email or password.');
        }

        if (!$user->isActive) {
            $this->userRepository->recordLoginAttempt($email, $ip, false);
            throw new AccountInactiveException('Your account is inactive. Contact support.');
        }

        $this->userRepository->recordLoginAttempt($email, $ip, true);
        $this->userRepository->updateLastLogin($user->id);

        return $user;
    }
}

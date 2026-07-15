<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../models/User.php';

class AuthService
{
    /** @var UserRepository */
    private $userRepository;

    public function __construct($userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function register(array $data): int
    {
        $existingUser = $this->userRepository->findByEmail($data['email']);
        if ($existingUser !== null) {
            throw new InvalidArgumentException('A user with that email already exists.');
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
        ]);
    }

    public function authenticate(string $email, string $password): User
    {
        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            throw new InvalidArgumentException('Invalid email or password.');
        }

        if (!password_verify($password, $user->passwordHash)) {
            throw new InvalidArgumentException('Invalid email or password.');
        }

        if (!$user->isActive) {
            throw new RuntimeException('Your account is inactive. Contact support.');
        }

        $this->userRepository->updateLastLogin($user->id);

        return $user;
    }
}

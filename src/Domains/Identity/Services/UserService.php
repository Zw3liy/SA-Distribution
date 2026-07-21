<?php
declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Repositories\UserRepositoryInterface;
use RuntimeException;

class UserService implements UserServiceInterface
{
    /** @var UserRepositoryInterface */
    private $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getUserById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    public function updateProfile(int $userId, array $data): bool
    {
        $allowed = ['first_name', 'last_name', 'company_name', 'phone', 'password_hash', 'notifications_marketing', 'notifications_updates'];
        $payload = array_intersect_key($data, array_flip($allowed));

        if (isset($data['password'])) {
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                throw new RuntimeException('Unable to hash the new password.');
            }
            $payload['password_hash'] = $passwordHash;
        }

        return $this->userRepository->update($userId, $payload);
    }
}

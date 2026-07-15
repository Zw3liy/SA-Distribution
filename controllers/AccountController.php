<?php
declare(strict_types=1);

class AccountController
{
    /** @var UserService */
    private $userService;

    public function __construct($userService)
    {
        $this->userService = $userService;
    }

    public function getDashboardData(int $userId): array
    {
        $user = $this->userService->getUserById($userId);
        if ($user === null) {
            throw new RuntimeException('User account not found.');
        }

        return [
            'user' => $user,
        ];
    }

    public function updateProfile(int $userId): bool
    {
        $firstName = trim((string) filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $lastName = trim((string) filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $companyName = trim((string) filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $phone = trim((string) filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $password = trim((string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW) ?: '');
        $passwordConfirm = trim((string) filter_input(INPUT_POST, 'password_confirm', FILTER_UNSAFE_RAW) ?: '');

        if ($firstName === '' || $lastName === '' || $phone === '') {
            throw new InvalidArgumentException('First name, last name, and phone are required.');
        }

        if ($password !== '' && $password !== $passwordConfirm) {
            throw new InvalidArgumentException('Password confirmation does not match.');
        }

        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'phone' => $phone,
            'notifications_marketing' => (int) filter_input(INPUT_POST, 'notifications_marketing', FILTER_VALIDATE_BOOLEAN),
            'notifications_updates' => (int) filter_input(INPUT_POST, 'notifications_updates', FILTER_VALIDATE_BOOLEAN),
        ];

        if ($password !== '') {
            $payload['password'] = $password;
        }

        return $this->userService->updateProfile($userId, $payload);
    }
}

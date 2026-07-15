<?php
declare(strict_types=1);

class AuthController
{
    /** @var AuthService */
    private $authService;

    public function __construct($authService)
    {
        $this->authService = $authService;
    }

    public function handleRegister(): void
    {
        $firstName = trim((string) filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $lastName = trim((string) filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $companyName = trim((string) filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $email = trim((string) filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '');
        $phone = trim((string) filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $password = trim((string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW) ?: '');
        $passwordConfirm = trim((string) filter_input(INPUT_POST, 'password_confirm', FILTER_UNSAFE_RAW) ?: '');

        if ($password === '' || $passwordConfirm === '' || $password !== $passwordConfirm) {
            throw new InvalidArgumentException('Password and confirmation must match.');
        }

        if ($firstName === '' || $lastName === '' || $email === '' || $phone === '') {
            throw new InvalidArgumentException('Please complete all required registration fields.');
        }

        $this->authService->register([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'notifications_marketing' => (int) filter_input(INPUT_POST, 'notifications_marketing', FILTER_VALIDATE_BOOLEAN),
            'notifications_updates' => (int) filter_input(INPUT_POST, 'notifications_updates', FILTER_VALIDATE_BOOLEAN),
        ]);

        setFlashMessage('Registration successful. Please check your email to verify your account.');
    }

    public function handleLogin(): User
    {
        $email = trim((string) filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '');
        $password = trim((string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW) ?: '');

        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('Email and password are required.');
        }

        $user = $this->authService->authenticate($email, $password);

        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name'] = $user->getFullName();
        $_SESSION['user_role'] = 'customer';

        return $user;
    }

    public function handleLogout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['user_role']);
        session_regenerate_id(true);
        setFlashMessage('You have been logged out successfully.');
    }
}

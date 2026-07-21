<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Domains\Identity\Services\UserServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AccountController
{
    /** @var UserServiceInterface */
    private $userService;

    /** @var Config */
    private $config;

    public function __construct(UserServiceInterface $userService, Config $config)
    {
        $this->userService = $userService;
        $this->config = $config;
    }

    /**
     * Original business logic, unchanged.
     */
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

    /**
     * Original business logic, unchanged.
     */
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

    /**
     * Route action for GET /account-dashboard.php.
     */
    public function dashboard(Request $request): Response
    {
        if (!isAuthenticated()) {
            return Response::redirect('/login.php');
        }

        $userId = currentUserId();
        $userData = $this->getDashboardData($userId);

        $html = View::render('pages/account-dashboard', [
            'appConfig' => $this->config->all(),
            'user' => $userData['user'],
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    /**
     * Route action for GET/POST /account-edit.php.
     */
    public function edit(Request $request): Response
    {
        if (!isAuthenticated()) {
            return Response::redirect('/login.php');
        }

        $userId = currentUserId();
        $user = $this->userService->getUserById($userId);
        $error = '';

        if ($user === null) {
            return Response::redirect('/login.php');
        }

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                $this->updateProfile($userId);
                setFlashMessage('Your profile has been updated.');

                return Response::redirect('/account-dashboard.php');
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $html = View::render('pages/account-edit', [
            'appConfig' => $this->config->all(),
            'user' => $user,
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }
}

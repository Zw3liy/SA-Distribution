<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Http\Request;
use App\Http\Response;
use App\Models\User;
use App\Services\AuthService;
use App\Support\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AuthController
{
    /** @var AuthService */
    private $authService;

    /** @var Config */
    private $config;

    public function __construct(AuthService $authService, Config $config)
    {
        $this->authService = $authService;
        $this->config = $config;
    }

    /**
     * Original business logic, unchanged: parses and validates
     * registration input and delegates to AuthService.
     */
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

    /**
     * Original business logic, unchanged: parses login input,
     * authenticates, and populates the session.
     */
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

    /**
     * Original business logic, unchanged.
     */
    public function handleLogout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['user_role']);
        session_regenerate_id(true);
        setFlashMessage('You have been logged out successfully.');
    }

    /**
     * Route action for GET/POST /login.php — orchestration that used to
     * live at the top of login.php: guest-only guard, CSRF check, calling
     * handleLogin(), and rendering the same login form.
     */
    public function login(Request $request): Response
    {
        if (isAuthenticated()) {
            return Response::redirect('/account-dashboard.php');
        }

        $error = '';

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                $this->handleLogin();

                return Response::redirect('/account-dashboard.php');
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $html = View::render('pages/login', [
            'appConfig' => $this->config->all(),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    /**
     * Route action for GET/POST /register.php.
     */
    public function register(Request $request): Response
    {
        if (isAuthenticated()) {
            return Response::redirect('/account-dashboard.php');
        }

        $error = '';

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                $this->handleRegister();

                return Response::redirect('/login.php');
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $html = View::render('pages/register', [
            'appConfig' => $this->config->all(),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    /**
     * Route action for GET /logout.php.
     */
    public function logout(Request $request): Response
    {
        if (!isAuthenticated()) {
            return Response::redirect('/login.php');
        }

        $this->handleLogout();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }

        return Response::redirect('/login.php');
    }
}

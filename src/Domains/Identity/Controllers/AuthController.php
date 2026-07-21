<?php
declare(strict_types=1);

namespace App\Domains\Identity\Controllers;

use App\Config\Config;
use App\Domains\Identity\Exceptions\AccountInactiveException;
use App\Domains\Identity\Exceptions\AccountLockedException;
use App\Domains\Identity\Exceptions\DuplicateEmailException;
use App\Domains\Identity\Exceptions\InvalidCredentialsException;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AuthController
{
    /** @var AuthServiceInterface */
    private $authService;

    /** @var Config */
    private $config;

    public function __construct(AuthServiceInterface $authService, Config $config)
    {
        $this->authService = $authService;
        $this->config = $config;
    }

    /**
     * Original business logic, unchanged: parses and validates
     * registration input and delegates to AuthService. Now throws
     * DuplicateEmailException (via AuthService) instead of a generic
     * InvalidArgumentException for the duplicate-email case specifically
     * -- everything else about this method's observable behavior is
     * identical to Phase 3.
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
     * Original business logic, unchanged in observable behavior: parses
     * login input, authenticates, and populates the session. Two real,
     * narrowly-scoped additions per docs/specs/01-identity.md: the
     * client IP is now passed through to AuthService for rate-limiting
     * (§2/§16), and the session ID is regenerated on successful login
     * to close a session-fixation gap that existed in Phase 3 (logout
     * already did this; login never did).
     */
    public function handleLogin(): User
    {
        $email = trim((string) filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '');
        $password = trim((string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW) ?: '');

        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('Email and password are required.');
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $user = $this->authService->authenticate($email, $password, $ip);

        session_regenerate_id(true);

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
     * Route action for GET/POST /login.php. The catch block now maps
     * each typed exception to the same user-facing message Phase 3
     * showed for every failure case (a plain "Invalid email or
     * password." etc.) -- the messages are unchanged, only the
     * underlying exception types are now distinguishable in code and in
     * logs.
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
            } catch (InvalidCredentialsException $exception) {
                $error = 'Invalid email or password.';
            } catch (AccountLockedException $exception) {
                $error = 'Too many failed login attempts. Please try again later.';
            } catch (AccountInactiveException $exception) {
                $error = 'Your account is inactive. Contact support.';
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
            } catch (DuplicateEmailException $exception) {
                $error = 'A user with that email already exists.';
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

<?php
declare(strict_types=1);

namespace App\Domains\Administration\Controllers;

use App\Config\Config;
use App\Domains\Administration\Services\AuditLoggerInterface;
use App\Domains\Identity\Services\AuthServiceInterface;
use App\Domains\Identity\Services\UserServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use RuntimeException;
use Throwable;

/**
 * Staff account management -- delegates to Identity's AuthService/
 * UserService for the actual user record rather than owning any part
 * of the users table itself (docs/specs/02-administration.md §7).
 */
class StaffController
{
    /** @var AuthServiceInterface */
    private $authService;

    /** @var UserServiceInterface */
    private $userService;

    /** @var AuditLoggerInterface */
    private $auditLogger;

    /** @var Config */
    private $config;

    public function __construct(
        AuthServiceInterface $authService,
        UserServiceInterface $userService,
        AuditLoggerInterface $auditLogger,
        Config $config
    ) {
        $this->authService = $authService;
        $this->userService = $userService;
        $this->auditLogger = $auditLogger;
        $this->config = $config;
    }

    /**
     * Route action for GET/POST /admin/staff.
     */
    public function index(Request $request): Response
    {
        $error = '';

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                $action = trim((string) filter_input(INPUT_POST, 'form_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

                if ($action === 'create') {
                    $this->createStaff();
                } elseif ($action === 'toggle_active') {
                    $this->toggleActive();
                } else {
                    throw new RuntimeException('Unknown staff action.');
                }

                return Response::redirect('/admin/staff');
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $html = View::render('pages/admin/staff', [
            'appConfig' => $this->config->all(),
            'staff' => $this->userService->listByAccountKind('staff'),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    private function createStaff(): void
    {
        $firstName = trim((string) filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $lastName = trim((string) filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $email = trim((string) filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '');
        $phone = trim((string) filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $password = trim((string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW) ?: '');

        if ($firstName === '' || $lastName === '' || $email === '' || $phone === '' || $password === '') {
            throw new RuntimeException('All staff fields are required.');
        }

        $id = $this->authService->register([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'account_kind' => 'staff',
        ]);

        $this->auditLogger->record('administration', 'staff.created', 'user', $id, [], [
            'email' => $email,
            'account_kind' => 'staff',
        ]);

        setFlashMessage('Staff account created.');
    }

    private function toggleActive(): void
    {
        $userId = (int) filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $isActive = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_BOOLEAN);

        if ($userId <= 0) {
            throw new RuntimeException('Invalid staff user.');
        }

        $this->userService->setActive($userId, (bool) $isActive);
        $this->auditLogger->record('administration', 'staff.status_changed', 'user', $userId, [], ['is_active' => (bool) $isActive]);

        setFlashMessage('Staff status updated.');
    }
}

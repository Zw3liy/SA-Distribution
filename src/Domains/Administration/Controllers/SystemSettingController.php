<?php
declare(strict_types=1);

namespace App\Domains\Administration\Controllers;

use App\Config\Config;
use App\Domains\Administration\Services\AuditLoggerInterface;
use App\Domains\Administration\Services\SettingsServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use RuntimeException;
use Throwable;

class SystemSettingController
{
    /** @var SettingsServiceInterface */
    private $settingsService;

    /** @var AuditLoggerInterface */
    private $auditLogger;

    /** @var Config */
    private $config;

    public function __construct(SettingsServiceInterface $settingsService, AuditLoggerInterface $auditLogger, Config $config)
    {
        $this->settingsService = $settingsService;
        $this->auditLogger = $auditLogger;
        $this->config = $config;
    }

    /**
     * Route action for GET/POST /admin/settings.
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

                $key = trim((string) filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
                $value = trim((string) filter_input(INPUT_POST, 'value', FILTER_UNSAFE_RAW) ?: '');

                if ($key === '') {
                    throw new RuntimeException('Setting key is required.');
                }

                $before = $this->settingsService->get($key);
                $this->settingsService->set($key, $value, (int) currentUserId());
                $this->auditLogger->record('administration', 'setting.updated', 'system_setting', $key, ['value' => $before], ['value' => $value]);

                setFlashMessage('Setting updated.');

                return Response::redirect('/admin/settings');
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $html = View::render('pages/admin/settings', [
            'appConfig' => $this->config->all(),
            'settings' => $this->settingsService->all(),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }
}

<?php
declare(strict_types=1);

namespace App\Domains\Administration\Controllers;

use App\Config\Config;
use App\Domains\Administration\Services\AuditLoggerInterface;
use App\Domains\Administration\Services\FeatureFlagServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use RuntimeException;
use Throwable;

class FeatureFlagController
{
    /** @var FeatureFlagServiceInterface */
    private $featureFlagService;

    /** @var AuditLoggerInterface */
    private $auditLogger;

    /** @var Config */
    private $config;

    public function __construct(FeatureFlagServiceInterface $featureFlagService, AuditLoggerInterface $auditLogger, Config $config)
    {
        $this->featureFlagService = $featureFlagService;
        $this->auditLogger = $auditLogger;
        $this->config = $config;
    }

    /**
     * Route action for GET/POST /admin/feature-flags.
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
                $isEnabled = filter_input(INPUT_POST, 'is_enabled', FILTER_VALIDATE_BOOLEAN);
                $staffOnly = filter_input(INPUT_POST, 'staff_only', FILTER_VALIDATE_BOOLEAN);

                if ($key === '') {
                    throw new RuntimeException('Feature flag key is required.');
                }

                $this->featureFlagService->set($key, (bool) $isEnabled, ['staff_only' => (bool) $staffOnly], (int) currentUserId());
                $this->auditLogger->record('administration', 'feature_flag.toggled', 'feature_flag', $key, [], ['is_enabled' => (bool) $isEnabled]);

                setFlashMessage('Feature flag updated.');

                return Response::redirect('/admin/feature-flags');
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $html = View::render('pages/admin/feature-flags', [
            'appConfig' => $this->config->all(),
            'flags' => $this->featureFlagService->all(),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }
}

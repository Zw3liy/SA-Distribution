<?php
declare(strict_types=1);

namespace Tests\Unit\Administration;

use App\Domains\Administration\Repositories\AuditLogRepositoryInterface;
use App\Domains\Administration\Services\AuditLogger;
use App\Logging\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuditLoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/audit-logger-test-' . uniqid('', true) . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testRecordInsertsEntryWithExpectedShape(): void
    {
        $repo = $this->createMock(AuditLogRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('insert')
            ->with($this->callback(function (array $entry) {
                return $entry['domain'] === 'administration'
                    && $entry['action'] === 'setting.updated'
                    && $entry['entity_type'] === 'system_setting'
                    && $entry['entity_id'] === 'site.name'
                    && $entry['before'] === ['value' => 'Old']
                    && $entry['after'] === ['value' => 'New'];
            }));

        $service = new AuditLogger($repo, new Logger($this->logFile));
        $service->record('administration', 'setting.updated', 'system_setting', 'site.name', ['value' => 'Old'], ['value' => 'New']);

        $this->assertFalse(is_file($this->logFile), 'Logger must not write anything on a successful audit insert.');
    }

    /**
     * The one behavior this class exists to guarantee
     * (docs/specs/02-administration.md §14): a broken audit table must
     * never propagate an exception back to the caller, since the
     * caller is always in the middle of a different, more important
     * write. It's recorded to the application log instead.
     */
    public function testRecordSwallowsRepositoryFailuresAndLogsThemInstead(): void
    {
        $repo = $this->createMock(AuditLogRepositoryInterface::class);
        $repo->method('insert')->willThrowException(new RuntimeException('DB is down'));

        $service = new AuditLogger($repo, new Logger($this->logFile));

        // Must not throw.
        $service->record('administration', 'setting.updated', 'system_setting', 'site.name', [], []);

        $this->assertTrue(is_file($this->logFile), 'Failure must be written to the application log.');
        $this->assertStringContainsString('Audit log write failed', (string) file_get_contents($this->logFile));
    }
}

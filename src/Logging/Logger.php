<?php
declare(strict_types=1);

namespace App\Logging;

use Throwable;

/**
 * Minimal file-based logger. Not a PSR-3 implementation, just enough
 * structure to stop errors from being silently swallowed or only ever
 * shown raw to the end user. Writes to storage/logs/app.log.
 */
final class Logger
{
    /** @var string */
    private $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function exception(Throwable $exception): void
    {
        $this->error($exception->getMessage(), [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }

    private function write(string $level, string $message, array $context): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            '[%s] %s: %s%s%s',
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '',
            PHP_EOL
        );

        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

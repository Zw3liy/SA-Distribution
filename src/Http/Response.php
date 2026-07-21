<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Represents an outgoing response. Controllers build one of these and
 * return it; the Kernel is the only place that actually emits headers
 * and output, which is what makes the front-controller pattern possible.
 */
final class Response
{
    /** @var string */
    private $body;

    /** @var int */
    private $statusCode;

    /** @var array<string, string> */
    private $headers;

    public function __construct(string $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public static function html(string $body, int $statusCode = 200): self
    {
        return new self($body, $statusCode, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(array $data, int $statusCode = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $statusCode,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $location, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $location]);
    }

    public static function notFound(string $body): self
    {
        return new self($body, 404, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function forbidden(string $body): self
    {
        return new self($body, 403, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}

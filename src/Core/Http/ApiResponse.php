<?php

declare(strict_types=1);

namespace ForkPanelXc\Core\Http;

/**
 * JSON-first response object for the Fork Panel XC API.
 */
class ApiResponse
{
    private $streamer = null;

    private function __construct(
        private int $status = 200,
        private array $headers = [],
        private string $body = '',
        ?callable $streamer = null
    ) {
        $this->streamer = $streamer;
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        return new self($status, array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
        ], $headers), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    public static function error(string $message, int $status = 400, array $errors = []): self
    {
        $payload = ['error' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return self::json($payload, $status);
    }

    public static function download(string $content, string $filename, string $contentType = 'application/octet-stream'): self
    {
        return new self(200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Content-Length' => (string) strlen($content),
        ], $content);
    }

    public static function raw(string $content, string $contentType = 'text/plain; charset=utf-8', int $status = 200): self
    {
        return new self($status, ['Content-Type' => $contentType], $content);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self($status, ['Location' => $url]);
    }

    /**
     * Stream a binary response without buffering it in memory.
     */
    public static function stream(callable $streamer, array $headers = [], int $status = 200): self
    {
        return new self($status, array_merge([
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ], $headers), '', $streamer);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    public function withHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            header(sprintf('%s: %s', $key, $value));
        }

        if ($this->streamer !== null) {
            ($this->streamer)();

            return;
        }

        echo $this->body;
    }
}

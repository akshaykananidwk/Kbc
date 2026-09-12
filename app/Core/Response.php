<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private int $status = 200;
    private string $body = '';
    /** @var array<string,string> */
    private array $headers = [];

    public static function make(string $body = '', int $status = 200): Response
    {
        $response = new self();
        $response->body = $body;
        $response->status = $status;
        return $response;
    }

    public static function html(string $body, int $status = 200): Response
    {
        return self::make($body, $status)->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function text(string $body, int $status = 200): Response
    {
        return self::make($body, $status)->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** @param array<string,mixed>|list<mixed> $data */
    public static function json(array $data, int $status = 200): Response
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return self::make($encoded === false ? '{}' : $encoded, $status)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Standard API envelope used by every endpoint under /api.
     *
     * @param array<string,mixed> $data
     */
    public static function apiSuccess(string $message = 'OK', array $data = [], int $status = 200): Response
    {
        return self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    /** @param array<string,mixed> $errors */
    public static function apiError(string $message, int $status = 400, array $errors = []): Response
    {
        $payload = ['success' => false, 'message' => $message, 'data' => new \stdClass()];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }
        return self::json($payload, $status);
    }

    public static function redirect(string $to, int $status = 302): Response
    {
        $target = str_starts_with($to, 'http://') || str_starts_with($to, 'https://')
            ? $to
            : Application::url($to);
        return self::make('', $status)->withHeader('Location', $target);
    }

    public static function download(string $path, string $filename, string $contentType = 'application/octet-stream'): Response
    {
        $response = new self();
        $response->status = 200;
        $response->headers = [
            'Content-Type'              => $contentType,
            'Content-Disposition'       => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'Content-Length'            => (string) (filesize($path) ?: 0),
            'X-Content-Type-Options'    => 'nosniff',
            'Cache-Control'             => 'no-store',
        ];
        $response->body = '';
        $response->streamFile = $path;
        return $response;
    }

    private ?string $streamFile = null;

    public function withHeader(string $name, string $value): Response
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withStatus(int $status): Response
    {
        $this->status = $status;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }
        if ($this->streamFile !== null && is_file($this->streamFile)) {
            $handle = fopen($this->streamFile, 'rb');
            if ($handle !== false) {
                while (!feof($handle)) {
                    echo fread($handle, 8192);
                    flush();
                }
                fclose($handle);
            }
            return;
        }
        echo $this->body;
    }
}

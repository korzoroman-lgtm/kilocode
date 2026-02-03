<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Response
 * Wrapper for HTTP response operations
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private mixed $body = '';

    public function __construct(mixed $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = array_merge([
            'Content-Type' => 'text/html; charset=UTF-8',
        ], $headers);
    }

    /**
     * Create JSON response
     */
    public static function json(mixed $data, int $statusCode = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $statusCode,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Create success response
     */
    public static function success(mixed $data = null, string $message = 'Success'): self
    {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Create error response
     */
    public static function error(string $message, int $statusCode = 400, array $errors = []): self
    {
        $body = [
            'success' => false,
            'error' => $message,
        ];

        if (!empty($errors)) {
            $body['errors'] = $errors;
        }

        return self::json($body, $statusCode);
    }

    /**
     * Create redirect response
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $url]);
    }

    /**
     * Create HTML response
     */
    public static function html(string $html, int $statusCode = 200): self
    {
        return new self($html, $statusCode, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Set status code
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Get status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set header
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set multiple headers
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Get header value
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Set response body
     */
    public function setBody(mixed $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Get response body
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Send the response
     */
    public function send(): void
    {
        // Set status code
        http_response_code($this->statusCode);

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Send body
        if (is_array($this->body) || is_object($this->body)) {
            echo json_encode($this->body, JSON_UNESCAPED_UNICODE);
        } else {
            echo $this->body;
        }
    }

    /**
     * Get response as string (for testing)
     */
    public function getContent(): string
    {
        if (is_array($this->body) || is_object($this->body)) {
            return json_encode($this->body, JSON_UNESCAPED_UNICODE);
        }
        return (string) $this->body;
    }
}

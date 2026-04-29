<?php
declare(strict_types=1);

namespace Cms\Http;

final class Response
{
    public function __construct(
        private string $content,
        private int $statusCode = 200,
        private array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public static function redirect(string $location, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->content;
    }
}

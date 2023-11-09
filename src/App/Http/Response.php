<?php
declare(strict_types=1);

namespace App\Http;

/**
 *
 */
class Response
{
    public int $statusCode;
    public array $headers;
    public string $body;

    /**
     * @param int $statusCode
     * @param array $headers
     * @param string $body
     */
    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * @param int $statusCode
     * @param array $headers
     * @param string $body
     * @return Response
     */
    public static function new(int $statusCode, array $headers, string $body): Response
    {
        return new self($statusCode, $headers, $body);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $context = "HTTP/1.1 {$this->statusCode}\r\n";
        $this->headers['Content-Length'] = strlen($this->body);
        foreach ($this->headers as $key => $value) {
            $context .= "{$key}: {$value}\r\n";
        }
        $context .= "\r\n";
        $context .= $this->body;
        return $context;
    }
}

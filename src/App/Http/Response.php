<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\App\Http;

use function strlen;

/**
 *
 */
class Response
{
    public int $statusCode;
    public array $headers;
    public string $body;

    /**
     * @param $statusCode
     * @param $headers
     * @param $body
     */
    public function __construct($statusCode, $headers, $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
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

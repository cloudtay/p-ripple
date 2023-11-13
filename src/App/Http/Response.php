<?php
declare(strict_types=1);

namespace App\Http;

use FileSystem\File;
use Worker\NetWorker\Client;

/**
 * 响应实体
 */
class Response
{
    /**
     * @var int
     */
    public int $statusCode = 200;

    /**
     * @var array
     */
    public array $headers = [];

    /**
     * @var string
     */
    public string $body = '';

    /**
     * @var File $file
     */
    public File $file;

    /**
     * @var bool $isFile
     */
    public bool $isFile = false;

    /**
     * @var Request $request
     */
    public Request $request;

    /**
     * @var Client $client
     */
    public Client $client;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->client  = $request->client;
    }

    /**
     * @param string     $key
     * @param string|int $value
     * @return Response
     */
    public function setHeader(string $key, string|int $value): Response
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * @param int $statusCode
     * @return Response
     */
    public function setStatusCode(int $statusCode): Response
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function setHeaders(array $headers): Response
    {
        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }
        return $this;
    }

    /**
     * @param string|File $body
     * @return Response
     */
    public function setBody(string|File $body): Response
    {
        if ($body instanceof File) {
            $this->isFile = true;
            $this->file   = $body;
            $this->body   = '';
        } else {
            $this->body = $body;
        }
        return $this;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $context = "HTTP/1.1 {$this->statusCode}\r\n";
        if (!$this->isFile) {
            $this->headers['Content-Length'] = strlen($this->body);
        }
        foreach ($this->headers as $key => $value) {
            $context .= "{$key}: {$value}\r\n";
        }
        $context .= "\r\n";
        $context .= $this->body;
        return $context;
    }
}

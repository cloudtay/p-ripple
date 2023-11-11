<?php
declare(strict_types=1);

namespace App\Http;

use Worker\NetWorker\Client;


/**
 * 请求单例
 */
class RequestSingle
{

    /**
     * @var string
     */
    public string $hash;

    /**
     * @var string
     */
    public string $method;

    /**
     * @var string
     */
    public string $url;

    /**
     * @var string
     */
    public string $version;

    /**
     * @var string
     */
    public string $header;

    /**
     * @var array
     */
    public array $headers = [];

    /**
     * @var string
     */
    public string $body = '';

    /**
     * @var int
     */
    public int $bodyLength = 0;


    /**
     * @var int
     */
    public int $statusCode;

    /**
     * @var Client
     */
    public Client $client;


    /**
     * @var bool
     */
    public bool $upload = false;

    /**
     * @var string
     */
    public string $boundary = '';

    /**
     * @var RequestUpload
     */
    public RequestUpload $uploadHandler;


    /**
     * RequestSingle constructor.
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->hash = md5($client->getHash() . microtime(true) . mt_rand(0, 1000000));
        $this->client = $client;
        $this->statusCode = RequestFactory::INCOMPLETE;
    }

    /**
     * @param Client $client
     * @return self
     */
    public static function new(Client $client): RequestSingle
    {
        return new self($client);
    }

    /**
     * Push request body
     * @param string $context
     * @return RequestSingle
     * @throws RequestSingleException
     */
    public function revolve(string $context): self
    {
        if (!isset($this->method)) {
            if ($this->parseRequestHead($context)) {
                $context = $this->body;
            } else {
                return $this;
            }
        }
        switch ($this->method) {
            case 'GET':
                $this->statusCode = RequestFactory::COMPLETE;
                break;
            case 'POST':
                $this->bodyLength += strlen($context);
                if ($this->upload) {
                    $this->uploadHandler->push($context);
                } else {
                    $this->body .= $context;
                }
                if ($this->bodyLength === intval($this->headers['Content-Length'])) {
                    $this->statusCode = RequestFactory::COMPLETE;
                } elseif ($this->bodyLength > intval($this->headers['Content-Length'])) {
                    throw new RequestSingleException('Content-Length is not match');
                } else {
                    $this->statusCode = RequestFactory::INCOMPLETE;
                }
                break;
        }
        return $this;
    }

    /**
     * @param string $context
     * @return bool
     * @throws RequestSingleException
     */
    private function parseRequestHead(string $context): bool
    {
        if ($headerEnd = strpos($context, "\r\n\r\n")) {
            $this->header = substr($context, 0, $headerEnd);
            $this->body = substr($context, $headerEnd + 4);
            $baseContent = strtok($this->header, "\r\n");
            if (count($base = explode(' ', $baseContent)) !== 3) {
                $this->statusCode = RequestFactory::INVALID;
                return false;
            }
            $this->url = $base[1];
            $this->version = $base[2];
            $this->method = $base[0];
            while ($line = strtok("\r\n")) {
                $lineParam = explode(': ', $line, 2);
                if (count($lineParam) >= 2) {
                    $this->headers[$lineParam[0]] = $lineParam[1];
                }
            }

            if ($this->method === 'GET') {
                $this->statusCode = RequestFactory::COMPLETE;
                return true;
            }

            if (!isset($this->headers['Content-Length'])) {
                throw new RequestSingleException('Content-Length is not set');
            }

            # 初次解析POST类型
            if (!isset($this->headers['Content-Type'])) {
                throw new RequestSingleException('Content-Type is not set');
            }

            $contentType = $this->headers['Content-Type'];

            if (str_contains($contentType, 'multipart/form-data')) {
                preg_match('/boundary=(.*)$/', $contentType, $matches);
                if (isset($matches[1])) {
                    $this->boundary = $matches[1];
                    $this->upload = true;
                    $this->uploadHandler = new RequestUpload($this, $this->boundary);
                    $this->uploadHandler->push($this->body);
                    $this->body = '';
                } else {
                    throw new RequestSingleException('boundary is not set');
                }
            }
            return true;
        } else {
            $this->body .= $context;
        }
        return false;
    }

    /**
     * 打包请求
     * @return Request
     */
    public function build(): Request
    {
        return new Request($this);
    }
}

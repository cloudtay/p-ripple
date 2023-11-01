<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\App\Http;

use Cclilshy\PRipple\Build;
use Cclilshy\PRipple\Service\Client;
use Fiber;
use Throwable;

class Request
{
    public string $hash;
    public string $host;
    public string $scheme;
    public string $url;
    public string $method;
    public bool $upload;
    public array $files = array();
    public string $path;
    public string $version;
    public string $header;
    public string $body;
    public array $headerArray = array();
    public array $post = array();
    public array $query = array();
    public Client $client;

    /**
     * @var callable $onUpload
     */
    private $onUpload;

    /**
     * @param RequestSingle $requestSingle
     */
    public function __construct(RequestSingle $requestSingle)
    {
        $this->hash = $requestSingle->hash;
        $this->url = $requestSingle->url;
        $this->method = $requestSingle->method;
        if (($this->upload = $requestSingle->upload)) {
            $this->files = $requestSingle->uploadHandler->files;
        }
        $this->version = $requestSingle->version;
        $this->header = $requestSingle->header;
        $this->headerArray = $requestSingle->headers;
        $this->body = $requestSingle->body;
        $this->client = $requestSingle->client;

        $info = parse_url($this->url);
        if ($query = $info['query'] ?? null) {
            parse_str($query, $this->query);
        }
        $this->path = $info['path'];
        $this->host = $info['host'] ?? '';
        $this->scheme = $info['scheme'] ?? '';
        if (isset($this->headerArray['Content-Type']) && $this->headerArray['Content-Type'] === 'application/json') {
            $this->post = json_decode($this->body, true);
        } else {
            parse_str($this->body, $this->post);
        }
    }

    /**
     * @param callable $callable
     * @return void
     */
    public function handleUpload(callable $callable): void
    {
        $this->onUpload = $callable;
    }

    /**
     * @return void
     */
    public function wait(): void
    {
        try {
            if ($response = Fiber::suspend(Build::new('suspend', null, $this->hash))) {
                $this->onEvent($response);
            }
        } catch (Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    /**
     * @param \Cclilshy\PRipple\Build $event
     * @return void
     */
    private function onEvent(Build $event): void
    {
        switch ($event->name) {
            case 'http.upload.complete':
                if (isset($this->onUpload)) {
                    call_user_func($this->onUpload, $event->data['info']);
                }
                break;
            default:
                break;
        }
    }
}

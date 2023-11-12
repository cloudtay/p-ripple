<?php
declare(strict_types=1);

namespace App\Http;

use Closure;
use Std\CollaborativeFiberStd;
use Throwable;
use Worker\Build;
use Worker\NetWorker\Client;

/**
 * 请求实体
 */
class Request extends CollaborativeFiberStd
{
    public const EVENT_UPLOAD = 'http.upload.complete';

    /**
     * @var string|mixed
     */
    public string $host;

    /**
     * @var string|mixed
     */
    public string $scheme;

    /**
     * @var string
     */
    public string $url;

    /**
     * @var string
     */
    public string $method;

    /**
     * @var bool
     */
    public bool $upload;

    /**
     * @var array
     */
    public array $files = array();

    /**
     * @var string|mixed
     */
    public string $path;

    /**
     * @var string
     */
    public string $version;

    /**
     * @var string
     */
    public string $header;

    /**
     * @var string
     */
    public string $body;

    /**
     * @var array
     */
    public array $headerArray = array();

    /**
     * @var array|mixed
     */
    public array $post = array();

    /**
     * @var array
     */
    public array $query = array();

    /**
     * @var Client
     */
    public Client $client;

    /**
     * @var array
     */
    public array $serverArray = array();

    /**
     * @var mixed|array
     */
    public mixed $cookieArray = array();

    /**
     * @var bool
     */
    public bool $keepAlive = false;

    /**
     * 异步事件订阅列表
     * @var Closure[] $asyncHandlers
     */
    private array $asyncHandlers = array();
    private RequestSingle $requestSingle;

    /**
     * @param RequestSingle $requestSingle
     */
    public function __construct(RequestSingle $requestSingle)
    {
        $this->url = $requestSingle->url;
        $this->method = $requestSingle->method;
        if (($this->upload = $requestSingle->upload)) {
            $this->files = $requestSingle->uploadHandler->files;
        }
        $this->version = $requestSingle->version;
        $this->header = $requestSingle->header;
        $this->headerArray = $requestSingle->headers;

        if ($connection = $this->headerArray['Connection'] ?? null) {
            $this->keepAlive = strtoupper($connection) === 'KEEP-ALIVE';
        }

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
        if ($cookie = $this->headerArray['Cookie'] ?? null) {
            $this->cookieArray['Cookie'] = explode('; ', $cookie);
        }
        $this->hash = $requestSingle->hash;
        $this->requestSingle = $requestSingle;
    }

    /**
     * 订阅异步事件
     * @param string $action
     * @param Closure $callable
     * @return void
     */
    public function async(string $action, Closure $callable): void
    {
        $this->asyncHandlers[$action] = $callable;
    }

    /**
     * 声明等待异步事件
     * @return void
     * @throws Throwable
     */
    public function await(): void
    {
        foreach ($this->asyncHandlers as $action => $handler) {
            $event = $this->publishAwait($action, null);
            $this->handleEvent($event);
        }
    }

    /**
     * 处理异步事件
     * @param Build $event
     * @return void
     */
    protected function handleEvent(Build $event): void
    {
        if ($handler = $this->asyncHandlers[$event->name]) {
            call_user_func_array($handler, [$event->data]);
        }
    }

    public function setupWithCallable(Closure $callable): CollaborativeFiberStd
    {
        $result = parent::setupWithCallable($callable);
        $this->requestSingle->hash = $this->hash;
        return $result;
    }
}

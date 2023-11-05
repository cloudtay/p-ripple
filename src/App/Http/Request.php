<?php
declare(strict_types=1);

namespace PRipple\App\Http;

use Closure;
use PRipple\Std\TaskStd;
use PRipple\Worker\Build;
use PRipple\Worker\NetWorker\Client;
use Throwable;

/**
 * 请求实体
 */
class Request extends TaskStd
{
    public const EVENT_UPLOAD = 'http.upload.complete';

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
     * 异步事件订阅列表
     * @var Closure[] $asyncHandlers
     */
    private array $asyncHandlers = array();

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
        parent::__construct();
        $this->hash = $requestSingle->hash;
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
     * 处理异步事件
     * @param Build $event
     * @return void
     */
    protected function handleEvent(Build $event): void
    {
        if ($uploadHandler = $this->asyncHandlers[$event->name]) {
            call_user_func_array($uploadHandler, [$event->data]);
        }
    }

    /**
     * 声明等待异步事件
     * @return void
     * @throws Throwable
     */
    public function await(): void
    {
        foreach ($this->asyncHandlers as $action => $handler) {
            $this->handleEvent($this->publishAwait($action, null));
        }
    }
}

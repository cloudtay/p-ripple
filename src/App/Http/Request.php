<?php
declare(strict_types=1);

namespace App\Http;

use Closure;
use Std\TaskStd;
use Throwable;
use Worker\Build;
use Worker\NetWorker\Client;

/**
 * 请求实体
 */
class Request extends TaskStd
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
//        $this->serverArray['SERVER_NAME'] = $this->host;
//        $this->serverArray['SERVER_PORT'] = $info['port'] ?? 80;
//        $this->serverArray['REQUEST_URI'] = $this->path;
//        $this->serverArray['REQUEST_METHOD'] = $this->method;
//        $this->serverArray['SCRIPT_NAME'] = $this->path;
//        $this->serverArray['SCRIPT_FILENAME'] = $this->path;
//        $this->serverArray['QUERY_STRING'] = $query;
//        $this->serverArray['SERVER_PROTOCOL'] = $this->version;
//        $this->serverArray['HTTP_HOST'] = $this->host;
//        $this->serverArray['HTTP_USER_AGENT'] = $this->headerArray['User-Agent'] ?? '';
//        $this->serverArray['HTTP_ACCEPT'] = $this->headerArray['Accept'] ?? '';
//        $this->serverArray['HTTP_ACCEPT_LANGUAGE'] = $this->headerArray['Accept-Language'] ?? '';
//        $this->serverArray['HTTP_ACCEPT_ENCODING'] = $this->headerArray['Accept-Encoding'] ?? '';
//        $this->serverArray['HTTP_COOKIE'] = $this->headerArray['Cookie'] ?? '';
//        $this->serverArray['HTTP_CONNECTION'] = $this->headerArray['Connection'] ?? '';
//        $this->serverArray['REMOTE_ADDR'] = $this->client->getAddress();
//        $this->serverArray['REMOTE_PORT'] = $this->client->getPort();
//        $this->serverArray['SERVER_ADDR'] = '127.0.0.1';
//        $this->serverArray['SERVER_PORT'] = 80;
//        $this->serverArray['SERVER_SOFTWARE'] = 'PRipple';
//        $this->serverArray['REQUEST_TIME'] = time();
//        $this->serverArray['REQUEST_TIME_FLOAT'] = microtime(true);
//        $this->serverArray['HTTP_ORIGIN'] = $this->headerArray['Origin'] ?? '';
//        $this->serverArray['HTTP_REFERER'] = $this->headerArray['Referer'] ?? '';
//        $this->serverArray['HTTP_CACHE_CONTROL'] = $this->headerArray['Cache-Control'] ?? '';
//        $this->serverArray['HTTP_PRAGMA'] = $this->headerArray['Pragma'] ?? '';
//        $this->serverArray['HTTP_UPGRADE_INSECURE_REQUESTS'] = $this->headerArray['Upgrade-Insecure-Requests'] ?? '';
//        $this->serverArray['HTTP_DNT'] = $this->headerArray['DNT'] ?? '';
//        $this->serverArray['HTTP_TE'] = $this->headerArray['TE'] ?? '';
//        $this->serverArray['HTTP_CDN_LOOP'] = $this->headerArray['CDN-Loop'] ?? '';
//        $this->serverArray['HTTP_CF_CONNECTING_IP'] = $this->headerArray['CF-Connecting-IP'] ?? '';
//        $this->serverArray['HTTP_CF_RAY'] = $this->headerArray['CF-Ray'] ?? '';
//        $this->serverArray['HTTP_CF_VISITOR'] = $this->headerArray['CF-Visitor'] ?? '';
//        $this->serverArray['HTTP_X_FORWARDED_PORT'] = $this->headerArray['X-Forwarded-Port'] ?? '';
//        $this->serverArray['HTTP_X_REAL_IP'] = $this->headerArray['X-Real-Ip'] ?? '';
//        $this->serverArray['HTTP_X_REQUEST_ID'] = $this->headerArray['X-Request-Id'] ?? '';
//        $this->serverArray['HTTP_X_REQUEST_START'] = $this->headerArray['X-Request-Start'] ?? '';
//        $this->serverArray['HTTP_X_REQUESTED_WITH'] = $this->headerArray['X-Requested-With'] ?? '';
//        $this->serverArray['HTTP_X_WAP_PROFILE'] = $this->headerArray['X-Wap-Profile'] ?? '';
//        $this->serverArray['HTTP_PROXY_CONNECTION'] = $this->headerArray['Proxy-Connection'] ?? '';
//        $this->serverArray['HTTP_X_CLIENT_IP'] = $this->headerArray['X-Client-Ip'] ?? '';
//        $this->serverArray['HTTP_X_FORWARDED_HOST'] = $this->headerArray['X-Forwarded-Host'] ?? '';
//        $this->serverArray['HTTP_X_FORWARDED_SERVER'] = $this->headerArray['X-Forwarded-Server'] ?? '';
//        $this->serverArray['HTTP_X_FORWARDED_FOR'] = $this->headerArray['X-Forwarded-For'] ?? '';
//        $this->serverArray['HTTP_X_FORWARDED_PROTO'] = $this->headerArray['X-Forwarded-Proto'] ?? '';
//        $this->serverArray = array_filter($this->serverArray, function ($item) {
//            return !empty($item);
//        });
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
}

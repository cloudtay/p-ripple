<?php
declare(strict_types=1);

namespace App\Http;

use Closure;
use Core\Output;
use Fiber;
use FileSystem\File;
use Std\CollaborativeFiberStd;
use Throwable;
use Worker\Build;
use Worker\NetWorker\Client;

/**
 * 请求实体
 */
class Request extends CollaborativeFiberStd
{
    public const        EVENT_UPLOAD   = 'http.upload.complete';
    public const        EVENT_DOWNLOAD = 'http.download.complete';

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
     * @var Closure $exceptionHandler
     */
    public Closure $exceptionHandler;

    /**
     * 异步事件订阅列表
     * @var Closure[] $asyncHandlers
     */
    private array $asyncHandlers = array();

    /**
     * 请求原始单例
     * @var RequestSingle
     */
    private RequestSingle $requestSingle;

    /**
     * Response包应该储存请求原始数据,包括客户端连接
     * 考虑到Request在HttpWorker中的生命周期,当请求对象在Worker中被释放时
     * 如用户下载文件等情况无需保留Request,只需在心跳期间Response依然与客户端交互
     * @var Response $response
     */
    public Response $response;

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
        $this->response = new Response($this);
    }

    /**
     * 响应基础文本
     * @param string|File $body
     * @param array|null  $headers
     * @return Response
     */
    public function respondBody(string|File $body, array|null $headers = []): Response
    {
        $headers = array_merge([
            'Content-Type' => 'text/html; charset=utf-8',
        ], $headers);
        return $this->response->setHeaders($headers)->setBody($body);
    }

    /**
     * 响应json
     * @param array|string $data
     * @return Response
     */
    public function respondJson(array|string $data): Response
    {
        $this->response->setHeader('Content-Type', 'application/json');
        $body = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
        return $this->response->setBody($body);
    }

    /**
     * 响应一个下载请求
     * @param string     $path
     * @param string     $filename
     * @param array|null $headers
     * @return Response
     */
    public function respondFile(string $path, string $filename, array|null $headers = []): Response
    {
        $filesize                       = filesize($path);
        $headers['Content-Type']        = 'application/octet-stream';
        $headers['Content-Disposition'] = "attachment; filename=\"{$filename}\"";
        $headers['Content-Length']      = $filesize;
        $headers['Accept-Length']       = $filesize;
        $this->async(self::EVENT_DOWNLOAD, function () {
        });
        return $this->respondBody(File::open($path, 'r'), $headers);
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
     * @return bool
     */
    public function await(): bool
    {
        foreach ($this->asyncHandlers as $action => $handler) {
            try {
                $this->handleEvent($this->publishAwait($action, null));
                return true;
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        }
        return false;
    }

    /**
     * 处理异步事件
     * @param Build $event
     * @return void
     */
    public function handleEvent(Build $event): void
    {
        if ($handler = $this->asyncHandlers[$event->name]) {
            call_user_func_array($handler, [$event->data]);
        }
    }

    /**
     * 重写CollaborativeFiberStd的main注入器
     * 将自身作为最基础的依赖对象,供其他类构建时使用
     * @param Closure $callable
     * @return CollaborativeFiberStd
     */
    public function setupWithCallable(Closure $callable): CollaborativeFiberStd
    {
        $result = parent::setupWithCallable($callable);
        $this->injectDependencies(Response::class, $this->response);
        $this->requestSingle->hash = $this->hash;
        return $result;
    }

    /**
     * 作为一个CollaborativeFiberStd对象的产物, 必须实现错误处理方法
     * @param Throwable $exception
     * @return void
     */
    public function exceptionHandler(Throwable $exception): void
    {
        if (isset($this->exceptionHandler)) {
            call_user_func_array($this->exceptionHandler, [$exception]);
        }
        $this->destroy();
        try {
            Fiber::suspend(Build::new('suspend', $exception, $this->hash));
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }
}

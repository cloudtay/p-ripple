<?php
declare(strict_types=1);

namespace recycle\Http;

use Closure;
use Core\FileSystem\FileException;
use Core\Map\CollaborativeFiberMap;
use Core\Output;
use InvalidArgumentException;
use PRipple;
use recycle\Extends\Session\SessionManager;
use Throwable;
use Worker\Prop\Build;
use Worker\Socket\TCPConnection;
use Worker\Tunnel\SocketTunnelException;
use Worker\Worker;

/**
 * Http服务类
 */
class HttpWorker extends Worker
{
    /**
     * 上传文件路径
     * @var string
     */
    public static string $uploadPath;

    /**
     * Http流工厂
     * @var RequestFactory $requestFactory
     */
    private RequestFactory $requestFactory;

    /**
     * 请求处理器
     * @var Closure $requestHandler
     */
    private Closure $requestHandler;

    /**
     * 请求队列
     * @var Request[] $requests
     */
    private array $requests = [];

    /**
     * 请求异常处理器
     * @var Closure $exceptionHandler
     */
    private Closure $exceptionHandler;

    /**
     * 会话管理
     * @var SessionManager $sessionManager
     */
    private SessionManager $sessionManager;

    /**
     * 定义请求处理
     * @param Closure $requestHandler
     * @return void
     */
    public function defineRequestHandler(Closure $requestHandler): void
    {
        $this->requestHandler = $requestHandler;
    }

    /**
     * 定义异常处理器
     * @param Closure $exceptionHandler
     * @return void
     */
    public function defineExceptionHandler(Closure $exceptionHandler): void
    {
        $this->exceptionHandler = $exceptionHandler;
    }

    /**
     * 心跳
     * @return void
     */
    public function heartbeat(): void
    {
        while ($request = array_shift($this->requests)) {
            $request->setupWithCallable(function () use ($request) {
                try {
                    $requesting = call_user_func($this->requestHandler, $request);
                    foreach ($requesting as $response) {
                        if ($response instanceof Response) {
                            if ($request->keepAlive) {
                                $response->headers['Connection'] = 'Keep-Alive';
                            }
                            $request->client->send($response->__toString());
                            if ($response->isFile) {
                                $response->headers['Connection'] = 'Keep-Alive';
                                $this->queue[$request->hash]     = Build::new(
                                    Request::EVENT_DOWNLOAD,
                                    $response,
                                    $request->hash
                                );
                            } elseif (!$request->keepAlive) {
                                $this->removeClient($request->client);
                            }
                        }
                    }
                } catch (SocketTunnelException|FileException $exception) {
                    $this->recover($request->hash);
                } catch (Throwable $exception) {
                    $this->handleException($exception, $request);
                }
            });

            /**
             * 遵循CollaborativeFiberStd的设计，将Worker异常处理器注入到CollaborativeFiber中
             * @param Throwable $exception
             * @return void
             */
            $request->exceptionHandler = function (Throwable $exception) use ($request) {
                call_user_func_array($this->exceptionHandler, [$exception, $request]);
            };

            try {
                if ($request->executeFiber()) {
                    CollaborativeFiberMap::$collaborativeFiberMap[$request->hash] = $request;
                } else {
                    $this->recover($request->hash);
                }
            } catch (Throwable $exception) {
                $this->handleException($exception, $request);
                $this->recover($request->hash);
            }
        }

        foreach ($this->queue as $hash => $event) {
            $response = $event->data;
            /**
             * @var Response $response
             */
            switch ($event->name) {
                case Request::EVENT_DOWNLOAD:
                    do {
                        if (!$content = $response->file->readWithTrace($response->client->getSendBufferSize())) {
                            $this->recover($hash);
                            break;
                        }
                    } while ($response->client->send($content));
                    break;
                default:
                    break;
            }
        }
        $this->busy = false;
    }

    /**
     * @param string $hash
     * @return void
     */
    private function recover(string $hash): void
    {
        if ($this->requests[$hash]?->destroy()) {
            unset($this->requests[$hash]);
        }
        unset($this->queue[$hash]);
    }

    /**
     * @param Throwable $exception
     * @param Request   $request
     * @return void
     */
    public function handleException(Throwable $exception, Request $request): void
    {
        if (isset($this->exceptionHandler)) {
            try {
                call_user_func($this->exceptionHandler, $exception, $request);
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        } else {
            Output::printException($exception);
        }
        $this->recover($request->hash);
    }

    /**
     * @return void
     */
    public function destroy(): void
    {
    }

    /**
     * 创建请求工厂
     * @return void
     */
    public function initialize(): void
    {
        $this->subscribe(Request::EVENT_UPLOAD);
        $this->subscribe(Request::EVENT_DOWNLOAD);
        $this->requestFactory = new RequestFactory($this);
        parent::initialize();
        if (!$uploadPath = PRipple::getArgument('HTTP_UPLOAD_PATH')) {
            Output::printException(new InvalidArgumentException('HTTP_UPLOAD_PATH is not defined'));
            exit;
        }
        HttpWorker::$uploadPath = $uploadPath;
    }

    /**
     * 设置为非堵塞模式
     * @param TCPConnection $client
     * @return void
     */
    protected function onConnect(TCPConnection $client): void
    {
        $client->setNoBlock();
        $client->setReceiveBufferSize(8192);
    }

    /**
     * 原始报文到达,压入请求工厂
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    protected function onMessage(string $context, TCPConnection $client): void
    {
        try {
            if (($request = $this->requestFactory->revolve($context, $client)) instanceof Request) {
                $this->onRequest($request);
            }
        } catch (RequestSingleException $exception) {
            $client->send(
                (new Response())
                    ->setStatusCode(400)
                    ->setBody($exception->getMessage())
                    ->__toString()
            );
        }
    }

    /**
     * 一个新请求到达
     * @param Request $request
     * @return void
     */
    public function onRequest(Request $request): void
    {
        $this->requests[$request->hash] = $request;
        unset(CollaborativeFiberMap::$collaborativeFiberMap[$request->client->getName()]);
        $request->client->setName($request->hash);
        $this->busy = true;
    }

    /**
     * 回收请求
     * @param TCPConnection $client
     * @return void
     */
    protected function onClose(TCPConnection $client): void
    {
        $this->recover($client->getName());
    }

    /**
     * 处理事件
     * @param Build $event
     * @return void
     */
    protected function handleEvent(Build $event): void
    {
        $hash = $event->source;
        if ($collaborativeFiber = CollaborativeFiberMap::$collaborativeFiberMap[$hash] ?? null) {
            try {
                if ($collaborativeFiber->checkIfTerminated()) {
                    $this->recover($hash);
                } else {
                    $this->resume($hash, $event);
                }
            } catch (Throwable $exception) {
                $collaborativeFiber->exceptionHandler($exception);
                Output::printException($exception);
            }
        }
    }

    /**
     * 不接管任何父进程请求
     * @return void
     */
    public function forking(): void
    {
        $this->requests = [];
        parent::forking();
    }
}

<?php
declare(strict_types=1);

namespace App\Http;

use Closure;
use Core\Map\CollaborativeFiberMap;
use Core\Output;
use FileSystem\FileException;
use PRipple;
use Throwable;
use Worker\Build;
use Worker\NetWorker\Client;
use Worker\NetWorker\Tunnel\SocketTunnelException;
use Worker\NetworkWorkerBase;

/**
 * Http服务类
 */
class HttpWorker extends NetworkWorkerBase
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
                                $this->builds[$request->hash]    = Build::new(
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

        foreach ($this->builds as $hash => $event) {
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
        unset($this->builds[$hash]);
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
        // TODO: Implement destroy() method.
    }

    /**
     * 创建请求工厂
     * @return void
     */
    protected function initialize(): void
    {
        $this->subscribe(Request::EVENT_UPLOAD);
        $this->subscribe(Request::EVENT_DOWNLOAD);
        $this->requestFactory = new RequestFactory($this);
        parent::initialize();
        HttpWorker::$uploadPath = PRipple::getArgument('HTTP_UPLOAD_PATH');
    }

    /**
     * 设置为非堵塞模式
     * @param Client $client
     * @return void
     */
    protected function onConnect(Client $client): void
    {
        $client->setNoBlock();
        $client->setReceiveBufferSize(8192);
    }

    /**
     * 原始报文到达,压入请求工厂
     * @param string $context
     * @param Client $client
     * @return void
     */
    protected function onMessage(string $context, Client $client): void
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
        //删除原有的纤程并建立客户端纤程绑定关系
        $this->requests[$request->hash] = $request;
        unset(CollaborativeFiberMap::$collaborativeFiberMap[$request->client->getName()]);
        $request->client->setName($request->hash);
        $this->busy = true;
    }

    /**
     * 回收请求
     * @param Client $client
     * @return void
     */
    protected function onClose(Client $client): void
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
                call_user_func_array($collaborativeFiber->exceptionHandler, [$exception]);
                Output::printException($exception);
            }
        }
    }
}

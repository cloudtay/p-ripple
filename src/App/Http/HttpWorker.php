<?php
declare(strict_types=1);

namespace App\Http;

use App\Facade\Http;
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
     * @var string
     */
    public static string $uploadPath;

    /**
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
     * @param Closure $exceptionHandler
     * @return void
     */
    public function defineExceptionHandler(Closure $exceptionHandler): void
    {
        $this->exceptionHandler = $exceptionHandler;
    }

    /**
     * 创建请求工厂
     * @return void
     */
    protected function initialize(): void
    {
        $this->subscribe(Request::EVENT_UPLOAD);
        $this->requestFactory = new RequestFactory($this);
        parent::initialize();
        HttpWorker::$uploadPath = PRipple::getArgument('HTTP_UPLOAD_PATH');
        Http::setInstance($this);
    }

    /**
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
                                $request->client->send($response->__toString());
                            } else {
                                $request->client->send($response->__toString());
                                $this->removeClient($request->client);
                            }
                        }
                    }
                } catch (SocketTunnelException|FileException $exception) {
                    $this->recover($request->hash);
                } catch (Throwable $exception) {
                    $this->handleException($exception, $request);
                }
                $this->recover($request->hash);
            });
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
        $this->todo = false;
    }

    /**
     * @param string $hash
     * @return void
     */
    private function recover(string $hash): void
    {
        unset(CollaborativeFiberMap::$collaborativeFiberMap[$hash]);
        unset($this->requests[$hash]);
    }

    /**
     * @param Client $client
     * @return void
     */
    protected function onConnect(Client $client): void
    {
        $client->setNoBlock();
        $client->setReceiveBufferSize(1024 * 1024);
    }

    /**
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
            try {
                $client->send((new Response(400, [], $exception->getMessage()))->__toString());
            } catch (SocketTunnelException $exception) {
                return;
            }
        }
    }

    /**
     * @param Request $request
     * @return void
     */
    public function onRequest(Request $request): void
    {
        $this->requests[$request->hash] = $request;
        unset(CollaborativeFiberMap::$collaborativeFiberMap[$request->client->getName()]);
        $request->client->setName($request->hash);
        $this->todo = true;
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
     * @return void
     */
    public function destroy(): void
    {
        // TODO: Implement destroy() method.
    }

    /**
     * 处理事件
     * @param Build $event
     * @return void
     */
    protected function handleEvent(Build $event): void
    {
        $hash = $event->publisher;
        if (isset(CollaborativeFiberMap::$collaborativeFiberMap[$hash])) {
            try {
                if (!CollaborativeFiberMap::$collaborativeFiberMap[$hash]->resumeFiberExecution($event)) {
                    $this->recover($hash);
                }
            } catch (Throwable $exception) {
                $this->recover($hash);
                Output::printException($exception);
            }
        }
    }

    /**
     * @param Throwable $exception
     * @param Request $request
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
}

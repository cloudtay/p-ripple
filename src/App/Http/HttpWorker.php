<?php
declare(strict_types=1);

namespace App\Http;

use App\Facade\Http;
use Closure;
use Fiber;
use PRipple;
use Throwable;
use Worker\Build;
use Worker\NetWorker\Client;
use Worker\NetWorker\Tunnel\SocketAisleException;
use Worker\NetworkWorkerInterface;

/**
 * Http服务类
 */
class HttpWorker extends NetworkWorkerInterface
{
    /**
     * Http流工厂
     * @var RequestFactory $requestFactory
     */
    private RequestFactory $requestFactory;

    /**
     * 工作Fiber
     * @var Fiber[] $workFibers
     */
    private array $workFibers = [];

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
    public function initialize(): void
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
            $fiber = new Fiber(function () use ($request) {
                try {
                    $requesting = call_user_func($this->requestHandler, $request);
                    foreach ($requesting as $response) {
                        if ($response instanceof Response) {
                            try {
                                $request->client->send($response->__toString());
                            } catch (Throwable $exception) {
                                return;
                            }
                        }
                    }
                } catch (Throwable $exception) {
                    PRipple::printExpect($exception);
                    if (isset($this->exceptionHandler)) {
                        call_user_func($this->exceptionHandler, $exception, $request);
                    }
                }
            });

            try {
                if ($fiber->start()) {
                    $this->workFibers[$request->hash] = $fiber;
                } else {
                    $this->recover($request->hash);
                }
            } catch (Throwable $exception) {
                if (isset($this->exceptionHandler)) {
                    call_user_func($this->exceptionHandler, $request, $exception);
                }
                PRipple::printExpect($exception);
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
        unset($this->workFibers[$hash]);
        unset($this->requests[$hash]);
    }

    /**
     * @param Client $client
     * @return void
     */
    public function onConnect(Client $client): void
    {
        $client->setNoBlock();
    }

    /**
     * @param string $context
     * @param Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void
    {
        try {
            if (($request = $this->requestFactory->revolve($context, $client)) instanceof Request) {
                $this->onRequest($request);
            }
        } catch (RequestSingleException $exception) {
            try {
                $client->send((new Response(400, [], $exception->getMessage()))->__toString());
            } catch (SocketAisleException $exception) {
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
        unset($this->workFibers[$request->client->getName()]);
        $request->client->setName($request->hash);
        $this->todo = true;
    }

    /**
     * 回收请求
     * @param Client $client
     * @return void
     */
    public function onClose(Client $client): void
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
    public function handleEvent(Build $event): void
    {
        $hash = $event->publisher;
        if (isset($this->workFibers[$hash])) {
            try {
                if (!$this->resume($this->workFibers[$hash], $event)) {
                    $this->recover($hash);
                }
            } catch (Throwable $exception) {
                $this->recover($hash);
                PRipple::printExpect($exception);
            }
        }
    }
}

<?php
declare(strict_types=1);

namespace PRipple\App\Http;

use Closure;
use Exception;
use Fiber;
use PRipple\PRipple;
use PRipple\Worker\Build;
use PRipple\Worker\NetWorker;
use PRipple\Worker\NetWorker\Client;
use Throwable;

/**
 * Http服务类
 */
class Http extends NetWorker
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
     * 定义请求处理
     * @param Closure $requestHandler
     * @return void
     */
    public function defineRequestHandler(Closure $requestHandler): void
    {
        $this->requestHandler = $requestHandler;
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
        \PRipple\App\Facade\Http::setInstance($this);
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        while ($request = array_shift($this->requests)) {
            $fiber = new Fiber(function () use ($request) {
                $requesting = call_user_func($this->requestHandler, $request);
                foreach ($requesting as $response) {
                    try {
                        if ($response instanceof Response) {
                            $request->client->send($response->__toString());
                        }
                    } catch (Exception $exception) {
//                        PRipple::printExpect($exception);
                        return;
                    }
                }
            });

            try {
                if ($response = $fiber->start()) {
                    if (in_array($response->name, $this->subscribes)) {
                        $this->workFibers[$request->hash] = $fiber;
                    } else {
                        $this->publishAsync($response);
                    }
                } else {
                    $this->recover($request->hash);
                }
            } catch (Throwable $exception) {
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
            $client->send((new Response(400, [], $exception->getMessage()))->__toString());
        }
    }

    /**
     * @param Request $request
     * @return void
     */
    public function onRequest(Request $request): void
    {
        $this->requests[$request->hash] = $request;
        $this->todo = true;
//        $this->workFibers[$request->hash] = new Fiber(function () use ($request) {
//            /**
//             * @var Generator $requesting
//             */
//            $requesting = call_user_func($this->requestHandler, $request);
//            foreach ($requesting as $response) {
//                $request->client->send($response->__toString());
//            }
//        });
//        try {
//            if (!$response = $this->workFibers[$request->hash]->start()) {
//                $this->recover($request->hash);
//            } else {
//                $this->publishAsync($response);
//            }
//        } catch (Throwable $exception) {
//            PRipple::printExpect($exception);
//        }
    }

    /**
     * 回收请求
     * @param Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {
//        echo 'close:' . $client->getHash() . PHP_EOL;
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
                if (!$response = $this->workFibers[$hash]->resume($event)) {
                    $this->recover($hash);
                } elseif (!in_array($response->name, $this->subscribes)) {
                    $this->publishAsync($response);
                }
            } catch (Throwable $exception) {
                PRipple::printExpect($exception);
            }
        }
    }

    /**
     * @param Client $client
     * @return void
     */
    public function onHandshake(Client $client): void
    {
        // TODO: Implement onHandshake() method.
    }
}

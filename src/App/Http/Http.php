<?php
declare(strict_types=1);

namespace PRipple\App\Http;

use Closure;
use Fiber;
use Generator;
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
    protected function initialize(): void
    {
        $this->subscribe(Request::EVENT_UPLOAD);
        $this->requestFactory = new RequestFactory($this);
        parent::initialize();
    }

    /**
     * @return void
     */
    protected function heartbeat(): void
    {
//        while ($request = array_shift($this->requests)) {
//            $this->workFibers[$request->hash] = new Fiber(function () use ($request) {
//                call_user_func($this->requestHandler, $request);
//            });
//            try {
//                if (!$response = $this->workFibers[$request->hash]->start()) {
//                    $this->recover($request->hash);
//                } else {
//                    $this->publishAsync($response);
//                }
//            } catch (Throwable $exception) {
//                PRipple::self::printExpect($exception);
//            }
//        }
    }

    /**
     * @param Client $client
     * @return void
     */
    protected function onConnect(Client $client): void
    {
        $client->setNoBlock();
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
        $this->workFibers[$request->hash] = new Fiber(function () use ($request) {
            /**
             * @var Generator $requesting
             */
            $requesting = call_user_func($this->requestHandler, $request);
            foreach ($requesting as $response) {
                $request->client->send($response->__toString());
            }
        });
        try {
            if (!$response = $this->workFibers[$request->hash]->start()) {
                $this->recover($request->hash);
            } else {
                $this->publishAsync($response);
            }
        } catch (Throwable $exception) {
            PRipple::printExpect($exception);
        }
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
     * 回收请求
     * @param Client $client
     * @return void
     */
    protected function onClose(Client $client): void
    {
//        echo 'close:' . $client->getHash() . PHP_EOL;
    }

    /**
     * @return void
     */
    protected function destroy(): void
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
        if (isset($this->workFibers[$hash])) {
            try {
                if (!$response = $this->workFibers[$hash]->resume($event)) {
                    $this->recover($hash);
                } else {
                    $this->publishAsync($response);
                }
            } catch (Throwable $exception) {
                PRipple::printExpect($exception);
                $this->recover($hash);
            }
        }
    }

    /**
     * @param Client $client
     * @return void
     */
    protected function onHandshake(Client $client): void
    {
        // TODO: Implement onHandshake() method.
    }
}

<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\App\Http;

use Cclilshy\PRipple\Build;
use Cclilshy\PRipple\Service\Client;
use Cclilshy\PRipple\Worker\NetWorker;
use Fiber;
use Throwable;

/**
 *
 */
class Http extends NetWorker
{
    /**
     * @var RequestFactory $requestFactory
     */
    private RequestFactory $requestFactory;

    /**
     * @var Fiber[] $fibers
     */
    private array $fibers = [];

    /**
     * @var callable $requestHandler
     */
    private mixed $requestHandler;

    /**
     * @var Request[]
     */
    private array $requests = [];

    /**
     * 定义请求处理
     * @param callable $requestHandler
     * @return void
     */
    public function defineRequestHandler(callable $requestHandler): void
    {
        $this->requestHandler = $requestHandler;
    }

    /**
     * @param Request $request
     * @return void
     */
    public function onRequest(Request $request): void
    {
        $this->requests[$request->hash] = $request;
        $this->fibers[$request->hash] = new Fiber(function () use ($request) {
            call_user_func($this->requestHandler, $request);
        });
        try {
            if (!$response = $this->fibers[$request->hash]->start()) {
                $this->recover($request->hash);
            } else {
                $this->publishAsync($response);
            }
        } catch (Throwable $exception) {
            echo $exception->getMessage() . PHP_EOL;
        }
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
     * @param string $hash
     * @return void
     */
    private function recover(string $hash): void
    {
        unset($this->fibers[$hash]);
        unset($this->requests[$hash]);
    }

    /**
     * @return void
     */
    protected function heartbeat(): void
    {
//        while ($request = array_shift($this->requests)) {
//            $this->fibers[$request->hash] = new Fiber(function () use ($request) {
//                call_user_func($this->requestHandler, $request);
//            });
//            try {
//                if (!$response = $this->fibers[$request->hash]->start()) {
//                    $this->recover($request->hash);
//                } else {
//                    $this->publishAsync($response);
//                }
//            } catch (Throwable $exception) {
//                echo $exception->getMessage() . PHP_EOL;
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
        if (isset($this->fibers[$hash])) {
            try {
                if (!$response = $this->fibers[$hash]->resume($event)) {
                    $this->recover($hash);
                } else {
                    $this->publishAsync($response);
                }
            } catch (Throwable $exception) {
                echo $exception->getMessage() . PHP_EOL;
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

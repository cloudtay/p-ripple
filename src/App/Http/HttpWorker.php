<?php
declare(strict_types=1);

namespace PRipple\App\Http;

use Closure;
use Exception;
use Fiber;
use PRipple\App\Facade\Http;
use PRipple\PRipple;
use PRipple\Worker\Build;
use PRipple\Worker\NetWorker\Client;
use PRipple\Worker\NetworkWorkerInterface;
use Throwable;

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
                $requesting = call_user_func($this->requestHandler, $request);
                foreach ($requesting as $response) {
                    try {
                        if ($response instanceof Response) {
                            !$request->client->deprecated && $request->client->send($response->__toString());
                        }
                    } catch (Exception $exception) {
                        PRipple::printExpect($exception);
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
                if (!$this->resume($this->workFibers[$hash], $event)) {
                    $this->recover($hash);
                }
            } catch (Throwable $exception) {
                PRipple::printExpect($exception);
            }
        }
    }
}

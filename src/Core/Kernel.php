<?php
declare(strict_types=1);

namespace Core;

use Core\Map\EventMap;
use Core\Map\SocketMap;
use Core\Map\WorkerMap;
use Facade\JsonRpc;
use Generator;
use JetBrains\PhpStorm\NoReturn;
use Throwable;
use Worker\Built\BufferWorker;
use Worker\Built\JsonRpc\JsonRpcClient;
use Worker\Built\ProcessManager\ProcessContainer;
use Worker\Built\ProcessManager\ProcessManager;
use Worker\Built\Timer;
use Worker\Prop\Build;
use Worker\Worker;

/**
 * loop
 */
class Kernel
{
    /**
     * 处理速率
     * @var int
     */
    private int $rate = 1000000;

    /**
     * 是否为子进程
     * @var bool $isFork
     */
    private bool $isFork = false;

    /**
     * 当前进程主导者
     * @var Worker $masterWorker
     */
    private Worker $masterWorker;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->initialize();
    }

    /**
     * 初始化
     * @return void
     */
    private function initialize(): void
    {
        $this->registerSignalHandler();
        $this->loadExtends();
        $this->loadBuiltInServices();
    }

    /**
     * 加载插件
     * @return void
     */
    private function loadExtends(): void
    {
//        ExtendMap::set(Laravel::class, new Laravel());
    }

    /**
     * 启动内置服务
     * @return void
     */
    private function loadBuiltInServices(): void
    {
        $this->push(
            $timer = Timer::new(Timer::class),
            $processManager = ProcessManager::new(ProcessManager::class),
            $bufferWorker = BufferWorker::new(BufferWorker::class),
            $jsonRpcClientWorker = JsonRpcClient::new(JsonRpcClient::class)
        );
    }

    /**
     * 注册信号处理器
     * @return void
     */
    private function registerSignalHandler(): void
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [$this, 'signalHandler']);
        pcntl_signal(SIGTERM, [$this, 'signalHandler']);
        pcntl_signal(SIGQUIT, [$this, 'signalHandler']);
        pcntl_signal(SIGUSR1, [$this, 'signalHandler']);
        pcntl_signal(SIGUSR2, [$this, 'signalHandler']);
    }

    /**
     * 插入服务
     * @param Worker ...$workers
     * @return Kernel
     */
    public function push(Worker ...$workers): Kernel
    {
        foreach ($workers as $worker) {
            try {
                Output::info("[{$worker->name}]");
                if ($response = WorkerMap::addWorker($worker)->start()) {
                    EventMap::push($response);
                }
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        }
        return $this;
    }

    /**
     * 信号处理器
     * @param int $signal
     * @return void
     */
    #[NoReturn] public function signalHandler(int $signal): void
    {
        foreach (WorkerMap::$workerMap as $worker) {
            $worker->destroy();
        }
        Output::info('[PRipple]', 'Stopped successfully...');
        exit;
    }

    /**
     * 启动服务
     * @return void
     */
    public function launch(): void
    {
        foreach (EventMap::$eventMap as $event) {
            $this->handleEvent($event);
        }
    }

    #[NoReturn] public function listen(): void
    {
        Output::info('[PRipple]', 'Started successfully...');
        $this->launch();
        JsonRpc::reConnects();
        $this->loop();
    }

    /**
     * 循环监听
     * @return void
     */
    #[NoReturn] private function loop(): void
    {
        /**
         * @var Build $event
         */
        foreach ($this->generator() as $event) {
            $this->handleEvent($event);
        }
    }

    /**
     * 处理事件
     * @param Build $event
     * @return void
     */
    public function handleEvent(Build $event): void
    {
        switch ($event->name) {
            case Constants::EVENT_SUSPEND:
                break;
            case Constants::EVENT_SOCKET_EXPECT:
            case Constants::EVENT_SOCKET_READ:
            case Constants::EVENT_SOCKET_WRITE:
                $socketHash = spl_object_hash($event->data);
                if ($workerName = SocketMap::$workerMap[$socketHash] ?? null) {
                    WorkerMap::$workerMap[$workerName]->handleSocket($event->data);
                }
                break;
            case Constants::EVENT_SOCKET_SUBSCRIBE:
                $socketHash                        = spl_object_hash($event->data);
                SocketMap::$workerMap[$socketHash] = $event->source;
                SocketMap::$sockets[$socketHash]   = $event->data;
                break;
            case Constants::EVENT_SOCKET_UNSUBSCRIBE:
                $socketHash = spl_object_hash($event->data);
                unset(SocketMap::$workerMap[$socketHash]);
                unset(SocketMap::$sockets[$socketHash]);
                break;
            case Constants::EVENT_EVENT_SUBSCRIBE:
                SocketMap::$workerMap[$event->data] = $event->source;
                break;
            case Constants::EVENT_EVENT_UNSUBSCRIBE:
                unset(SocketMap::$workerMap[$event->data]);
                break;
            case Constants::EVENT_TEMP_FIBER:
                try {
                    if ($response = $event->data->executeFiber()) {
                        EventMap::push($response);
                    } else {
                        $event->data->destroy();
                    }
                } catch (Throwable $exception) {
                    $event->data->exceptionHandler($exception);
                    Output::printException($exception);
                }
                break;
            case Constants::EVENT_KERNEL_RATE_SET:
                $this->rate = $event->data;
                return;
            case Constants::EVENT_FIBER_THROW_EXCEPTION:
                $event->source->throwExceptionInFiber($event->data);
                break;
            default:
                $this->distribute($event);
        }
    }

    /**
     * 生成事件
     * @return Generator
     */
    #[NoReturn] private function generator(): Generator
    {
        while (true) {
            while ($event = array_shift(EventMap::$eventMap)) {
                yield $event;
                EventMap::$count--;
            }
            $readSockets = SocketMap::$sockets;
            if (count($readSockets) === 0) {
                usleep($this->rate);
            } else {
                $writeSockets  = [];
                $exceptSockets = SocketMap::$sockets;
                if (socket_select($readSockets, $writeSockets, $exceptSockets, 0, $this->rate)) {
                    foreach ($exceptSockets as $socket) {
                        yield Build::new(Constants::EVENT_SOCKET_EXPECT, $socket, Kernel::class);
                    }
                    foreach ($readSockets as $socket) {
                        yield Build::new(Constants::EVENT_SOCKET_READ, $socket, Kernel::class);
                    }
                    foreach ($writeSockets as $socket) {
                        yield Build::new(Constants::EVENT_SOCKET_WRITE, $socket, Kernel::class);
                    }
                    array_map(function (Worker $worker) {
                        if ($worker->busy) {
                            $worker->heartbeat();
                        }
                    }, WorkerMap::$workerMap);
                } else {
                    $this->heartbeat();
                }
                $this->adjustRate();
            }

        }
    }

    /**
     * @return void
     */
    private function heartbeat(): void
    {
        foreach (WorkerMap::$workerMap as $worker) {
            $worker->heartbeat();
        }
        gc_collect_cycles();
    }

    /**
     * 调整频率
     * @return void
     */
    private function adjustRate(): void
    {
        $this->rate = max(1000000 - (EventMap::$count + SocketMap::$count) * 1000, 0);
    }

    /**
     * 派发事件
     * @param Build $event
     * @return void
     */
    private function distribute(Build $event): void
    {
        if ($subscriber = SocketMap::$workerMap[$event->name] ?? null) {
            try {
                if ($event = WorkerMap::$fiberMap[$subscriber]->resume($event)) {
                    EventMap::push($event);
                }
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        }
    }

    /**
     * @return void
     */
    public function forkAfter(): void
    {
        foreach (WorkerMap::$workerMap as $worker) {
            $worker->forkAfter();
        }
    }

    /**
     * @return Worker[] $workers
     */
    public function forkBefore(): array
    {
        return array_filter(WorkerMap::$workerMap, function (Worker $worker) {
            return $worker->forkBefore();
        });
    }

    /**
     * @param Worker $masterWorker
     * @return int
     */
    public function fork(Worker $masterWorker): int
    {
        while ($event = array_shift(EventMap::$eventMap)) {
            $this->handleEvent($event);
        }
        $this->forkBefore();
        $processId = ProcessContainer::fork(function () use ($masterWorker) {
            $this->isFork       = true;
            $this->masterWorker = $masterWorker;
            foreach (WorkerMap::$workerMap as $workerName => $worker) {
                if ($worker->name !== $masterWorker->name) {
                    $worker->forkPassive();
                } else {
                    $worker->forking();
                }
            }
        }, false);
        if ($processId > 0) {
            $this->forkAfter();
        }
        return $processId;
    }

    /**
     * @return bool
     */
    public function isFork(): bool
    {
        return $this->isFork;
    }
}

<?php
declare(strict_types=1);

namespace Core;

use App\PDOProxy\PDOProxyPool;
use App\ProcessManager\ProcessManager;
use App\Timer\Timer;
use Core\Map\EventMap;
use Core\Map\SocketMap;
use Core\Map\WorkerMap;
use Generator;
use JetBrains\PhpStorm\NoReturn;
use Throwable;
use Worker\BufferWorker;
use Worker\Build;
use Worker\WorkerBase;

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
     * 构造函数
     */
    public function __construct()
    {
        $this->initialize();
    }

    /**
     * @return void
     */
    private function initialize(): void
    {
        $this->registerSignalHandler();
        $timer = Timer::new(Timer::class);
        $bufferWorker = BufferWorker::new(BufferWorker::class);
        $processManager = ProcessManager::new(ProcessManager::class);
        $pdoProxyPool = PDOProxyPool::new(PDOProxyPool::class);
        $this->push($timer, $bufferWorker, $pdoProxyPool, $processManager);
    }

    /**
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
     * @param WorkerBase ...$workers
     * @return Kernel
     */
    public function push(WorkerBase ...$workers): Kernel
    {
        foreach ($workers as $worker) {
            WorkerMap::addWorker($worker);
            try {
                Output::info("[{$worker->name}]");
                if ($response = WorkerMap::$fiberMap[$worker->name]->start()) {
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
    #[NoReturn] public function launch(): void
    {
        Output::info('[PRipple]', 'Started successfully...');
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
                    $socketHash = spl_object_hash($event->data);
                    SocketMap::$workerMap[$socketHash] = $event->source;
                    SocketMap::$sockets[$socketHash] = $event->data;
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
                case Constants::EVENT_HEARTBEAT:
                    if ($event->source === Kernel::class) {
                        // TODO: 全局心跳[空闲时]
                        foreach (WorkerMap::$fiberMap as $fiber) {
                            try {
                                if ($response = $fiber->resume($event)) {
                                    EventMap::push($response);
                                }
                            } catch (Throwable $exception) {
                                Output::printException($exception);
                            }
                        }
                    } else {
                        // TODO: 定向心跳[声明活跃]
                        try {
                            if ($response = WorkerMap::$fiberMap[$event->source]->resume($event)) {
                                EventMap::push($response);
                            }
                        } catch (Throwable $exception) {
                            Output::printException($exception);
                        }
                    }
                    break;
                case Constants::EVENT_TEMP_FIBER:
                    try {
                        if ($response = $event->data->start()) {
                            EventMap::push($response);
                        }
                    } catch (Throwable $exception) {
                        $event->data->throw($exception);
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
                $writeSockets = [];
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
                    array_map(function (WorkerBase $worker) {
                        if ($worker->todo) {
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
        $this->rate = max(1000000 - (EventMap::$count + SocketMap::$count) * 100, 0);
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
}

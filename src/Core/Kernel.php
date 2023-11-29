<?php
/*
 * Copyright (c) 2023 cclilshy
 * Contact Information:
 * Email: jingnigg@gmail.com
 * Website: https://cc.cloudtay.com/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * 版权所有 (c) 2023 cclilshy
 *
 * 特此免费授予任何获得本软件及相关文档文件（“软件”）副本的人，不受限制地处理
 * 本软件，包括但不限于使用、复制、修改、合并、出版、发行、再许可和/或销售
 * 软件副本的权利，并允许向其提供本软件的人做出上述行为，但须符合以下条件：
 *
 * 上述版权声明和本许可声明应包含在本软件的所有副本或主要部分中。
 *
 * 本软件按“原样”提供，不提供任何形式的保证，无论是明示或暗示的，
 * 包括但不限于适销性、特定目的的适用性和非侵权性的保证。在任何情况下，
 * 无论是合同诉讼、侵权行为还是其他方面，作者或版权持有人均不对
 * 由于软件或软件的使用或其他交易而引起的任何索赔、损害或其他责任承担责任。
 */

declare(strict_types=1);

namespace Core;

use Core\Map\EventMap;
use Core\Map\SocketMap;
use Core\Map\WorkerMap;
use Facade\JsonRpc;
use Fiber;
use Generator;
use JetBrains\PhpStorm\NoReturn;
use Throwable;
use Worker\Built\BufferWorker;
use Worker\Built\JsonRpc\JsonRpcClient;
use Worker\Built\JsonRpc\JsonRpcServer;
use Worker\Built\ProcessManager;
use Worker\Built\Timer;
use Worker\Prop\Build;
use Worker\Worker;

/**
 * loop
 */
class Kernel
{
    public const BUILT_SERVICES = [ProcessManager::class, JsonRpcClient::class, BufferWorker::class, Timer::class];

    /**
     * 处理速率
     * @var int
     */
    private int $rate = 1000000;

    /**
     * 是否为子进程
     * @var bool $isFork
     */
    public bool $isFork = false;

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
     * 启动服务
     * @return void
     */
    #[NoReturn] public function launch(): void
    {
        foreach (WorkerMap::$workerMap as $worker) {
            $this->launchWorker($worker);
            if ($this->isFork()) {
                break;
            }
        }
        $this->consumption();
        if (!$this->isFork()) {
            Output::info('', '-----------------------------------------');
            Output::info('Please Ctrl+C to stop. ', 'Starting successfully...');
        }
        $this->loop();
    }

    /**
     * @param Worker $worker
     * @return void
     */
    public function launchWorker(Worker $worker): void
    {
        WorkerMap::$fiberMap[$worker->name] = new Fiber(function () use ($worker) {
            $worker->launch();
        });
        try {
            if ($response = WorkerMap::$fiberMap[$worker->name]->start()) {
                EventMap::push($response);
            }
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
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
        exit(0);
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
     * Initialization: At this stage, you should ensure that services such as RPC/listeners are registered with the corresponding list
     * Ensure that different types of service startup modes are supported during Launch, and the connection can be established smoothly
     * @return void
     */
    private function initialize(): void
    {
        $this->registerSignalHandler();
        $this->loadBuiltInServices();
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
     * Insert a service, which directly performs the initialization of the service
     * @param Worker ...$workers
     * @return Kernel
     */
    public function push(Worker ...$workers): Kernel
    {
        foreach ($workers as $worker) {
            try {
                Output::info('Initialize: ', $worker->name);
                WorkerMap::addWorker($worker)->initialize();
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        }
        return $this;
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
        pcntl_signal(SIGUSR2, [$this, 'signalHandler']);
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
                if ($workerName = SocketMap::$worker[$socketHash] ?? null) {
                    WorkerMap::$workerMap[$workerName]->handleSocket($event->data);
                }
                break;
            case Constants::EVENT_SOCKET_SUBSCRIBE:
                $socketHash                      = spl_object_hash($event->data);
                SocketMap::$worker[$socketHash]  = $event->source;
                SocketMap::$sockets[$socketHash] = $event->data;
                break;
            case Constants::EVENT_SOCKET_UNSUBSCRIBE:
                $socketHash = spl_object_hash($event->data);
                unset(SocketMap::$worker[$socketHash]);
                unset(SocketMap::$sockets[$socketHash]);
                break;
            case Constants::EVENT_EVENT_SUBSCRIBE:
                SocketMap::$worker[$event->data] = $event->source;
                break;
            case Constants::EVENT_EVENT_UNSUBSCRIBE:
                unset(SocketMap::$worker[$event->data]);
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
            case Constants::EVENT_PUSH_SERVICE:
                $this->push($event->data);
                if ($this->isFork && $event->data instanceof JsonRpcServer) {
                    JsonRpc::call(
                        ProcessManager::class,
                        'rpcServiceIsOnline',
                        $event->data->worker->name);
                }
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
     * 派发事件
     * @param Build $event
     * @return void
     */
    private function distribute(Build $event): void
    {
        if ($subscriber = SocketMap::$worker[$event->name] ?? null) {
            try {
                if ($event = WorkerMap::$fiberMap[$subscriber]?->resume($event)) {
                    EventMap::push($event);
                }
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        }
    }

    /**
     * 全局心跳
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
     * 消费所有事件
     * @return void
     */
    public function consumption(): void
    {
        while ($event = array_shift(EventMap::$eventMap)) {
            $this->handleEvent($event);
            EventMap::$count--;
        }
    }

    /**
     * 是否为子进程
     * @return bool
     */
    public function isFork(): bool
    {
        return $this->isFork;
    }
}

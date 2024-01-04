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

use Closure;
use Core\Map\EventMap;
use Core\Map\SocketMap;
use Core\Map\WorkerMap;
use Exception;
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
    /**
     * 内置服务
     */
    public const BUILT_SERVICES = [
        Timer::class,
        JsonRpcClient::class,
        BufferWorker::class,
        ProcessManager::class,
    ];

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
     * 启动服务: 内置服务->主服务
     * @return void
     */
    #[NoReturn] public function launch(): void
    {
        /**
         * @var Worker[] $beforeWorker
         */
        $beforeWorker = [];
        foreach (WorkerMap::$workerMap as $worker) {
            if (in_array($worker->name, Kernel::BUILT_SERVICES, true)) {
                continue;
            }
            $beforeWorker[$worker->name] = $worker;
        }

        while ($worker = array_shift($beforeWorker)) {
            try {
                if ($worker->mode === Worker::MODE_COLLABORATIVE) {
                    $worker->initialize();
                    $worker->listen();
                    if ($worker->checkRpcService()) {
                        $worker->rpcService                      = JsonRpcServer::load($worker);
                        $beforeWorker[$worker->rpcService->name] = $worker->rpcService;
                        $this->push($worker->rpcService);
                    }
                } elseif ($worker->mode === Worker::MODE_INDEPENDENT) {
                    $processId = $this->fork(function () use ($worker, $beforeWorker) {
                        $worker->isFork = true;
                        $worker->initialize();
                        $worker->listen();
                        $this->consumption();
                        for ($i = 1; $i < $worker->thread(); $i++) {
                            $processId = $worker->fork();
                            if ($processId === 0) {
                                break;
                            }
                        }
                    }, false);
                    if ($processId === 0) {
                        $beforeWorker = [];
                        if ($worker->checkRpcService()) {
                            $worker->rpcService                      = JsonRpcServer::load($worker);
                            $beforeWorker[$worker->rpcService->name] = $worker->rpcService;
                            $this->push($worker->rpcService);
                        }
                    }
                } else {
                    continue;
                }

                $this->consumption();
                $fiber = WorkerMap::$fiberMap[$worker->name] = new Fiber([$worker, 'launch']);
                if ($response = $fiber->start()) {
                    EventMap::push($response);
                }
                if ($worker instanceof JsonRpcServer) {
                    JsonRpc::call([ProcessManager::class, 'registerRpcService'],
                        $worker->worker->name,
                        $worker->worker->getRpcServiceAddress(),
                        get_class($worker->worker)
                    );
                }
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        }

        if (!$this->isFork()) {
            Output::info('', '-----------------------------------------');
            Output::info('Please Ctrl+C to stop. ', 'Starting successfully...');
        }
        $this->loop();
    }

    /**
     * 信号处理器
     * @param int $signal
     * @return void
     */
    #[NoReturn] public function signalHandler(int $signal): void
    {
        ProcessManager::getInstance()->processSignalHandler();
        foreach (ProcessManager::getInstance()->childrenProcessIds as $processId) {
            pcntl_waitpid($processId, $status);
        }
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
     * 启动内置服务
     * @return void
     */
    private function loadBuiltInServices(): void
    {
        foreach (Kernel::BUILT_SERVICES as $serviceClass) {
            $worker = WorkerMap::add(new $serviceClass($serviceClass));
            $worker->initialize();
            $worker->listen();
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
            case Constants::EVENT_SOCKET_READ:
            case Constants::EVENT_SOCKET_EXPECT:
            case Constants::EVENT_SOCKET_WRITE:
                $socketHash = spl_object_hash($event->data);
                if ($workerName = SocketMap::$worker[$socketHash] ?? null) {
                    WorkerMap::$workerMap[$workerName]->handleSocket($event->data, $event->name);
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
                    if ($response = $event->data->execute()) {
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
                $this->rate += $event->data;
                return;
            case 'rpcServiceOnline':
                JsonRpc::addService($event->data['name'], $event->data['address'], $event->data['type']);
                foreach (WorkerMap::$workerMap as $worker) {
                    $worker->rpcServiceOnline($event->data);
                }
                break;
            default:
                $this->distribute($event);
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
            WorkerMap::get($subscriber)?->handleEvent($event);
        }
    }

    /**
     * 全局心跳
     * @return void
     */
    private function heartbeat(): void
    {
        foreach (WorkerMap::$workerMap as $worker) {
            $worker->callWorkerEvent(Worker::HOOK_HEARTBEAT);
        }
        gc_collect_cycles();
    }

    /**
     * 高频心跳
     * @return void
     */
    private function busyHeartbeat(): void
    {
        foreach (WorkerMap::$workerMap as $worker) {
            if ($worker->busy) {
                $worker->callWorkerEvent(Worker::HOOK_HEARTBEAT);
            }
        }
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
        while ($event = EventMap::arrayShift()) {
            $this->handleEvent($event);
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

    /**
     * 开启进程分生
     * @param Closure $closure
     * @param bool    $exit
     * @return int
     */
    public function fork(Closure $closure, bool $exit = true): int
    {
        $this->consumption();
        $processId = pcntl_fork();
        if ($processId === 0) {
            $this->isFork = true;
            try {
                foreach (Kernel::BUILT_SERVICES as $serviceName) {
                    WorkerMap::get($serviceName)->forkPassive();
                }

                /**
                 * forkPassive属于初始化行为, 它决定了该进程的最基础依赖
                 * 因为部分forkPassive中使用了异步操作,因此匿名包需要在forkPassive之后再执行
                 * 所以使用了PRipple\async($closure)来执行匿名包,确保了forkPassive的执行顺序
                 */
                call_user_func($closure);
                if ($exit) {
                    exit(0);
                }
            } catch (Exception $exception) {
                Output::printException($exception);
                exit(0);
            }
        } elseif ($processId > 0) {
            if ($this->isFork()) {
                JsonRpc::call([ProcessManager::class, 'setObserverProcessId'], $processId, posix_getpid());
            } else {
                ProcessManager::getInstance()->setObserverProcessId($processId, posix_getpid());
            }
            ProcessManager::getInstance()->childrenProcessIds[] = $processId;
        }
        return $processId;
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
                WorkerMap::add($worker);
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        }
        return $this;
    }

    /**
     * 生成事件
     * @return Generator
     */
    #[NoReturn] private function generator(): Generator
    {
        while (true) {
            while ($event = EventMap::arrayShift()) {
                yield $event;
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
                    $this->busyHeartbeat();
                } else {
                    $this->heartbeat();
                }
                pcntl_signal_dispatch();
                $this->adjustRate();
            }
        }
    }
}

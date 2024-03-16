<?php declare(strict_types=1);
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


namespace Cclilshy\PRipple\Core;

use Cclilshy\Container\Container;
use Cclilshy\PRipple\Component\LaravelComponent;
use Cclilshy\PRipple\Core\Event\Event;
use Cclilshy\PRipple\Core\Map\CoroutineMap;
use Cclilshy\PRipple\Core\Map\EventMap;
use Cclilshy\PRipple\Core\Map\ExtendMap;
use Cclilshy\PRipple\Core\Map\WorkerMap;
use Cclilshy\PRipple\Core\Net\Stream;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Utils\JsonRPC;
use Cclilshy\PRipple\Worker\Built\Buffer;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Client;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Exception\RPCException;
use Cclilshy\PRipple\Worker\Built\JsonRPC\Server;
use Cclilshy\PRipple\Worker\Built\ProcessManager;
use Cclilshy\PRipple\Worker\Built\ProcessService;
use Cclilshy\PRipple\Worker\Built\TCPClient\TCPClient;
use Cclilshy\PRipple\Worker\Built\Timer;
use Cclilshy\PRipple\Worker\Worker;
use Closure;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Revolt\EventLoop;
use Revolt\EventLoop\UnsupportedFeatureException;
use Throwable;
use function fopen;
use function pcntl_fork;
use const FS;
use const PP_RUNTIME_PATH;
use const SIGINT;
use const SIGQUIT;
use const SIGTERM;
use const SIGUSR2;

/**
 * @class Kernel 内核
 */
final class Kernel extends Container
{
    public const string EVENT_STREAM_READ            = 'system.net.stream.read';
    public const string EVENT_STREAM_WRITE           = 'system.net.stream.write';
    public const string EVENT_STREAM_SUBSCRIBE_WRITE = 'system.net.stream.subscribe.write';
    public const string EVENT_EVENT_SUBSCRIBE        = 'system.event.subscribe';
    public const string EVENT_EVENT_UNSUBSCRIBE      = 'system.event.unsubscribe';

    /**
     * 内置服务
     */
    public const array BUILT_SERVICES = [
        Timer::class,
        Client::class,
        Buffer::class,
        ProcessManager::class,
        ProcessService::class,
        TCPClient::class
    ];

    /**
     * 是否为子进程
     * @var bool $isFork
     */
    public bool $isFork = false;

    /**
     * 订阅事件表
     * @var array $subscribeEventMap
     */
    private array $subscribeEventMap = [];

    /**
     * 日志文件
     * @var Stream $logFile
     */
    private Stream $logFile;


    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->initialize();
    }

    /**
     * Initialization: At this stage, you should ensure that services such as RPC/listeners are registered with the corresponding list
     * Ensure that different types of service startup modes are supported during Launch, and the connection can be established smoothly
     * @return void
     */
    public function initialize(): void
    {
        // 重置opcache缓存
        $this->opcacheReset();

        // 初始化映射表
        $this->initializeMap();

        // 初始化事件循环监听器
        $this->initializeEventLoop();

        // 初始化内核日志
        $this->initializeLog();

        // 初始化自定义组件
        $this->initializeComponent();

        // 启动内置服务
        $this->loadBuiltInServices();

        // 注册信号处理器
        $this->registerSignalHandler();
    }

    /**
     * @return void
     */
    private function initializeEventLoop(): void
    {
        EventLoop::setDriver((new EventLoop\DriverFactory())->create());
        EventLoop::repeat(0.1, function () {
            $this->heartbeat();
            return null;
        });
    }

    /**
     * @return void
     */
    private function opcacheReset(): void
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    /**
     * @return void
     */
    private function initializeMap(): void
    {
        CoroutineMap::initialize();
        EventMap::initialize();
        ExtendMap::initialize();
        WorkerMap::initialize();
    }

    /**
     * 启动内置服务
     * @return void
     */
    private function loadBuiltInServices(): void
    {
        foreach (Kernel::BUILT_SERVICES as $serviceClass) {
            $this->push(new $serviceClass($serviceClass));
        }
    }

    /**
     * @return void
     */
    private function initializeComponent(): void
    {
        LaravelComponent::initialize();
    }


    /**
     * @return void
     */
    private function initializeLog(): void
    {
        $logFilePath   = PRipple::getArgument('PP_LOG_PATH', PP_RUNTIME_PATH) . FS . 'p-ripple.log';
        $this->logFile = new Stream(fopen($logFilePath, 'a+'));
    }

    /**
     * 注册信号处理器
     * @return void
     */
    public function registerSignalHandler(): void
    {
        try {
            EventLoop::onSignal(SIGINT, fn() => $this->signalHandler(SIGINT));
            EventLoop::onSignal(SIGTERM, fn() => $this->signalHandler(SIGTERM));
            EventLoop::onSignal(SIGQUIT, fn() => $this->signalHandler(SIGQUIT));
            EventLoop::onSignal(SIGUSR2, fn() => $this->signalHandler(SIGUSR2));
        } catch (UnsupportedFeatureException $exception) {
            Output::error($exception);
            exit(0);
        }
    }

    /**
     * 循环监听
     * @return void
     */
    public function run(): void
    {
        EventLoop::run();
    }

    /**
     * 循环监听
     * @return void
     */
    public function heartbeat(): void
    {
        while ($event = EventMap::arrayShift()) {
            $this->handleEvent($event);
        }

        foreach (WorkerMap::$workerMap as $worker) {
            $worker->heartbeat();
        }

        CoroutineMap::gc();
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
                WorkerMap::add($worker)->launch();
            } catch (Throwable $exception) {
                Output::error($exception);
            }
        }
        return $this;
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
     * 处理事件
     * @param Event $event
     * @return void
     */
    public function handleEvent(Event $event): void
    {
        try {
            switch ($event->name) {
                case Kernel::EVENT_EVENT_SUBSCRIBE:
                    $this->subscribeEventMap[$event->data][] = $event->source;
                    break;
                case Kernel::EVENT_EVENT_UNSUBSCRIBE:
                    if (isset($this->subscribeEventMap[$event->data])) {
                        if (($index = array_search($event->source, $this->subscribeEventMap[$event->data])) !== false) {
                            unset($this->subscribeEventMap[$event->data][$index]);
                        }
                    }
                    break;
                case Server::EVENT_ONLINE:
                    JsonRPC::addService($event->data['name'], $event->data['address'], $event->data['type']);
                    foreach (WorkerMap::$workerMap as $worker) {
                        $worker->rPCServiceOnline($event->data);
                    }
                    break;
                default:
                    if ($subscriber = $this->subscribeEventMap[$event->name] ?? null) {
                        foreach ($subscriber as $workerName) {
                            WorkerMap::get($workerName)?->handleEvent($event);
                        }
                    }
            }
        } catch (Throwable $exception) {
            Output::error($exception);
        }
    }

    /**
     * 日志
     * @param string $message
     * @return void
     */
    public function log(string $message): void
    {
        $this->logFile->write("$message\n");
        Output::info('[LOG] ', $message);
    }

    /**
     * 开启进程分生
     * @param Closure $closure
     * @param bool    $exit
     * @return int
     */
    public function fork(Closure $closure, bool $exit = true): int
    {
        $processId = pcntl_fork();
        if ($processId === 0) {
            $this->forking();
            try {
                call_user_func($closure);
                $exit && exit(0);
            } catch (Exception $exception) {
                Output::error($exception);
                exit(0);
            }
        } elseif ($processId > 0) {
            if ($this->isFork()) {
                try {
                    JsonRPC::call([ProcessManager::class, 'setObserverProcessId'], $processId, posix_getpid());
                } catch (RPCException $exception) {
                    Output::error($exception);
                }
            } else {
                ProcessManager::getInstance()->setObserverProcessId($processId, posix_getpid());
            }
            ProcessManager::getInstance()->childrenProcessIds[] = $processId;
        }
        return $processId;
    }

    /**
     * @return void
     */
    public function destruct(): void
    {
        ProcessManager::getInstance()->processSignalHandler();
        Output::info('[PRipple]', 'Stopped successfully...');
        $this->logFile->close();
    }

    private function forking(): void
    {
        $this->isFork = true;
        $this->initializeEventLoop();
        $this->initializeLog();
        CoroutineMap::forkPassive();
        foreach (Kernel::BUILT_SERVICES as $serviceName) {
            WorkerMap::get($serviceName)->forkPassive();
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
     * 信号处理器
     * @param int $signal
     * @return void
     */
    #[NoReturn] public function signalHandler(int $signal): void
    {
        $this->destruct();
        exit(0);
    }
}

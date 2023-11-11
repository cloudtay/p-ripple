<?php
declare(strict_types=1);

use App\PDOProxy\PDOProxy;
use App\PDOProxy\PDOProxyPool;
use App\ProcessManager\ProcessManager;
use App\Timer\Timer;
use JetBrains\PhpStorm\NoReturn;
use Worker\BufferWorker;
use Worker\Build;
use Worker\WorkerInterface;

/**
 * loop
 */
class PRipple
{
    public const VERSION = '0.1';
    public const EVENT_SUSPEND = 'suspend';
    public const EVENT_SOCKET_EXPECT = 'socket.expect';
    public const EVENT_SOCKET_READ = 'socket.read';
    public const EVENT_SOCKET_WRITE = 'socket.write';
    public const EVENT_SOCKET_SUBSCRIBE = 'socket.subscribe';
    public const EVENT_SOCKET_UNSUBSCRIBE = 'socket.unsubscribe';
    public const EVENT_EVENT_SUBSCRIBE = 'event.subscribe';
    public const EVENT_EVENT_UNSUBSCRIBE = 'event.unsubscribe';
    public const EVENT_HEARTBEAT = 'heartbeat';
    public const EVENT_TEMP_FIBER = 'temp.fiber';
    public const EVENT_KERNEL_RATE_SET = 'kernel.rate.set';

    /**
     * 是否启动
     * @var bool $construct
     */
    private static bool $construct = false;

    /**
     * 双口单向待载入的事件队列
     * @var Build[] $loadOnStartup
     */
    private static array $loadOnStartup = [];

    /**
     * 单例
     * @var PRipple $instance
     */
    private static PRipple $instance;

    /**
     * 处理速率
     * @var int
     */
    private int $rate = 1000000;

    /**
     * 纤程列表
     * @var array
     */
    private array $fibers = [];
    /**
     * 套接字列表
     * @var array
     */
    private array $sockets = [];
    /**
     * 事件列表
     * @var array
     */
    private array $events = [];
    /**
     * 套接字哈希表
     * @var array
     */
    private array $socketSubscribeHashMap = [];
    /**
     * 服务列表
     * @var WorkerInterface[]
     */
    private array $workers = [];

    /**
     * 套接字数量
     * @var int $socketNumber
     */
    private int $socketNumber = 0;

    /**
     * 事件数量
     * @var int $eventNumber
     */
    private int $eventNumber = 0;

    /**
     * 安装参数
     * @var array $configureArguments
     */
    private static array $configureArguments;

    /**
     * 唯一索引
     * @var int $index
     */
    private static int $index = 0;

    /**
     * 构造函数
     */
    public function __construct(array $argument)
    {
        PRipple::$configureArguments = $argument;
        PRipple::$instance = $this;
        $this->initialize();
        PRipple::$construct = true;
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
        $pdoProxyPool = PDOProxyPool::new(PDOProxy::class);
        $this->push($timer, $bufferWorker, $pdoProxyPool, $processManager);
    }

    /**
     * 插入服务
     * @param WorkerInterface ...$workers
     * @return PRipple
     */
    public function push(WorkerInterface ...$workers): PRipple
    {
        foreach ($workers as $worker) {
            $this->fibers[$worker->name] = new Fiber(function () use ($worker) {
                $worker->launch();
            });
            $this->workers[$worker->name] = $worker;
            try {
                PRipple::info("[{$worker->name}]");
                if ($response = $this->fibers[$worker->name]->start()) {
                    PRipple::publishAsync($response);
                }
            } catch (Throwable $exception) {
                PRipple::printExpect($exception);
            }
        }
        return $this;
    }

    /**
     * @return void
     */
    private function registerSignalHandler(): void
    {
        //只捕获常见终止信号,意料之外的错误开发者自行负责
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [$this, 'signalHandler']);
        pcntl_signal(SIGTERM, [$this, 'signalHandler']);
        pcntl_signal(SIGQUIT, [$this, 'signalHandler']);
        pcntl_signal(SIGUSR1, [$this, 'signalHandler']);
        pcntl_signal(SIGUSR2, [$this, 'signalHandler']);
    }

    /**
     * @param int $signal
     * @return void
     */
    #[NoReturn] public function signalHandler(int $signal): void
    {
        foreach ($this->workers as $worker) {
            $worker->destroy();
        }
        PRipple::info('[PRipple]', 'Stopped successfully...');
        exit;
    }

    /**
     * 启动服务
     * @return void
     */
    #[NoReturn] public function launch(): void
    {
        while ($event = array_pop(PRipple::$loadOnStartup)) {
            array_unshift($this->events, $event);
            $this->events[] = $event;
            $this->eventNumber++;
            PRipple::info('[preloading]', $event->name);
        }
        PRipple::info('[PRipple]', 'Started successfully...');
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
                case PRipple::EVENT_SUSPEND:
                    break;
                case PRipple::EVENT_SOCKET_EXPECT:
                case PRipple::EVENT_SOCKET_READ:
                case PRipple::EVENT_SOCKET_WRITE:
                    $socketHash = spl_object_hash($event->data);
                    if ($workerName = $this->socketSubscribeHashMap[$socketHash] ?? null) {
                        $this->workers[$workerName]->handleSocket($event->data);
                    }
                    break;
                case PRipple::EVENT_SOCKET_SUBSCRIBE:
                    $socketHash = spl_object_hash($event->data);
                    $this->socketSubscribeHashMap[$socketHash] = $event->publisher;
                    $this->sockets[$socketHash] = $event->data;
                    break;
                case PRipple::EVENT_SOCKET_UNSUBSCRIBE:
                    $socketHash = spl_object_hash($event->data);
                    unset($this->socketSubscribeHashMap[$socketHash]);
                    unset($this->sockets[$socketHash]);
                    break;
                case PRipple::EVENT_EVENT_SUBSCRIBE:
                    $this->socketSubscribeHashMap[$event->data] = $event->publisher;
                    break;
                case PRipple::EVENT_EVENT_UNSUBSCRIBE:
                    unset($this->socketSubscribeHashMap[$event->data]);
                    break;
                case PRipple::EVENT_HEARTBEAT:
                    if ($event->publisher === PRipple::class) {
                        // TODO: 全局心跳[空闲时]
                        foreach ($this->fibers as $fiber) {
                            if ($response = $fiber->resume($event)) {
                                PRipple::publishAsync($response);
                            }
                        }
                    } else {
                        // TODO: 定向心跳[声明活跃]
                        if ($response = $this->fibers[$event->publisher]->resume($event)) {
                            PRipple::publishAsync($response);
                        }
                    }
                    break;
                case PRipple::EVENT_TEMP_FIBER:
                    try {
                        if ($response = $event->data->start()) {
                            PRipple::publishAsync($response);
                        }
                    } catch (Throwable $exception) {
                        $event->data->throw($exception);
                        PRipple::printExpect($exception);
                    }
                    break;
                case PRipple::EVENT_KERNEL_RATE_SET:
                    $this->rate = $event->data;
                    return;
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
            while ($event = array_shift($this->events)) {
                yield $event;
                $this->eventNumber--;
            }
            $readSockets = $this->sockets;
            if (count($readSockets) === 0) {
                usleep($this->rate);
            } else {
                $writeSockets = [];
                $exceptSockets = $this->sockets;
                if (socket_select($readSockets, $writeSockets, $exceptSockets, 0, $this->rate)) {
                    foreach ($exceptSockets as $socket) {
                        yield Build::new(PRipple::EVENT_SOCKET_EXPECT, $socket, PRipple::class);
                    }
                    foreach ($readSockets as $socket) {
                        yield Build::new(PRipple::EVENT_SOCKET_READ, $socket, PRipple::class);
                    }
                    foreach ($writeSockets as $socket) {
                        yield Build::new(PRipple::EVENT_SOCKET_WRITE, $socket, PRipple::class);
                    }
                    array_map(function (WorkerInterface $worker) {
                        if ($worker->todo) {
                            $worker->heartbeat();
                        }
                    }, $this->workers);
                } else {
                    $this->heartbeat();
                }
                $this->adjustRate();
            }

        }
    }

    /**
     * 调整频率
     * @return void
     */
    private function adjustRate(): void
    {
        $this->rate = max(1000000 - ($this->eventNumber + $this->socketNumber) * 100, 0);
    }

    /**
     * 异步发布事件
     * @param Build $event
     * @return void
     */
    public static function publishAsync(Build $event): void
    {
        if (PRipple::$construct) {
            PRipple::instance()->events[] = $event;
            PRipple::instance()->eventNumber++;
        } else {
            PRipple::$loadOnStartup[] = $event;
        }
    }

    /**
     * 获取单例
     * @return PRipple
     */
    public static function instance(): PRipple
    {
        if (!isset(PRipple::$instance)) {
            try {
                throw new Exception('PRipple not initialized');
            } catch (Exception $e) {
                PRipple::printExpect($e);
                exit;
            }
        }
        return PRipple::$instance;
    }

    /**
     * @param array $arguments
     * @return PRipple
     */
    public static function configure(array $arguments): PRipple
    {
        $instance = new PRipple($arguments);
        error_reporting(E_ALL & ~E_WARNING);
        ini_set('max_execution_time', 0);
        define('UL', '_');
        define('FS', DIRECTORY_SEPARATOR);
        define('BS', '\\');
        define('PP_START_TIMESTAMP', time());
        define('PP_ROOT_PATH', __DIR__);
        define('PP_RUNTIME_PATH', PRipple::getArgument('RUNTIME_PATH'));
        define('PP_MAX_FILE_HANDLE', intval(shell_exec("ulimit -n")));
        return $instance;
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public static function printExpect(Throwable $exception): void
    {
        echo "\033[1;31mException: " . get_class($exception) . "\033[0m\n";
        echo "\033[1;33mMessage: " . $exception->getMessage() . "\033[0m\n";
        echo "\033[1;34mFile: " . $exception->getFile() . "\033[0m\n";
        echo "\033[1;34mLine: " . $exception->getLine() . "\033[0m\n";
        echo "\033[0;32mStack trace:\033[0m\n";
        $trace = $exception->getTraceAsString();
        $traceLines = explode("\n", $trace);
        foreach ($traceLines as $line) {
            echo "\033[0;32m" . $line . "\033[0m\n";
        }
//        if ($previous = $exception->getPrevious()) {
//            echo "\033[0;36mPrevious exception:\033[0m\n";
//        }
        echo PHP_EOL;
    }

    /**
     * 派发事件
     * @param Build $event
     * @return void
     */
    private function distribute(Build $event): void
    {
        if ($subscriber = $this->socketSubscribeHashMap[$event->name] ?? null) {
            if ($event = $this->fibers[$subscriber]->resume($event)) {
                PRipple::publishAsync($event);
            }
        }
    }

    /**
     * @param string $title
     * @param string ...$contents
     * @return void
     */
    public static function info(string $title, string ...$contents): void
    {
        echo "\033[1;32m" . $title . "\033[0m";
        foreach ($contents as $content) {
            echo "\033[1;33m" . $content . "\033[0m";
        }
        echo PHP_EOL;
    }

    /**
     * 获取一个服务
     * @param string $name
     * @return WorkerInterface|null
     */
    public static function worker(string $name): WorkerInterface|null
    {
        return PRipple::instance()->workers[$name] ?? null;
    }

    /**
     * @param Build $event
     * @return mixed
     */
    public static function publishAwait(Build $event): mixed
    {
        try {
            return Fiber::suspend($event);
        } catch (Throwable $exception) {
            PRipple::printExpect($exception);
            return false;
        }
    }

    /**
     * 唯一HASH
     * @return string
     */
    public static function uniqueHash(): string
    {
        return md5(strval(PRipple::$index++));
    }

    /**
     * 获取安装参数
     * @param string $name
     * @return array
     */
    public static function getArgument(string $name): mixed
    {
        if (!$value = PRipple::$configureArguments[$name] ?? null) {
            try {
                throw new Exception("Argument {$name} not found");
            } catch (Exception $e) {
                PRipple::printExpect($e);
                exit;
            }
        }
        return $value;
    }

    /**
     * @return void
     */
    private function heartbeat(): void
    {
        foreach ($this->workers as $worker) {
            $worker->heartbeat();
        }
        gc_collect_cycles();
    }
}

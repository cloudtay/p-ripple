<?php

namespace Cclilshy\PRipple;

use Cclilshy\PRipple\Help\StrFunctions;
use Cclilshy\PRipple\Worker\BufferWorker;
use Cclilshy\PRipple\Worker\Worker;
use Fiber;
use Generator;
use JetBrains\PhpStorm\NoReturn;
use Throwable;

class PRipple
{
    use StrFunctions;

    /**
     * 单例
     * @var PRipple $instance
     */
    private static PRipple $instance;
    /**
     * 协程列表
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
     * @var Worker[]
     */
    private array $services = [];
    private int $index = 0;
    private int $rate = 1000000;
    private int $socketNumber = 0;
    private int $eventNumber = 0;

    public function __construct()
    {
        $this->initialize();
    }

    private function initialize(): void
    {
        error_reporting(E_ALL & ~E_WARNING);
        ini_set('max_execution_time', 0);
        define('UL', '_');
        define('FS', DIRECTORY_SEPARATOR);
        define('BS', '\\');
        define('PP_START_TIMESTAMP', time());
        define('PP_ROOT_PATH', __DIR__);
        define('PP_RUNTIME_PATH', '/tmp');
        define('PP_MAX_FILE_HANDLE', intval(shell_exec("ulimit -n")));
        define('PP_MAX_MEMORY', $this->strToBytes(ini_get('memory_limit')));
    }

    /**
     * @param Build $event
     * @return void
     */
    public static function publishAsync(Build $event): void
    {
        PRipple::instance()->events[] = $event;
        PRipple::instance()->eventNumber++;
    }

    /**
     * 获取单例
     * @return PRipple
     */
    public static function instance(): PRipple
    {
        if (!isset(PRipple::$instance)) {
            PRipple::$instance = new PRipple();
        }
        return PRipple::$instance;
    }

    /**
     * 获取一个服务
     * @param string $name
     * @return Worker|null
     */
    public static function service(string $name): Worker|null
    {
        return PRipple::instance()->services[$name] ?? null;
    }

    /**
     * @param Build $event
     * @return mixed
     */
    public static function publishSync(Build $event): mixed
    {
        try {
            return Fiber::suspend($event);
        } catch (Throwable $exception) {
            echo $exception->getMessage() . PHP_EOL;
            return false;
        }
    }

    /**
     * @return Build|false
     */
    public static function suspend(): mixed
    {
        try {
            return Fiber::suspend(Build::new('suspend', null, PRipple::class));
        } catch (Throwable $exception) {
            echo $exception->getMessage() . PHP_EOL;
            return false;
        }
    }

    /**
     * 插入服务
     * @param Worker ...$workers
     * @return PRipple
     */
    public function push(Worker ...$workers): PRipple
    {
        foreach ($workers as $worker) {
            $this->fibers[$worker->name] = new Fiber(function () use ($worker) {
                $worker->launch();
            });
            $this->services[$worker->name] = $worker;
            try {
                if ($response = $this->fibers[$worker->name]->start()) {
                    PRipple::publishAsync($response);
                }
            } catch (Throwable $exception) {
                echo $exception->getMessage() . PHP_EOL;
            }
        }
        return $this;
    }

    /**
     * 启动服务
     * @return void
     */
    #[NoReturn] public function launch(): void
    {
        PRipple::instance()->push(BufferWorker::new('bufferHandler'));
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
                case 'socket.expect':
                case 'socket.read':
                case 'socket.write':
                    $socketHash = spl_object_hash($event->data);
                $event = Build::new($event->name, $event->data, PRipple::class);
                    if ($clientName = $this->socketSubscribeHashMap[$socketHash] ?? null) {
                        if ($response = $this->fibers[$clientName]?->resume($event)) {
                            PRipple::publishAsync($response);
                            break;
                        }
                    }
                    break;
                case 'socket.subscribe':
                    $socketHash = spl_object_hash($event->data);
                    $this->socketSubscribeHashMap[$socketHash] = $event->publisher;
                    $this->sockets[$socketHash] = $event->data;
                    break;
                case 'socket.unsubscribe':
                    $socketHash = spl_object_hash($event->data);
                    unset($this->socketSubscribeHashMap[$socketHash]);
                    unset($this->sockets[$socketHash]);
                    break;
                case 'event.subscribe':
                    $this->socketSubscribeHashMap[$event->data] = $event->publisher;
                    break;
                case 'event.unsubscribe':
                    unset($this->socketSubscribeHashMap[$event->data]);
                    break;
                case 'heartbeat':
                    $this->adjustRate();
                    if ($event->publisher === PRipple::class) {
                        foreach ($this->fibers as $fiber) {
                            if ($response = $fiber->resume($event)) {
                                PRipple::publishAsync($response);
                            }
                        }
                    }
                    break;
                case 'kernel.rate.set':
                    $this->rate = $event->data;
                    break;
                default:
                    $this->distribute($event);
            }
        }
    }

    /**
     * 生成任务
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
                        yield Build::new('socket.expect', $socket, PRipple::class);
                    }
                    foreach ($readSockets as $socket) {
                        yield Build::new('socket.read', $socket, PRipple::class);
                    }
                    foreach ($writeSockets as $socket) {
                        yield Build::new('socket.write', $socket, PRipple::class);
                    }
                } else {
                    yield Build::new('heartbeat', null, PRipple::class);
                }
            }
        }
    }

    private function adjustRate(): void
    {
        $this->rate = max(1000000 - ($this->eventNumber + $this->socketNumber) * 100, 0);
    }

    /**
     * 派发事件
     * @param Build $event
     * @return void
     */
    private function distribute(Build $event): void
    {
        if ($subscriber = $this->socketSubscribeHashMap[$event->name] ?? null) {
            $this->fibers[$subscriber]->resume($event);
        }
    }

    /**
     * 唯一HASH
     * @return string
     */
    public function uniqueHash(): string
    {
        return md5(strval($this->index++));
    }
}

<?php

namespace Cclilshy\PRipple;

use Cclilshy\PRipple\Help\StrFunctions;
use Cclilshy\PRipple\Worker\BufferWorker;
use Fiber;
use Generator;
use JetBrains\PhpStorm\NoReturn;
use Throwable;

class PRipple
{
    use StrFunctions;

    /**
     * 单例子
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
    public static function publish(Build $event): void
    {
        PRipple::instance()->events[] = $event;
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
                    $this->events[] = $response;
                }
            } catch (Throwable $e) {
                echo $e->getMessage() . PHP_EOL;
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
            echo "event:{$event->name}" . PHP_EOL;
            switch ($event->name) {
                case 'socket.expect':
                case 'socket.read':
                case 'socket.write':
                    $socketHash = spl_object_hash($event->data);
                    $event = new Build($event->name, $event->data, PRipple::class);
                    if ($clientName = $this->socketSubscribeHashMap[$socketHash] ?? null) {
                        if ($response = $this->fibers[$clientName]?->resume($event)) {
                            $this->events[] = $response;
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
                    foreach ($this->fibers as $fiber) {
                        if ($response = $fiber->resume($event)) {
                            $this->events[] = $response;
                        }
                    }
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
            }
            $readSockets = $this->sockets;
            if (count($readSockets) === 0) {
                sleep(1);
                yield new Build('heartbeat', null, PRipple::class);
            } else {
                $writeSockets = [];
                $exceptSockets = $this->sockets;
                if (socket_select($readSockets, $writeSockets, $exceptSockets, 0, 1000000)) {
                    foreach ($exceptSockets as $socket) {
                        yield new Build('socket.expect', $socket, PRipple::class);
                    }
                    foreach ($readSockets as $socket) {
                        yield new Build('socket.read', $socket, PRipple::class);
                    }
                    foreach ($writeSockets as $socket) {
                        yield new Build('socket.write', $socket, PRipple::class);
                    }
                } else {
                    yield new Build('heartbeat', null, PRipple::class);
                }
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
        if ($subscriber = $this->socketSubscribeHashMap[$event->name] ?? null) {
            $this->fibers[$subscriber]->resume($event);
        }
    }
}

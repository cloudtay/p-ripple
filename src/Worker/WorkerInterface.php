<?php
declare(strict_types=1);

namespace Worker;

use Fiber;
use JetBrains\PhpStorm\NoReturn;
use PRipple;
use Socket;
use Throwable;

/**
 * WorkerInterface
 */
abstract class WorkerInterface
{
    /**
     * 客户端名称
     * @var string
     */
    public string $name;

    /**
     * 事件列表
     * @var Build[] $builds
     */
    protected array $builds = [];

    /**
     * 订阅事件列表
     * @var array $subscribes
     */
    protected array $subscribes = [];

    /**
     * 活跃的工作者
     * @var bool $todo
     */
    public bool $todo = false;

    /**
     * 构造函数
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * 启动服务
     * @return void
     */
    #[NoReturn] public function launch(): void
    {
        $this->initialize();
        while (true) {
            while ($build = array_shift($this->builds)) {
                $this->consumption($build);
            }
            $this->publishAwait();
        }
    }

    /**
     * 初始化
     * @return void
     */
    abstract protected function initialize(): void;

    /**
     * 处理返回
     * @param Build $build
     * @return void
     * @throws Throwable
     */
    private function consumption(Build $build): void
    {
        switch ($build->name) {
            case PRipple::EVENT_SOCKET_READ:
                $this->handleSocket($build->data);
                break;
            case PRipple::EVENT_SOCKET_EXPECT:
                $this->expectSocket($build->data);
                break;
            case PRipple::EVENT_SOCKET_WRITE:
//                $this->writeSocket($build->data);
                break;
            case PRipple::EVENT_HEARTBEAT:
                $this->heartbeat();
                break;
            default:
                $this->handleEvent($build);
        }
    }

    /**
     * 处理套接字
     * @param Socket $socket
     * @return void
     */
    abstract public function handleSocket(Socket $socket): void;

    /**
     * 处理异常套接字
     * @param Socket $socket
     * @return void
     */
    abstract public function expectSocket(Socket $socket): void;

    /**
     * 心跳
     * @return void
     */
    abstract public function heartbeat(): void;

    /**
     * 处理事件
     * @param Build $event
     * @return void
     */
    abstract protected function handleEvent(Build $event): void;

    /**
     * 等待驱动
     * @param Build|null $event
     * @return void
     */
    protected function publishAwait(Build|null $event = null): void
    {
        try {
            if (!$event) {
                $event = Build::new('suspend', null, $this->name);
            }
            if ($response = Fiber::suspend($event)) {
                $this->builds[] = $response;
            }
        } catch (Throwable $exception) {
            PRipple::printExpect($exception);
        }
    }

    /**
     * 快捷创建
     * @param string $name
     * @return static
     */
    public static function new(string $name): static
    {
        return new static($name);
    }

    /**
     * 订阅一个事件
     * @param string $event
     * @return void
     */
    protected function subscribe(string $event): void
    {
        try {
            $this->publishAsync(Build::new('event.subscribe', $event, $this->name));
            $this->subscribes[] = $event;
        } catch (Throwable $exception) {
            PRipple::printExpect($exception);
        }
    }

    /**
     * 发布一个事件
     * @param Build $event
     * @return void
     */
    protected function publishAsync(Build $event): void
    {
        PRipple::publishAsync($event);
    }

    /**
     * 取消订阅一个事件
     * @param string $event
     * @return void
     * @throws Throwable
     */
    protected function unsubscribe(string $event): void
    {
        try {
            $this->publishAsync(Build::new('event.unsubscribe', $event, $this->name));
            $index = array_search($event, $this->subscribes);
            if ($index !== false) {
                unset($this->subscribes[$index]);
            }
        } catch (Throwable $exception) {
            PRipple::printExpect($exception);
        }
    }

    /**
     * 订阅一个套接字连接
     * @param Socket $socket
     * @return void
     */
    protected function subscribeSocket(Socket $socket): void
    {
        try {
            socket_set_nonblock($socket);
            $this->publishAsync(Build::new('socket.subscribe', $socket, $this->name));
        } catch (Throwable $exception) {
            PRipple::printExpect($exception);
        }
    }

    /**
     * 取消订阅一个套接字连接
     * @param Socket $socket
     * @return void
     */
    protected function unsubscribeSocket(Socket $socket): void
    {
        try {
            $this->publishAsync(Build::new('socket.unsubscribe', $socket, $this->name));
        } catch (Throwable $exception) {
            PRipple::printExpect($exception);
        }
    }

    /**
     * @param Fiber $fiber
     * @param mixed|null $data
     * @return bool
     */
    protected function resume(Fiber $fiber, mixed $data = null): bool
    {
        try {
            if ($event = $fiber->resume($data)) {
                if (in_array($event->name, $this->subscribes)) {
                    $this->builds[] = $event;
                } else {
                    $this->publishAsync($event);
                }
                return true;
            }
        } catch (Throwable $exception) {
            PRipple::printExpect($exception);
        }
        return false;
    }

    /**
     * 释放资源
     * @return void
     */
    abstract public function destroy(): void;
}

<?php
declare(strict_types=1);

namespace Worker;

use Core\Constants;
use Core\Map\CollaborativeFiberMap;
use Core\Map\EventMap;
use Core\Output;
use Fiber;
use JetBrains\PhpStorm\NoReturn;
use Socket;
use Std\WorkerInterface;
use Throwable;

/**
 * WorkerInterface
 */
abstract class WorkerBase implements WorkerInterface
{
    /**
     * 客户端名称
     * @var string
     */
    public string $name;

    /**
     * 活跃的工作者
     * @var bool $busy
     */
    public bool $busy = false;

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
            case Constants::EVENT_SOCKET_READ:
                $this->handleSocket($build->data);
                break;
            case Constants::EVENT_SOCKET_EXPECT:
                $this->expectSocket($build->data);
                break;
            case Constants::EVENT_SOCKET_WRITE:
                break;
            case Constants::EVENT_HEARTBEAT:
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
            Output::printException($exception);
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
     * 释放资源
     * @return void
     */
    abstract public function destroy(): void;

    /**
     * 订阅一个事件
     * @param string $event
     * @return void
     */
    protected function subscribe(string $event): void
    {
        try {
            $this->publishAsync(Build::new(Constants::EVENT_EVENT_SUBSCRIBE, $event, $this->name));
            $this->subscribes[] = $event;
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }

    /**
     * 发布一个事件
     * @param Build $event
     * @return void
     */
    protected function publishAsync(Build $event): void
    {
        EventMap::push($event);
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
            $this->publishAsync(Build::new(Constants::EVENT_EVENT_UNSUBSCRIBE, $event, $this->name));
            $index = array_search($event, $this->subscribes);
            if ($index !== false) {
                unset($this->subscribes[$index]);
            }
        } catch (Throwable $exception) {
            Output::printException($exception);
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
            $this->publishAsync(Build::new(Constants::EVENT_SOCKET_SUBSCRIBE, $socket, $this->name));
        } catch (Throwable $exception) {
            Output::printException($exception);
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
            $this->publishAsync(Build::new(Constants::EVENT_SOCKET_UNSUBSCRIBE, $socket, $this->name));
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }

    /**
     * worker自带的纤程恢复方法,自动遵循规范处流程理大多数的事件
     * 如果Worker有自己的调度规范,请重写此方法
     * 一般我不建议这么做,如果有新的想法可以发表你的意见
     *
     * @param string $hash
     * @param mixed|null $data
     * @return bool
     */
    protected function resume(string $hash, mixed $data = null): bool
    {
        if (!$fiber = CollaborativeFiberMap::$collaborativeFiberMap[$hash] ?? null) {
            return false;
        }
        try {
            if ($event = $fiber->resumeFiberExecution($data)) {
                if (in_array($event->name, $this->subscribes)) {
                    $this->builds[] = $event;
                } else {
                    $this->publishAsync($event);
                }
                return true;
            }
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
        return false;
    }
}

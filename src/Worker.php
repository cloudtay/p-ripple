<?php

namespace Cclilshy\PRipple;

use Fiber;
use JetBrains\PhpStorm\NoReturn;
use Socket;
use Throwable;

abstract class Worker
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
     * @param string $name
     */
    protected function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function new(string $name): static
    {
        return new static($name);
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
            $this->heartbeat();
//            $this->consumption(Build::new('suspend', null, $this->name));
            $this->wait();
        }
    }

    /**
     * 处理返回
     * @param Build $build
     * @return void
     * @throws Throwable
     */
    private function consumption(Build $build): void
    {
        switch ($build->name) {
            case 'socket.read':
                $this->handleSocket($build->data);
                break;
            case 'socket.expect':
                $this->expectSocket($build->data);
                break;
            case 'socket.write':
//                $this->writeSocket($build->data);
                break;
            default:
                $this->handleEvent($build);
        }
    }


    /**
     * 订阅一个事件
     * @param string $event
     * @return void
     */
    protected function subscribe(string $event): void
    {
        try {
            $this->publish(Build::new('event.subscribe', $event, $this->name));
        } catch (Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
        }
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
            $this->publish(Build::new('event.unsubscribe', $event, $this->name));
        } catch (Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
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
            $this->publish(Build::new('socket.subscribe', $socket, $this->name));
        } catch (Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
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
            $this->publish(Build::new('socket.unsubscribe', $socket, $this->name));
        } catch (Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    /**
     * 发布一个事件
     * @param \Cclilshy\PRipple\Build $event
     * @return void
     */
    protected function publish(Build $event): void
    {
        try {
            PRipple::publish($event);
//            $this->builds[] = Fiber::suspend($event);
        } catch (Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    /**
     * 等待驱动
     * @param Build|null $event
     * @return void
     */
    protected function wait(Build|null $event = null): void
    {
        try {
            if ($event === null) {
                $event = Build::new('suspend', null, $this->name);
            }
            if ($response = Fiber::suspend($event)) {
                $this->builds[] = $response;
            }
        } catch (Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    abstract protected function initialize(): void;

    abstract protected function handleSocket(Socket $socket): void;

    abstract protected function expectSocket(Socket $socket): void;

    abstract protected function handleEvent(Build $event): void;

    abstract protected function heartbeat(): void;

    abstract protected function destroy(): void;
}

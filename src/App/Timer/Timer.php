<?php
declare(strict_types=1);

namespace App\Timer;

use Closure;
use Core\Map\CollaborativeFiberMap;
use Core\Map\WorkerMap;
use Core\Output;
use Exception;
use Socket;
use SplPriorityQueue;
use Throwable;
use Worker\Build;
use Worker\WorkerBase;

/**
 * 计时器服务
 */
class Timer extends WorkerBase
{
    public const EVENT_TIMER_EVENT = 'timer.event';
    public const EVENT_TIMER_LOOP = 'timer.loop';
    public const EVENT_TIMER_SLEEP = 'timer.sleep';

    /**
     * 任务队列
     * @var SplPriorityQueue $taskQueue
     */
    private SplPriorityQueue $taskQueue;

    /**
     * @return Timer|WorkerBase
     */
    public static function instance(): Timer|WorkerBase
    {
        return WorkerMap::getWorker(Timer::class);
    }

    /**
     * 心跳
     * @return void
     */
    public function heartbeat(): void
    {
        $now = time();
        while (!$this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->top();
            if ($task['expire'] <= $now && $event = $task['event']) {
                switch ($event->name) {
                    case Timer::EVENT_TIMER_EVENT:
                        $this->publishAsync($event->data['data']);
                        break;
                    case Timer::EVENT_TIMER_LOOP:
                        call_user_func($event->data['data']);
                        $this->loop($event->data['time'], $event->data['data']);
                        break;
                    case Timer::EVENT_TIMER_SLEEP:
                        try {
                            $this->resume($event->data['data']);
                        } catch (Throwable|Exception $exception) {
                            Output::printException($exception);
                        }
                        break;
                }
                $this->taskQueue->extract();
            } else {
                break;
            }
        }
    }

    /**
     * 循环执行一个闭包
     * @param int $second
     * @param Closure $callable
     * @return void
     */
    public function loop(int $second, Closure $callable): void
    {
        $this->publishAsync(Build::new(Timer::EVENT_TIMER_LOOP, [
            'time' => $second,
            'data' => $callable
        ], Timer::class));
    }

    /**
     * @return void
     */
    public function destroy(): void
    {

    }

    /**
     * 延时发送一个事件
     * @param int $second
     * @param Build $event
     * @return void
     */
    public function event(int $second, Build $event): void
    {
        $this->publishAsync(Build::new(Timer::EVENT_TIMER_EVENT, [
            'time' => $second,
            'data' => $event
        ], Timer::class));
    }

    /**
     * 在纤程内延时
     * @param int $second
     * @return void
     */
    public function sleep(int $second): void
    {
        $event = Build::new(Timer::EVENT_TIMER_SLEEP, [
            'time' => $second,
            'data' => CollaborativeFiberMap::current()->hash
        ], Timer::class);
        $timerData = $event->data;
        $duration = $timerData['time'];
        $this->taskQueue->insert([
            'expire' => time() + $duration,
            'event' => $event
        ], -time() - $duration);
        $this->publishAwait();
    }

    /**
     * @param Socket $socket
     * @return void
     */
    public function handleSocket(Socket $socket): void
    {
        // TODO: Implement handleSocket() method.
    }

    /**
     * @param Socket $socket
     * @return void
     */
    public function expectSocket(Socket $socket): void
    {
        // TODO: Implement expectSocket() method.
    }

    /**
     * 初始化
     * @return void
     */
    protected function initialize(): void
    {
        $this->todo      = true;
        $this->taskQueue = new SplPriorityQueue();
        $this->subscribe(Timer::EVENT_TIMER_EVENT);
        $this->subscribe(Timer::EVENT_TIMER_LOOP);
        $this->subscribe(Timer::EVENT_TIMER_SLEEP);
        \App\Facade\Timer::setInstance($this);
    }

    /**
     * 处理事件
     * @param Build $event
     * @return void
     */
    protected function handleEvent(Build $event): void
    {
        $timerData = $event->data;
        $duration  = $timerData['time'];
        $this->taskQueue->insert([
            'expire' => time() + $duration,
            'event'  => $event
        ], -time() - $duration);
    }
}

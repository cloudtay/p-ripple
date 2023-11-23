<?php
declare(strict_types=1);

namespace Worker\Built;

use Closure;
use Core\Map\CollaborativeFiberMap;
use Core\Output;
use Exception;
use SplPriorityQueue;
use Throwable;
use Worker\Prop\Build;
use Worker\Worker;

/**
 * Timer is a process-level service that provides timing services for the current process and does not support uninstallation.
 * Timer should clear the task queue after Fork occurs to ensure that it does not interfere with the work of the parent process.
 */
class Timer extends Worker
{
    public const EVENT_TIMER_EVENT = 'timer.event';
    public const EVENT_TIMER_LOOP  = 'timer.loop';
    public const EVENT_TIMER_SLEEP = 'timer.sleep';

    /**
     * 门面类
     * @var string $facadeClass
     */
    protected static string $facadeClass = \Facade\Timer::class;

    /**
     * 任务队列
     * @var SplPriorityQueue $taskQueue
     */
    private SplPriorityQueue $taskQueue;

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
     * @param int     $second
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
     * 延时发送一个事件
     * @param int   $second
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
        $event     = Build::new(Timer::EVENT_TIMER_SLEEP, [
            'time' => $second,
            'data' => CollaborativeFiberMap::current()->hash
        ], Timer::class);
        $timerData = $event->data;
        $duration  = $timerData['time'];
        $this->taskQueue->insert([
            'expire' => time() + $duration,
            'event'  => $event
        ], -time() - $duration);
        $this->publishAwait();
    }

    /**
     * 初始化
     * @return void
     */
    public function initialize(): void
    {
        $this->busy      = true;
        $this->taskQueue = new SplPriorityQueue();
        $this->subscribe(Timer::EVENT_TIMER_EVENT);
        $this->subscribe(Timer::EVENT_TIMER_LOOP);
        $this->subscribe(Timer::EVENT_TIMER_SLEEP);
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

    /**
     * 被动fork时清空任务队列
     * @return void
     */
    public function forkPassive(): void
    {
        while (!$this->taskQueue->isEmpty()) {
            $this->taskQueue->extract();
        }
        parent::forkPassive();
    }

    /**
     * 主动fork时清空任务队列
     * @return void
     */
    public function forking(): void
    {
        while (!$this->taskQueue->isEmpty()) {
            $this->taskQueue->extract();
        }
        parent::forking();
    }
}

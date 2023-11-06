<?php
declare(strict_types=1);

namespace PRipple\App\Timer;

use Closure;
use Exception;
use Fiber;
use PRipple\App\PDOProxy\Exception\PDOProxyException;
use PRipple\PRipple;
use PRipple\Worker\Build;
use PRipple\Worker\Worker;
use Socket;
use SplPriorityQueue;
use Throwable;

/**
 * 计时器服务
 */
class Timer extends Worker
{
    /**
     * 任务队列
     * @var SplPriorityQueue $taskQueue
     */
    private SplPriorityQueue $taskQueue;

    /**
     * @return Timer|Worker
     */
    public static function instance(): Timer|Worker
    {
        return PRipple::worker(Timer::class);
    }

    /**
     * 初始化
     * @return void
     */
    public function initialize(): void
    {
        $this->taskQueue = new SplPriorityQueue();
        $this->subscribe('timer.event');
        $this->subscribe('timer.loop');
        $this->subscribe('timer.sleep');
        $this->todo = true;
        \PRipple\App\Facade\Timer::setInstance($this);
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
                    case 'timer.event':
                        $this->publishAsync($event->data['data']);
                        break;
                    case 'timer.loop':
                        call_user_func($event->data['data']);
                        $this->loop($event->data['time'], $event->data['data']);
                        break;
                    case 'timer.sleep':
                        try {
                            $event->data['data']->resume();
                        } catch (Throwable|Exception|PDOProxyException $exception) {
                            PRipple::printExpect($exception);
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
        $this->publishAsync(Build::new('timer.loop', [
            'time' => $second,
            'data' => $callable
        ], Timer::class));
    }

    /**
     * 处理事件
     * @param Build $event
     * @return void
     */
    public function handleEvent(Build $event): void
    {
        $timerData = $event->data;
        $duration = $timerData['time'];
        $this->taskQueue->insert([
            'expire' => time() + $duration,
            'event' => $event
        ], -time() - $duration);
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
        $this->publishAsync(Build::new('timer.event', [
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
        $this->publishAwait(Build::new('timer.sleep', [
            'time' => $second,
            'data' => Fiber::getCurrent()
        ], Timer::class));
    }

    public function handleSocket(Socket $socket): void
    {
        // TODO: Implement handleSocket() method.
    }

    public function expectSocket(Socket $socket): void
    {
        // TODO: Implement expectSocket() method.
    }

    public function handleBuild(Build $event): void
    {
        // TODO: Implement handleBuild() method.
    }
}

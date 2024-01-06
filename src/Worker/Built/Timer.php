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


namespace Cclilshy\PRipple\Worker\Built;

use Cclilshy\PRipple\Core\Coroutine\Coroutine;
use Cclilshy\PRipple\Core\Event\Event;
use Cclilshy\PRipple\Core\Map\CoroutineMap;
use Cclilshy\PRipple\Core\Map\EventMap;
use Cclilshy\PRipple\Core\Output;
use Cclilshy\PRipple\Core\Standard\WorkerInterface;
use Cclilshy\PRipple\Worker\Worker;
use Closure;
use Exception;
use Override;
use SplPriorityQueue;
use Throwable;
use function Co\async;
use function time;

/**
 * @class Timer
 * Timer is a process-level service that provides timing services for the current process and does not support uninstallation.
 * Timer should clear the task queue after Fork occurs to ensure that it does not interfere with the work of the parent process.
 */
final class Timer extends Worker implements WorkerInterface
{
    public const string EVENT_TIMER_EVENT = 'timer.event';
    public const string EVENT_TIMER_LOOP  = 'timer.loop';
    public const string EVENT_TIMER_SLEEP = 'timer.sleep';

    /**
     * 门面类
     * @var string $facadeClass
     */
    public static string $facadeClass = \Cclilshy\PRipple\Facade\Timer::class;

    /**
     * 任务队列
     * @var SplPriorityQueue $taskQueue
     */
    private SplPriorityQueue $taskQueue;

    /**
     * 心跳
     * @return void
     */
    #[Override] public function heartbeat(): void
    {
        $baseTime = time();
        while (!$this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->top();
            if ($task['expire'] <= $baseTime && $event = $task['event']) {
                $this->taskQueue->extract();
                switch ($event->name) {
                    case Timer::EVENT_TIMER_EVENT:
                        EventMap::push($event->data['data']);
                        break;
                    case Timer::EVENT_TIMER_LOOP:
                        async(function (Coroutine $coroutine) use ($event) {
                            try {
                                if ($coroutine->callUserFunction($event->data['data'])) {
                                    $this->loop($event->data['data'], $event->data['time']);
                                }
                            } catch (Throwable $exception) {
                                Output::printException($exception);
                            }
                        });
                        break;
                    case Timer::EVENT_TIMER_SLEEP:
                        try {
                            CoroutineMap::resume(
                                $event->data['data'],
                                Event::build(Coroutine::EVENT_RESUME, null, Timer::class)
                            );
                        } catch (Throwable|Exception $exception) {
                            Output::printException($exception);
                        }
                        break;
                }
            } else {
                break;
            }
        }
    }

    /**
     * 循环执行一个闭包
     * @param Closure $closure
     * @param int     $second
     * @return void
     */
    public function loop(Closure $closure, int $second): void
    {
        EventMap::push(Event::build(Timer::EVENT_TIMER_LOOP, ['time' => $second, 'data' => $closure], Timer::class));
    }

    /**
     * 延时发送一个事件
     * @param Event $event
     * @param int   $second
     * @return void
     */
    public function event(Event $event, int $second): void
    {
        EventMap::push(Event::build(Timer::EVENT_TIMER_EVENT, ['time' => $second, 'data' => $event], Timer::class));
    }

    /**
     * 在协程内延时
     * @param int $second
     * @return mixed
     * @throws Throwable
     */
    public function sleep(int $second): mixed
    {
        if (!$coroutine = CoroutineMap::this()) {
            sleep($second);
            return null;
        }
        $event     = Event::build(Timer::EVENT_TIMER_SLEEP, ['time' => $second, 'data' => CoroutineMap::this()->hash], Timer::class);
        $timerData = $event->data;
        $duration  = $timerData['time'];
        $this->taskQueue->insert(['expire' => time() + $duration, 'event' => $event], -time() - $duration);
        return $coroutine->suspend();
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
        parent::initialize();
    }

    /**
     * 处理事件
     * @param Event $event
     * @return void
     */
    #[Override] public function handleEvent(Event $event): void
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
        parent::forkPassive();
        while (!$this->taskQueue->isEmpty()) {
            $this->taskQueue->extract();
        }
    }
}

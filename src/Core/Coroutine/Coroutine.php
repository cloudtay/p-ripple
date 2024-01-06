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


namespace Cclilshy\PRipple\Core\Coroutine;

use Cclilshy\Container\Container;
use Cclilshy\PRipple\Core\Coroutine\Exception\Exception;
use Cclilshy\PRipple\Core\Coroutine\Exception\TimeoutException;
use Cclilshy\PRipple\Core\Event\Event;
use Cclilshy\PRipple\Core\Map\CoroutineMap;
use Cclilshy\PRipple\Core\Output;
use Closure;
use Fiber;
use Throwable;
use function spl_object_hash;
use function count;

/**
 * @class Coroutine 基础协程对象
 */
class Coroutine extends Container
{
    public const string  EVENT_TIMEOUT   = 'system.coroutine.timeout';
    public const string  EVENT_EXCEPTION = 'system.coroutine.exception';
    public const string  EVENT_RESUME    = 'system.coroutine.resume';
    public const string  EVENT_SUSPEND   = 'system.coroutine.suspend';

    public const string  STATUS_PENDING   = 'system.coroutine.status.pending';
    public const string  STATUS_FULFILLED = 'system.coroutine.status.fulfilled';
    public const string  STATUS_REJECTED  = 'system.coroutine.status.rejected';

    /**
     * 任务唯一标识
     * @var string $hash
     */
    public string $hash;

    /**
     * 协程实例
     * @var Fiber $fiber
     */
    public Fiber $fiber;

    /**
     * 异步事件订阅列表
     * @var Closure[] $asyncHandlers
     */
    public array $asyncHandlers = [];

    /**
     * 最终执行
     * @var Closure[] $defers
     */
    public array $defers = [];

    /**
     * 成功后执行
     * @var Closure[] $thenList
     */
    public array $thenList = [];

    /**
     * 超时执行
     * @var int $timeout
     */
    public int $timeout;

    /**
     * 标记
     * @var array $flags
     */
    public array $flags = [];

    /**
     * 状态
     * @var string $status
     */
    public string $status = Coroutine::STATUS_PENDING;

    /**
     * 结果
     * @var mixed $result
     */
    public mixed $result;

    /**
     * @param Closure $closure
     * @return void
     */
    private function entrance(Closure $closure): void
    {
        try {
            $this->fulfilled(
                $this->callUserFunction($closure)
            );
        } catch (Throwable $exception) {
            $this->rejected(
                $exception
            );
            $this->processException($exception);
        } finally {
            $this->finalize();
        }
    }

    /**
     * 状态设置为已完成
     * @param mixed $result
     * @return void
     */
    public function fulfilled(mixed $result): void
    {
        $this->suspendIfNeeded();
        $this->status = Coroutine::STATUS_FULFILLED;
        $this->result = $result;
        $this->handleEvent(Event::build(
            Coroutine::STATUS_FULFILLED,
            $result,
            $this->hash
        ));
    }

    /**
     * 状态设置为已拒绝
     * @param mixed $result
     * @return void
     */
    public function rejected(mixed $result): void
    {
        $this->suspendIfNeeded();
        $this->status = Coroutine::STATUS_REJECTED;
        $this->result = $result;
        $this->handleEvent(Event::build(
            Coroutine::STATUS_REJECTED,
            $result,
            $this->hash
        ));
    }

    /**
     * 结束处理
     * @return void
     */
    private function finalize(): void
    {
        $this->runDefers();
        $this->destroy();
    }

    /**
     * 执行最终回调
     * @return void
     */
    private function runDefers(): void
    {
        foreach ($this->defers as $defer) {
            try {
                $this->callUserFunction($defer);
            } catch (Throwable $exception) {
                $this->processException($exception);
            }
        }
    }

    /**
     * 如有必要，挂起协程
     * @return void
     */
    private function suspendIfNeeded(): void
    {
        if (count($this->flags) > 0) {
            try {
                $this->suspend();
            } catch (Throwable $exception) {
                $this->processException($exception);
            }
        }
    }

    /**
     * 处理异常
     * @param Throwable $exception
     * @return void
     */
    private function processException(Throwable $exception): void
    {
        $this->flag(Coroutine::EVENT_EXCEPTION);
        $event = Event::build(
            $exception instanceof TimeoutException
                ? Coroutine::EVENT_TIMEOUT
                : Coroutine::EVENT_EXCEPTION,
            $exception,
            $this->hash
        );
        $this->handleEvent($event);
        $this->erase(Coroutine::EVENT_EXCEPTION);
    }

    /**
     * 装配入口函数
     * @param Closure $closure
     * @return static
     */
    public function setup(Closure $closure): Coroutine
    {
        $this->inject(Coroutine::class, $this);

        $this->fiber = new Fiber(fn() => $this->entrance($closure));
        $this->hash  = spl_object_hash($this->fiber);

        $this->on(Coroutine::STATUS_FULFILLED, function () {
            foreach ($this->thenList as $then) {
                $this->callUserFunction($then);
            }
        });

        CoroutineMap::insert($this);
        return $this;
    }

    /**
     * @param Coroutine $coroutine
     * @return mixed
     * @throws Throwable
     */
    public function await(Coroutine $coroutine): mixed
    {
        $coroutine->defer(function (Coroutine $coroutine) {
            $this->resume(Event::build(Coroutine::EVENT_RESUME, $coroutine, $this->hash));
        });
        return $this->suspend();
    }

    /**
     * Synchronous emitting an event,
     * Captured by the last caller, usually not required,
     * Because the caller logs the event
     * @return mixed
     * @throws Throwable
     */
    public function suspend(): mixed
    {
        wait:
        if (!$event = Fiber::suspend(Event::build(Coroutine::EVENT_SUSPEND, null, $this->hash))) {
            throw new Exception('This should never happen');
        } elseif (!$event instanceof Event) {
            throw new Exception('This should never happen');
        } elseif ($event->name === Coroutine::EVENT_RESUME) {
            return $event->data;
        }
        $this->handleEvent($event);
        if (count($this->flags) === 0) {
            return false;
        } else {
            goto wait;
        }
    }

    /**
     * 处理事件
     * @param Event $event
     * @return void
     */
    public function handleEvent(Event $event): void
    {
        if ($handler = $this->asyncHandlers[$event->name] ?? null) {
            try {
                $this->callUserFunctionArray($handler, [$event, $this]);
            } catch (Throwable $exception) {
                if ($event->data instanceof Throwable) {
                    Output::printException($event->data);
                } else {
                    $this->processException($exception);
                }
            }
        } elseif ($event->name === Coroutine::EVENT_EXCEPTION) {
            Output::printException($event->data);
        }
    }

    /**
     * 立即执行协程
     * @return mixed
     * @throws Exception|Throwable
     */
    public function execute(): mixed
    {
        if (!isset($this->fiber)) {
            throw new Exception('Coroutine not setup');
        }
        return $this->fiber->start($this);
    }

    /**
     * 协程抛入队列而非立即执行
     * @throws Exception
     */
    public function queue(): void
    {
        if (!isset($this->fiber)) {
            throw new Exception('Coroutine not setup');
        }
        CoroutineMap::production($this);
    }

    /**
     * 恢复协程执行
     * @param mixed|null $value
     * @return mixed
     * @throws Throwable
     */
    public function resume(mixed $value = null): mixed
    {
        return $this->fiber->resume($value);
    }

    /**
     * 向协程抛出一个异常,由挂起处获取
     * @param Throwable $exception
     * @return void
     */
    public function throw(Throwable $exception): void
    {
        try {
            $this->fiber->throw($exception);
        } catch (Throwable $exception) {
            Output::printException($exception);
        }
    }

    /**
     * 设置超时处理
     * @param Closure $closure
     * @param int     $time
     * @return $this
     */
    public function timeout(Closure $closure, int $time): static
    {
        $this->timeout = time() + $time;
        CoroutineMap::timer($this->hash, $time);
        $this->on(Coroutine::EVENT_TIMEOUT, $closure);
        return $this;
    }

    /**
     * @param Closure $closure
     * @return static
     * @deprecated
     * 设置错误处理器
     */
    public function except(Closure $closure): static
    {
        return $this->catch($closure);
    }

    /**
     * 设置最终执行
     * @param Closure $closure
     * @return $this
     */
    public function then(Closure $closure): Coroutine
    {
        $this->thenList[] = $closure;
        return $this;
    }

    /**
     * 设置错误处理器
     * @param Closure $closure
     * @return static
     */
    public function catch(Closure $closure): static
    {
        $this->on(Coroutine::EVENT_EXCEPTION, $closure);
        return $this;
    }

    /**
     * 设置最终执行
     * @param Closure $closure
     * @return Coroutine
     */
    public function defer(Closure $closure): Coroutine
    {
        $this->defers[] = $closure;
        return $this;
    }

    /**
     * 订阅异步事件
     * @param string  $eventName
     * @param Closure $closure
     * @return void
     */
    public function on(string $eventName, Closure $closure): void
    {
        $this->asyncHandlers[$eventName] = $closure;
    }

    /**
     * 打上一个标记
     * @param string $key
     * @return void
     */
    public function flag(string $key): void
    {
        if (isset($this->flags[$key])) {
            $this->flags[$key]++;
        } else {
            $this->flags[$key] = 1;
        }
    }

    /**
     * 擦除标记
     * @param string    $key
     * @param bool|null $all
     * @return void
     */
    public function erase(string $key, bool|null $all = false): void
    {
        if ($all) {
            unset($this->flags[$key]);
        } elseif (isset($this->flags[$key])) {
            $this->flags[$key]--;
            if ($this->flags[$key] === 0) {
                unset($this->flags[$key]);
            }
        }
    }

    /**
     * 验证协程是否已终止
     * @return bool
     */
    public function terminated(): bool
    {
        return $this->fiber->isTerminated();
    }

    /**
     * 销毁自身
     * @return true
     */
    public function destroy(): true
    {
        CoroutineMap::remove($this);
        return true;
    }
}

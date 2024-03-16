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


namespace Cclilshy\PRipple\Worker;

use Cclilshy\PRipple\Core\Event\Event;
use Cclilshy\PRipple\Core\Kernel;
use Cclilshy\PRipple\Core\Map\EventMap;
use Cclilshy\PRipple\Core\Map\WorkerMap;
use Cclilshy\PRipple\Core\Net\Stream;
use Cclilshy\PRipple\Core\Output;
use Cclilshy\PRipple\Core\Standard\WorkerInterface;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Protocol\Slice;
use Closure;
use Exception;
use Revolt\EventLoop;
use Throwable;
use function array_search;
use function call_user_func;
use function count;
use function in_array;
use function posix_getpid;

/**
 * @class Worker 基础工作器
 * WorkerInterface
 * #abstract
 */
class Worker implements WorkerInterface
{
    public const string HOOK_HEARTBEAT    = 'system.worker.heartbeat';
    public const string HOOK_HANDLE_EVENT = 'system.worker.event.call';

    /**
     * Collaborative work mode
     */
    public const int MODE_COLLABORATIVE = 1;

    /**
     * Stand-alone working mode
     */
    public const int MODE_INDEPENDENT = 2;

    /**
     * Facade class
     * @var string $facadeClass
     */
    public static string $facadeClass;

    /**
     * Mode of operation
     * @var int $mode
     */
    public int $mode = 1;

    /**
     * The name of the service
     * @var string $name
     */
    public string $name;

    /**
     * Is active workers
     * @var bool $busy
     */
    public bool $busy = false;

    /**
     * Whether it is a register parallel process
     * @var bool $isFork
     */
    public bool $isFork = false;

    /**
     * Coroutine task queue mapping: Maps the hash of each coroutine to an array of events
     * @var array<string, Event[]> $queue
     * @var array[]                $queue
     */
    public array $queue = [];

    /**
     * Subscribe to a list of events
     * @var array $subscribes
     */
    public array $subscribes = [];

    /**
     * Message cutter
     * @var Slice $slice
     */
    public Slice $slice;

    /**
     * RPC Services
     * @var Worker $rpcService
     */
    public Worker $rpcService;

    /**
     * Whether it serves the root
     * @var bool $root
     */
    public bool $root = true;

    /**
     * Service Incident Processor
     * @var array $hooks
     */
    public array $hooks = [];

    /**
     * Number of threads
     * @var int $thread
     */
    public int $thread = 1;

    /**
     * A list of client flows
     * @var Stream[] $streamMap
     */
    public array $streamMap = [];

    /**
     * Easy to create
     * @param string $name
     * @return static
     */
    final public static function new(string $name): static
    {
        return new static($name);
    }

    /**
     * __construct
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name  = $name;
        $this->slice = new Slice();
        $this->hook(Worker::HOOK_HEARTBEAT, fn() => $this->heartbeat());
        $this->hook(Worker::HOOK_HANDLE_EVENT, fn(Event $event) => $this->handleEvent($event));
    }

    /**
     * Get the façade class
     * @return Worker|null
     */
    final public static function getInstance(): static|null
    {
        if (isset(static::$facadeClass)) {
            return call_user_func([static::$facadeClass, 'getInstance']);
        }
        try {
            throw new Exception('Facade class not found.');
        } catch (Exception $exception) {
            Output::error($exception);
            return null;
        }
    }


    /**
     * Install the Network Event Handler
     * @param string  $event
     * @param Closure $closure
     * @return int
     */
    final public function hook(string $event, Closure $closure): int
    {
        $this->hooks[$event][] = $closure;
        return count($this->hooks[$event]) - 1;
    }

    /**
     * Unload the network event handler
     * @param string $event
     * @param int    $index
     * @return void
     */
    final public function unhook(string $event, int $index): void
    {
        unset($this->hooks[$event][$index]);
    }

    /**
     * Handle flow exception events
     * @param mixed $stream
     * @return void
     * 处理流异常事件
     */
    final public function expectStream(Stream $stream): void
    {
        $this->removeStream($stream);
    }

    /**
     * @param Stream      $stream
     * @param string|null $event
     * @return void
     */
    final public function addStream(Stream $stream, string|null $event = Kernel::EVENT_STREAM_READ): void
    {
        $this->streamMap[$stream->id] = $stream;
        $this->subscribeStream($stream, $event);
    }

    /**
     * Remove references everywhere Stream
     * @param Stream $stream
     * @return void
     */
    final public function removeStream(Stream $stream): void
    {
        $stream->close();
        unset($this->streamMap[$stream->id]);
        $this->unsubscribeStream($stream);
    }

    /**
     * @var string[] $callableIdMap
     */
    private array $callableIdMap = [];

    /**
     * Subscribe to a stream
     * @param Stream      $stream
     * @param string|null $event
     * @return void
     */
    final public function subscribeStream(Stream $stream, string|null $event = Kernel::EVENT_STREAM_READ): void
    {
        switch ($event) {
            case Kernel::EVENT_STREAM_READ:
                $this->callableIdMap[$stream->id] = EventLoop::onReadable($stream->stream, fn() => $this->handleStreamRead($stream));
                break;
            case Kernel::EVENT_STREAM_WRITE:
                $this->callableIdMap[$stream->id] = EventLoop::onWritable($stream->stream, fn() => $this->handleStreamWrite($stream));
                break;
        }
    }

    /**
     * Unsubscribe from a stream
     * @param Stream      $stream
     * @param string|null $event
     * @return void
     */
    final public function unsubscribeStream(Stream $stream, string|null $event = Kernel::EVENT_STREAM_READ): void
    {
        if (isset($this->callableIdMap[$stream->id])) {
            EventLoop::cancel($this->callableIdMap[$stream->id]);
            unset($this->callableIdMap[$stream->id]);
        }
    }


    /**
     * Subscribe to an event
     * @param string $eventName
     * @return void
     */
    final public function subscribe(string $eventName): void
    {
        try {
            EventMap::push(Event::build(
                Kernel::EVENT_EVENT_SUBSCRIBE,
                $eventName,
                $this->name
            ));
            $this->subscribes[] = $eventName;
        } catch (Throwable $exception) {
            Output::error($exception);
        }
    }

    /**
     * Unsubscribe from an event
     * @param string $event
     * @return void
     * @throws Throwable
     */
    final public function unsubscribe(string $event): void
    {
        try {
            EventMap::push(Event::build(Kernel::EVENT_EVENT_UNSUBSCRIBE, $event, $this->name));
            $index = array_search($event, $this->subscribes);
            if ($index !== false) {
                unset($this->subscribes[$index]);
            }
        } catch (Throwable $exception) {
            Output::error($exception);
        }
    }

    /**
     * Define the worker running mode
     * @param int|null $mode
     * @param int|null $thread
     * @return Worker
     */
    final public function mode(int|null $mode = Worker::MODE_COLLABORATIVE, int|null $thread = 1): static
    {
        $this->mode   = $mode;
        $this->thread = $thread;
        return $this;
    }

    /**
     * Set up the service thread
     * @param int|null $thread
     * @return Worker|int
     */
    final public function thread(int|null $thread = null): static|int
    {
        if ($thread) {
            $this->thread = $thread;
        } else {
            return $this->thread;
        }
        return $this;
    }

    /**
     * Whether it is a child process
     * @return bool
     */
    final public function isFork(): bool
    {
        return $this->isFork;
    }

    /**
     * Whether it is a root service process, usually the first process to listen on the socket
     * @return bool
     */
    final public function isRoot(): bool
    {
        return $this->root;
    }

    /**
     * Start the service
     * @return void
     */
    public function launch(): void
    {
        if ($this->mode === Worker::MODE_INDEPENDENT) {
            PRipple::kernel()->fork(function () {
                $this->initialize();
                PRipple::kernel()->run();
            });
        } else {
            $this->initialize();
        }
    }

    /**
     * Executed at initialization
     * @return void
     */
    public function initialize(): void
    {
        if (isset(static::$facadeClass)) {
            call_user_func([static::$facadeClass, 'setInstance'], $this);
        }
        Output::info('[initialize]', $this->name . ' [process:' . posix_getpid() . ']');
    }


    /**
     * RPC service is launched
     * @param array $data
     * @return void
     */
    public function rPCServiceOnline(array $data): void
    {
    }

    /**
     * Handle the event
     * @param Event $event
     * @return void
     * #abstract
     */
    public function handleEvent(Event $event): void
    {
    }

    /**
     * HEARTBEAT
     * @return void
     * #abstract
     */
    public function heartbeat(): void
    {
    }


    /**
     * @param Stream $stream
     * @return void
     */
    protected function handleStreamRead(Stream $stream): void
    {
    }

    /**
     * @param mixed $stream
     * @return void
     */
    protected function handleStreamWrite(Stream $stream)
    {
    }

    /**
     * Workers are born in parallel
     * @return int $count
     */
    public function fork(): int
    {
        return Kernel::fork(function () {
            $this->isFork = true;
            $this->root   = false;
            foreach (WorkerMap::$workerMap as $worker) {
                if (in_array($worker->name, Kernel::BUILT_SERVICES, true)) {
                    continue;
                }
                if ($worker->name !== $this->name) {
                    $worker->forkPassive();
                }
            }
            $this->forking();
        }, false);
    }


    /**
     * Call network events
     * @param string $method
     * @param array  $params
     * @return void
     */
    public function callWorkerEvent(string $method, mixed ...$params): void
    {
        try {
            foreach ($this->hooks[$method] ?? [] as $hook) {
                call_user_func_array($hook, $params);
            }
        } catch (Throwable $exception) {
            Output::error($exception);
        }
    }

    /**
     * Passive shunts parallelism triggers,
     * which by default cancel all client connections and listeners that take over the parent process
     * @return void
     */
    public function forkPassive(): void
    {
        // Disconnect the client that takes over the parent process
        $this->isFork = true;
        $this->root   = false;
        foreach ($this->streamMap as $stream) {
            $this->removeStream($stream->stream);
        }
    }

    /**
     * Executed when processes are split in parallel
     * By default, all client connections that take over the parent process are canceled
     * @return void
     */
    public function forking(): void
    {
        // Disconnect the client that takes over the parent process
        $this->isFork = true;
    }

    /**
     * destroy
     * @return void
     */
    public function destroy(): void
    {
        try {
            foreach ($this->streamMap as $stream) {
                $this->removeStream($stream);
            }
        } catch (Exception $exception) {
            Output::error($exception);
        }
    }
}

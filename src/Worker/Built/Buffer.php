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
use Cclilshy\PRipple\Core\Kernel;
use Cclilshy\PRipple\Core\Map\CoroutineMap;
use Cclilshy\PRipple\Core\Net\Socket;
use Cclilshy\PRipple\Core\Net\Stream;
use Cclilshy\PRipple\Core\Output;
use Cclilshy\PRipple\Core\Standard\WorkerInterface;
use Cclilshy\PRipple\Facade\IO;
use Cclilshy\PRipple\Filesystem\Exception\FileException;
use Cclilshy\PRipple\Worker\Worker;
use Override;
use Throwable;
use function fopen;
use function get_resource_id;
use function spl_object_hash;

/**
 * @class Buffer
 * Buffer Worker is a process-level service that will actively push and clean up all data blocked in the buffer when it is idle.
 * It allows workers of all network types in this process to interact with data without having to worry too
 * much about issues such as sending bytes/residuals/recycling etc.
 * When passively forking, all buffer queues should be actively discarded to ensure the smooth operation of this process service.
 */
final class Buffer extends Worker implements WorkerInterface
{
    public const string   EVENT_SOCKET_BUFFER_UN = 'system.net.socket.buffer.un';
    public const string   EVENT_SOCKET_BUFFER    = 'system.net.socket.buffer';
    public const string   TASK_WRITE             = 'system.net.buffer.write';
    public const string   TASK_READ              = 'system.net.buffer.read';
    public static string $facadeClass = IO::class;
    /**
     * 缓冲区套接字列表
     * @var Event[] $tasks
     */
    public array $tasks = [];

    /**
     * 初始化
     * @return void
     */
    public function initialize(): void
    {
        $this->subscribe(Buffer::EVENT_SOCKET_BUFFER);
        $this->subscribe(Buffer::EVENT_SOCKET_BUFFER_UN);
        parent::initialize();
    }

    /**
     * 处理事件
     * @param Event $event
     * @return void
     */
    #[Override] public function handleEvent(Event $event): void
    {
        switch ($event->name) {
            case Buffer::EVENT_SOCKET_BUFFER:
                $socketHash               = spl_object_hash($event->data);
                $this->tasks[$socketHash] = $event->data;
                break;
            case Buffer::EVENT_SOCKET_BUFFER_UN:
                $socketHash = spl_object_hash($event->data);
                unset($this->tasks[$socketHash]);
                break;
            default:
                break;
        }
    }

    /**
     * 处理流可读事件
     * @param mixed $stream
     * @return void
     */
    #[Override] protected function handleStreamRead(Stream $stream): void
    {
        /**
         * 查找该流是否在任务队列中,目前储存的任务队列为
         * @task 异步文件读取
         * @task 文件到Socket传输
         */
        if ($event = $this->tasks[$stream->id]) {
            switch ($event->name) {
                case Buffer::TASK_WRITE:
                    try {
                        if ($content = $stream->read(81920)) {
                            $event->data->write($content);
                        }
                        if ($stream->eof()) {
                            $this->removeStream($stream);
                            unset($this->tasks[$stream->id]);
                            if ($event->source) {
                                CoroutineMap::resume($event->source, Event::build(Coroutine::EVENT_RESUME, null, Buffer::class));
                            }
                        }
                    } catch (Throwable $exception) {
                        Output::printException($exception);
                        $this->removeStream($stream);
                        unset($this->tasks[$stream->id]);
                        if ($event->source) {
                            try {
                                CoroutineMap::resume($event->source, Event::build(
                                    Coroutine::EVENT_EXCEPTION,
                                    $exception,
                                    Buffer::class
                                ));
                            } catch (Throwable $exception) {
                                Output::printException($exception);
                            }
                        }
                    }
                    break;
                case Buffer::TASK_READ:
                    try {
                        if ($content = $stream->read(81920)) {
                            $event->data .= $content;
                        }
                        if ($stream->eof()) {
                            $this->removeStream($stream);
                            unset($this->tasks[$stream->id]);
                            if ($coroutine = CoroutineMap::get($event->source)) {
                                $coroutine->resume(Event::build(Coroutine::EVENT_RESUME, $event->data, Buffer::class));
                            }
                        }
                    } catch (Throwable $exception) {
                        Output::printException($exception);
                        $this->removeStream($stream);
                        unset($this->tasks[$stream->id]);
                    }
                    break;
                default:
                    break;
            }
        } else {
            $this->removeStream($stream);
        }
    }

    /**
     * 处理流可写事件
     * @param Stream $stream
     * @return void
     */
    #[Override] protected function handleStreamWrite(Stream $stream): void
    {
        /**
         * 查找该流是否在任务队列中,目前储存的任务队列为
         * @task Socket套接字缓冲区清理
         */
        if ($event = $this->tasks[$stream->id]) {
            switch ($event->name) {
                case Buffer::TASK_WRITE:
                    try {
                        write:
                        if (!$event->data->openBuffer) {
                            $this->unsubscribeStream($stream, Kernel::EVENT_STREAM_SUBSCRIBE_WRITE);
                            unset($this->tasks[$stream->id]);
                        } elseif ($event->data->deprecated) {
                            $this->unsubscribeStream($stream, Kernel::EVENT_STREAM_SUBSCRIBE_WRITE);
                            unset($this->tasks[$stream->id]);
                        } elseif ($event->data->write('')) {
                            goto write;
                        }
                    } catch (FileException $exception) {
                        Output::printException($exception);
                        $this->unsubscribeStream($stream, Kernel::EVENT_STREAM_SUBSCRIBE_WRITE);
                        unset($this->tasks[$stream->id]);
                        return;
                    }
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * 嫁接文件传输到Socket,直到文件结束
     * @param string $path
     * @param Socket $socketTunnel
     * @return void
     */
    public function fileToSocket(string $path, Socket $socketTunnel): void
    {
        if ($coroutine = CoroutineMap::this()) {
            $coroutineHash = $coroutine->hash;
        } else {
            $coroutineHash = null;
        }
        $stream                 = fopen($path, 'r');
        $streamId               = get_resource_id($stream);
        $this->tasks[$streamId] = Event::build(Buffer::TASK_WRITE, $socketTunnel, $coroutineHash);
        $this->addStream(new Stream($stream));
        if ($coroutine) {
            try {
                $coroutine->suspend();
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
        }
    }

    /**
     * 监听Socket缓冲区
     * @param Socket $socket
     * @return void
     */
    public function cleanBuffer(Socket $socket): void
    {
        $this->subscribeStream($socket, Kernel::EVENT_STREAM_SUBSCRIBE_WRITE);
        $this->tasks[$socket->id] = Event::build(Buffer::TASK_WRITE, $socket, $this->name);
    }

    /**
     * 异步读文件
     * @param string $path
     * @return string
     * @throws Throwable
     */
    public function fileGetContents(string $path): string
    {
        if (!file_exists($path)) {
            return '';
        } elseif (!$coroutine = CoroutineMap::this()) {
            return file_get_contents($path);
        } else {
            $stream                                = fopen($path, 'r');
            $this->tasks[get_resource_id($stream)] = Event::build(Buffer::TASK_READ, '', $coroutine->hash);
            $this->addStream(new Stream($stream));
            return $coroutine->suspend();
        }
    }

    /**
     * 被动fork
     * @return void
     */
    public function forkPassive(): void
    {
        parent::forkPassive();
        $this->tasks = [];
    }
}

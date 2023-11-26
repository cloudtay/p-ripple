<?php
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

declare(strict_types=1);

namespace Worker\Built;

use Core\Constants;
use Core\FileSystem\FileException;
use Core\Output;
use Worker\Prop\Build;
use Worker\Tunnel\SocketTunnel;
use Worker\Worker;

/**
 * Buffer Worker is a process-level service that will actively push and clean up all data blocked in the buffer when it is idle.
 * It allows workers of all network types in this process to interact with data without having to worry too
 * much about issues such as sending bytes/residuals/recycling etc.
 * When passively forking, all buffer queues should be actively discarded to ensure the smooth operation of this process service.
 */
class BufferWorker extends Worker
{
    /**
     * 缓冲区套接字列表
     * @var SocketTunnel[] $buffers
     */
    private array $buffers = [];

    /**
     * 心跳
     * @return void
     */
    public function heartbeat(): void
    {
        foreach ($this->buffers as $buffer) {
            try {
                while ($buffer->openBuffer && !$buffer->deprecated && $buffer->write('')) {
                }
            } catch (FileException $exception) {
                Output::printException($exception);
                unset($this->buffers[$buffer->getHash()]);
                return;
            }
        }
    }

    /**
     * 初始化
     * @return void
     */
    public function initialize(): void
    {
        $this->subscribe(Constants::EVENT_SOCKET_BUFFER);
        $this->subscribe(Constants::EVENT_SOCKET_BUFFER_UN);
        parent::initialize();
    }

    /**
     * 处理事件
     * @param Build $event
     * @return void
     */
    public function handleEvent(Build $event): void
    {
        switch ($event->name) {
            case Constants::EVENT_SOCKET_BUFFER:
                $socketHash                 = spl_object_hash($event->data);
                $this->buffers[$socketHash] = $event->data;
                break;
            case Constants::EVENT_SOCKET_BUFFER_UN:
                $socketHash = spl_object_hash($event->data);
                unset($this->buffers[$socketHash]);
                break;
            default:
                break;
        }
    }


    /**
     * 被动fork
     * @return void
     */
    public function forkPassive(): void
    {
        $this->buffers = [];
        parent::forkPassive();
    }

    /**
     * @return void
     */
    public function forking(): void
    {
        parent::forking();
        $this->forkPassive();
    }
}

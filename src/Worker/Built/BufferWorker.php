<?php
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
    protected function handleEvent(Build $event): void
    {
        switch ($event->name) {
            case Constants::EVENT_SOCKET_BUFFER:
                $socketHash = spl_object_hash($event->data);
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
     * 重置
     * @return void
     */
    public function forking(): void
    {
        $this->buffers = [];
        parent::forking();
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
}

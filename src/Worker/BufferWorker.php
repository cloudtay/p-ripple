<?php
declare(strict_types=1);

namespace Worker;

use Core\Constants;
use Core\Output;
use FileSystem\FileException;
use Socket;
use Worker\NetWorker\Tunnel\SocketTunnel;
use Worker\NetWorker\Tunnel\SocketTunnelException;

/**
 * 缓冲区工作器
 */
class BufferWorker extends WorkerBase
{
    /**
     * 缓冲区套接字列表
     * @var SocketTunnel[] $buffers
     */
    private array $buffers = [];

    /**
     * @return void
     */
    protected function initialize(): void
    {
        $this->subscribe(Constants::EVENT_SOCKET_BUFFER);
        $this->subscribe(Constants::EVENT_SOCKET_BUFFER_UN);
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        foreach ($this->buffers as $buffer) {
            try {
                while ($buffer->openBuffer && !$buffer->deprecated && $buffer->write('')) {
                    //TODO: 有效缓冲区释放
                }
            } catch (FileException|SocketTunnelException $exception) {
                Output::printException($exception);
                unset($this->buffers[$buffer->getHash()]);
                return;
            }
        }
    }

    /**
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
        }
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
     * @return void
     */
    public function destroy(): void
    {
        // TODO: Implement destroy() method.
    }

    /**
     * @param Socket $socket
     * @return void
     */
    public function expectSocket(Socket $socket): void
    {
        // TODO: Implement expectSocket() method.
    }
}

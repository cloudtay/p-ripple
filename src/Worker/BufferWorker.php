<?php
declare(strict_types=1);

namespace PRipple\Worker;

use PRipple\Worker\NetWorker\Tunnel\SocketAisle;
use Socket;

/**
 * 缓冲区工作器
 */
class BufferWorker extends Worker
{
    /**
     * @var SocketAisle[] $buffers
     */
    private array $buffers = [];

    /**
     * @return void
     */
    protected function initialize(): void
    {
        $this->subscribe('socket.buffer');
        $this->subscribe('socket.unBuffer');
    }

    /**
     * @return void
     */
    protected function heartbeat(): void
    {
        foreach ($this->buffers as $buffer) {
            while ($buffer->write('') > 0) {
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
            case 'socket.buffer':
                $socketHash = spl_object_hash($event->data);
                $this->buffers[$socketHash] = $event->data;
                break;
            case 'socket.unBuffer':
                $socketHash = spl_object_hash($event->data);
                unset($this->buffers[$socketHash]);
                break;
        }
    }

    /**
     * @param Socket $socket
     * @return void
     */
    protected function handleSocket(Socket $socket): void
    {
        // TODO: Implement handleSocket() method.
    }

    /**
     * @return void
     */
    protected function destroy(): void
    {
        // TODO: Implement destroy() method.
    }

    /**
     * @param Socket $socket
     * @return void
     */
    protected function expectSocket(Socket $socket): void
    {
        // TODO: Implement expectSocket() method.
    }
}

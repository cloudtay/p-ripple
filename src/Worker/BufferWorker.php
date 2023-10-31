<?php

namespace Cclilshy\PRipple\Worker;

use Cclilshy\PRipple\Build;
use Cclilshy\PRipple\Tunnel\SocketAisle;
use Cclilshy\PRipple\Worker;
use Socket;


class BufferWorker extends Worker
{
    /**
     * @var SocketAisle[] $buffers
     */
    private array $buffers = [];

    protected function initialize(): void
    {
        $this->subscribe('socket.buffer');
        $this->subscribe('socket.unBuffer');
    }

    protected function heartbeat(): void
    {
        foreach ($this->buffers as $buffer) {
            while ($buffer->write('') > 0) {
            }
        }
    }

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

    protected function handleSocket(Socket $socket): void
    {
        // TODO: Implement handleSocket() method.
    }

    protected function destroy(): void
    {
        // TODO: Implement destroy() method.
    }

    protected function expectSocket(Socket $socket): void
    {
        // TODO: Implement expectSocket() method.
    }
}

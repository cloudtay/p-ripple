<?php
declare(strict_types=1);

namespace PRipple\Worker;

use PRipple\Worker\NetWorker\Tunnel\SocketAisle;
use Socket;

/**
 * 缓冲区工作器
 */
class BufferWorker extends WorkerInterface
{
    /**
     * @var SocketAisle[] $buffers
     */
    private array $buffers = [];

    /**
     * @return void
     */
    public function initialize(): void
    {
        $this->subscribe('socket.buffer');
        $this->subscribe('socket.unBuffer');
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        foreach ($this->buffers as $buffer) {
            while ($buffer->openCache && $length = $buffer->write('')) {
//                echo '缓冲释放' . $length . PHP_EOL;
            }
        }
    }

    /**
     * @param Build $event
     * @return void
     */
    public function handleEvent(Build $event): void
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

<?php
declare(strict_types=1);

namespace Worker;

use FileSystem\FileException;
use PRipple;
use Socket;
use Worker\NetWorker\Tunnel\SocketAisle;
use Worker\NetWorker\Tunnel\SocketAisleException;

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
            try {
                while ($buffer->openCache && !$buffer->deprecated && $buffer->write('')) {
                    //TODO: 有效缓冲区释放
                }
            } catch (FileException|SocketAisleException $exception) {
                PRipple::printExpect($exception);
                unset($this->buffers[$buffer->getHash()]);
                return;
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

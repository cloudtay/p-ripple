<?php
declare(strict_types=1);

namespace Tests;

use Worker\NetWorker\Client;
use Worker\NetWorker\Tunnel\SocketTunnelException;
use Worker\NetworkWorkerBase;

/**
 *
 */
class TestTCP extends NetworkWorkerBase
{
    /**
     * @return void
     * @throws SocketTunnelException
     */
    public function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
        foreach ($this->getClients() as $client) {
            $client->send('ping' . PHP_EOL);
        }
    }

    /**
     * @return void
     */
    public function destroy(): void
    {
        // TODO: Implement destroy() method.
    }

    /**
     * @param Client $client
     * @return void
     */
    protected function onConnect(Client $client): void
    {
        // TODO: Implement onConnect() method.
    }

    /**
     * @param string $context
     * @param Client $client
     * @return void
     */
    protected function onMessage(string $context, Client $client): void
    {
        // TODO: Implement onMessage() method.
    }

    /**
     * @param Client $client
     * @return void
     */
    protected function onClose(Client $client): void
    {
        // TODO: Implement onClose() method.
    }

    /**
     * @param Client $client
     * @return void
     */
    protected function onHandshake(Client $client): void
    {
        // TODO: Implement onHandshake() method.
    }
}

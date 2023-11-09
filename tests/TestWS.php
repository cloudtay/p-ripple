<?php
declare(strict_types=1);

namespace Tests;

use Worker\NetWorker\Client;
use Worker\NetWorker\Tunnel\SocketAisleException;
use Worker\NetworkWorkerInterface;

/**
 *
 */
class TestWS extends NetworkWorkerInterface
{
    /**
     * @return void
     */
    public function heartbeat(): void
    {
        foreach ($this->getClients() as $client) {
            try {
                $client->send('hello');
            } catch (SocketAisleException $e) {
            }
        }
    }

    /**
     * @param Client $client
     * @return void
     */
    public function onConnect(Client $client): void
    {
        // TODO: Implement onConnect() method.
    }

    /**
     * @return void
     */
    public function destroy(): void
    {
        // TODO: Implement destroy() method.
    }

    /**
     * @param string $context
     * @param Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void
    {
        // TODO: Implement onMessage() method.
    }

    /**
     * @param Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {
        // TODO: Implement onClose() method.
    }

    /**
     * @param Client $client
     * @return void
     */
    public function onHandshake(Client $client): void
    {
        // TODO: Implement onHandshake() method.
    }
}

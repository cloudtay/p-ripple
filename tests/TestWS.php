<?php
declare(strict_types=1);

namespace PRipple\Tests;

use PRipple\Worker\NetWorker;
use PRipple\Worker\NetWorker\Client;

/**
 *
 */
class TestWS extends NetWorker
{
    /**
     * @return void
     */
    public function heartbeat(): void
    {
        foreach ($this->getClients() as $client) {
            $client->send('hello');
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

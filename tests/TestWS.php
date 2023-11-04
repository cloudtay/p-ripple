<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Tests;

use Cclilshy\PRipple\Worker\NetWorker;
use Cclilshy\PRipple\Worker\NetWorker\Client;

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

<?php
declare(strict_types=1);

namespace PRipple\Tests;

use PRipple\Worker\NetWorker;
use PRipple\Worker\NetWorker\Client;

/**
 *
 */
class TestTCP extends NetWorker
{
    /**
     * @param Client $client
     * @return void
     */
    protected function onConnect(Client $client): void
    {
        // TODO: Implement onConnect() method.
    }

    /**
     * @return void
     */
    protected function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
    }

    /**
     * @return void
     */
    protected function destroy(): void
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

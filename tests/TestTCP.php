<?php

namespace Cclilshy\PRipple\Tests;

use Cclilshy\PRipple\Service\Client;
use Cclilshy\PRipple\Worker\NetWorker;

class TestTCP extends NetWorker
{
    protected function onConnect(Client $client): void
    {
        // TODO: Implement onConnect() method.
    }

    protected function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
    }

    protected function destroy(): void
    {
        // TODO: Implement destroy() method.
    }

    protected function onMessage(string $context, Client $client): void
    {
        // TODO: Implement onMessage() method.
    }

    protected function onClose(Client $client): void
    {
        // TODO: Implement onClose() method.
    }

    protected function onHandshake(Client $client): void
    {
        // TODO: Implement onHandshake() method.
    }
}

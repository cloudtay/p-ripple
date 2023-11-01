<?php

namespace Cclilshy\PRipple\Tests;

use Cclilshy\PRipple\Service\Client;
use Cclilshy\PRipple\Worker\NetWorker;

class TestWS extends NetWorker
{

    public function heartbeat(): void
    {
        foreach ($this->getClients() as $client) {
            $client->send('hello');
        }
    }

    public function onConnect(Client $client): void
    {
        // TODO: Implement onConnect() method.
    }

    public function destroy(): void
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
}

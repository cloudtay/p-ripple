<?php
declare(strict_types=1);

namespace Tests;

use Worker\Built\JsonRpc\Attribute\Rpc;
use Worker\Built\JsonRpc\JsonRpc;
use Worker\Socket\TCPConnection;
use Worker\Worker;

class TestWs extends Worker
{
    use JsonRpc;

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        $this->sendMessageToClients('hello,world');
    }

    /**
     * @return void
     */
    public function destroy(): void
    {
        parent::destroy();
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    protected function onConnect(TCPConnection $client): void
    {
        // TODO: Implement onConnect() method.
    }

    /**
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    protected function onMessage(string $context, TCPConnection $client): void
    {
        // TODO: Implement onMessage() method.
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    protected function onClose(TCPConnection $client): void
    {
        // TODO: Implement onClose() method.
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    protected function onHandshake(TCPConnection $client): void
    {
        // TODO: Implement onHandshake() method.
    }


    /**
     * @param string $message
     * @return mixed
     */
    #[Rpc("发送信息到所有客户端")] public function sendMessageToClients(string $message): mixed
    {
        foreach ($this->getClients() as $client) {
            $client->send($message);
        }
        return true;
    }
}

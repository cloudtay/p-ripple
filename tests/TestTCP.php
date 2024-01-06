<?php

use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Utils\TCPClient;
use Cclilshy\PRipple\Worker\Built\TCPClient\Client;
use Cclilshy\PRipple\Worker\Socket\TCPConnection;
use Cclilshy\PRipple\Worker\WorkerNet;
use JetBrains\PhpStorm\NoReturn;
use PHPUnit\Framework\TestCase;

class TestTCP extends TestCase
{
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    #[NoReturn] public function testTcp(): void
    {
        ob_end_clean();
        $kernel = PRipple::configure([]);
        $client = Client::create("tcp://127.0.0.1:8009");
        $client->hook(WorkerNet::HOOK_ON_MESSAGE, function (string $content, TCPConnection $tcpConnection) use ($client) {
            $tcpConnection->send('you say ' . $content);
            $command = trim($content, "\ \n\r\t\v\0");
            if ($command === 'exit') {
                $client->unhook(WorkerNet::HOOK_ON_CLOSE);
                TCPClient::remove($client);
            } elseif ($command === 'throw') {
                Co\async(function () {
                    Co\sleep(3);
                    throw new Exception('error');
                })->catch(function (Throwable $throwable) {
                    echo $throwable->getMessage() . PHP_EOL;
                });
            }
        });

        $client->hook(WorkerNet::HOOK_ON_CONNECT, function (TCPConnection $tcpConnection) {
            $tcpConnection->send('hello');
        });

        $client->hook(WorkerNet::HOOK_ON_CLOSE, function (TCPConnection $tcpConnection) use ($client) {
            Co\repeat(function () use ($client) {
                return !TCPClient::add($client);
            });
        });

        Co\repeat(function () use ($client) {
            return !TCPClient::add($client);
        });

        $kernel->build()->loop();

        while (true) {
            if (!$kernel->heartbeat()) {
                usleep(100000);
            }
        }
    }
}

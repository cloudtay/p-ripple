<?php

use Cclilshy\PRipple\Core\Coroutine\Coroutine;
use Cclilshy\PRipple\Core\Coroutine\Promise;
use Cclilshy\PRipple\PRipple;
use Cclilshy\PRipple\Utils\TCPClient;
use Cclilshy\PRipple\Worker\Built\JsonRPC\JsonRPC;
use Cclilshy\PRipple\Worker\Built\TCPClient\Client;
use Cclilshy\PRipple\Worker\Socket\TCPConnection;
use Cclilshy\PRipple\Worker\Worker;
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
        $kernel->push((new class('test') extends Worker {
            use JsonRPC;
        })->mode(Worker::MODE_INDEPENDENT));
        $kernel->push((new class('test2') extends Worker {
            use JsonRPC;
        })->mode(Worker::MODE_INDEPENDENT));
        $client = Client::create("tcp://127.0.0.1:8009");

        $client->hook(WorkerNet::HOOK_ON_MESSAGE, function (string $content, TCPConnection $tcpConnection) use ($client) {
            $tcpConnection->send('you say ' . $content);
            $command = trim($content, "\ \n\r\t\v\0");
            if ($command === 'exit') {
                $client->unhook(WorkerNet::HOOK_ON_CLOSE);
                TCPClient::remove($client);
            } elseif ($command === 'throw') {
                Co\async(function () {
                    Co\sleep(1);
                    throw new Exception('error');
                })->then(function () {
                }, function () {
                    var_dump('is error');
                });
            } elseif ($command === 'fork') {
                if (pcntl_fork() === 0) {
                    exit;
                }
            }
        });

        $client->hook(WorkerNet::HOOK_ON_CONNECT, function (TCPConnection $tcpConnection) {
            $tcpConnection->send("hello\n");
        });

        $client->hook(WorkerNet::HOOK_ON_CLOSE, function (TCPConnection $tcpConnection) use ($client) {
            Co\repeat(function () use ($client) {
                return !TCPClient::add($client);
            });
        });

        Co\repeat(function () use ($client) {
            return !TCPClient::add($client);
        });

        $kernel->run();
    }

    /**
     * @param string $path
     * @return Promise
     */
    public function fileGetContents(string $path): Promise
    {
        return new Coroutine(function ($resolve, $reject) use ($path) {
            Co\sleep(1);
            if (file_exists($path)) {
                $resolve(file_get_contents($path));
            } else {
                $reject(new Exception('file not found'));
            }
        });
    }
}

<?php

namespace Cclilshy\PRipple\App\Redis;

use Cclilshy\PRipple\Service\Client;
use Cclilshy\PRipple\Service\SocketType\SocketInet;
use Cclilshy\PRipple\Worker\NetWorker;
use Fiber;
use Throwable;

class Redis extends NetWorker
{
    /**
     * @var Client $redisServer
     */
    private Client $redisServer;

    /**
     * @var RedisTask[] $taskStock
     */
    private array $taskStock = [];

    /**
     * @var RedisTask $currentTask
     */
    private RedisTask $currentTask;

    /**
     * @var RedisAuthorize $authorize
     */
    private RedisAuthorize $authorize;

    /**
     * @var bool $idle
     */
    private bool $idle = true;

    /**
     * @param string $name
     * @return mixed
     */
    public function get(string $name): mixed
    {
        try {
            $this->taskStock[] = $task = new RedisTask($this->authorize->buildGetCommand($name), Fiber::getCurrent());
            $response = $this->authorize->parseResponse($task->publishAwait('suspend', null));
            return $response[0] ?? null;
        } catch (Throwable $exception) {
            echo $exception->getMessage() . PHP_EOL;
            return false;
        }
    }

    /**
     * @param string $host
     * @param int    $port
     * @param string $password
     * @param int    $database
     * @return $this
     */
    public function authorize(string $host, int $port, string $password, int $database): self
    {
        $this->authorize = new RedisAuthorize($host, $port, $password, $database);
        return $this;
    }

    /**
     * @param string $context
     * @param Client $client
     * @return void
     */
    protected function onMessage(string $context, Client $client): void
    {
        try {
            $this->currentTask->caller->resume($context);
        } catch (Throwable $exception) {
            echo $exception->getMessage() . PHP_EOL;
        }
        $this->idle = true;
    }

    /**
     * @return void
     */
    protected function heartbeat(): void
    {
        if ($this->idle && $task = array_shift($this->taskStock)) {
            $this->currentTask = $task;
            $this->redisServer->send($task->command);
            $this->idle = false;
        }
    }

    /**
     * @return void
     */
    protected function initialize(): void
    {
        $this->socketType = SocketInet::class;
        parent::initialize();
        $redisServer = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!socket_connect($redisServer, '127.0.0.1', 6379)) {
            exit('redis server connect failed' . PHP_EOL);
        }
        $this->addSocket($redisServer);
        $this->redisServer = $this->getClientBySocket($redisServer);
    }

    /**
     * @param Client $client
     * @return void
     */
    protected function onConnect(Client $client): void
    {
    }

    /**
     * @param Client $client
     * @return void
     */
    protected function onClose(Client $client): void
    {
    }

    /**
     * @return void
     */
    protected function destroy(): void
    {
        // TODO: Implement destroy() method.
    }

    /**
     * @param \Cclilshy\PRipple\Service\Client $client
     * @return void
     */
    protected function onHandshake(Client $client): void
    {
        // TODO: Implement onHandshake() method.
    }
}

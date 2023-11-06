<?php

namespace PRipple\App\PDOProxy;

use Closure;
use Exception;
use Fiber;
use PRipple\App\Facade\PDOProxy;
use PRipple\App\Facade\Process;
use PRipple\App\PDOProxy\Exception\PDOProxyException;
use PRipple\App\PDOProxy\Exception\PDOProxyExceptionBuild;
use PRipple\App\PDOProxy\Exception\RollbackException;
use PRipple\PRipple;
use PRipple\Protocol\CCL;
use PRipple\Worker\Build;
use PRipple\Worker\NetWorker;
use PRipple\Worker\NetWorker\Client;
use PRipple\Worker\Worker;
use Throwable;

class PDOProxyWorker extends NetWorker
{
    public const UNIX_PATH = '/tmp/p_ripple_pdo_proxy.sock';
    public array $proxyProcessIds = [];
    public int $proxyProcessCount = 0;
    public array $fibers = [];
    /**
     * @var PDOBuild[] $queue
     */
    private array $queue = [];
    private array $bindMap = [];
    /**
     * @var PDOProxyConnection[] $connections
     */
    private array $connections = [];

    /**
     * @param Client $client
     * @return void
     */
    public function onConnect(Client $client): void
    {
        $proxyName = PRipple::instance()->uniqueHash();
        $client->setName($proxyName);
        $this->connections[$proxyName] = new PDOProxyConnection($client);
        parent::onConnect($client);
    }

    /**
     * @return PDOProxyWorker|Worker
     */
    public static function instance(): PDOProxyWorker|Worker
    {
        return PRipple::worker(PDOProxyWorker::class);
    }

    /**
     * @param string $context
     * @param Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void
    {
        /**
         * @var PDOBuild $event
         */
        $event = unserialize($context);
        if ($fiber = $this->fibers[$event->publisher] ?? null) {
            if (!$fiber->resume($event->data)) {
                unset($this->fibers[$event->publisher]);
            }
        }
    }

    /**
     * @param Client $client
     * @return string|false
     */
    public function splitMessage(Client $client): string|false
    {
        return $this->protocol->cut($client);
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        unlink(PDOProxyWorker::UNIX_PATH);
        $this->bind('unix://' . PDOProxyWorker::UNIX_PATH);
        $this->protocol(CCL::class);
        parent::initialize();
        PDOProxy::setInstance($this);
    }

    /**
     * @param Closure $callable
     * @return void
     * @throws PDOProxyException
     */
    public function transaction(Closure $callable): void
    {
        if ($connection = $this->getConnection()) {
            $transaction = new PDOTransaction($connection);
            if ($transaction->hash = $this->beginTransaction()) {
                $this->fibers[$transaction->hash] = Fiber::getCurrent();
                try {
                    call_user_func($callable, $transaction);
                    $transaction->_commit();
                } catch (RollbackException $exception) {
                    try {
                        $transaction->_rollBack();
                    } catch (Throwable $exception) {
                        PRipple::printExpect($exception);
                    }
                } catch (PDOProxyException|Throwable $exception) {
                    PRipple::printExpect($exception);
                }
            }
        } else {
            try {
                throw new PDOProxyException('No available connections');
            } catch (Exception $exception) {
                PRipple::printExpect($exception);
            }
        }
    }

    /**
     * @param string|null $hash
     * @return PDOProxyConnection|false
     */
    public function getConnection(string|null $hash = null): PDOProxyConnection|false
    {
        if ($hash) {
            //TODO: 获取事务连接
            return $this->bindMap[$hash] ?? false;
        }

        //TODO: 获取空闲连接
        foreach ($this->connections as $connection) {
            if ($connection->transaction === false) {
                return $connection;
            }
        }
        return false;
    }

    /**
     * @return string|false
     */
    public function beginTransaction(): string|false
    {
        // 绑定事务连接,防止事务混淆其他提交
        if (!$connection = $this->getConnection()) {
            return false;
        }

        $queueHash = PRipple::instance()->uniqueHash();
        $this->fibers[$queueHash] = Fiber::getCurrent();
        $connection->beginTransaction($queueHash);
        try {
            $result = $this->waitResponse();
        } catch (PDOProxyException $exception) {
            PRipple::printExpect($exception);
            return false;
        }
        if ($result === true) {
            $connection->transaction = true;
            $this->bindMap[$queueHash] = $connection;
            return $queueHash;
        }
        return false;
    }

    /**
     * @return mixed
     * @throws PDOProxyException
     */
    public function waitResponse(): mixed
    {
        try {
            $response = Fiber::suspend(Build::new('suspend', null, PDOProxyWorker::class));
        } catch (Throwable $exception) {
            PRipple::printExpect($exception);
            return false;
        }
        if ($response instanceof PDOProxyExceptionBuild) {
            throw new PDOProxyException($response->message, $response->code, $response->previous);
        }
        return $response;
    }

    public function onClose(Client $client): void
    {
        $this->proxyProcessCount--;
    }

    public function addProxy(int $num, array $config): int
    {
        if ($num < 1) {
            return 0;
        }
        $result = 0;
        foreach (range(1, $num) as $_) {
            $pid = Process::fork(function () use ($config) {
                PDOProxy::launch($config['dns'], $config['username'], $config['password'], $config['options']);
            });
            if ($pid) {
                $this->proxyProcessIds[] = $pid;
                $this->proxyProcessCount++;
                $result++;
            }
        }
        return $result;
    }

    public function heartbeat(): void
    {
        while ($queue = array_shift($this->queue)) {
            if ($connection = $this->getConnection()) {
                switch ($queue->name) {
                    case PDOBuild::EVENT_QUERY:
                        $connection->query($queue->publisher, $queue->query, $queue->bindings, $queue->bindParams);
                        break;
                    default:

                }
            } else {
                array_unshift($this->queue, $queue);
                return;
            }
        }
        $this->todo = false;
    }

    /**
     * @param string $query
     * @param array|null $bindValues
     * @param array|null $bindParams
     * @return mixed
     */
    public function query(string $query, array|null $bindValues = [], array|null $bindParams = []): mixed
    {
        $queueHash = PRipple::instance()->uniqueHash();
        $this->fibers[$queueHash] = Fiber::getCurrent();
        if ($connection = $this->getConnection()) {
            $connection->query($queueHash, $query, $bindValues, $bindParams);
        } else {
            $this->queue[] = PDOBuild::query($queueHash, $query, $bindValues, $bindParams);
            $this->todo = true;
        }
        try {
            return $this->waitResponse();
        } catch (PDOProxyException $exception) {
            PRipple::printExpect($exception);
            return false;
        }
    }
}

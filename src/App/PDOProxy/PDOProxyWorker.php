<?php
declare(strict_types=1);

namespace App\PDOProxy;

use App\Facade\PDOProxy;
use App\Facade\Process;
use App\PDOProxy\Exception\PDOProxyException;
use App\PDOProxy\Exception\PDOProxyExceptionBuild;
use App\PDOProxy\Exception\RollbackException;
use Closure;
use Fiber;
use FileSystem\FileException;
use PRipple;
use Protocol\CCL;
use Throwable;
use Worker\Build;
use Worker\NetWorker\Client;
use Worker\NetWorker\Tunnel\SocketAisleException;
use Worker\NetworkWorkerInterface;
use Worker\WorkerInterface;

/**
 *
 */
class PDOProxyWorker extends NetworkWorkerInterface
{
    public static string $UNIX_PATH;
    public array $proxyProcessIds = [];
    public int $proxyProcessCount = 0;
    /**
     * @var Fiber[] $fibers
     */
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
     * @return PDOProxyWorker|WorkerInterface
     */
    public static function instance(): PDOProxyWorker|WorkerInterface
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
            $this->resume($fiber, $event->data);
            unset($this->fibers[$event->publisher]);
        }

        $this->connections[$client->getName()]->count--;
    }

//    /**
//     * @param Client $client
//     * @return string|false|null
//     */
//    public function splitMessage(Client $client): string|null|false
//    {
//        if ($result = $this->protocol->cut($client)) {
//            $this->onMessage($result, $client);
//        }
//        return null;
//    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        PDOProxyWorker::$UNIX_PATH = PRipple::getArgument('RUNTIME_PATH') . '/p_ripple_pdo_proxy.sock';
        file_exists(PDOProxyWorker::$UNIX_PATH) && unlink(PDOProxyWorker::$UNIX_PATH);
        $this->bind('unix://' . PDOProxyWorker::$UNIX_PATH);
        $this->protocol(CCL::class);
        parent::initialize();
        PDOProxy::setInstance($this);
    }

    /**
     * @param Closure $callable
     * @return void
     * @throws PDOProxyException|Throwable
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
            throw new PDOProxyException('No available connections');
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

        $freeConnections = array_filter($this->connections, function ($connection) {
            return $connection->transaction === false;
        });

        if (empty($freeConnections)) {
            return false;
        }

        $counts = array_column($freeConnections, 'count');
        $connectionsByCount = array_combine($counts, $freeConnections);
        $connection = $connectionsByCount[min($counts)];
        $connection->count++;
        return $connection;
    }

    /**
     * @return string|false
     * @throws Throwable
     */
    public function beginTransaction(): string|false
    {
        // 绑定事务连接,防止事务混淆其他提交
        if (!$connection = $this->getConnection()) {
            return false;
        }
        $queueHash = PRipple::instance()->uniqueHash();
        $this->fibers[$queueHash] = Fiber::getCurrent();
        try {
            $connection->pushBeginTransaction($queueHash);
        } catch (FileException|SocketAisleException $e) {
            PRipple::printExpect($e);
            return false;
        }
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
     * @throws PDOProxyException|Throwable
     */
    public function waitResponse(): mixed
    {
        $response = Fiber::suspend(Build::new('suspend', null, PDOProxyWorker::class));
        if ($response instanceof PDOProxyExceptionBuild) {
            //TODO:抛出一个错误的包,本地模拟构建错误
            throw new PDOProxyException($response->message, $response->code, $response->previous);
        }
        return $response;
    }

    /**
     * @param Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {
        $this->proxyProcessCount--;
        unset($this->connections[$client->getName()]);
    }

    /**
     * @param int $num
     * @param array $config
     * @return int
     */
    public function addProxy(int $num, array $config): int
    {
        if ($num < 1) {
            return 0;
        }
        $result = 0;
        foreach (range(1, $num) as $_) {
            $pid = Process::fork(function () use ($config) {
                PDOProxyTransfer::launch(
                    $config['dns'],
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
            });
            if ($pid) {
                $this->proxyProcessIds[] = $pid;
                $this->proxyProcessCount++;
                $result++;
            }
        }
        return $result;
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        while ($queue = array_shift($this->queue)) {
            if ($connection = $this->getConnection()) {
                switch ($queue->name) {
                    case PDOBuild::EVENT_QUERY:
                        try {
                            $connection->pushQuery($queue->publisher, $queue->query, $queue->bindings, $queue->bindParams);
                        } catch (FileException|SocketAisleException $exception) {
                            //TODO:查询失败,往返回队列中添加失败信息
                            if ($fiber = $this->fibers[$queue->publisher] ?? null) {
                                try {
                                    $fiber->throw($exception);
                                } catch (Throwable $exception) {
                                    PRipple::printExpect($exception);
                                }
                            }
                        }
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
     * @throws PDOProxyException
     * @throws Throwable
     */
    public function query(string $query, array|null $bindValues = [], array|null $bindParams = []): mixed
    {
        $queueHash = PRipple::instance()->uniqueHash();
        $this->fibers[$queueHash] = Fiber::getCurrent();
        if ($connection = $this->getConnection()) {
            try {
                //TODO: PDO代理发送查询请求
                $connection->pushQuery($queueHash, $query, $bindValues, $bindParams);
            } catch (FileException|SocketAisleException $exception) {
                PRipple::printExpect($exception);
                return false;
            }
        } else {
            //TODO: 没有空闲连接时,将查询请求加入队列
            $this->queue[] = PDOBuild::query($queueHash, $query, $bindValues, $bindParams);
            $this->todo = true;
        }
        //TODO: 等待查询结果
        return $this->waitResponse();
    }
}

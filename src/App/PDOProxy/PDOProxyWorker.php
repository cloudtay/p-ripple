<?php
declare(strict_types=1);

namespace App\PDOProxy;

use App\Facade\Process;
use App\PDOProxy\Exception\PDOProxyException;
use App\PDOProxy\Exception\PDOProxyExceptionBuild;
use App\PDOProxy\Exception\RollbackException;
use Closure;
use Core\Map\CollaborativeFiberMap;
use Core\Map\WorkerMap;
use Core\Output;
use Fiber;
use FileSystem\FileException;
use PRipple;
use Protocol\CCL;
use Throwable;
use Worker\Build;
use Worker\NetWorker\Client;
use Worker\NetWorker\Tunnel\SocketTunnelException;
use Worker\NetworkWorkerBase;
use Worker\WorkerBase;

/**
 * PDOWorker
 */
class PDOProxyWorker extends NetworkWorkerBase
{
    public static string $UNIX_PATH;
    public array $proxyProcessIds   = [];
    public int   $proxyProcessCount = 0;

    /**
     * @var PDOBuild[] $queue
     */
    private array $queue = [];
    private array $bindMap = [];
    /**
     * @var PDOProxyClient[] $connections
     */
    private array $connections = [];
    private array $config;

    /**
     * @return PDOProxyWorker|WorkerBase
     */
    public static function instance(): PDOProxyWorker|WorkerBase
    {
        return WorkerMap::getWorker(PDOProxyWorker::class);
    }

    /**
     * @param array $config
     * @return $this
     */
    public function config(array $config): PDOProxyWorker
    {
        $this->config = $config;
        return $this;
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
                try {
                    call_user_func($callable, $transaction);
                    $transaction->_commit();
                } catch (RollbackException $exception) {
                    try {
                        $transaction->_rollBack();
                    } catch (Throwable $exception) {
                        Output::printException($exception);
                    }
                } catch (PDOProxyException|Throwable $exception) {
                    Output::printException($exception);
                }
            }
        } else {
            throw new PDOProxyException('No available connections');
        }
    }

    /**
     * @param string|null $hash
     * @return PDOProxyClient|false
     */
    public function getConnection(string|null $hash = null): PDOProxyClient|false
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
     * @throws PDOProxyException
     */
    public function beginTransaction(): string|false
    {
        // 绑定事务连接,防止事务混淆其他提交
        if (!$connection = $this->getConnection()) {
            return false;
        }
        try {
            $hash = CollaborativeFiberMap::current()->hash;
            $connection->pushBeginTransaction($hash);
        } catch (FileException|SocketTunnelException $e) {
            Output::printException($e);
            return false;
        }
        $result = $this->waitResponse();
        if ($result === true) {
            $connection->transaction = true;
            $this->bindMap[$hash] = $connection;
            return $hash;
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
            CollaborativeFiberMap::current()->throwExceptionInFiber(new PDOProxyException('No available connections'));
            return false;
        }
        if ($response instanceof PDOProxyExceptionBuild) {
            throw new PDOProxyException($response->message, $response->code, $response->previous);
        }
        return $response;
    }

    /**
     * @param int $num
     * @return int
     */
    public function activate(int $num): int
    {
        if ($num < 1) {
            return 0;
        }
        $result = 0;
        foreach (range(1, $num) as $_) {
            $pid = Process::fork(function () {
                PDOProxyServer::launch(
                    $this->config['dns'],
                    $this->config['username'],
                    $this->config['password'],
                    $this->config['options']
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
                            $connection->pushQuery($queue->source, $queue->query, $queue->bindings, $queue->bindParams);
                        } catch (FileException|SocketTunnelException $exception) {
                            //TODO:查询失败,往返回队列中添加失败信息
                            try {
                                CollaborativeFiberMap::getCollaborativeFiber($queue->source)?->throwExceptionInFiber($exception);
                            } catch (Throwable $exception) {
                                Output::printException($exception);
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
     * @throws FileException
     * @throws PDOProxyException
     * @throws SocketTunnelException
     */
    public function query(string $query, array|null $bindValues = [], array|null $bindParams = []): mixed
    {
        $hash = CollaborativeFiberMap::current()->hash;
        if ($connection = $this->getConnection()) {
            $connection->pushQuery($hash, $query, $bindValues, $bindParams);
        } else {
            //TODO: 没有空闲连接时,将查询请求加入队列
            $this->queue[] = PDOBuild::query($hash, $query, $bindValues, $bindParams);
            $this->todo = true;
        }
        //TODO: 等待查询结果
        return $this->waitResponse();
    }

    /**
     * @param Client $client
     * @return void
     */
    protected function onConnect(Client $client): void
    {
        $proxyName = PRipple::uniqueHash();
        $client->setName($proxyName);
        $this->connections[$proxyName] = new PDOProxyClient($client);
        Output::info('    - PDOPRoxy-online:' . $proxyName);
        parent::onConnect($client);
    }

    /**
     * @param Client $client
     * @return void
     */
    protected
    function splitMessage(Client $client): void
    {
        while ($content = $client->getPlaintext()) {
            $this->onMessage($content, $client);
        }
    }

    /**
     * @param string $context
     * @param Client $client
     * @return void
     */
    protected
    function onMessage(string $context, Client $client): void
    {
        /**
         * @var PDOBuild $event
         */
        $event = unserialize($context);
        $this->resume($event->source, $event->data);
        $this->connections[$client->getName()]->count--;
    }

    /**
     * @return void
     */
    protected function initialize(): void
    {
        PDOProxyWorker::$UNIX_PATH = PRipple::getArgument('RUNTIME_PATH') . '/p_ripple_pdo_proxy.sock';
        file_exists(PDOProxyWorker::$UNIX_PATH) && unlink(PDOProxyWorker::$UNIX_PATH);
        $this->bind('unix://' . PDOProxyWorker::$UNIX_PATH);
        $this->protocol(CCL::class);
        parent::initialize();
    }

    /**
     * @param Client $client
     * @return void
     */
    protected function onClose(Client $client): void
    {
        $this->proxyProcessCount--;
        unset($this->connections[$client->getName()]);
    }
}

<?php
declare(strict_types=1);

namespace recycle;

use Closure;
use Core\FileSystem\FileException;
use Core\Map\CollaborativeFiberMap;
use Core\Map\WorkerMap;
use Core\Output;
use Fiber;
use PRipple;
use Protocol\CCL;
use recycle\PDOProxy\Exception\CommitException;
use recycle\PDOProxy\Exception\PDOProxyException;
use recycle\PDOProxy\Exception\PDOProxyExceptionBuild;
use Throwable;
use Worker\Prop\Build;
use Worker\Socket\TCPConnection;
use Worker\Worker;

/**
 * PDOWorker
 */
class PDOProxyWorker extends Worker
{
    public static string $UNIX_PATH;
    public array         $proxyProcessIds   = [];
    public int           $proxyProcessCount = 0;

    /**
     * @var PDOBuild[] $queue
     */
    protected array $queue   = [];
    private array   $bindMap = [];
    /**
     * @var PDOProxyClient[] $connections
     */
    private array $connections = [];
    private array $config;

    /**
     * @return PDOProxyWorker|Worker
     */
    public static function instance(): PDOProxyWorker|Worker
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
     */
    public function transaction(Closure $callable): void
    {
        if ($connection = $this->getConnection()) {
            try {
                $connection->pushBeginTransaction(CollaborativeFiberMap::current()->hash);
            } catch (FileException $exception) {
                CollaborativeFiberMap::current()->exceptionHandler(new PDOProxyException('No available connections'));
            }
            if ($this->waitResponse() === true) {
                $transaction = new PDOTransaction($connection, $this);
                try {
                    call_user_func($callable, $transaction);
                    $transaction->_commit();
                } catch (CommitException $exception) {
                    $transaction->_commit();
                } catch (Throwable $exception) {
                    try {
                        $transaction->_rollback();
                    } catch (Throwable $exception) {
                        Output::printException($exception);
                    }
                }
                $connection->transaction = false;
            }
        } else {
            CollaborativeFiberMap::current()->exceptionHandler(new PDOProxyException('No available connections'));
        }
    }

    /**
     * @param string|null $hash
     * @return PDOProxyClient|false
     */
    public function getConnection(string|null $hash = null): PDOProxyClient|false
    {
        if ($hash) {
            return $this->bindMap[$hash] ?? false;
        }

        $freeConnections = array_filter($this->connections, function (PDOProxyClient $connection) {
            return $connection->transaction === false;
        });

        if (empty($freeConnections)) {
            return false;
        }

        $counts             = array_column($freeConnections, 'count');
        $connectionsByCount = array_combine($counts, $freeConnections);
        $connection         = $connectionsByCount[min($counts)];
        $connection->count++;
        return $connection;
    }

    /**
     * @return mixed
     */
    public function waitResponse(): mixed
    {
        try {
            $response = Fiber::suspend(Build::new('suspend', null, PDOProxyWorker::class));
        } catch (Throwable $exception) {
            CollaborativeFiberMap::current()->exceptionHandler(new PDOProxyException('No available connections'));
            return false;
        }
        if ($response instanceof PDOProxyExceptionBuild) {
            CollaborativeFiberMap::current()->exceptionHandler(
                new PDOProxyException($response->message, $response->code, $response->previous)
            );
            try {
                Fiber::suspend(Build::new('suspend', null, PDOProxyWorker::class));
            } catch (Throwable $exception) {
                Output::printException($exception);
            }
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
        foreach (range(1, $num) as $ignored) {
            $pid = $this->fork();
            if ($pid) {
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
                        $connection->pushQuery($queue->source, $queue->query, $queue->bindings, $queue->bindParams);
                        break;
                    default:
                }
            } else {
                array_unshift($this->queue, $queue);
                return;
            }
        }
        $this->busy = false;
    }

    /**
     * @param string     $query
     * @param array|null $bindValues
     * @param array|null $bindParams
     * @return mixed
     */
    public function query(string $query, array|null $bindValues = [], array|null $bindParams = []): mixed
    {
        $hash = CollaborativeFiberMap::current()->hash;
        if ($connection = $this->getConnection()) {
            $connection->pushQuery($hash, $query, $bindValues, $bindParams);
        } else {
            $this->queue[] = PDOBuild::query($hash, $query, $bindValues, $bindParams);
            $this->busy    = true;
        }
        return $this->waitResponse();

    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    protected function onConnect(TCPConnection $client): void
    {
        $proxyName = PRipple::uniqueHash();
        $client->setName($proxyName);
        $this->connections[$proxyName] = new PDOProxyClient($client);
        Output::info('    - PDOPRoxy-online:' . $proxyName);
        parent::onConnect($client);
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    protected function splitMessage(TCPConnection $client): void
    {
        while ($content = $client->getPlaintext()) {
            $this->onMessage($content, $client);
        }
    }

    /**
     * @param string        $context
     * @param TCPConnection $client
     * @return void
     */
    protected function onMessage(string $context, TCPConnection $client): void
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
    public function initialize(): void
    {
        PDOProxyWorker::$UNIX_PATH = PP_RUNTIME_PATH . '/p_ripple_pdo_proxy.sock';
        file_exists(PDOProxyWorker::$UNIX_PATH) && unlink(PDOProxyWorker::$UNIX_PATH);
        $this->bind('unix://' . PDOProxyWorker::$UNIX_PATH);
        $this->protocol(CCL::class);
        parent::initialize();
    }

    /**
     * @param TCPConnection $client
     * @return void
     */
    protected function onClose(TCPConnection $client): void
    {
        $this->proxyProcessCount--;
        unset($this->connections[$client->getName()]);
    }
}

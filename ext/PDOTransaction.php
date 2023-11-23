<?php
declare(strict_types=1);

namespace recycle;

use Core\Map\CollaborativeFiberMap;

/**
 * PDO打包器
 */
class PDOTransaction
{
    public string $hash;
    /**
     * @var PDOProxyClient $connection
     */
    private PDOProxyClient $proxyConnection;
    private PDOProxyWorker $worker;

    /**
     * @param PDOProxyClient $proxyConnection
     * @param PDOProxyWorker $worker
     */
    public function __construct(PDOProxyClient $proxyConnection, PDOProxyWorker $worker)
    {
        $this->hash            = CollaborativeFiberMap::current()->hash;
        $this->proxyConnection = $proxyConnection;
        $this->worker          = $worker;
    }

    /**
     * @param string $query
     * @param array  $bindings
     * @param array  $bindParams
     * @return mixed
     */
    public function query(string $query, array $bindings, array $bindParams): mixed
    {
        $this->proxyConnection->pushQuery($this->hash, $query, $bindings, $bindParams);
        return $this->worker->waitResponse();
    }

    /**
     * @return bool
     */
    public function _commit(): bool
    {
        $this->proxyConnection->pushCommit($this->hash);
        return $this->worker->waitResponse();
    }

    /**
     * @return bool
     */
    public function _rollback(): bool
    {
        $this->proxyConnection->pushRollBack($this->hash);
        return $this->worker->waitResponse();
    }
}

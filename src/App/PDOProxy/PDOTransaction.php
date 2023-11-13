<?php
declare(strict_types=1);

namespace App\PDOProxy;

use App\PDOProxy\Exception\RollbackException;
use FileSystem\FileException;
use PRipple;

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

    /**
     * @param PDOProxyClient $proxyConnection
     */
    public function __construct(PDOProxyClient $proxyConnection)
    {
        $this->hash = PRipple::uniqueHash();
        $this->proxyConnection = $proxyConnection;
    }

    /**
     * @param string $query
     * @param array  $bindings
     * @param array  $bindParams
     * @return mixed
     * @throws FileException
     */
    public function query(string $query, array $bindings, array $bindParams): mixed
    {
        $this->proxyConnection->pushQuery($this->hash, $query, $bindings, $bindParams);
        return PDOProxyWorker::instance()->waitResponse();
    }

    /**
     * @return bool
     * @throws FileException
     */
    public function _commit(): bool
    {
        $this->proxyConnection->pushCommit($this->hash);
        return PDOProxyWorker::instance()->waitResponse();
    }

    /**
     * @return bool
     * @throws FileException
     */
    public function _rollBack(): bool
    {
        $this->proxyConnection->pushRollBack($this->hash);
        return PDOProxyWorker::instance()->waitResponse();
    }

    /**
     * @return bool
     * @throws RollbackException
     */
    public function rollBack(): bool
    {
        throw new RollbackException();
    }
}

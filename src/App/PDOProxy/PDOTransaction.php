<?php
declare(strict_types=1);

namespace App\PDOProxy;

use App\PDOProxy\Exception\RollbackException;
use PRipple;
use Throwable;

/**
 * PDO打包器
 */
class PDOTransaction
{
    public string $hash;
    /**
     * @var PDOProxyConnection $connection
     */
    private PDOProxyConnection $proxyConnection;

    /**
     * @param PDOProxyConnection $proxyConnection
     */
    public function __construct(PDOProxyConnection $proxyConnection)
    {
        $this->hash = PRipple::uniqueHash();
        $this->proxyConnection = $proxyConnection;
    }

    /**
     * @param string $query
     * @param array $bindings
     * @param array $bindParams
     * @return mixed
     * @throws Throwable
     */
    public function query(string $query, array $bindings, array $bindParams): mixed
    {
        $this->proxyConnection->pushQuery($this->hash, $query, $bindings, $bindParams);
        return PDOProxy::instance()->waitResponse();
    }

    /**
     * @return bool
     * @throws Throwable
     */
    public function _commit(): bool
    {
        $this->proxyConnection->pushCommit($this->hash);
        return PDOProxy::instance()->waitResponse();
    }

    /**
     * @return bool
     * @throws Throwable
     */
    public function _rollBack(): bool
    {
        $this->proxyConnection->pushRollBack($this->hash);
        return PDOProxy::instance()->waitResponse();
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

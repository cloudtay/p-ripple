<?php
declare(strict_types=1);

namespace App\PDOProxy;

use FileSystem\FileException;
use Protocol\CCL;
use Worker\NetWorker\Client;
use Worker\NetWorker\Tunnel\SocketTunnelException;

/**
 * PDO代理客户端
 */
class PDOProxyClient
{
    /**
     * @var bool
     */
    public bool $transaction = false;
    /**
     * @var int
     */
    public int $count = 0;
    /**
     * @var Client
     */
    public Client $client;

    /**
     * @var CCL
     */
    protected CCL $ccl;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->ccl = new CCL();
    }

    /**
     * @param string $hash
     * @return void
     * @throws FileException
     * @throws SocketTunnelException
     */
    public function pushBeginTransaction(string $hash): void
    {
        $this->ccl->send($this->client, PDOBuild::beginTransaction($hash)->serialize());

    }

    /**
     * @param string $hash
     * @param string $query
     * @param array|null $bindValues
     * @param array|null $bindParams
     * @return void
     * @throws FileException
     * @throws SocketTunnelException
     */
    public function pushQuery(string $hash, string $query, array|null $bindValues = [], array|null $bindParams = []): void
    {
        $this->ccl->send($this->client, PDOBuild::query($hash, $query, $bindValues, $bindParams)->serialize());
    }

    /**
     * @param string $hash
     * @return void
     * @throws FileException
     * @throws SocketTunnelException
     */
    public function pushCommit(string $hash): void
    {
        $this->ccl->send($this->client, PDOBuild::commit($hash)->serialize());
    }

    /**
     * @param string $hash
     * @return void
     * @throws FileException
     * @throws SocketTunnelException
     */
    public function pushRollBack(string $hash): void
    {
        $this->ccl->send($this->client, PDOBuild::rollBack($hash)->serialize());
    }
}

<?php
declare(strict_types=1);

namespace App\PDOProxy;

use FileSystem\FileException;
use Protocol\CCL;
use Worker\NetWorker\Client;
use Worker\NetWorker\Tunnel\SocketAisleException;

/**
 *
 */
class PDOProxyConnection
{
    public bool $transaction = false;
    public int $count = 0;
    public Client $client;
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
     * @throws SocketAisleException
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
     * @throws SocketAisleException
     */
    public function pushQuery(string $hash, string $query, array|null $bindValues = [], array|null $bindParams = []): void
    {
        $this->ccl->send($this->client, PDOBuild::query($hash, $query, $bindValues, $bindParams)->serialize());
    }

    /**
     * @param string $hash
     * @return void
     * @throws FileException
     * @throws SocketAisleException
     */
    public function pushCommit(string $hash): void
    {
        $this->ccl->send($this->client, PDOBuild::commit($hash)->serialize());
    }

    /**
     * @param string $hash
     * @return void
     * @throws FileException
     * @throws SocketAisleException
     */
    public function pushRollBack(string $hash): void
    {
        $this->ccl->send($this->client, PDOBuild::rollBack($hash)->serialize());
    }
}

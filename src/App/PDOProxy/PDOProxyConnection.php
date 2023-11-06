<?php

namespace PRipple\App\PDOProxy;

use PRipple\Protocol\CCL;
use PRipple\Worker\NetWorker\Client;

class PDOProxyConnection
{
    public bool $transaction = false;
    protected Client $client;
    protected CCL $ccl;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->ccl = new CCL;
    }

    /**
     * @param string $hash
     * @return void
     */
    public function beginTransaction(string $hash): void
    {
        $this->ccl->send($this->client, PDOBuild::beginTransaction($hash)->serialize());
    }

    /**
     * @param string $hash
     * @param string $query
     * @param array|null $bindValues
     * @param array|null $bindParams
     * @return void
     */
    public function query(string $hash, string $query, array|null $bindValues = [], array|null $bindParams = []): void
    {
        $this->ccl->send($this->client, PDOBuild::query($hash, $query, $bindValues, $bindParams)->serialize());
    }

    /**
     * @param string $hash
     * @return void
     */
    public function commit(string $hash): void
    {
        $this->ccl->send($this->client, PDOBuild::commit($hash)->serialize());
    }

    /**
     * @param string $hash
     * @return void
     */
    public function rollBack(string $hash): void
    {
        $this->ccl->send($this->client, PDOBuild::rollBack($hash)->serialize());
    }
}

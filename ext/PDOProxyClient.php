<?php
declare(strict_types=1);

namespace recycle;

use Core\FileSystem\FileException;
use Core\Map\CollaborativeFiberMap;
use Protocol\CCL;
use Worker\Socket\TCPConnection;

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
     * @var TCPConnection
     */
    public TCPConnection $client;

    /**
     * @var CCL
     */
    protected CCL $ccl;

    /**
     * @param TCPConnection $client
     */
    public function __construct(TCPConnection $client)
    {
        $this->client = $client;
        $this->ccl    = new CCL();
    }

    /**
     * @param string $hash
     * @return void
     * @throws FileException
     */
    public function pushBeginTransaction(string $hash): void
    {
        $this->ccl->send($this->client, PDOBuild::beginTransaction($hash)->serialize());

    }

    /**
     * @param string     $hash
     * @param string     $query
     * @param array|null $bindValues
     * @param array|null $bindParams
     * @return void
     */
    public function pushQuery(string $hash, string $query, array|null $bindValues = [], array|null $bindParams = []): void
    {
        try {
            $this->ccl->send($this->client, PDOBuild::query($hash, $query, $bindValues, $bindParams)->serialize());
        } catch (FileException $exception) {
            CollaborativeFiberMap::current()->exceptionHandler($exception);
        }
    }

    /**
     * @param string $hash
     * @return void
     */
    public function pushCommit(string $hash): void
    {
        try {
            $this->ccl->send($this->client, PDOBuild::commit($hash)->serialize());
        } catch (FileException $exception) {
            CollaborativeFiberMap::current()->exceptionHandler($exception);
        }
    }

    /**
     * @param string $hash
     * @return void
     */
    public function pushRollBack(string $hash): void
    {
        try {
            $this->ccl->send($this->client, PDOBuild::rollBack($hash)->serialize());
        } catch (FileException $exception) {
            CollaborativeFiberMap::current()->exceptionHandler($exception);
        }
    }
}

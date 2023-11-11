<?php

namespace App\PDOProxy;

use App\Facade\PDOPool;
use PRipple;
use Socket;
use Worker\Build;
use Worker\WorkerInterface;

/**
 * @method static PDOProxy|null get(string $name)
 * @method static void add(string $name, PDOProxy $pdoProxy)
 */
class PDOProxyPool extends WorkerInterface
{
    private static PDOProxyPool $instance;
    private array $pool = [];

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return call_user_func_array([PDOProxyPool::instance(), $name], $arguments);
    }

    /**
     * @return PDOProxyPool|WorkerInterface
     */
    private static function instance(): PDOProxyPool|WorkerInterface
    {
        return PDOProxyPool::$instance;
    }

    public function handleSocket(Socket $socket): void
    {
        // TODO: Implement handleSocket() method.
    }

    public function expectSocket(Socket $socket): void
    {
        // TODO: Implement expectSocket() method.
    }

    public function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
    }

    public function destroy(): void
    {
        foreach ($this->pool as $pdoProxy) {
            $pdoProxy->destroy();
        }
    }

    protected function initialize(): void
    {
        PDOPool::setInstance($this);
        PDOProxyPool::$instance = $this;
    }

    protected function handleEvent(Build $event): void
    {
        // TODO: Implement handleEvent() method.
    }

    /**
     * @param string $name
     * @param array $config
     * @return PDOProxy
     */
    private function add(string $name, array $config): PDOProxy
    {
        $proxy = PDOProxy::new($name)->config($config);
        PRipple::instance()->push($proxy);
        $this->pool[$name] = $proxy;
        return $proxy;
    }

    /**
     * @param string $name
     * @return PDOProxy|null
     */
    private function get(string $name): PDOProxy|null
    {
        return $this->pool[$name] ?? null;
    }
}

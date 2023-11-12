<?php
declare(strict_types=1);

namespace App\PDOProxy;

use App\Facade\PDOPool;
use PRipple;
use Socket;
use Worker\Build;
use Worker\WorkerBase;

/**
 * @method static PDOProxy|null get(string $name)
 * @method static void add(string $name, PDOProxy $pdoProxy)
 */
class PDOProxyPool extends WorkerBase
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
     * @return PDOProxyPool|WorkerBase
     */
    private static function instance(): PDOProxyPool|WorkerBase
    {
        return PDOProxyPool::$instance;
    }

    /**
     * @param Socket $socket
     * @return void
     */
    public function handleSocket(Socket $socket): void
    {
        // TODO: Implement handleSocket() method.
    }

    /**
     * @param Socket $socket
     * @return void
     */
    public function expectSocket(Socket $socket): void
    {
        // TODO: Implement expectSocket() method.
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
    }

    /**
     * @return void
     */
    public function destroy(): void
    {
        foreach ($this->pool as $pdoProxy) {
            $pdoProxy->destroy();
        }
    }

    /**
     * @return void
     */
    protected function initialize(): void
    {
        PDOPool::setInstance($this);
        PDOProxyPool::$instance = $this;
    }

    /**
     * @param Build $event
     * @return void
     */
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
        PRipple::kernel()->push($proxy);
        $this->pool[$name] = $proxy;
        return $proxy;
    }

    /**
     * @param string|null $name
     * @return PDOProxy|null
     */
    private function get(string|null $name = 'DEFAULT'): PDOProxy|null
    {
        return $this->pool[$name] ?? null;
    }
}

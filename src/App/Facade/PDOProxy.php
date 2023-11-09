<?php
declare(strict_types=1);

namespace App\Facade;

use App\PDOProxy\PDOProxyWorker;
use Std\Facade;
use Worker\WorkerInterface;

/**
 * @method static mixed query(string $query, array|null $bindings = [], array|null $bindParams = [])
 * @method static void transaction(callable $callback)
 * @method static int|false addProxy(int $num, array $config)
 */
class PDOProxy extends Facade
{
    public static mixed $instance;

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([PDOProxy::$instance, $name], $arguments);
    }

    /**
     * @return PDOProxyWorker
     */
    public static function getInstance(): PDOProxyWorker
    {
        return PDOProxy::$instance;
    }

    /**
     * @param WorkerInterface $worker
     * @return PDOProxyWorker
     */
    public static function setInstance(WorkerInterface $worker): PDOProxyWorker
    {
        PDOProxy::$instance = $worker;
        return PDOProxy::$instance;
    }
}

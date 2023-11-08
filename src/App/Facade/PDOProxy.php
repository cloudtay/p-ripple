<?php

namespace PRipple\App\Facade;

use PRipple\App\PDOProxy\PDOProxyWorker;
use PRipple\Std\Facade;
use PRipple\Worker\WorkerInterface;

/**
 * @method static mixed query(string $query, array|null $bindings = [], array|null $bindParams = [])
 * @method static void transaction(callable $callback)
 * @method static int|false addProxy(int $num, array $config)
 */
class PDOProxy extends Facade
{
    public static mixed $instance;

    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([PDOProxy::$instance, $name], $arguments);
    }

    public static function getInstance(): PDOProxyWorker
    {
        return PDOProxy::$instance;
    }

    public static function setInstance(WorkerInterface $worker): PDOProxyWorker
    {
        PDOProxy::$instance = $worker;
        return PDOProxy::$instance;
    }
}

<?php
declare(strict_types=1);

namespace App\Facade;

use App\PDOProxy\PDOProxyPool;
use App\PDOProxy\PDOProxyWorker;
use Std\FacadeStd;
use Worker\WorkerBase;

/**
 * PDO代理池门面
 * @method static PDOProxyWorker|null get(string $name)
 * @method static PDOProxyWorker add(string $name, array $config)
 */
class PDOPool extends FacadeStd
{
    /**
     * @var mixed
     */
    public static mixed $instance;

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([PDOProxyPool::instance(), $name], $arguments);
    }

    /**
     * @return PDOProxyPool
     */
    public static function getInstance(): PDOProxyPool
    {
        return PDOPool::$instance;
    }

    /**
     * @param WorkerBase $worker
     * @return PDOProxyPool
     */
    public static function setInstance(WorkerBase $worker): PDOProxyPool
    {
        PDOPool::$instance = $worker;
        return PDOPool::$instance;
    }
}

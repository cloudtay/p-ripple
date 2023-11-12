<?php
declare(strict_types=1);

namespace App\Facade;

use App\PDOProxy\PDOProxy;
use App\PDOProxy\PDOProxyPool;
use Std\FacadeStd;
use Worker\WorkerBase;

/**
 * @method static PDOProxy|null get(string $name)
 * @method static PDOProxy add(string $name, array $config)
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
        return call_user_func_array([PDOProxyPool::class, $name], $arguments);
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

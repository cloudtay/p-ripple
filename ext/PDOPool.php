<?php
declare(strict_types=1);

namespace recycle;

use Core\Std\FacadeStd;
use Worker\Worker;

/**
 * PDO代理池门面
 * @method static PDOProxyWorker|null get(string|null $name = 'default')
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
     * @param array  $arguments
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
     * @param Worker $worker
     * @return PDOProxyPool
     */
    public static function setInstance(Worker $worker): PDOProxyPool
    {
        PDOPool::$instance = $worker;
        return PDOPool::$instance;
    }
}

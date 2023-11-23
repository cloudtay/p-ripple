<?php
declare(strict_types=1);

namespace Facade;

use Core\Std\FacadeStd;
use Worker\Built\ProcessManager\ProcessContainer;
use Worker\Built\ProcessManager\ProcessManager;
use Worker\Worker;

/**
 * 进程管理器门面
 * @method static void signal(int $processId, int $signal)
 * @method static int|false fork(callable $callback, bool|null $exit = true)
 */
class Process extends FacadeStd
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
        if ($name === 'fork') {
            return call_user_func_array([ProcessContainer::class, $name], $arguments);
        }
        return call_user_func_array([Process::$instance, $name], $arguments);
    }

    /**
     * @return ProcessManager
     */
    public static function getInstance(): ProcessManager
    {
        return Process::$instance;
    }

    /**
     * @param Worker $worker
     * @return ProcessManager
     */
    public static function setInstance(Worker $worker): ProcessManager
    {
        Process::$instance = $worker;
        return Process::$instance;
    }
}

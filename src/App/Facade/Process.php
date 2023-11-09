<?php
declare(strict_types=1);

namespace App\Facade;

use App\ProcessManager\ProcessContainer;
use App\ProcessManager\ProcessManager;
use Std\Facade;
use Worker\WorkerInterface;

/**
 * @method static void signal(int $processId, int $signal)
 * @method static int|false fork(callable $callback)
 */
class Process extends Facade
{
    public static mixed $instance;

    /**
     * @param string $name
     * @param array $arguments
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
     * @param WorkerInterface $worker
     * @return ProcessManager
     */
    public static function setInstance(WorkerInterface $worker): ProcessManager
    {
        Process::$instance = $worker;
        return Process::$instance;
    }
}

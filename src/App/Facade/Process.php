<?php

namespace PRipple\App\Facade;

use PRipple\App\ProcessManager\ProcessManager;
use PRipple\Std\Facade;
use PRipple\Worker\Worker;

/**
 * @method static void signal(int $processId, int $signal)
 * @method static int|false fork(callable $callback)
 */
class Process extends Facade
{
    public static mixed $instance;

    public static function __callStatic(string $name, array $arguments): mixed
    {
        if ($name === 'fork') {
            return call_user_func_array([\PRipple\App\ProcessManager\Process::class, $name], $arguments);
        }
        return call_user_func_array([Process::$instance, $name], $arguments);
    }

    public static function getInstance(): ProcessManager
    {
        return Process::$instance;
    }

    public static function setInstance(Worker $worker): ProcessManager
    {
        Process::$instance = $worker;
        return Process::$instance;
    }
}

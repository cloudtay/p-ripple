<?php
declare(strict_types=1);

namespace App\Facade;

use Std\Facade;
use Worker\Build;
use Worker\WorkerInterface;

/**
 * @method static void sleep(int $seconds)
 * @method static void loop(int $seconds, callable $callback)
 * @method static void event(int $seconds, Build $event)
 */
class Timer extends Facade
{
    public static mixed $instance;

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([Timer::$instance, $name], $arguments);
    }

    /**
     * @return \App\Timer\Timer
     */
    public static function getInstance(): \App\Timer\Timer
    {
        return Timer::$instance;
    }

    /**
     * @param WorkerInterface $worker
     * @return \App\Timer\Timer
     */
    public static function setInstance(WorkerInterface $worker): \App\Timer\Timer
    {
        Timer::$instance = $worker;
        return Timer::$instance;
    }
}

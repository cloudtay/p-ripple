<?php

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

    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([Timer::$instance, $name], $arguments);
    }

    public static function getInstance(): \App\Timer\Timer
    {
        return Timer::$instance;
    }

    public static function setInstance(WorkerInterface $worker): \App\Timer\Timer
    {
        Timer::$instance = $worker;
        return Timer::$instance;
    }
}

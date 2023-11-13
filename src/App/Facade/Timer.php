<?php
declare(strict_types=1);

namespace App\Facade;

use Std\FacadeStd;
use Worker\Build;
use Worker\WorkerBase;

/**
 * 计时器门面
 * @method static void sleep(int $seconds)
 * @method static void loop(int $seconds, callable $callback)
 * @method static void event(int $seconds, Build $event)
 */
class Timer extends FacadeStd
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
     * @param WorkerBase $worker
     * @return \App\Timer\Timer
     */
    public static function setInstance(WorkerBase $worker): \App\Timer\Timer
    {
        Timer::$instance = $worker;
        return Timer::$instance;
    }
}

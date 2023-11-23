<?php
declare(strict_types=1);

namespace Facade;

use Core\Std\FacadeStd;
use Worker\Prop\Build;
use Worker\Worker;

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
     * @param array  $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return call_user_func_array([Timer::$instance, $name], $arguments);
    }

    /**
     * @return \Worker\Built\Timer
     */
    public static function getInstance(): \Worker\Built\Timer
    {
        return Timer::$instance;
    }

    /**
     * @param Worker $worker
     * @return \Worker\Built\Timer
     */
    public static function setInstance(Worker $worker): \Worker\Built\Timer
    {
        Timer::$instance = $worker;
        return Timer::$instance;
    }
}

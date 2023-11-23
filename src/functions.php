<?php
declare(strict_types=1);

namespace PRipple;

use Closure;
use Core\Constants;
use Core\Map\EventMap;
use Core\Std\CollaborativeFiberStd;
use Worker\Built\ProcessManager\ProcessContainer;
use Worker\Built\ProcessManager\ProcessManager;
use Worker\Built\Timer;
use Worker\Prop\Build;

/**
 * 延时
 * @param int $second
 * @return void
 */
function delay(int $second): void
{
    Timer::getInstance()->sleep($second);
}

/**
 * 延时发布一个事件
 * @param int   $second
 * @param Build $event
 * @return void
 */
function event(int $second, Build $event): void
{
    Timer::getInstance()->event($second, $event);
}

/**
 * 循环执行一个闭包
 * @param int     $second
 * @param Closure $callable
 * @return void
 */
function loop(int $second, Closure $callable): void
{
    Timer::getInstance()->loop($second, $callable);
}

/**
 * 向进程发布信号
 * @param int $processId
 * @param int $signalNo
 * @return void
 */
function signal(int $processId, int $signalNo): void
{
    ProcessManager::getInstance()->signal($processId, $signalNo);
}

/**
 * 创建一个进程
 * @param Closure   $closure
 * @param bool|null $exit
 * @return bool|int
 */
function fork(Closure $closure, bool|null $exit = true): bool|int
{
    return ProcessContainer::fork($closure);
}

/**
 * 异步执行
 * @param Closure $callable
 * @return void
 */
function async(Closure $callable): void
{
    EventMap::push(Build::new(Constants::EVENT_TEMP_FIBER, new class($callable) extends CollaborativeFiberStd {
        public function __construct(Closure $callable)
        {
            $this->setupWithCallable($callable);
        }
    }, 'anonymous'));
}

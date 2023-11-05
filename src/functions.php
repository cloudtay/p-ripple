<?php

namespace Cclilshy\PRipple;

use Closure;
use Fiber;
use PRipple\App\ProcessManager\Process;
use PRipple\App\ProcessManager\ProcessManager;
use PRipple\App\Timer\Timer;
use PRipple\PRipple;
use PRipple\Worker\Build;

/**
 * 延时
 * @param int $second
 * @return void
 */
function delay(int $second): void
{
    Timer::instance()->sleep($second);
}

/**
 * 延时发布一个事件
 * @param int $second
 * @param Build $event
 * @return void
 */
function event(int $second, Build $event): void
{
    Timer::instance()->event($second, $event);
}

/**
 * 循环执行一个闭包
 * @param int $second
 * @param Closure $callable
 * @return void
 */
function loop(int $second, Closure $callable): void
{
    Timer::instance()->loop($second, $callable);
}

/**
 * 向进程发布信号
 * @param int $processId
 * @param int $signalNo
 * @return void
 */
function signal(int $processId, int $signalNo): void
{
    ProcessManager::instance()->signal($processId, $signalNo);
}

/**
 * 创建一个进程
 * @param Closure $closure
 * @return bool|int
 */
function fork(Closure $closure): bool|int
{
    return Process::fork($closure);
}


/**
 * @param Closure $callable
 * @return void
 */
function async(Closure $callable): void
{
    PRipple::publishAsync(Build::new('temp.fiber', new Fiber($callable), 'anonymous'));
}

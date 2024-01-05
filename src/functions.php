<?php
/*
 * Copyright (c) 2023 cclilshy
 * Contact Information:
 * Email: jingnigg@gmail.com
 * Website: https://cc.cloudtay.com/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * 版权所有 (c) 2023 cclilshy
 *
 * 特此免费授予任何获得本软件及相关文档文件（“软件”）副本的人，不受限制地处理
 * 本软件，包括但不限于使用、复制、修改、合并、出版、发行、再许可和/或销售
 * 软件副本的权利，并允许向其提供本软件的人做出上述行为，但须符合以下条件：
 *
 * 上述版权声明和本许可声明应包含在本软件的所有副本或主要部分中。
 *
 * 本软件按“原样”提供，不提供任何形式的保证，无论是明示或暗示的，
 * 包括但不限于适销性、特定目的的适用性和非侵权性的保证。在任何情况下，
 * 无论是合同诉讼、侵权行为还是其他方面，作者或版权持有人均不对
 * 由于软件或软件的使用或其他交易而引起的任何索赔、损害或其他责任承担责任。
 */

declare(strict_types=1);

namespace PRipple;

use Closure;
use Core\Constants;
use Core\Map\EventMap;
use Core\Std\CollaborativeFiberStd;
use Facade\Process;
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
 * @param Build    $event
 * @param int|null $second
 * @return void
 */
function event(Build $event, int|null $second = 0): void
{
    Timer::getInstance()->event($event, $second);
}

/**
 * 循环执行一个闭包
 * @param Closure  $callable
 * @param int|null $second
 * @return void
 */
function loop(Closure $callable, int|null $second = 1): void
{
    Timer::getInstance()->loop($callable, $second);
}

/**
 * 向进程发布信号
 * @param int $processId
 * @param int $signalNo
 * @return void
 */
function signal(int $processId, int $signalNo): void
{
    Process::signal($processId, $signalNo);
}

/**
 * 关闭某个进程
 * @param int $processId
 * @return void
 */
function kill(int $processId): void
{
    Process::kill($processId);
}

/**
 * 创建一个进程
 * @param Closure   $closure
 * @param bool|null $exit
 * @return bool|int
 */
function process(Closure $closure, bool|null $exit = true): bool|int
{
    return Process::process($closure, $exit);
}

/**
 * 异步执行
 * @param Closure $callable
 * @return CollaborativeFiberStd
 */
function async(Closure $callable): CollaborativeFiberStd
{
    EventMap::push(Build::new(Constants::EVENT_TEMP_FIBER, $class = new class($callable) extends CollaborativeFiberStd {
        public function __construct(Closure $callable)
        {
            $this->setup($callable);
        }

        public function handleEvent(Build $event): void
        {

        }
    }, 'anonymous'));
    return $class;
}
